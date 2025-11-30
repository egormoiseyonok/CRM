<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$pageTitle = 'Панель управления';
$user = getCurrentUser();
$isAdmin = isAdmin();
$isManager = isManager();
$isAdminOrManager = isAdminOrManager();

// Для обычных пользователей показываем только их данные
$userFilterSimple = $isAdminOrManager ? '' : " AND user_id = {$user['id']}";
$userFilterDeals = $isAdminOrManager ? '' : " AND d.user_id = {$user['id']}";
$userFilter = $isAdminOrManager ? '' : " AND a.user_id = {$user['id']}";
$userFilterTasks = $isAdminOrManager ? '' : " AND t.user_id = {$user['id']}";

// Статистика
if ($isAdminOrManager) {
    // Полная статистика для админа
    $stats = [
        'companies' => $db->query("SELECT COUNT(*) FROM companies WHERE status='active'")->fetchColumn(),
        'contacts' => $db->query("SELECT COUNT(*) FROM contacts")->fetchColumn(),
        'deals' => $db->query("SELECT COUNT(*) FROM deals WHERE stage NOT IN ('won', 'lost')")->fetchColumn(),
        'tasks' => $db->query("SELECT COUNT(*) FROM tasks WHERE status != 'completed'")->fetchColumn(),
        'revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM deals WHERE stage = 'won'")->fetchColumn(),
        'pipeline' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM deals WHERE stage NOT IN ('won', 'lost')")->fetchColumn(),
    ];
} else {
    // Ограниченная статистика для пользователя
    $stats = [
        'companies' => $db->query("SELECT COUNT(*) FROM companies WHERE status='active' AND user_id = {$user['id']}")->fetchColumn(),
        'contacts' => $db->query("SELECT COUNT(*) FROM contacts WHERE user_id = {$user['id']}")->fetchColumn(),
        'deals' => $db->query("SELECT COUNT(*) FROM deals WHERE stage NOT IN ('won', 'lost') AND user_id = {$user['id']}")->fetchColumn(),
        'tasks' => $db->query("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND user_id = {$user['id']}")->fetchColumn(),
    ];
}

