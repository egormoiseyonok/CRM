<?php
// api/company.php - API для компаний
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config.php';
checkAuth();

header('Content-Type: application/json');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $company = $stmt->fetch();
    
    if ($company) {
        echo json_encode($company);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Company not found']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Bad request']);