<?php

declare(strict_types=1);

/**
 * API: solde_chef_equipe.php
 * Solde financier d'un chef d'équipe (tickets des agents rattachés).
 * GET ?token=BAEB3101
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function jsonOut(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require __DIR__ . '/connexion.php';

    if (!isset($conn) || !($conn instanceof PDO)) {
        jsonOut(500, ['success' => false, 'error' => 'Connexion PDO indisponible.']);
    }

    $token = trim((string) ($_GET['token'] ?? ''));
    if ($token === '') {
        jsonOut(422, ['success' => false, 'error' => 'Le paramètre token est requis.']);
    }

    $sql = 'SELECT 
        ce.id_chef,
        ce.nom,
        ce.prenoms,
        ce.token,
        COALESCE(SUM(t.montant_paie), 0) AS total_montant,
        COALESCE(SUM(t.montant_payer), 0) AS montant_paye,
        COALESCE(SUM(t.montant_paie), 0) - COALESCE(SUM(t.montant_payer), 0) AS reste_a_payer,
        COALESCE(SUM(CASE WHEN a.sous_groupe = \'particulier\' THEN t.montant_paie ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN a.sous_groupe = \'particulier\' THEN COALESCE(t.montant_payer, 0) ELSE 0 END), 0)
            AS reste_particuliers,
        COALESCE(SUM(CASE WHEN a.sous_groupe = \'professionnel\' THEN t.montant_paie ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN a.sous_groupe = \'professionnel\' THEN COALESCE(t.montant_payer, 0) ELSE 0 END), 0)
            AS reste_professionnels
    FROM chef_equipe ce
    LEFT JOIN agents a ON a.id_chef = ce.id_chef AND a.date_suppression IS NULL
    LEFT JOIN tickets t ON t.id_agent = a.id_agent AND t.montant_paie IS NOT NULL
    WHERE ce.token = :token
    GROUP BY ce.id_chef, ce.nom, ce.prenoms, ce.token';

    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $token]);
    $solde = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solde) {
        jsonOut(404, ['success' => false, 'error' => 'Chef d\'équipe introuvable pour ce token.']);
    }

    jsonOut(200, [
        'success' => true,
        'solde' => [
            'id_chef' => (int) $solde['id_chef'],
            'nom' => $solde['nom'],
            'prenoms' => $solde['prenoms'],
            'token' => $solde['token'],
            'total_montant' => (float) $solde['total_montant'],
            'montant_paye' => (float) $solde['montant_paye'],
            'reste_a_payer' => (float) $solde['reste_a_payer'],
            'reste_particuliers' => (float) $solde['reste_particuliers'],
            'reste_professionnels' => (float) $solde['reste_professionnels'],
        ],
    ]);
} catch (Throwable $e) {
    $debug = (string) ($_GET['debug'] ?? '') === '1';
    if ($debug) {
        jsonOut(500, ['success' => false, 'error' => 'Erreur serveur.', 'detail' => $e->getMessage()]);
    }
    jsonOut(500, ['success' => false, 'error' => 'Erreur serveur.']);
}
