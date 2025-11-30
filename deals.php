<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$pageTitle = 'Сделки';

$user = getCurrentUser();
$userFilter = canViewAll() ? '' : " AND d.user_id = {$user['id']}";

// Получить списки для выбора
$companiesFilter = canViewAll() ? '' : " WHERE user_id = {$user['id']}";
$companies = $db->query("SELECT id, name FROM companies" . $companiesFilter . " ORDER BY name")->fetchAll();
$contactsFilter = canViewAll() ? '' : " WHERE user_id = {$user['id']}";
$contacts = $db->query("SELECT id, first_name, last_name FROM contacts" . $contactsFilter . " ORDER BY first_name")->fetchAll();

// CRUD операции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $title = trim($_POST['title']);
        $amount = floatval($_POST['amount'] ?? 0);
        $stage = $_POST['stage'] ?? 'lead';
        $probability = intval($_POST['probability'] ?? 50);
        $company_id = $_POST['company_id'] ?: null;
        $contact_id = $_POST['contact_id'] ?: null;
        $expected_close_date = $_POST['expected_close_date'] ?: null;
        $notes = trim($_POST['notes'] ?? '');
        
        if ($title) {
            if ($id) {
                // Проверка прав на редактирование
                $stmt = $db->prepare("SELECT user_id FROM deals WHERE id = ?");
                $stmt->execute([$id]);
                $deal = $stmt->fetch();
                
                if (!$deal) {
                    setFlash('Сделка не найдена', 'danger');
                } elseif (!canEdit($deal['user_id'])) {
                    setFlash('У вас нет прав на редактирование этой сделки', 'danger');
                } else {
                    $stmt = $db->prepare("
                        UPDATE deals 
                        SET title=?, amount=?, stage=?, probability=?, company_id=?, contact_id=?, expected_close_date=?, notes=?, updated_at=CURRENT_TIMESTAMP 
                        WHERE id=?
                    ");
                    $stmt->execute([$title, $amount, $stage, $probability, $company_id, $contact_id, $expected_close_date, $notes, $id]);
                    setFlash('Сделка обновлена', 'success');
                }
            } else {
                // Создание - автоматически присваивается текущему пользователю
                $stmt = $db->prepare("
                    INSERT INTO deals (title, amount, stage, probability, company_id, contact_id, user_id, expected_close_date, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $amount, $stage, $probability, $company_id, $contact_id, $_SESSION['user_id'], $expected_close_date, $notes]);
                setFlash('Сделка создана', 'success');
            }
        }
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        // Проверка прав на удаление
        if (!canDelete()) {
            setFlash('У вас нет прав на удаление сделок', 'danger');
            header('Location: deals.php');
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM deals WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        setFlash('Сделка удалена', 'success');
    }
    
    header('Location: deals.php');
    exit;
}

