<?php
require_once 'config.php';
checkAuth();

// Подключаем автозагрузчик Composer
$phpspreadsheetLoaded = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $phpspreadsheetLoaded = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
}

$user = getCurrentUser();

// Проверка прав доступа - только для администраторов и менеджеров
if (!in_array($user['role'], ['admin', 'manager'])) {
    setFlash('Доступ запрещен. Отчёты доступны только администраторам и менеджерам.', 'danger');
    header('Location: index.php');
    exit;
}

$db = getDB();
$reportType = $_GET['report_type'] ?? '';
$format = $_GET['format'] ?? 'excel';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Функция для экспорта в Excel (XLSX через PhpSpreadsheet)
function exportToExcel($data, $filename, $headers = []) {
    global $phpspreadsheetLoaded;
    
    // Проверяем наличие библиотеки PhpSpreadsheet
    if (!$phpspreadsheetLoaded && !class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fallback на старый формат, если библиотека не установлена
        // Но создаем файл с расширением .xlsx для совместимости
        exportToExcelLegacy($data, $filename, $headers);
        return;
    }
    
    // Безопасное имя файла (удаляем опасные символы)
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    $filename = substr($filename, 0, 100);
    
    try {
        // Создаем новый Spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $rowIndex = 1;
        
        // Заголовки
        if (!empty($headers)) {
            $colIndex = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $header);
                $sheet->getStyleByColumnAndRow($colIndex, $rowIndex)->getFont()->setBold(true);
                $sheet->getStyleByColumnAndRow($colIndex, $rowIndex)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0E0E0');
                $colIndex++;
            }
            $rowIndex++;
        }
        
        // Данные
        foreach ($data as $row) {
            $colIndex = 1;
            foreach ($row as $cell) {
                $cellValue = $cell;
                $cellType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
                
                // Обрабатываем даты в формате DD.MM.YYYY из PostgreSQL
                if (is_string($cell) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})(\s+(\d{2}):(\d{2}))?$/', $cell, $matches)) {
                    try {
                        $dateStr = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                        if (isset($matches[4]) && isset($matches[5])) {
                            $dateStr .= ' ' . $matches[5] . ':' . $matches[6] . ':00';
                        }
                        $cellValue = \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($dateStr);
                        $cellType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                        $sheet->getStyleByColumnAndRow($colIndex, $rowIndex)
                            ->getNumberFormat()
                            ->setFormatCode(isset($matches[4]) ? 'dd.mm.yyyy hh:mm' : 'dd.mm.yyyy');
                    } catch (Exception $e) {
                        $cellValue = $cell;
                    }
                }
                // Обрабатываем даты в формате YYYY-MM-DD
                elseif (is_string($cell) && preg_match('/^(\d{4})-(\d{2})-(\d{2})(\s+(\d{2}):(\d{2}))?/', $cell, $matches)) {
                    try {
                        $dateStr = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                        if (isset($matches[4]) && isset($matches[5])) {
                            $dateStr .= ' ' . $matches[5] . ':' . $matches[6] . ':00';
                        }
                        $cellValue = \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($dateStr);
                        $cellType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                        $sheet->getStyleByColumnAndRow($colIndex, $rowIndex)
                            ->getNumberFormat()
                            ->setFormatCode(isset($matches[4]) ? 'dd.mm.yyyy hh:mm' : 'dd.mm.yyyy');
                    } catch (Exception $e) {
                        $cellValue = $cell;
                    }
                }
                // Обрабатываем числа
                elseif (is_numeric($cell) || (is_string($cell) && preg_match('/^-?\d+\.?\d*$/', trim($cell)))) {
                    $cellValue = is_string($cell) ? floatval($cell) : $cell;
                    $cellType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                } else {
                    $cellType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
                }
                
                $sheet->setCellValueExplicitByColumnAndRow($colIndex, $rowIndex, $cellValue, $cellType);
                $colIndex++;
            }
            $rowIndex++;
        }
        
        // Автоматическая ширина столбцов
        foreach (range(1, count($headers ?: (isset($data[0]) ? $data[0] : []))) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        
        // Заголовки HTTP
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        // Сохраняем в поток вывода
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        // В случае ошибки используем старый формат
        exportToExcelLegacy($data, $filename, $headers);
    }
}

