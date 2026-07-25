<?php

declare(strict_types=1);

/**
 * API: mes_tickets.php
 * Tickets des agents rattachés au chef d'équipe connecté.
 * GET ?token=BAEB3101&page=1
 * GET ?id_chef=12&page=1
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

function normalizeChef(array $row): array
{
    $nom = trim((string) ($row['nom'] ?? ''));
    $prenoms = trim((string) ($row['prenoms'] ?? ''));

    return [
        'id_chef' => (int) ($row['id_chef'] ?? 0),
        'nom' => $nom,
        'prenoms' => $prenoms,
        'nom_complet' => trim($nom . ' ' . $prenoms),
        'token' => (string) ($row['token'] ?? ''),
    ];
}

try {
    require __DIR__ . '/connexion.php';

    if (!isset($conn) || !($conn instanceof PDO)) {
        jsonOut(500, ['success' => false, 'error' => 'Connexion PDO indisponible.']);
    }

    $token = trim((string) ($_GET['token'] ?? ''));
    $idChef = (int) ($_GET['id_chef'] ?? 0);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));

    if ($token === '' && $idChef <= 0) {
        jsonOut(422, [
            'success' => false,
            'error' => 'Le paramètre token ou id_chef est requis.',
        ]);
    }

    $where = ['a.date_suppression IS NULL'];
    $bindings = [];

    if ($token !== '') {
        $where[] = 'ce.token = :token';
        $bindings['token'] = $token;
    } else {
        $where[] = 'a.id_chef = :id_chef';
        $bindings['id_chef'] = $idChef;
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) AS total
        FROM tickets t
        INNER JOIN agents a ON a.id_agent = t.id_agent
        INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
        WHERE {$whereSql}";

    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($bindings);
    $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $lastPage = max(1, (int) ceil($total / $perPage));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT
            t.id_ticket,
            t.id_usine,
            t.date_ticket,
            t.id_agent,
            t.numero_ticket,
            t.vehicule_id,
            t.id_pont,
            t.poids,
            t.id_utilisateur,
            t.prix_unitaire,
            t.date_validation_boss,
            t.montant_paie,
            t.montant_payer,
            t.montant_reste,
            t.date_paie,
            t.created_at,
            t.updated_at,
            t.statut_ticket,
            t.numero_bordereau,
            v.matricule_vehicule,
            v.type_vehicule,
            v.id_proprietaire,
            a.nom AS agent_nom,
            a.prenom AS agent_prenom,
            a.numero_agent,
            a.id_chef,
            ce.nom AS chef_nom,
            ce.prenoms AS chef_prenoms,
            ce.token AS chef_token,
            u.nom_usine,
            pb.nom_pont
        FROM tickets t
        INNER JOIN agents a ON a.id_agent = t.id_agent
        INNER JOIN chef_equipe ce ON ce.id_chef = a.id_chef
        LEFT JOIN vehicules v ON v.vehicules_id = t.vehicule_id
        LEFT JOIN usines u ON u.id_usine = t.id_usine
        LEFT JOIN pont_bascule pb ON pb.id_pont = t.id_pont
        WHERE {$whereSql}
        ORDER BY t.id_ticket DESC
        LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $conn->prepare($sql);
    $stmt->execute($bindings);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chef = null;
    if ($token !== '') {
        $chefStmt = $conn->prepare('SELECT id_chef, nom, prenoms, token FROM chef_equipe WHERE token = :token LIMIT 1');
        $chefStmt->execute(['token' => $token]);
        $chefRow = $chefStmt->fetch(PDO::FETCH_ASSOC);
        if ($chefRow) {
            $chef = normalizeChef($chefRow);
        }
    } elseif ($idChef > 0) {
        $chefStmt = $conn->prepare('SELECT id_chef, nom, prenoms, token FROM chef_equipe WHERE id_chef = :id_chef LIMIT 1');
        $chefStmt->execute(['id_chef' => $idChef]);
        $chefRow = $chefStmt->fetch(PDO::FETCH_ASSOC);
        if ($chefRow) {
            $chef = normalizeChef($chefRow);
        }
    }

    jsonOut(200, [
        'success' => true,
        'chef' => $chef,
        'tickets' => $tickets,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ],
    ]);
} catch (Throwable $e) {
    $debug = (string) ($_GET['debug'] ?? '') === '1';
    if ($debug) {
        jsonOut(500, ['success' => false, 'error' => 'Erreur serveur.', 'detail' => $e->getMessage()]);
    }
    jsonOut(500, ['success' => false, 'error' => 'Erreur serveur.']);
}
