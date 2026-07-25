<?php
/**
 * API: mes_financements.php
 * Description: Récupère les financements accordés aux agents (groupés par agent)
 * À déployer sur: https://api.objetombrepegasus.online/api/camions/
 * 
 * Tables utilisées:
 * - financement (Numero_financement, id_agent, montant, motif, date_financement)
 * - agents (id_agent, numero_agent, nom, prenom, contact, id_chef, cree_par)
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

// Paramètres de pagination et recherche
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête SQL - Grouper par agent
$whereClause = "WHERE a.date_suppression IS NULL";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (a.nom LIKE :search OR a.prenom LIKE :search OR a.numero_agent LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

// Compter le total d'agents avec financements
$countSql = "SELECT COUNT(DISTINCT a.id_agent) as total 
             FROM agents a
             LEFT JOIN financement f ON f.id_agent = a.id_agent
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Récupérer les agents avec leurs totaux de financements
$sql = "SELECT 
            a.id_agent,
            TRIM(CONCAT(COALESCE(a.nom, ''), ' ', COALESCE(a.prenom, ''))) as nom_agent,
            a.numero_agent,
            COUNT(f.Numero_financement) as nombre_financements,
            COALESCE(SUM(f.montant), 0) as montant_initial,
            0 as deja_rembourse,
            COALESCE(SUM(f.montant), 0) as solde_financement
        FROM agents a
        LEFT JOIN financement f ON f.id_agent = a.id_agent
        $whereClause
        GROUP BY a.id_agent, a.nom, a.prenom, a.numero_agent
        ORDER BY montant_initial DESC, nom_agent ASC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$financements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer la pagination
$lastPage = max(1, ceil($total / $perPage));

// Réponse JSON
echo json_encode([
    'financements' => $financements,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'last_page' => $lastPage,
    ],
], JSON_UNESCAPED_UNICODE);
