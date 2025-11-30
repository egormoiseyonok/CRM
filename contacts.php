<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$pageTitle = 'Контакты';
$user = getCurrentUser();
$userFilter = canViewAll() ? '' : " AND c.user_id = {$user['id']}";

// CRUD операции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $company_id = $_POST['company_id'] ?: null;
        
        if ($first_name && $last_name) {
            if ($id) {
                // Проверка прав на редактирование
                $stmt = $db->prepare("SELECT user_id FROM contacts WHERE id = ?");
                $stmt->execute([$id]);
                $contact = $stmt->fetch();
                
                if (!$contact) {
                    setFlash('Контакт не найден', 'danger');
                } elseif (!canEdit($contact['user_id'])) {
                    setFlash('У вас нет прав на редактирование этого контакта', 'danger');
                } else {
                    $stmt = $db->prepare("
                        UPDATE contacts 
                        SET first_name=?, last_name=?, email=?, phone=?, position=?, company_id=?, updated_at=CURRENT_TIMESTAMP 
                        WHERE id=?
                    ");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $position, $company_id, $id]);
                    setFlash('Контакт обновлен', 'success');
                }
            } else {
                // Создание - автоматически присваивается текущему пользователю
                $stmt = $db->prepare("
                    INSERT INTO contacts (first_name, last_name, email, phone, position, company_id, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $position, $company_id, $_SESSION['user_id']]);
                setFlash('Контакт создан', 'success');
            }
        }
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        // Проверка прав на удаление
        if (!canDelete()) {
            setFlash('У вас нет прав на удаление контактов', 'danger');
            header('Location: contacts.php');
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        setFlash('Контакт удален', 'success');
    }
    
    header('Location: contacts.php');
    exit;
}

// Получить список компаний для выбора
$companiesFilter = canViewAll() ? '' : " WHERE user_id = {$user['id']}";
$companies = $db->query("SELECT id, name FROM companies" . $companiesFilter . " ORDER BY name")->fetchAll();