// Сделки по этапам
$dealsByStage = $db->query("
    SELECT stage, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM deals 
    WHERE stage NOT IN ('won', 'lost') $userFilterSimple
    GROUP BY stage
    ORDER BY 
        CASE stage
            WHEN 'lead' THEN 1
            WHEN 'qualified' THEN 2
            WHEN 'proposal' THEN 3
            WHEN 'negotiation' THEN 4
        END
")->fetchAll();

// Последние активности
$activities = $db->query("
    SELECT a.*, u.name as user_name, c.name as company_name
    FROM activities a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN companies c ON a.company_id = c.id
    WHERE 1=1 $userFilter
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll();

// Приоритетные задачи
$urgentTasks = $db->query("
    SELECT t.*, c.name as company_name, co.first_name || ' ' || co.last_name as contact_name
    FROM tasks t
    LEFT JOIN companies c ON t.company_id = c.id
    LEFT JOIN contacts co ON t.contact_id = co.id
    WHERE t.status != 'completed' AND t.due_date <= CURRENT_DATE + INTERVAL '7 days' $userFilterTasks
    ORDER BY t.due_date ASC, t.priority DESC
    LIMIT 5
")->fetchAll();

// Топ сделки
$topDeals = $db->query("
    SELECT d.*, c.name as company_name
    FROM deals d
    LEFT JOIN companies c ON d.company_id = c.id
    WHERE d.stage NOT IN ('won', 'lost') $userFilterDeals
    ORDER BY d.amount DESC
    LIMIT 5
")->fetchAll();

// Вычисляем pipeline для процентов
$pipelineTotal = array_sum(array_column($dealsByStage, 'total'));

ob_start();
?>

<div class="page-header">
    <div class="page-title">
        <h1>Панель управления</h1>
    </div>
    <p class="page-description">
        <?= $isAdmin ? 'Обзор общей активности и ключевых показателей' : 'Обзор вашей активности' ?>
    </p>
</div>

<!-- Быстрые действия для админа -->
                <?php if ($isAdminOrManager): ?>
<div class="card mb-20" style="background: var(--dark); color: white; border: none;">
    <div class="card-body" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h3 style="color: white; margin-bottom: 8px; font-size: 20px;">Отчёты и аналитика</h3>
                <p style="color: rgba(255, 255, 255, 0.9); margin: 0; font-size: 14px;">
                    Создавайте отчёты и экспортируйте данные в Excel
                </p>
            </div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="reports.php" class="btn" style="background: white; color: var(--dark); font-weight: 600;">
                    Открыть отчёты
                </a>
                <a href="export_report.php?report_type=stats&format=excel" class="btn" style="background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                    Экспорт статистики
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Статистические карточки -->
                <?php if ($isAdminOrManager): ?>
    <!-- Полная статистика для админа -->
    <div class="grid grid-4 mb-20">
        <div class="stat-card">
            <div class="stat-icon primary"></div>
            <div class="stat-content">
                <div class="stat-label">Активные компании</div>
                <div class="stat-value"><?= $stats['companies'] ?></div>
                <div class="stat-change up">↑ 12% за месяц</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success"></div>
            <div class="stat-content">
                <div class="stat-label">Активные сделки</div>
                <div class="stat-value"><?= $stats['deals'] ?></div>
                <div class="stat-change up">↑ 8% за месяц</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon warning"></div>
            <div class="stat-content">
                <div class="stat-label">Открытые задачи</div>
                <div class="stat-value"><?= $stats['tasks'] ?></div>
                <div class="stat-change down">↓ 5% за неделю</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon info"></div>
            <div class="stat-content">
                <div class="stat-label">Выручка</div>
                <div class="stat-value"><?= formatMoney($stats['revenue']) ?></div>
                <div class="stat-change up">↑ 23% за месяц</div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Упрощенная статистика для пользователя -->
    <div class="grid grid-4 mb-20">
        <div class="stat-card">
            <div class="stat-icon primary"></div>
            <div class="stat-content">
                <div class="stat-label">Мои компании</div>
                <div class="stat-value"><?= $stats['companies'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon info"></div>
            <div class="stat-content">
                <div class="stat-label">Мои контакты</div>
                <div class="stat-value"><?= $stats['contacts'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success"></div>
            <div class="stat-content">
                <div class="stat-label">Мои сделки</div>
                <div class="stat-value"><?= $stats['deals'] ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon warning"></div>
            <div class="stat-content">
                <div class="stat-label">Мои задачи</div>
                <div class="stat-value"><?= $stats['tasks'] ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-2 mb-20">
    <!-- Воронка продаж -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= $isAdmin ? 'Воронка продаж' : 'Мои сделки' ?></h3>
                <?php if ($isAdminOrManager): ?>
                <span class="text-muted"><?= formatMoney($stats['pipeline']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($dealsByStage): ?>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($dealsByStage as $stage): ?>
                        <?php
                        $percent = $pipelineTotal > 0 ? round(($stage['total'] / $pipelineTotal) * 100) : 0;
                        ?>
                        <div>
                            <div class="d-flex justify-content-between mb-10">
                                <span style="font-weight: 500;"><?= translateStatus($stage['stage']) ?></span>
                                <span class="text-muted"><?= $stage['count'] ?> <?= $stage['count'] == 1 ? 'сделка' : 'сделок' ?><?= $isAdmin ? ' • ' . formatMoney($stage['total']) : '' ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: <?= $percent ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>Нет активных сделок</p>
                    <a href="deals.php" class="btn btn-primary btn-sm mt-10">Создать сделку</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Топ сделки / Срочные задачи -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Срочные задачи</h3>
            <a href="tasks.php" class="btn btn-sm btn-outline">Все задачи →</a>
        </div>
        <div class="card-body p-0">
            <?php if ($urgentTasks): ?>
                <div class="card-list">
                    <?php foreach ($urgentTasks as $task): ?>
                        <?php
                        $daysLeft = floor((strtotime($task['due_date']) - time()) / 86400);
                        $isOverdue = $daysLeft < 0;
                        ?>
                        <div class="list-card" style="margin: 0; box-shadow: none; border-bottom: 1px solid var(--border); border-radius: 0;">
                            <div class="list-card-content">
                                <div class="list-card-title"><?= e($task['title']) ?></div>
                                <div class="list-card-subtitle"><?= e($task['company_name'] ?? $task['contact_name'] ?? 'Без привязки') ?></div>
                                <div class="list-card-meta">
                                    <span class="badge badge-<?= getBadgeClass('priority', $task['priority']) ?>">
                                        <?= translateStatus($task['priority']) ?>
                                    </span>
                                    <span style="color: <?= $isOverdue ? 'var(--danger)' : '#6b7280' ?>;">
                                        <?= $isOverdue ? 'Просрочено' : formatDate($task['due_date']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>Нет срочных задач</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

                <?php if ($isAdminOrManager): ?>
<!-- Дополнительная секция для админа -->
<div class="grid grid-2">
    <!-- Топ сделки -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Крупные сделки</h3>
            <a href="deals.php" class="btn btn-sm btn-outline">Все сделки →</a>
        </div>
        <div class="card-body p-0">
            <?php if ($topDeals): ?>
                <div class="card-list">
                    <?php foreach ($topDeals as $deal): ?>
                        <div class="list-card" style="margin: 0; box-shadow: none; border-bottom: 1px solid var(--border); border-radius: 0;">
                            <div class="list-card-content">
                                <div class="list-card-title"><?= e($deal['title']) ?></div>
                                <div class="list-card-subtitle"><?= e($deal['company_name'] ?? 'Без компании') ?></div>
                                <div class="list-card-meta">
                                    <span class="badge badge-<?= getBadgeClass('stage', $deal['stage']) ?>">
                                        <?= translateStatus($deal['stage']) ?>
                                    </span>
                                    <span style="font-weight: 600; color: var(--success);"><?= formatMoney($deal['amount']) ?></span>
                                </div>
                            </div>
                            <div class="list-card-actions">
                                <span style="font-size: 12px; color: #9ca3af;"><?= $deal['probability'] ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>Нет сделок</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Последние активности -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Последние активности</h3>
            <a href="activities.php" class="btn btn-sm btn-outline">Все →</a>
        </div>
        <div class="card-body">
            <?php if ($activities): ?>
                <div class="timeline">
                    <?php foreach (array_slice($activities, 0, 5) as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker <?= $activity['type'] === 'note' ? 'info' : 'primary' ?>"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <div class="timeline-title"><?= e($activity['subject'] ?? $activity['type']) ?></div>
                                    <div class="timeline-time"><?= timeAgo($activity['created_at']) ?></div>
                                </div>
                                <?php if ($activity['description']): ?>
                                    <p class="text-muted" style="font-size: 13px; margin-top: 4px;">
                                        <?= e(mb_substr($activity['description'], 0, 100)) ?><?= mb_strlen($activity['description']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($activity['company_name']): ?>
                                    <p style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                                        <?= e($activity['company_name']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>Нет активностей</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Упрощенная активность для пользователя -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Моя активность</h3>
        <a href="activities.php" class="btn btn-sm btn-outline">Все →</a>
    </div>
    <div class="card-body">
        <?php if ($activities): ?>
            <div class="timeline">
                <?php foreach (array_slice($activities, 0, 8) as $activity): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker <?= $activity['type'] === 'note' ? 'info' : 'primary' ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <div class="timeline-title"><?= e($activity['subject'] ?? $activity['type']) ?></div>
                                <div class="timeline-time"><?= timeAgo($activity['created_at']) ?></div>
                            </div>
                            <?php if ($activity['description']): ?>
                                <p class="text-muted" style="font-size: 13px; margin-top: 4px;">
                                    <?= e(mb_substr($activity['description'], 0, 100)) ?><?= mb_strlen($activity['description']) > 100 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($activity['company_name']): ?>
                                <p style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                                    <?= e($activity['company_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <p>Пока нет активностей</p>
                <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">
                    Начните работать с компаниями, контактами и сделками
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>