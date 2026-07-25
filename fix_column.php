<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=gest-camions;charset=utf8mb4', 'root', '');
    $pdo->exec('ALTER TABLE particulier_agent_prix ADD COLUMN produit_id BIGINT UNSIGNED NULL AFTER type_transporteur');
    echo "OK: Colonne produit_id ajoutee avec succes\n";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
