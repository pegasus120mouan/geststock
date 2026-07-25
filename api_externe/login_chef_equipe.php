<?php

declare(strict_types=1);

/**
 * API: login_chef_equipe.php
 * Authentification chef d'équipe (table chef_equipe).
 *
 * POST (JSON ou form) : login, password
 * GET  (secours)     : ?login=&password=
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
        'login' => trim((string) ($row['login'] ?? '')),
    ];
}

/**
 * @return array<string, mixed>
 */
function readInput(): array
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        return $_GET;
    }

    if ($method !== 'POST') {
        jsonOut(405, [
            'success' => false,
            'error' => 'Méthode non autorisée.',
            'method' => $method,
        ]);
    }

    $raw = (string) file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

try {
    require __DIR__ . '/connexion.php';

    if (!isset($conn) || !($conn instanceof PDO)) {
        jsonOut(500, ['success' => false, 'error' => 'Connexion PDO indisponible.']);
    }

    $input = readInput();

    $login = trim((string) ($input['login'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($login === '' || $password === '') {
        jsonOut(422, ['success' => false, 'error' => 'Login et mot de passe requis.']);
    }

    $stmt = $conn->prepare(
        'SELECT id_chef, nom, prenoms, token, login, password
        FROM chef_equipe
        WHERE LOWER(TRIM(login)) = LOWER(:login)
        LIMIT 1'
    );
    $stmt->execute(['login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || trim((string) ($row['password'] ?? '')) === '') {
        jsonOut(401, ['success' => false, 'error' => 'Identifiants invalides.']);
    }

    if (hash('sha256', $password) !== (string) $row['password']) {
        jsonOut(401, ['success' => false, 'error' => 'Identifiants invalides.']);
    }

    jsonOut(200, [
        'success' => true,
        'chef' => normalizeChef($row),
    ]);
} catch (Throwable $e) {
    $debug = (string) ($_GET['debug'] ?? '') === '1';
    if ($debug) {
        jsonOut(500, ['success' => false, 'error' => 'Erreur serveur.', 'detail' => $e->getMessage()]);
    }
    jsonOut(500, ['success' => false, 'error' => 'Erreur serveur.']);
}
