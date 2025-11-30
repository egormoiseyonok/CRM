<?php
// api/user.php - API для профиля пользователя
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config.php';
checkAuth();

header('Content-Type: application/json');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Если передан id, получить конкретного пользователя (только для админов)
    if (isset($_GET['id'])) {
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        $id = intval($_GET['id']);
        $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
        exit;
    }
    
    // Иначе вернуть текущего пользователя
    $user = getCurrentUser();
    
    if ($user) {
        // Убрать пароль из ответа
        unset($user['password']);
        
        // Добавить статистику
        $user['stats'] = [
            'companies' => $db->query("SELECT COUNT(*) FROM companies WHERE user_id = {$user['id']}")->fetchColumn(),
            'contacts' => $db->query("SELECT COUNT(*) FROM contacts WHERE user_id = {$user['id']}")->fetchColumn(),
            'deals' => $db->query("SELECT COUNT(*) FROM deals WHERE user_id = {$user['id']}")->fetchColumn(),
            'tasks' => $db->query("SELECT COUNT(*) FROM tasks WHERE user_id = {$user['id']}")->fetchColumn(),
        ];
        
        echo json_encode($user);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Bad request']);