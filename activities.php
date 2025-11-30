<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$pageTitle = 'Активности';
$user = getCurrentUser();
$userFilter = canViewAll() ? '' : " AND a.user_id = {$user['id']}";

// Фильтры
$type = $_GET['type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;

// Подсчёт
$countSql = "SELECT COUNT(*) FROM activities a WHERE 1=1" . $userFilter;
$params = [];

if ($type) {
    $countSql .= " AND type = ?";
    $params[] = $type;
}

$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Получить активности
$sql = "SELECT a.*, 
        u.name as user_name,
        c.name as company_name,
        co.first_name || ' ' || co.last_name as contact_name,
        d.title as deal_title
        FROM activities a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN companies c ON a.company_id = c.id
        LEFT JOIN contacts co ON a.contact_id = co.id
        LEFT JOIN deals d ON a.deal_id = d.id
        WHERE 1=1" . $userFilter;

if ($type) {
    $sql .= " AND a.type = ?";
}

$sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $pagination['offset'];

$stmt = $db->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Типы активностей
$types = $db->query("SELECT DISTINCT type FROM activities ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);

ob_start();
?>

<div class="page-header">
    <div class="page-title">
        <h1>
            <span class="page-title-icon">◉</span>
            Активности
        </h1>
    </div>
    <p class="page-description">История всех действий в системе</p>
</div>

<!-- Фильтры -->
<div class="filter-bar">
    <form method="GET" class="d-flex gap-10 w-100" style="flex-wrap: wrap;">
        <div class="filter-group">
            <select name="type" class="filter-select">
                <option value="">Все типы</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-secondary">Применить</button>
        <?php if ($type): ?>
            <a href="activities.php" class="btn btn-outline">Сбросить</a>
        <?php endif; ?>
    </form>
</div>

<!-- Timeline активностей -->
<?php if ($activities): ?>
    <div class="card">
        <div class="card-body">
            <div class="timeline">
                <?php foreach ($activities as $activity): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker <?= $activity['type'] === 'error' ? 'danger' : ($activity['type'] === 'note' ? 'info' : 'primary') ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <div>
                                    <div class="timeline-title"><?= e($activity['subject'] ?? ucfirst($activity['type'])) ?></div>
                                    <?php if ($activity['user_name']): ?>
                                        <div style="font-size: 12px; color: #9ca3af; margin-top: 2px;">
                                            👤 <?= e($activity['user_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-time"><?= timeAgo($activity['created_at']) ?></div>
                            </div>
                            
                            <?php if ($activity['description']): ?>
                                <p class="text-muted" style="font-size: 13px; margin-top: 8px;">
                                    <?= e($activity['description']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                                <?php if ($activity['company_name']): ?>
                                    <a href="company_view.php?id=<?= $activity['company_id'] ?>" style="font-size: 12px; color: #667eea; text-decoration: none;">
                                        <?= e($activity['company_name']) ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($activity['contact_name']): ?>
                                    <span style="font-size: 12px; color: #9ca3af;">
                                        👤 <?= e($activity['contact_name']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($activity['deal_title']): ?>
                                    <span style="font-size: 12px; color: #9ca3af;">
                                        <?= e($activity['deal_title']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Пагинация -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pagination['has_prev']): ?>
                <a href="?page=<?= $pagination['current_page'] - 1 ?><?= $type ? '&type='.$type : '' ?>" class="pagination-btn">← Назад</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>← Назад</button>
            <?php endif; ?>
            
            <span class="pagination-btn active"><?= $pagination['current_page'] ?> из <?= $pagination['total_pages'] ?></span>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?><?= $type ? '&type='.$type : '' ?>" class="pagination-btn">Вперёд →</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>Вперёд →</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <h3>Активности не найдены</h3>
                <p>История действий появится здесь</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>