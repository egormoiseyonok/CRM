<?php
// config.example.php - Пример конфигурации
// Скопируйте этот файл в config.php и заполните своими данными

session_start();

// Настройки базы данных PostgreSQL
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Настройки приложения
define('APP_NAME', 'Portata');
define('APP_URL', 'http://localhost');

// Остальные функции остаются такими же, как в config.php
// ...