// Резервная функция для экспорта без библиотеки (старый формат)
function exportToExcelLegacy($data, $filename, $headers = []) {
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    $filename = substr($filename, 0, 100);
    
    // Используем правильный MIME-тип для формата Excel 2003 XML (SpreadsheetML)
    // Это старый формат, но Excel его открывает корректно
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
    echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
    echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<Worksheet ss:Name="Sheet1">' . "\n";
    echo '<Table>' . "\n";
    
    $escape = function($value) {
        if (is_null($value)) return '';
        $value = htmlspecialchars($value, ENT_XML1, 'UTF-8');
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        return $value;
    };
    
    if (!empty($headers)) {
        echo '<Row>' . "\n";
        foreach ($headers as $header) {
            echo '<Cell><Data ss:Type="String">' . $escape($header) . '</Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";
    }
    
    foreach ($data as $row) {
        // Проверяем, что $row - это массив
        if (!is_array($row)) {
            continue;
        }
        
        echo '<Row>' . "\n";
        foreach ($row as $cell) {
            // Обрабатываем null и пустые значения
            if ($cell === null || $cell === '') {
                echo '<Cell><Data ss:Type="String"></Data></Cell>' . "\n";
            } elseif (is_numeric($cell) || (is_string($cell) && preg_match('/^-?\d+\.?\d*$/', trim($cell)))) {
                $numValue = is_string($cell) ? floatval($cell) : $cell;
                echo '<Cell><Data ss:Type="Number">' . $numValue . '</Data></Cell>' . "\n";
            } else {
                echo '<Cell><Data ss:Type="String">' . $escape($cell) . '</Data></Cell>' . "\n";
            }
        }
        echo '</Row>' . "\n";
    }
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>' . "\n";
    exit;
}

// Обработка различных типов отчётов
switch ($reportType) {
    case 'companies':
        // Дополнительные фильтры
        $whereConditions = [];
        $params = [];
        
        if ($dateFrom) {
            $whereConditions[] = "c.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $whereConditions[] = "c.created_at <= :date_to";
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }
        if (isset($_GET['status']) && $_GET['status']) {
            $whereConditions[] = "c.status = :status";
            $params[':status'] = $_GET['status'];
        }
        if (isset($_GET['industry']) && $_GET['industry']) {
            $whereConditions[] = "c.industry ILIKE :industry";
            $params[':industry'] = '%' . $_GET['industry'] . '%';
        }
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                c.id,
                c.name as \"Название\",
                c.email as \"Email\",
                c.phone as \"Телефон\",
                c.website as \"Сайт\",
                c.address as \"Адрес\",
                c.industry as \"Отрасль\",
                c.status as \"Статус\",
                u.name as \"Ответственный\",
                TO_CHAR(c.created_at, 'DD.MM.YYYY') as \"Дата создания\"
            FROM companies c
            LEFT JOIN users u ON c.user_id = u.id
            $whereClause
            ORDER BY c.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $headers = ['ID', 'Название', 'Email', 'Телефон', 'Сайт', 'Адрес', 'Отрасль', 'Статус', 'Ответственный', 'Дата создания'];
        $rows = [];
        foreach ($data as $row) {
            $rows[] = array_values($row);
        }
        exportToExcel($rows, 'companies_' . date('Y-m-d'), $headers);
        break;
        
    case 'contacts':
        $whereConditions = [];
        $params = [];
        
        if ($dateFrom) {
            $whereConditions[] = "c.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $whereConditions[] = "c.created_at <= :date_to";
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }
        if (isset($_GET['company_name']) && $_GET['company_name']) {
            $whereConditions[] = "comp.name ILIKE :company_name";
            $params[':company_name'] = '%' . $_GET['company_name'] . '%';
        }
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                c.id,
                c.first_name || ' ' || c.last_name as \"ФИО\",
                c.email as \"Email\",
                c.phone as \"Телефон\",
                c.position as \"Должность\",
                comp.name as \"Компания\",
                u.name as \"Ответственный\",
                TO_CHAR(c.created_at, 'DD.MM.YYYY') as \"Дата создания\"
            FROM contacts c
            LEFT JOIN companies comp ON c.company_id = comp.id
            LEFT JOIN users u ON c.user_id = u.id
            $whereClause
            ORDER BY c.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $headers = ['ID', 'ФИО', 'Email', 'Телефон', 'Должность', 'Компания', 'Ответственный', 'Дата создания'];
        $rows = [];
        foreach ($data as $row) {
            $rows[] = array_values($row);
        }
        exportToExcel($rows, 'contacts_' . date('Y-m-d'), $headers);
        break;
        
    case 'deals':
        $whereConditions = [];
        $params = [];
        
        if ($dateFrom) {
            $whereConditions[] = "d.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $whereConditions[] = "d.created_at <= :date_to";
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }
        if (isset($_GET['stage']) && $_GET['stage']) {
            $whereConditions[] = "d.stage = :stage";
            $params[':stage'] = $_GET['stage'];
        }
        if (isset($_GET['min_amount']) && $_GET['min_amount']) {
            $whereConditions[] = "d.amount >= :min_amount";
            $params[':min_amount'] = $_GET['min_amount'];
        }
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                d.id,
                d.title as \"Название\",
                d.amount as \"Сумма\",
                d.stage as \"Этап\",
                d.probability as \"Вероятность %\",
                comp.name as \"Компания\",
                c.first_name || ' ' || c.last_name as \"Контакт\",
                u.name as \"Ответственный\",
                TO_CHAR(d.expected_close_date, 'DD.MM.YYYY') as \"Ожидаемая дата закрытия\",
                TO_CHAR(d.created_at, 'DD.MM.YYYY') as \"Дата создания\"
            FROM deals d
            LEFT JOIN companies comp ON d.company_id = comp.id
            LEFT JOIN contacts c ON d.contact_id = c.id
            LEFT JOIN users u ON d.user_id = u.id
            $whereClause
            ORDER BY d.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $headers = ['ID', 'Название', 'Сумма', 'Этап', 'Вероятность %', 'Компания', 'Контакт', 'Ответственный', 'Ожидаемая дата закрытия', 'Дата создания'];
        $rows = [];
        foreach ($data as $row) {
            // Форматируем сумму без символа валюты для Excel (Excel может не распознать)
            $row['Сумма'] = number_format($row['Сумма'], 2, '.', '');
            $row['Этап'] = translateStatus($row['Этап']);
            $rows[] = array_values($row);
        }
        exportToExcel($rows, 'deals_' . date('Y-m-d'), $headers);
        break;
        
    case 'tasks':
        $whereConditions = [];
        $params = [];
        
        if ($dateFrom) {
            $whereConditions[] = "t.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $whereConditions[] = "t.created_at <= :date_to";
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }
        if (isset($_GET['status']) && $_GET['status']) {
            $whereConditions[] = "t.status = :status";
            $params[':status'] = $_GET['status'];
        }
        if (isset($_GET['priority']) && $_GET['priority']) {
            $whereConditions[] = "t.priority = :priority";
            $params[':priority'] = $_GET['priority'];
        }
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                t.id,
                t.title as \"Название\",
                t.description as \"Описание\",
                t.status as \"Статус\",
                t.priority as \"Приоритет\",
                TO_CHAR(t.due_date, 'DD.MM.YYYY') as \"Срок выполнения\",
                comp.name as \"Компания\",
                c.first_name || ' ' || c.last_name as \"Контакт\",
                u.name as \"Ответственный\",
                TO_CHAR(t.created_at, 'DD.MM.YYYY') as \"Дата создания\"
            FROM tasks t
            LEFT JOIN companies comp ON t.company_id = comp.id
            LEFT JOIN contacts c ON t.contact_id = c.id
            LEFT JOIN users u ON t.user_id = u.id
            $whereClause
            ORDER BY t.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $headers = ['ID', 'Название', 'Описание', 'Статус', 'Приоритет', 'Срок выполнения', 'Компания', 'Контакт', 'Ответственный', 'Дата создания'];
        $rows = [];
        foreach ($data as $row) {
            $row['Статус'] = translateStatus($row['Статус']);
            $row['Приоритет'] = translateStatus($row['Приоритет']);
            $rows[] = array_values($row);
        }
        exportToExcel($rows, 'tasks_' . date('Y-m-d'), $headers);
        break;
        
    case 'activities':
        $whereConditions = [];
        $params = [];
        
        if ($dateFrom) {
            $whereConditions[] = "a.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $whereConditions[] = "a.created_at <= :date_to";
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                a.id,
                a.type as \"Тип\",
                a.subject as \"Тема\",
                a.description as \"Описание\",
                comp.name as \"Компания\",
                c.first_name || ' ' || c.last_name as \"Контакт\",
                u.name as \"Автор\",
                a.created_at::timestamp as \"Дата создания\"
            FROM activities a
            LEFT JOIN companies comp ON a.company_id = comp.id
            LEFT JOIN contacts c ON a.contact_id = c.id
            LEFT JOIN users u ON a.user_id = u.id
            $whereClause
            ORDER BY a.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $headers = ['ID', 'Тип', 'Тема', 'Описание', 'Компания', 'Контакт', 'Автор', 'Дата создания'];
        $rows = [];
        foreach ($data as $row) {
            $rows[] = array_values($row);
        }
        exportToExcel($rows, 'activities_' . date('Y-m-d'), $headers);
        break;
        
    case 'user_stats':
        $query = "
            SELECT 
                u.name as \"Сотрудник\",
                u.role as \"Роль\",
                COUNT(DISTINCT c.id) as \"Компании\",
                COUNT(DISTINCT co.id) as \"Контакты\",
                COUNT(DISTINCT d.id) as \"Сделки\",
                COUNT(DISTINCT t.id) as \"Задачи\",
                COALESCE(SUM(CASE WHEN d.stage = 'won' THEN d.amount ELSE 0 END), 0) as \"Выручка\"
            FROM users u
            LEFT JOIN companies c ON u.id = c.user_id
            LEFT JOIN contacts co ON u.id = co.user_id
            LEFT JOIN deals d ON u.id = d.user_id
            LEFT JOIN tasks t ON u.id = t.user_id
            GROUP BY u.id, u.name, u.role
            ORDER BY COALESCE(SUM(CASE WHEN d.stage = 'won' THEN d.amount ELSE 0 END), 0) DESC
        ";
        
        $stmt = $db->query($query);
        $data = $stmt->fetchAll();
        
        $headers = ['Сотрудник', 'Роль', 'Компании', 'Контакты', 'Сделки', 'Задачи', 'Выручка'];
        $rows = [];
        foreach ($data as $row) {
            $row['Роль'] = $row['Роль'] === 'admin' ? 'Админ' : 'Пользователь';
            $row['Выручка'] = number_format($row['Выручка'], 2, '.', '');
            $rows[] = array_values($row);
        }
        exportToExcel($rows, 'user_stats_' . date('Y-m-d'), $headers);
        break;
        
    case 'financial':
        $whereConditions = [];
        $params = [];
        
        if ($dateFrom) {
            $whereConditions[] = "d.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $whereConditions[] = "d.created_at <= :date_to";
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                d.stage as \"Этап\",
                COUNT(*) as \"Количество сделок\",
                COALESCE(SUM(d.amount), 0) as \"Сумма\",
                AVG(d.amount) as \"Средняя сумма\",
                AVG(d.probability) as \"Средняя вероятность %\"
            FROM deals d
            $whereClause
            GROUP BY d.stage
            ORDER BY 
                CASE d.stage
                    WHEN 'lead' THEN 1
                    WHEN 'qualified' THEN 2
                    WHEN 'proposal' THEN 3
                    WHEN 'negotiation' THEN 4
                    WHEN 'won' THEN 5
                    WHEN 'lost' THEN 6
                END
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $headers = ['Этап', 'Количество сделок', 'Сумма', 'Средняя сумма', 'Средняя вероятность %'];
        $rows = [];
        foreach ($data as $row) {
            $row['Этап'] = translateStatus($row['Этап']);
            $row['Сумма'] = number_format($row['Сумма'], 2, '.', '');
            $row['Средняя сумма'] = number_format($row['Средняя сумма'], 2, '.', '');
            $row['Средняя вероятность %'] = round($row['Средняя вероятность %'], 1) . '%';
            $rows[] = array_values($row);
        }
        exportToExcel($rows, 'financial_' . date('Y-m-d'), $headers);
        break;
        
    case 'stats':
        // Общая статистика
        $headers = ['Показатель', 'Значение'];
        $rows = [
            ['Всего компаний', $db->query("SELECT COUNT(*) FROM companies")->fetchColumn()],
            ['Активных компаний', $db->query("SELECT COUNT(*) FROM companies WHERE status='active'")->fetchColumn()],
            ['Всего контактов', $db->query("SELECT COUNT(*) FROM contacts")->fetchColumn()],
            ['Всего сделок', $db->query("SELECT COUNT(*) FROM deals")->fetchColumn()],
            ['Выигранных сделок', $db->query("SELECT COUNT(*) FROM deals WHERE stage='won'")->fetchColumn()],
            ['Проигранных сделок', $db->query("SELECT COUNT(*) FROM deals WHERE stage='lost'")->fetchColumn()],
            ['Активных сделок', $db->query("SELECT COUNT(*) FROM deals WHERE stage NOT IN ('won', 'lost')")->fetchColumn()],
            ['Общая выручка', number_format($db->query("SELECT COALESCE(SUM(amount), 0) FROM deals WHERE stage='won'")->fetchColumn(), 2, '.', '')],
            ['Воронка продаж', number_format($db->query("SELECT COALESCE(SUM(amount), 0) FROM deals WHERE stage NOT IN ('won', 'lost')")->fetchColumn(), 2, '.', '')],
            ['Всего задач', $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn()],
            ['Завершённых задач', $db->query("SELECT COUNT(*) FROM tasks WHERE status='completed'")->fetchColumn()],
            ['Просроченных задач', $db->query("SELECT COUNT(*) FROM tasks WHERE status!='completed' AND due_date < CURRENT_DATE")->fetchColumn()],
        ];
        
        exportToExcel($rows, 'statistics_' . date('Y-m-d'), $headers);
        break;
        
    case 'top_companies':
        $query = "
            SELECT 
                c.name as \"Компания\",
                COUNT(d.id) as \"Количество сделок\",
                COALESCE(SUM(d.amount), 0) as \"Выручка\"
            FROM companies c
            LEFT JOIN deals d ON c.id = d.company_id AND d.stage = 'won'
            GROUP BY c.id, c.name
            HAVING COUNT(d.id) > 0
            ORDER BY COALESCE(SUM(d.amount), 0) DESC
            LIMIT 10
        ";
        
        $stmt = $db->query($query);
        $data = $stmt->fetchAll();
        
        $headers = ['Компания', 'Количество сделок', 'Выручка'];
        $rows = [];
        foreach ($data as $row) {
            $row['Выручка'] = number_format($row['Выручка'], 2, '.', '');
            $rows[] = array_values($row);
        }
        exportToExcel($rows, 'top_companies_' . date('Y-m-d'), $headers);
        break;
        
    default:
        setFlash('Неверный тип отчёта.', 'danger');
        header('Location: reports.php');
        exit;
}

