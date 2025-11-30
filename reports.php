<?php
require_once 'config.php';
checkAuth();

$user = getCurrentUser();

// Проверка прав доступа - только для администраторов и менеджеров
if (!in_array($user['role'], ['admin', 'manager'])) {
    setFlash('Доступ запрещен. Отчёты доступны только администраторам и менеджерам.', 'danger');
    header('Location: index.php');
    exit;
}

$db = getDB();
$pageTitle = 'Отчёты';

// Общая статистика
$stats = [
    'total_companies' => $db->query("SELECT COUNT(*) FROM companies")->fetchColumn(),
    'active_companies' => $db->query("SELECT COUNT(*) FROM companies WHERE status='active'")->fetchColumn(),
    'total_contacts' => $db->query("SELECT COUNT(*) FROM contacts")->fetchColumn(),
    'total_deals' => $db->query("SELECT COUNT(*) FROM deals")->fetchColumn(),
    'won_deals' => $db->query("SELECT COUNT(*) FROM deals WHERE stage='won'")->fetchColumn(),
    'lost_deals' => $db->query("SELECT COUNT(*) FROM deals WHERE stage='lost'")->fetchColumn(),
    'active_deals' => $db->query("SELECT COUNT(*) FROM deals WHERE stage NOT IN ('won', 'lost')")->fetchColumn(),
    'total_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM deals WHERE stage='won'")->fetchColumn(),
    'pipeline_value' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM deals WHERE stage NOT IN ('won', 'lost')")->fetchColumn(),
    'total_tasks' => $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn(),
    'completed_tasks' => $db->query("SELECT COUNT(*) FROM tasks WHERE status='completed'")->fetchColumn(),
    'overdue_tasks' => $db->query("SELECT COUNT(*) FROM tasks WHERE status!='completed' AND due_date < CURRENT_DATE")->fetchColumn(),
];

