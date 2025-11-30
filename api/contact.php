<?php
// api/contact.php - API для контактов
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config.php';
checkAuth();

header('Content-Type: application/json');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $contact = $stmt->fetch();
    
    if ($contact) {
        echo json_encode($contact);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Contact not found']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Bad request']);