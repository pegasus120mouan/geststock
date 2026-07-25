<?php

declare(strict_types=1);

/**
 * Connexion PDO partagée par les APIs camions.
 * Sur le serveur de production, ce fichier définit $conn (voir mes_tickets.php).
 * En local : copier config.example.php vers config.php ou définir les constantes ci-dessous.
 */

if (!defined('DB_HOST')) {
    $configFile = __DIR__ . '/config.php';
    if (is_file($configFile)) {
        $config = require $configFile;
        define('DB_HOST', $config['db_host'] ?? '127.0.0.1');
        define('DB_USER', $config['db_user'] ?? 'root');
        define('DB_PASS', $config['db_pass'] ?? '');
        define('DB_NAME', $config['db_name'] ?? '');
    } else {
        define('DB_HOST', '127.0.0.1');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_NAME', '');
    }
}

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

try {
    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Connexion échouée'], JSON_UNESCAPED_UNICODE);
    exit;
}
