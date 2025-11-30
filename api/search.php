<?php
// api/search.php - Глобальный поиск
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../config.php';
checkAuth();

header('Content-Type: application/json');

$db = getDB();
$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([
        'companies' => [],
        'contacts' => [],
        'deals' => []
    ]);
    exit;
}

$searchTerm = "%$query%";

// Поиск компаний
$companies = $db->prepare("
    SELECT id, name, industry 
    FROM companies 
    WHERE name ILIKE ? OR email ILIKE ? OR industry ILIKE ?
    LIMIT 5
");
$companies->execute([$searchTerm, $searchTerm, $searchTerm]);
$companiesResult = $companies->fetchAll();

// Поиск контактов
$contacts = $db->prepare("
    SELECT id, first_name, last_name, position 
    FROM contacts 
    WHERE first_name ILIKE ? OR last_name ILIKE ? OR email ILIKE ?
    LIMIT 5
");
$contacts->execute([$searchTerm, $searchTerm, $searchTerm]);
$contactsResult = $contacts->fetchAll();

// Поиск сделок
$deals = $db->prepare("
    SELECT id, title, amount 
    FROM deals 
    WHERE title ILIKE ? OR notes ILIKE ?
    LIMIT 5
");
$deals->execute([$searchTerm, $searchTerm]);
$dealsResult = $deals->fetchAll();

echo json_encode([
    'companies' => $companiesResult,
    'contacts' => $contactsResult,
    'deals' => $dealsResult
]);