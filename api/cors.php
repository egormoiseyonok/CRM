<?php
// CORS headers для поддержки запросов с фронтенда
// Включать в начале каждого API файла

// Разрешить запросы с любого origin (для разработки)
// В продакшене лучше указать конкретные домены
$allowedOrigins = [
    'http://localhost',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:8080',
    // Добавьте ваш GitHub Pages URL здесь, если нужно
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Если origin в списке разрешенных или это локальный запрос
if (in_array($origin, $allowedOrigins) || empty($origin) || strpos($origin, 'localhost') !== false) {
    header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

