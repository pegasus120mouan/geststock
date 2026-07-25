<?php
/**
 * API: mes_usines.php
 * Description: Récupère la liste des usines
 * À déployer sur: https://api.objetombrepegasus.online/api/camions/
 * 
 * Table utilisée:
 * - usines (id_usine, nom_usine, montant_total, montant_paye, montant_restant, derniere_date_paiement, created_by, created_at, updated_by)
 */

require_once __DIR__ . '/connexion.php';

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cookie');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données.']);
    exit;
}

// Paramètres de pagination et recherche
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête SQL
$whereClause = "";
$params = [];

if (!empty($search)) {
    $whereClause = "WHERE nom_usine LIKE :search";
    $params['search'] = '%' . $search . '%';
}

// Compter le total
$countSql = "SELECT COUNT(*) as total FROM usines $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Récupérer les usines
$sql = "SELECT 
            id_usine,
            nom_usine
        FROM usines
        $whereClause
        ORDER BY nom_usine ASC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$usines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer la pagination
$lastPage = max(1, ceil($total / $perPage));

// Réponse JSON
echo json_encode([
    'usines' => $usines,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'last_page' => $lastPage,
    ],
], JSON_UNESCAPED_UNICODE);
