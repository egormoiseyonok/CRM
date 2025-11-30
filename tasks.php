<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$pageTitle = 'Задачи';
$user = getCurrentUser();
$userFilter = canViewAll() ? '' : " AND t.user_id = {$user['id']}";

// CRUD операции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        $priority = $_POST['priority'] ?? 'medium';
        $due_date = $_POST['due_date'] ?: null;
        $company_id = $_POST['company_id'] ?: null;
        $contact_id = $_POST['contact_id'] ?: null;
        $deal_id = $_POST['deal_id'] ?: null;
        
        if ($title) {
            if ($id) {
                // Проверка прав на редактирование
                $stmt = $db->prepare("SELECT user_id FROM tasks WHERE id = ?");
                $stmt->execute([$id]);
                $task = $stmt->fetch();
                
                if (!$task) {
                    setFlash('Задача не найдена', 'danger');
                } elseif (!canEdit($task['user_id'])) {
                    setFlash('У вас нет прав на редактирование этой задачи', 'danger');
                } else {
                    // Для обычных пользователей нельзя менять user_id (назначать другим)
                    $updateSql = "
                        UPDATE tasks 
                        SET title=?, description=?, status=?, priority=?, due_date=?, company_id=?, contact_id=?, deal_id=?, updated_at=CURRENT_TIMESTAMP 
                        WHERE id=?
                    ";
                    
                    $stmt = $db->prepare($updateSql);
                    $stmt->execute([$title, $description, $status, $priority, $due_date, $company_id, $contact_id, $deal_id, $id]);
                    
                    // Если задача завершена, установить completed_at
                    if ($status === 'completed') {
                        $db->prepare("UPDATE tasks SET completed_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
                    }
                    
                    setFlash('Задача обновлена', 'success');
                }
            } else {
                // Создание - автоматически присваивается текущему пользователю
                $stmt = $db->prepare("
                    INSERT INTO tasks (title, description, status, priority, due_date, company_id, contact_id, deal_id, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $status, $priority, $due_date, $company_id, $contact_id, $deal_id, $_SESSION['user_id']]);
                setFlash('Задача создана', 'success');
            }
        }
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        // Проверка прав на удаление
        if (!canDelete()) {
            setFlash('У вас нет прав на удаление задач', 'danger');
            header('Location: tasks.php');
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        setFlash('Задача удалена', 'success');
    }
    
    if ($action === 'toggle' && isset($_POST['id'])) {
        $stmt = $db->prepare("SELECT status FROM tasks WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $currentStatus = $stmt->fetchColumn();
        
        $newStatus = $currentStatus === 'completed' ? 'pending' : 'completed';
        $completed = $newStatus === 'completed' ? 'CURRENT_TIMESTAMP' : 'NULL';
        
        $db->prepare("UPDATE tasks SET status = ?, completed_at = $completed WHERE id = ?")->execute([$newStatus, $_POST['id']]);
        setFlash('Статус задачи изменен', 'success');
    }
    
    header('Location: tasks.php');
    exit;
}

// Получить списки для выбора
$companies = $db->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$contacts = $db->query("SELECT id, first_name, last_name FROM contacts ORDER BY first_name")->fetchAll();
$deals = $db->query("SELECT id, title FROM deals WHERE stage NOT IN ('won', 'lost') ORDER BY title")->fetchAll();

// Фильтры
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Подсчёт
$countSql = "SELECT COUNT(*) FROM tasks t WHERE 1=1" . $userFilter;
$params = [];

if ($status) {
    $countSql .= " AND status = ?";
    $params[] = $status;
}

if ($priority) {
    $countSql .= " AND priority = ?";
    $params[] = $priority;
}

$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Получить задачи
$sql = "SELECT t.*, 
        c.name as company_name,
        co.first_name || ' ' || co.last_name as contact_name,
        d.title as deal_title
        FROM tasks t
        LEFT JOIN companies c ON t.company_id = c.id
        LEFT JOIN contacts co ON t.contact_id = co.id
        LEFT JOIN deals d ON t.deal_id = d.id
        WHERE 1=1" . $userFilter;

if ($status) {
    $sql .= " AND t.status = ?";
}

if ($priority) {
    $sql .= " AND t.priority = ?";
}

$sql .= " ORDER BY 
    CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END,
    t.due_date ASC NULLS LAST,
    CASE t.priority 
        WHEN 'high' THEN 1
        WHEN 'medium' THEN 2
        WHEN 'low' THEN 3
    END
    LIMIT ? OFFSET ?";
    
$params[] = $perPage;
$params[] = $pagination['offset'];

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

ob_start();
?>

<div class="page-header">
    <div class="page-title">
        <h1>
            <span class="page-title-icon">✓</span>
            Задачи
        </h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="openModal('taskModal')">
                + Добавить задачу
            </button>
        </div>
    </div>
    <p class="page-description">Управление задачами и напоминаниями</p>
</div>

<!-- Фильтры -->
<div class="filter-bar">
    <form method="GET" class="d-flex gap-10 w-100" style="flex-wrap: wrap;">
        <div class="filter-group">
            <select name="status" class="filter-select">
                <option value="">Все статусы</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>В ожидании</option>
                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>В работе</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Завершено</option>
            </select>
        </div>
        
        <div class="filter-group">
            <select name="priority" class="filter-select">
                <option value="">Все приоритеты</option>
                <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>Высокий</option>
                <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Средний</option>
                <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Низкий</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-secondary">Применить</button>
        <?php if ($status || $priority): ?>
            <a href="tasks.php" class="btn btn-outline">Сбросить</a>
        <?php endif; ?>
    </form>
</div>

<!-- Список задач -->
<?php if ($tasks): ?>
    <div class="card-list">
        <?php foreach ($tasks as $task): ?>
            <?php
            $isOverdue = $task['due_date'] && strtotime($task['due_date']) < time() && $task['status'] !== 'completed';
            $daysLeft = $task['due_date'] ? floor((strtotime($task['due_date']) - time()) / 86400) : null;
            ?>
            <div class="list-card" style="<?= $task['status'] === 'completed' ? 'opacity: 0.6;' : '' ?>">
                <div class="list-card-avatar">
                    <input type="checkbox" 
                           <?= $task['status'] === 'completed' ? 'checked' : '' ?>
                           onchange="toggleTask(<?= $task['id'] ?>)"
                           style="width: 24px; height: 24px; cursor: pointer;">
                </div>
                <div class="list-card-content">
                    <div class="list-card-title" style="<?= $task['status'] === 'completed' ? 'text-decoration: line-through;' : '' ?>">
                        <?= e($task['title']) ?>
                    </div>
                    <div class="list-card-subtitle">
                        <?php
                        $related = array_filter([
                            $task['company_name'],
                            $task['contact_name'],
                            $task['deal_title']
                        ]);
                        echo e(implode(' • ', $related) ?: 'Без привязки');
                        ?>
                    </div>
                    <div class="list-card-meta">
                        <span class="badge badge-<?= getBadgeClass('status', $task['status']) ?>">
                            <?= translateStatus($task['status']) ?>
                        </span>
                        <span class="badge badge-<?= getBadgeClass('priority', $task['priority']) ?>">
                            <?= translateStatus($task['priority']) ?>
                        </span>
                        <?php if ($task['due_date']): ?>
                            <span style="color: <?= $isOverdue ? 'var(--danger)' : '#6b7280' ?>;">
                                <?php if ($isOverdue): ?>
                                    ⚠️ Просрочено
                                <?php elseif ($daysLeft === 0): ?>
                                    📅 Сегодня
                                <?php elseif ($daysLeft === 1): ?>
                                    📅 Завтра
                                <?php else: ?>
                                    📅 <?= formatDate($task['due_date']) ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="list-card-actions">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline" onclick="toggleDropdown(this)">⋮</button>
                        <div class="dropdown-menu">
                            <?php if (canEdit($task['user_id'])): ?>
                                <button class="dropdown-item" onclick="editTask(<?= $task['id'] ?>)">
                                    Редактировать
                                </button>
                            <?php endif; ?>
                            <?php if (canDelete()): ?>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item danger" onclick="deleteTask(<?= $task['id'] ?>, '<?= e($task['title']) ?>')">
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
                <a href="?page=<?= $pagination['current_page'] - 1 ?><?= $status ? '&status='.$status : '' ?><?= $priority ? '&priority='.$priority : '' ?>" class="pagination-btn">← Назад</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>← Назад</button>
            <?php endif; ?>
            
            <span class="pagination-btn active"><?= $pagination['current_page'] ?> из <?= $pagination['total_pages'] ?></span>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?><?= $status ? '&status='.$status : '' ?><?= $priority ? '&priority='.$priority : '' ?>" class="pagination-btn">Вперёд →</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>Вперёд →</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">✓</div>
                <h3>Задачи не найдены</h3>
                <p>Создайте первую задачу для начала работы</p>
                <button class="btn btn-primary mt-20" onclick="openModal('taskModal')">
                    + Создать задачу
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Модальное окно -->
<div class="modal-overlay" id="taskModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Новая задача</h3>
            <button class="modal-close" onclick="closeModal('taskModal')">&times;</button>
        </div>
        <form method="POST" id="taskForm">
            <input type="hidden" name="action" value="create" id="formAction">
            <input type="hidden" name="id" id="taskId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Название задачи *</label>
                    <input type="text" name="title" class="form-control" required id="taskTitle">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="3" id="taskDescription"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Статус</label>
                        <select name="status" class="form-control" id="taskStatus">
                            <option value="pending">В ожидании</option>
                            <option value="in_progress">В работе</option>
                            <option value="completed">Завершено</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Приоритет</label>
                        <select name="priority" class="form-control" id="taskPriority">
                            <option value="low">Низкий</option>
                            <option value="medium" selected>Средний</option>
                            <option value="high">Высокий</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Срок выполнения</label>
                    <input type="date" name="due_date" class="form-control" id="taskDueDate">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Компания</label>
                    <select name="company_id" class="form-control" id="taskCompanyId">
                        <option value="">Без компании</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Контакт</label>
                        <select name="contact_id" class="form-control" id="taskContactId">
                            <option value="">Без контакта</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?= $contact['id'] ?>"><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Сделка</label>
                        <select name="deal_id" class="form-control" id="taskDealId">
                            <option value="">Без сделки</option>
                            <?php foreach ($deals as $deal): ?>
                                <option value="<?= $deal['id'] ?>"><?= e($deal['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('taskModal')">Отмена</button>
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
    document.getElementById('taskForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Новая задача';
}

function toggleTask(id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function editTask(id) {
    fetch(`api/task.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('formAction').value = 'update';
            document.getElementById('taskId').value = data.id;
            document.getElementById('taskTitle').value = data.title;
            document.getElementById('taskDescription').value = data.description || '';
            document.getElementById('taskStatus').value = data.status;
            document.getElementById('taskPriority').value = data.priority;
            document.getElementById('taskDueDate').value = data.due_date || '';
            document.getElementById('taskCompanyId').value = data.company_id || '';
            document.getElementById('taskContactId').value = data.contact_id || '';
            document.getElementById('taskDealId').value = data.deal_id || '';
            document.getElementById('modalTitle').textContent = 'Редактировать задачу';
            openModal('taskModal');
        });
}

function deleteTask(id, title) {
    if (!confirm(`Удалить задачу "${title}"?`)) return;
    
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