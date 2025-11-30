<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config.php';
checkAuth();

header('Content-Type: application/json');

$db = getDB();
$user = getCurrentUser();
$isAdmin = isAdmin();
$isAdminOrManager = isAdminOrManager();
$userFilter = $isAdminOrManager ? '' : " AND m.user_id = {$user['id']}";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("
        SELECT m.* 
        FROM meetings m
        WHERE m.id = ? $userFilter
    ");
    $stmt->execute([$id]);
    $meeting = $stmt->fetch();
    
    if ($meeting) {
        header('Content-Type: application/json');
        echo json_encode($meeting);
    } else {
        http_response_code(404);
    }
}

