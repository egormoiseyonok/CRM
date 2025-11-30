<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header('Location: companies.php');
    exit;
}

// Получить компанию
$stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$id]);
$company = $stmt->fetch();

if (!$company) {
    setFlash('Компания не найдена', 'danger');
    header('Location: companies.php');
    exit;
}

// Проверка прав на просмотр
$user = getCurrentUser();
if (!canViewAll() && $company['user_id'] != $user['id']) {
    setFlash('У вас нет доступа к этой компании', 'danger');
    header('Location: companies.php');
    exit;
}

$pageTitle = $company['name'];

// Статистика
$stats = [
    'contacts' => $db->prepare("SELECT COUNT(*) FROM contacts WHERE company_id = ?")->execute([$id]) ? $db->query("SELECT COUNT(*) FROM contacts WHERE company_id = $id")->fetchColumn() : 0,
    'deals' => $db->prepare("SELECT COUNT(*) FROM deals WHERE company_id = ?")->execute([$id]) ? $db->query("SELECT COUNT(*) FROM deals WHERE company_id = $id")->fetchColumn() : 0,
    'tasks' => $db->prepare("SELECT COUNT(*) FROM tasks WHERE company_id = ? AND status != 'completed'")->execute([$id]) ? $db->query("SELECT COUNT(*) FROM tasks WHERE company_id = $id AND status != 'completed'")->fetchColumn() : 0,
    'revenue' => $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM deals WHERE company_id = ? AND stage = 'won'")->execute([$id]) ? $db->query("SELECT COALESCE(SUM(amount), 0) FROM deals WHERE company_id = $id AND stage = 'won'")->fetchColumn() : 0,
];

// Контакты
$contacts = $db->query("SELECT * FROM contacts WHERE company_id = $id ORDER BY created_at DESC")->fetchAll();