// Сделки по этапам
$dealsByStage = $db->query("
    SELECT stage, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM deals
    GROUP BY stage
    ORDER BY 
        CASE stage
            WHEN 'lead' THEN 1
            WHEN 'qualified' THEN 2
            WHEN 'proposal' THEN 3
            WHEN 'negotiation' THEN 4
            WHEN 'won' THEN 5
            WHEN 'lost' THEN 6
        END
")->fetchAll();

// Топ компаний по выручке
$topCompanies = $db->query("
    SELECT c.name, COALESCE(SUM(d.amount), 0) as revenue, COUNT(d.id) as deals_count
    FROM companies c
    LEFT JOIN deals d ON c.id = d.company_id AND d.stage = 'won'
    GROUP BY c.id, c.name
    HAVING COUNT(d.id) > 0
    ORDER BY revenue DESC
    LIMIT 10
")->fetchAll();

// Статистика по пользователям
$userStats = $db->query("
    SELECT 
        u.name,
        u.role,
        COUNT(DISTINCT c.id) as companies,
        COUNT(DISTINCT co.id) as contacts,
        COUNT(DISTINCT d.id) as deals,
        COUNT(DISTINCT t.id) as tasks,
        COALESCE(SUM(CASE WHEN d.stage = 'won' THEN d.amount ELSE 0 END), 0) as revenue
    FROM users u
    LEFT JOIN companies c ON u.id = c.user_id
    LEFT JOIN contacts co ON u.id = co.user_id
    LEFT JOIN deals d ON u.id = d.user_id
    LEFT JOIN tasks t ON u.id = t.user_id
    GROUP BY u.id, u.name, u.role
    ORDER BY revenue DESC
")->fetchAll();

// Активность по месяцам
$monthlyActivity = $db->query("
    SELECT 
        TO_CHAR(created_at, 'YYYY-MM') as month,
        COUNT(*) as count
    FROM activities
    WHERE created_at >= CURRENT_DATE - INTERVAL '6 months'
    GROUP BY month
    ORDER BY month DESC
")->fetchAll();

// Конверсия по этапам
$conversionRate = $stats['total_deals'] > 0 ? round(($stats['won_deals'] / $stats['total_deals']) * 100, 1) : 0;
$lossRate = $stats['total_deals'] > 0 ? round(($stats['lost_deals'] / $stats['total_deals']) * 100, 1) : 0;

ob_start();
?>

<div class="page-header">
    <div>
        <div class="page-title">
            <h1>Отчёты и аналитика</h1>
            <span class="badge badge-primary">Только для администраторов</span>
        </div>
        <p class="page-description">Детальная статистика, графики и экспорт данных в Excel</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            ← Назад
        </a>
    </div>
</div>

<!-- Быстрые действия -->
<div class="grid grid-3 mb-20">
    <a href="export_report.php?report_type=companies&format=excel" class="card quick-action-card" style="text-decoration: none; color: inherit;">
        <div class="card-body" style="text-align: center; padding: 24px;">
            <h4 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">Экспорт компаний</h4>
            <p style="font-size: 13px; color: #6b7280; margin: 0;">Скачать в Excel</p>
        </div>
    </a>
    <a href="export_report.php?report_type=deals&format=excel" class="card quick-action-card" style="text-decoration: none; color: inherit;">
        <div class="card-body" style="text-align: center; padding: 24px;">
            <h4 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">Экспорт сделок</h4>
            <p style="font-size: 13px; color: #6b7280; margin: 0;">Скачать в Excel</p>
        </div>
    </a>
    <a href="export_report.php?report_type=user_stats&format=excel" class="card quick-action-card" style="text-decoration: none; color: inherit;">
        <div class="card-body" style="text-align: center; padding: 24px;">
            <h4 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">Статистика сотрудников</h4>
            <p style="font-size: 13px; color: #6b7280; margin: 0;">Скачать в Excel</p>
        </div>
    </a>
</div>

<!-- Форма создания отчёта -->
<div class="card mb-20">
    <div class="card-header">
        <h3 class="card-title">Создать кастомный отчёт</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="export_report.php" id="reportForm">
            <div class="grid grid-2 mb-20">
                <div class="form-group">
                    <label class="form-label">Тип отчёта</label>
                    <select name="report_type" id="reportType" class="form-control" required>
                        <option value="">Выберите тип отчёта</option>
                        <option value="companies">Компании</option>
                        <option value="contacts">Контакты</option>
                        <option value="deals">Сделки</option>
                        <option value="tasks">Задачи</option>
                        <option value="activities">Активности</option>
                        <option value="user_stats">Статистика по пользователям</option>
                        <option value="financial">Финансовый отчёт</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Формат экспорта</label>
                    <select name="format" class="form-control" required>
                        <option value="excel">Excel (CSV)</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-2 mb-20">
                <div class="form-group">
                    <label class="form-label">Дата от</label>
                    <input type="date" name="date_from" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Дата до</label>
                    <input type="date" name="date_to" class="form-control">
                </div>
            </div>
            
            <div id="additionalFilters" class="mb-20" style="display: none;">
                <!-- Дополнительные фильтры будут добавлены через JavaScript -->
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Экспортировать в Excel
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetReportForm()">
                    Очистить
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Общая статистика -->
<div class="card mb-20">
    <div class="card-header card-header-with-action">
        <h3 class="card-title">Общая статистика</h3>
        <a href="export_report.php?report_type=stats&format=excel" class="btn btn-sm btn-primary">
            Экспорт в Excel
        </a>
    </div>
    <div class="card-body">
        <div class="grid grid-4">
            <div class="stat-card" style="box-shadow: none; margin: 0;">
                <div class="stat-icon primary"></div>
                <div class="stat-content">
                    <div class="stat-label">Всего компаний</div>
                    <div class="stat-value"><?= $stats['total_companies'] ?></div>
                    <div class="stat-change"><?= $stats['active_companies'] ?> активных</div>
                </div>
            </div>
            
            <div class="stat-card" style="box-shadow: none; margin: 0;">
                <div class="stat-icon success"></div>
                <div class="stat-content">
                    <div class="stat-label">Контакты</div>
                    <div class="stat-value"><?= $stats['total_contacts'] ?></div>
                </div>
            </div>
            
            <div class="stat-card" style="box-shadow: none; margin: 0;">
                <div class="stat-icon warning"></div>
                <div class="stat-content">
                    <div class="stat-label">Всего сделок</div>
                    <div class="stat-value"><?= $stats['total_deals'] ?></div>
                    <div class="stat-change"><?= $stats['active_deals'] ?> активных</div>
                </div>
            </div>
            
            <div class="stat-card" style="box-shadow: none; margin: 0;">
                <div class="stat-icon info"></div>
                <div class="stat-content">
                    <div class="stat-label">Задачи</div>
                    <div class="stat-value"><?= $stats['total_tasks'] ?></div>
                    <div class="stat-change"><?= $stats['completed_tasks'] ?> завершено</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2 mb-20">
    <!-- Финансовые показатели -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Финансовые показатели</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 24px;">
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-weight: 500;">Общая выручка</span>
                        <span style="font-size: 20px; font-weight: 700; color: var(--success);">
                            <?= formatMoney($stats['total_revenue']) ?>
                        </span>
                    </div>
                    <div style="font-size: 13px; color: #6b7280;">
                        Закрытых сделок: <?= $stats['won_deals'] ?>
                    </div>
                </div>
                
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-weight: 500;">Воронка продаж</span>
                        <span style="font-size: 20px; font-weight: 700; color: var(--primary);">
                            <?= formatMoney($stats['pipeline_value']) ?>
                        </span>
                    </div>
                    <div style="font-size: 13px; color: #6b7280;">
                        Активных сделок: <?= $stats['active_deals'] ?>
                    </div>
                </div>
                
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-weight: 500;">Средний чек</span>
                        <span style="font-size: 20px; font-weight: 700; color: var(--dark);">
                            <?= $stats['won_deals'] > 0 ? formatMoney($stats['total_revenue'] / $stats['won_deals']) : '0 ₽' ?>
                        </span>
                    </div>
                </div>
                
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                        <span style="font-weight: 500;">Конверсия</span>
                        <div style="text-align: right;">
                            <div style="font-size: 20px; font-weight: 700; color: var(--success);">
                                <?= $conversionRate ?>%
                            </div>
                            <div style="font-size: 12px; color: #9ca3af;">
                                Проиграно: <?= $lossRate ?>%
                            </div>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar success" style="width: <?= $conversionRate ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Сделки по этапам -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Распределение сделок</h3>
        </div>
        <div class="card-body">
            <?php if ($dealsByStage): ?>
                <?php foreach ($dealsByStage as $stage): ?>
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 500;"><?= translateStatus($stage['stage']) ?></span>
                            <span>
                                <?= $stage['count'] ?> сделок • <?= formatMoney($stage['total']) ?>
                            </span>
                        </div>
                        <div class="progress">
                            <?php
                            $percent = $stats['total_deals'] > 0 ? ($stage['count'] / $stats['total_deals']) * 100 : 0;
                            $colorClass = '';
                            switch($stage['stage']) {
                                case 'won': $colorClass = 'success'; break;
                                case 'lost': $colorClass = 'danger'; break;
                                case 'negotiation': $colorClass = 'warning'; break;
                                default: $colorClass = '';
                            }
                            ?>
                            <div class="progress-bar <?= $colorClass ?>" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💼</div>
                    <p>Нет данных о сделках</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Статистика по пользователям -->
<?php if ($userStats): ?>
<div class="card mb-20">
    <div class="card-header card-header-with-action">
        <h3 class="card-title">Производительность сотрудников</h3>
        <a href="export_report.php?report_type=user_stats&format=excel" class="btn btn-sm btn-primary">
            Экспорт в Excel
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Роль</th>
                        <th>Компании</th>
                        <th>Контакты</th>
                        <th>Сделки</th>
                        <th>Задачи</th>
                        <th>Выручка</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userStats as $stat): ?>
                        <tr>
                            <td class="table-cell-primary"><?= e($stat['name']) ?></td>
                            <td>
                                <span class="badge badge-<?= $stat['role'] === 'admin' ? 'primary' : 'secondary' ?>">
                                    <?= $stat['role'] === 'admin' ? 'Админ' : 'Пользователь' ?>
                                </span>
                            </td>
                            <td><?= $stat['companies'] ?></td>
                            <td><?= $stat['contacts'] ?></td>
                            <td><?= $stat['deals'] ?></td>
                            <td><?= $stat['tasks'] ?></td>
                            <td><strong style="color: var(--success);"><?= formatMoney($stat['revenue']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Топ компаний -->
<?php if ($topCompanies): ?>
<div class="card mb-20">
    <div class="card-header card-header-with-action">
        <h3 class="card-title">Топ 10 компаний по выручке</h3>
        <a href="export_report.php?report_type=top_companies&format=excel" class="btn btn-sm btn-primary">
            Экспорт в Excel
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Компания</th>
                        <th>Сделок</th>
                        <th>Выручка</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topCompanies as $i => $company): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="table-cell-primary"><?= e($company['name']) ?></td>
                            <td><?= $company['deals_count'] ?></td>
                            <td><strong style="color: var(--success);"><?= formatMoney($company['revenue']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Активность -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Активность за последние 6 месяцев</h3>
    </div>
    <div class="card-body">
        <?php if ($monthlyActivity): ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php 
                $maxCount = max(array_column($monthlyActivity, 'count'));
                foreach ($monthlyActivity as $month): 
                    $percent = $maxCount > 0 ? ($month['count'] / $maxCount) * 100 : 0;
                    // Преобразовать формат YYYY-MM в читаемый вид
                    $monthParts = explode('-', $month['month']);
                    $monthNames = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
                    $monthName = $monthNames[(int)$monthParts[1] - 1] ?? '';
                    $year = $monthParts[0];
                ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 500;"><?= $monthName ?> <?= $year ?></span>
                            <span><?= $month['count'] ?> действий</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📊</div>
                <p>Нет данных за последние месяцы</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Динамические фильтры в зависимости от типа отчёта
document.getElementById('reportType').addEventListener('change', function() {
    const reportType = this.value;
    const filtersDiv = document.getElementById('additionalFilters');
    
    if (!reportType) {
        filtersDiv.style.display = 'none';
        return;
    }
    
    filtersDiv.style.display = 'block';
    let html = '<div class="grid grid-2">';
    
    if (reportType === 'deals') {
        html += `
            <div class="form-group">
                <label class="form-label">Этап сделки</label>
                <select name="stage" class="form-control">
                    <option value="">Все этапы</option>
                    <option value="lead">Лид</option>
                    <option value="qualified">Квалификация</option>
                    <option value="proposal">Предложение</option>
                    <option value="negotiation">Переговоры</option>
                    <option value="won">Выиграна</option>
                    <option value="lost">Проиграна</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Минимальная сумма</label>
                <input type="number" name="min_amount" class="form-control" placeholder="0" min="0" step="0.01">
            </div>
        `;
    } else if (reportType === 'companies') {
        html += `
            <div class="form-group">
                <label class="form-label">Статус</label>
                <select name="status" class="form-control">
                    <option value="">Все статусы</option>
                    <option value="active">Активна</option>
                    <option value="inactive">Неактивна</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Отрасль</label>
                <input type="text" name="industry" class="form-control" placeholder="IT, Строительство...">
            </div>
        `;
    } else if (reportType === 'tasks') {
        html += `
            <div class="form-group">
                <label class="form-label">Статус</label>
                <select name="status" class="form-control">
                    <option value="">Все статусы</option>
                    <option value="pending">В ожидании</option>
                    <option value="in_progress">В работе</option>
                    <option value="completed">Завершено</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Приоритет</label>
                <select name="priority" class="form-control">
                    <option value="">Все приоритеты</option>
                    <option value="low">Низкий</option>
                    <option value="medium">Средний</option>
                    <option value="high">Высокий</option>
                </select>
            </div>
        `;
    } else if (reportType === 'contacts') {
        html += `
            <div class="form-group">
                <label class="form-label">Компания</label>
                <input type="text" name="company_name" class="form-control" placeholder="Название компании">
            </div>
        `;
    }
    
    html += '</div>';
    filtersDiv.innerHTML = html;
});

function resetReportForm() {
    document.getElementById('reportForm').reset();
    document.getElementById('additionalFilters').style.display = 'none';
    document.getElementById('additionalFilters').innerHTML = '';
}

// Валидация дат
document.getElementById('reportForm').addEventListener('submit', function(e) {
    const dateFrom = document.querySelector('input[name="date_from"]').value;
    const dateTo = document.querySelector('input[name="date_to"]').value;
    
    if (dateFrom && dateTo && dateFrom > dateTo) {
        e.preventDefault();
        alert('Дата "от" не может быть больше даты "до"');
        return false;
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>