// Фильтры и поиск
$search = $_GET['search'] ?? '';
$company_id = $_GET['company_id'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Подсчёт
$countSql = "SELECT COUNT(*) FROM contacts c WHERE 1=1" . $userFilter;
$params = [];

if ($search) {
    $countSql .= " AND (first_name ILIKE ? OR last_name ILIKE ? OR email ILIKE ? OR phone ILIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

if ($company_id) {
    $countSql .= " AND company_id = ?";
    $params[] = $company_id;
}

$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Получить контакты
$sql = "SELECT c.*, co.name as company_name
        FROM contacts c
        LEFT JOIN companies co ON c.company_id = co.id
        WHERE 1=1" . $userFilter;

if ($search) {
    $sql .= " AND (c.first_name ILIKE ? OR c.last_name ILIKE ? OR c.email ILIKE ? OR c.phone ILIKE ?)";
}

if ($company_id) {
    $sql .= " AND c.company_id = ?";
}

$sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $pagination['offset'];

$stmt = $db->prepare($sql);
$stmt->execute($params);
$contacts = $stmt->fetchAll();

ob_start();
?>

<div class="page-header">
    <div class="page-title">
        <h1>
            <span class="page-title-icon">👥</span>
            Контакты
        </h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="openModal('contactModal')">
                + Добавить контакт
            </button>
        </div>
    </div>
    <p class="page-description">Управление контактными лицами</p>
</div>

<!-- Фильтры -->
<div class="filter-bar">
    <form method="GET" class="d-flex gap-10 w-100" style="flex-wrap: wrap;">
        <div class="filter-group" style="flex: 1; min-width: 300px;">
            <input type="text" name="search" class="form-control" placeholder="🔍 Поиск по имени, email, телефону..." value="<?= e($search) ?>" style="margin: 0;">
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
        <?php if ($search || $company_id): ?>
            <a href="contacts.php" class="btn btn-outline">Сбросить</a>
        <?php endif; ?>
    </form>
</div>

<!-- Список контактов -->
<?php if ($contacts): ?>
    <div class="card-list">
        <?php foreach ($contacts as $contact): ?>
            <div class="list-card">
                <div class="list-card-avatar">
                    <div class="avatar avatar-lg" style="background: <?= getAvatarColor($contact['first_name']) ?>">
                        <?= getInitials($contact['first_name'] . ' ' . $contact['last_name']) ?>
                    </div>
                </div>
                <div class="list-card-content">
                    <div class="list-card-title">
                        <?= e($contact['first_name'] . ' ' . $contact['last_name']) ?>
                    </div>
                    <div class="list-card-subtitle">
                        <?php if ($contact['position']): ?>
                            <?= e($contact['position']) ?>
                            <?php if ($contact['company_name']): ?>
                                в <?= e($contact['company_name']) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= e($contact['company_name'] ?? 'Без компании') ?>
                        <?php endif; ?>
                    </div>
                    <div class="list-card-meta">
                        <?php if ($contact['email']): ?>
                            <span>📧 <?= e($contact['email']) ?></span>
                        <?php endif; ?>
                        <?php if ($contact['phone']): ?>
                            <span>📞 <?= e($contact['phone']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="list-card-actions">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline" onclick="toggleDropdown(this)">⋮</button>
                        <div class="dropdown-menu">
                            <?php if (canEdit($contact['user_id'])): ?>
                                <button class="dropdown-item" onclick="editContact(<?= $contact['id'] ?>)">
                                    Редактировать
                                </button>
                            <?php endif; ?>
                            <?php if (canDelete()): ?>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item danger" onclick="deleteContact(<?= $contact['id'] ?>, '<?= e($contact['first_name'] . ' ' . $contact['last_name']) ?>')">
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
                <a href="?page=<?= $pagination['current_page'] - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $company_id ? '&company_id='.$company_id : '' ?>" class="pagination-btn">← Назад</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>← Назад</button>
            <?php endif; ?>
            
            <span class="pagination-btn active"><?= $pagination['current_page'] ?> из <?= $pagination['total_pages'] ?></span>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $company_id ? '&company_id='.$company_id : '' ?>" class="pagination-btn">Вперёд →</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>Вперёд →</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <h3>Контакты не найдены</h3>
                <p>Попробуйте изменить параметры поиска или создайте новый контакт</p>
                <button class="btn btn-primary mt-20" onclick="openModal('contactModal')">
                    + Создать контакт
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Модальное окно -->
<div class="modal-overlay" id="contactModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Новый контакт</h3>
            <button class="modal-close" onclick="closeModal('contactModal')">&times;</button>
        </div>
        <form method="POST" id="contactForm">
            <input type="hidden" name="action" value="create" id="formAction">
            <input type="hidden" name="id" id="contactId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Имя *</label>
                        <input type="text" name="first_name" class="form-control" required id="contactFirstName">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Фамилия *</label>
                        <input type="text" name="last_name" class="form-control" required id="contactLastName">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Компания</label>
                    <select name="company_id" class="form-control" id="contactCompanyId">
                        <option value="">Без компании</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Должность</label>
                    <input type="text" name="position" class="form-control" id="contactPosition">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" id="contactEmail">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-control" id="contactPhone">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('contactModal')">Отмена</button>
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
    document.getElementById('contactForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Новый контакт';
}

function editContact(id) {
    fetch(`api/contact.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('formAction').value = 'update';
            document.getElementById('contactId').value = data.id;
            document.getElementById('contactFirstName').value = data.first_name;
            document.getElementById('contactLastName').value = data.last_name;
            document.getElementById('contactCompanyId').value = data.company_id || '';
            document.getElementById('contactPosition').value = data.position || '';
            document.getElementById('contactEmail').value = data.email || '';
            document.getElementById('contactPhone').value = data.phone || '';
            document.getElementById('modalTitle').textContent = 'Редактировать контакт';
            openModal('contactModal');
        });
}

function deleteContact(id, name) {
    if (!confirm(`Удалить контакт "${name}"?`)) return;
    
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