// Сделки
$deals = $db->query("SELECT * FROM deals WHERE company_id = $id ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Задачи
$tasks = $db->query("SELECT * FROM tasks WHERE company_id = $id ORDER BY due_date ASC LIMIT 5")->fetchAll();

// Активности
$activities = $db->query("SELECT a.*, u.name as user_name FROM activities a LEFT JOIN users u ON a.user_id = u.id WHERE a.company_id = $id ORDER BY a.created_at DESC LIMIT 10")->fetchAll();

ob_start();
?>

<div class="page-header">
    <div class="page-title">
        <div>
            <a href="companies.php" style="color: #6b7280; text-decoration: none; font-size: 14px;">← Назад к списку</a>
            <h1 style="margin-top: 8px;">
                <div class="avatar avatar-xl" style="background: <?= getAvatarColor($company['name']) ?>; display: inline-flex; margin-right: 16px; vertical-align: middle;">
                    <?= getInitials($company['name']) ?>
                </div>
                <?= e($company['name']) ?>
                <span class="badge badge-<?= getBadgeClass('status', $company['status']) ?>" style="vertical-align: middle; margin-left: 12px;">
                    <?= translateStatus($company['status']) ?>
                </span>
            </h1>
        </div>
        <div class="page-actions">
            <button class="btn btn-outline" onclick="editCompany()">✏️ Редактировать</button>
            <?php if (canDelete()): ?>
                <button class="btn btn-danger" onclick="deleteCompany()">Удалить</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Информация о компании -->
<div class="card mb-20">
    <div class="card-header">
        <h3 class="card-title">Информация</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-3">
            <div>
                <strong class="text-muted" style="display: block; font-size: 13px; margin-bottom: 4px;">Индустрия</strong>
                <div><?= e($company['industry'] ?: '—') ?></div>
            </div>
            <div>
                <strong class="text-muted" style="display: block; font-size: 13px; margin-bottom: 4px;">Email</strong>
                <div>
                    <?php if ($company['email']): ?>
                        <a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <strong class="text-muted" style="display: block; font-size: 13px; margin-bottom: 4px;">Телефон</strong>
                <div>
                    <?php if ($company['phone']): ?>
                        <a href="tel:<?= e($company['phone']) ?>"><?= e($company['phone']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <strong class="text-muted" style="display: block; font-size: 13px; margin-bottom: 4px;">Веб-сайт</strong>
                <div>
                    <?php if ($company['website']): ?>
                        <a href="<?= e($company['website']) ?>" target="_blank"><?= e($company['website']) ?> ↗</a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
            <div style="grid-column: span 2;">
                <strong class="text-muted" style="display: block; font-size: 13px; margin-bottom: 4px;">Адрес</strong>
                <div><?= e($company['address'] ?: '—') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Статистика -->
<div class="grid grid-4 mb-20">
    <div class="stat-card">
        <div class="stat-icon primary"></div>
        <div class="stat-content">
            <div class="stat-label">Контакты</div>
            <div class="stat-value"><?= $stats['contacts'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"></div>
        <div class="stat-content">
            <div class="stat-label">Сделки</div>
            <div class="stat-value"><?= $stats['deals'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"></div>
        <div class="stat-content">
            <div class="stat-label">Активные задачи</div>
            <div class="stat-value"><?= $stats['tasks'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">💰</div>
        <div class="stat-content">
            <div class="stat-label">Выручка</div>
            <div class="stat-value" style="font-size: 20px;"><?= formatMoney($stats['revenue']) ?></div>
        </div>
    </div>
</div>

<!-- Вкладки -->
<div class="tabs">
    <button class="tab active" onclick="switchTab('contacts')">Контакты (<?= $stats['contacts'] ?>)</button>
    <button class="tab" onclick="switchTab('deals')">Сделки (<?= $stats['deals'] ?>)</button>
    <button class="tab" onclick="switchTab('tasks')">Задачи (<?= $stats['tasks'] ?>)</button>
    <button class="tab" onclick="switchTab('activities')">Активности</button>
</div>

<!-- Контакты -->
<div class="tab-content active" id="tab-contacts">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Контакты</h3>
            <a href="contacts.php?company_id=<?= $id ?>" class="btn btn-sm btn-primary">+ Добавить контакт</a>
        </div>
        <div class="card-body p-0">
            <?php if ($contacts): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Имя</th>
                                <th>Должность</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td class="table-cell-avatar">
                                        <div class="avatar avatar-sm" style="background: <?= getAvatarColor($contact['first_name']) ?>">
                                            <?= getInitials($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        </div>
                                        <span class="table-cell-primary">
                                            <?= e($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($contact['position'] ?: '—') ?></td>
                                    <td><?= e($contact['email'] ?: '—') ?></td>
                                    <td><?= e($contact['phone'] ?: '—') ?></td>
                                    <td class="table-actions">
                                        <a href="contact_view.php?id=<?= $contact['id'] ?>" class="btn btn-sm btn-outline">Открыть</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>Нет контактов</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Сделки -->
<div class="tab-content" id="tab-deals">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Сделки</h3>
            <a href="deals.php?company_id=<?= $id ?>" class="btn btn-sm btn-primary">+ Создать сделку</a>
        </div>
        <div class="card-body p-0">
            <?php if ($deals): ?>
                <div class="card-list">
                    <?php foreach ($deals as $deal): ?>
                        <div class="list-card" style="margin: 0; box-shadow: none; border-bottom: 1px solid var(--border); border-radius: 0;">
                            <div class="list-card-content">
                                <div class="list-card-title"><?= e($deal['title']) ?></div>
                                <div class="list-card-meta">
                                    <span class="badge badge-<?= getBadgeClass('stage', $deal['stage']) ?>">
                                        <?= translateStatus($deal['stage']) ?>
                                    </span>
                                    <span style="font-weight: 600; color: var(--success);"><?= formatMoney($deal['amount']) ?></span>
                                    <?php if ($deal['expected_close_date']): ?>
                                        <span class="text-muted">📅 <?= formatDate($deal['expected_close_date']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="list-card-actions">
                                <a href="deal_view.php?id=<?= $deal['id'] ?>" class="btn btn-sm btn-outline">Открыть</a>
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
</div>

<!-- Задачи -->
<div class="tab-content" id="tab-tasks">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Задачи</h3>
            <a href="tasks.php?company_id=<?= $id ?>" class="btn btn-sm btn-primary">+ Создать задачу</a>
        </div>
        <div class="card-body p-0">
            <?php if ($tasks): ?>
                <div class="card-list">
                    <?php foreach ($tasks as $task): ?>
                        <div class="list-card" style="margin: 0; box-shadow: none; border-bottom: 1px solid var(--border); border-radius: 0;">
                            <div class="list-card-content">
                                <div class="list-card-title"><?= e($task['title']) ?></div>
                                <div class="list-card-meta">
                                    <span class="badge badge-<?= getBadgeClass('priority', $task['priority']) ?>">
                                        <?= translateStatus($task['priority']) ?>
                                    </span>
                                    <span class="badge badge-<?= getBadgeClass('status', $task['status']) ?>">
                                        <?= translateStatus($task['status']) ?>
                                    </span>
                                    <?php if ($task['due_date']): ?>
                                        <span>📅 <?= formatDate($task['due_date']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>Нет задач</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Активности -->
<div class="tab-content" id="tab-activities">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Активности</h3>
        </div>
        <div class="card-body">
            <?php if ($activities): ?>
                <div class="timeline">
                    <?php foreach ($activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker primary"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <div class="timeline-title"><?= e($activity['subject'] ?? $activity['type']) ?></div>
                                    <div class="timeline-time"><?= timeAgo($activity['created_at']) ?></div>
                                </div>
                                <?php if ($activity['description']): ?>
                                    <p class="text-muted" style="font-size: 13px; margin-top: 4px;">
                                        <?= e($activity['description']) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($activity['user_name']): ?>
                                    <p style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                                        👤 <?= e($activity['user_name']) ?>
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

<script>
function switchTab(tabName) {
    // Деактивировать все вкладки
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Активировать выбранную
    event.target.classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

function editCompany() {
    window.location.href = 'companies.php?edit=<?= $id ?>';
}

function deleteCompany() {
    if (!confirm('Удалить компанию "<?= e($company['name']) ?>"?\n\nБудут также удалены все связанные данные.')) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'companies.php';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $id ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>