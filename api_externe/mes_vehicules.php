<?php

declare(strict_types=1);

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

    $stmt = $conn->prepare(
        'SELECT vehicules_id, matricule_vehicule, type_vehicule, id_proprietaire, created_at, updated_at
         FROM vehicules
         ORDER BY vehicules_id DESC'
    );
    $stmt->execute();
    $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonOut(200, [
        'success' => true,
        'vehicules' => $vehicules,
    ]);
} catch (Throwable $e) {
    $debug = (string)($_GET['debug'] ?? '') === '1';
    if ($debug) {
        jsonOut(500, ['error' => 'Erreur serveur.', 'detail' => $e->getMessage()]);
    }
    jsonOut(500, ['error' => 'Erreur serveur.']);
}