// Фильтры
$stage = $_GET['stage'] ?? '';
$company_id = $_GET['company_id'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Подсчёт
$countSql = "SELECT COUNT(*) FROM deals d WHERE 1=1" . $userFilter;
$params = [];

if ($stage) {
    $countSql .= " AND stage = ?";
    $params[] = $stage;
}

if ($company_id) {
    $countSql .= " AND company_id = ?";
    $params[] = $company_id;
}

$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Получить сделки
$sql = "SELECT d.*, d.user_id, c.name as company_name, co.first_name || ' ' || co.last_name as contact_name
        FROM deals d
        LEFT JOIN companies c ON d.company_id = c.id
        LEFT JOIN contacts co ON d.contact_id = co.id
        WHERE 1=1" . $userFilter;

if ($stage) {
    $sql .= " AND d.stage = ?";
}

if ($company_id) {
    $sql .= " AND d.company_id = ?";
}

$sql .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $pagination['offset'];

$stmt = $db->prepare($sql);
$stmt->execute($params);
$deals = $stmt->fetchAll();

// Статистика по этапам - ИСПРАВЛЕНО!
$stageStatsRaw = $db->query("
    SELECT stage, COUNT(*) as count
    FROM deals 
    WHERE stage NOT IN ('won', 'lost')
    GROUP BY stage
")->fetchAll();

// Преобразовать в удобный формат
$stageStats = [];
foreach ($stageStatsRaw as $row) {
    $stageStats[$row['stage']] = $row['count'];
}

ob_start();
?>

<div class="page-header">
    <div class="page-title">
        <h1>
            <span class="page-title-icon">💼</span>
            Сделки
        </h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="openModal('dealModal')">
                + Создать сделку
            </button>
        </div>
    </div>
    <p class="page-description">Управление сделками и воронкой продаж</p>
</div>

<!-- Статистика по этапам -->
<div class="grid grid-4 mb-20">
    <div class="stat-card">
        <div class="stat-icon secondary">🎯</div>
        <div class="stat-content">
            <div class="stat-label">Лиды</div>
            <div class="stat-value"><?= $stageStats['lead'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">✓</div>
        <div class="stat-content">
            <div class="stat-label">Квалификация</div>
            <div class="stat-value"><?= $stageStats['qualified'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary">📄</div>
        <div class="stat-content">
            <div class="stat-label">Предложения</div>
            <div class="stat-value"><?= $stageStats['proposal'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">🤝</div>
        <div class="stat-content">
            <div class="stat-label">Переговоры</div>
            <div class="stat-value"><?= $stageStats['negotiation'] ?? 0 ?></div>
        </div>
    </div>
</div>

<!-- Фильтры -->
<div class="filter-bar">
    <form method="GET" class="d-flex gap-10 w-100" style="flex-wrap: wrap;">
        <div class="filter-group">
            <select name="stage" class="filter-select">
                <option value="">Все этапы</option>
                <option value="lead" <?= $stage === 'lead' ? 'selected' : '' ?>>Лид</option>
                <option value="qualified" <?= $stage === 'qualified' ? 'selected' : '' ?>>Квалификация</option>
                <option value="proposal" <?= $stage === 'proposal' ? 'selected' : '' ?>>Предложение</option>
                <option value="negotiation" <?= $stage === 'negotiation' ? 'selected' : '' ?>>Переговоры</option>
                <option value="won" <?= $stage === 'won' ? 'selected' : '' ?>>Выиграна</option>
                <option value="lost" <?= $stage === 'lost' ? 'selected' : '' ?>>Проиграна</option>
            </select>
        </div>
        
        <div class="filter-group">
            <select name="company_id" class="filter-select">
                <option value="">Все компании</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= $company['id'] ?>" <?= $company_id == $company['id'] ? 'selected' : '' ?>>
                        <?= e($company['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-secondary">Применить</button>
        <?php if ($stage || $company_id): ?>
            <a href="deals.php" class="btn btn-outline">Сбросить</a>
        <?php endif; ?>
    </form>
</div>

<!-- Список сделок -->
<?php if ($deals): ?>
    <div class="card-list">
        <?php foreach ($deals as $deal): ?>
            <div class="list-card">
                <div class="list-card-content">
                    <div class="list-card-title"><?= e($deal['title']) ?></div>
                    <div class="list-card-subtitle">
                        <?= e($deal['company_name'] ?? 'Без компании') ?>
                        <?php if ($deal['contact_name']): ?>
                            • <?= e($deal['contact_name']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="list-card-meta">
                        <span class="badge badge-<?= getBadgeClass('stage', $deal['stage']) ?>">
                            <?= translateStatus($deal['stage']) ?>
                        </span>
                        <span style="font-weight: 600; color: var(--success);"><?= formatMoney($deal['amount']) ?></span>
                        <?php if ($deal['expected_close_date']): ?>
                            <span>📅 <?= formatDate($deal['expected_close_date']) ?></span>
                        <?php endif; ?>
                        <span style="color: #6b7280;"><?= $deal['probability'] ?>%</span>
                    </div>
                </div>
                <div class="list-card-actions">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline" onclick="toggleDropdown(this)">⋮</button>
                        <div class="dropdown-menu">
                            <?php if (canEdit($deal['user_id'])): ?>
                                <button class="dropdown-item" onclick="editDeal(<?= $deal['id'] ?>)">
                                    Редактировать
                                </button>
                            <?php endif; ?>
                            <?php if (canDelete()): ?>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item danger" onclick="deleteDeal(<?= $deal['id'] ?>, '<?= e($deal['title']) ?>')">
                                    Удалить
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Пагинация -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pagination['has_prev']): ?>
                <a href="?page=<?= $pagination['current_page'] - 1 ?><?= $stage ? '&stage='.$stage : '' ?><?= $company_id ? '&company_id='.$company_id : '' ?>" class="pagination-btn">← Назад</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>← Назад</button>
            <?php endif; ?>
            
            <span class="pagination-btn active"><?= $pagination['current_page'] ?> из <?= $pagination['total_pages'] ?></span>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?><?= $stage ? '&stage='.$stage : '' ?><?= $company_id ? '&company_id='.$company_id : '' ?>" class="pagination-btn">Вперёд →</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>Вперёд →</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">💼</div>
                <h3>Сделки не найдены</h3>
                <p>Создайте первую сделку для начала работы</p>
                <button class="btn btn-primary mt-20" onclick="openModal('dealModal')">
                    + Создать сделку
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Модальное окно -->
<div class="modal-overlay" id="dealModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Новая сделка</h3>
            <button class="modal-close" onclick="closeModal('dealModal')">&times;</button>
        </div>
        <form method="POST" id="dealForm">
            <input type="hidden" name="action" value="create" id="formAction">
            <input type="hidden" name="id" id="dealId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Название сделки *</label>
                    <input type="text" name="title" class="form-control" required id="dealTitle">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Сумма (₽)</label>
                        <input type="number" name="amount" class="form-control" step="0.01" value="0" id="dealAmount">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Вероятность (%)</label>
                        <input type="number" name="probability" class="form-control" min="0" max="100" value="50" id="dealProbability">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Компания</label>
                        <select name="company_id" class="form-control" id="dealCompanyId">
                            <option value="">Без компании</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>"><?= e($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Контакт</label>
                        <select name="contact_id" class="form-control" id="dealContactId">
                            <option value="">Без контакта</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?= $contact['id'] ?>"><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Этап</label>
                        <select name="stage" class="form-control" id="dealStage">
                            <option value="lead">Лид</option>
                            <option value="qualified">Квалификация</option>
                            <option value="proposal">Предложение</option>
                            <option value="negotiation">Переговоры</option>
                            <option value="won">Выиграна</option>
                            <option value="lost">Проиграна</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ожидаемое закрытие</label>
                        <input type="date" name="expected_close_date" class="form-control" id="dealExpectedCloseDate">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Заметки</label>
                    <textarea name="notes" class="form-control" rows="3" id="dealNotes"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('dealModal')">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('dealForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Новая сделка';
}

function editDeal(id) {
    fetch(`api/deal.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('formAction').value = 'update';
            document.getElementById('dealId').value = data.id;
            document.getElementById('dealTitle').value = data.title;
            document.getElementById('dealAmount').value = data.amount;
            document.getElementById('dealProbability').value = data.probability;
            document.getElementById('dealCompanyId').value = data.company_id || '';
            document.getElementById('dealContactId').value = data.contact_id || '';
            document.getElementById('dealStage').value = data.stage;
            document.getElementById('dealExpectedCloseDate').value = data.expected_close_date || '';
            document.getElementById('dealNotes').value = data.notes || '';
            document.getElementById('modalTitle').textContent = 'Редактировать сделку';
            openModal('dealModal');
        });
}

function deleteDeal(id, title) {
    if (!confirm(`Удалить сделку "${title}"?`)) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function toggleDropdown(btn) {
    const dropdown = btn.closest('.dropdown');
    const isOpen = dropdown.classList.contains('show');
    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
    if (!isOpen) dropdown.classList.add('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => closeModal(m.id));
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>