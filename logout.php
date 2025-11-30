<?php
require_once 'config.php';

// Добавить активность перед выходом
if (isset($_SESSION['user_id'])) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activities (type, subject, user_id) VALUES (?, ?, ?)");
    $stmt->execute(['logout', 'Выход из системы', $_SESSION['user_id']]);
}

session_destroy();
header('Location: login.php');
exit;