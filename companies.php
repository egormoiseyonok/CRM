<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$pageTitle = 'Компании';

// CRUD операции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if ($name) {
            if ($id) {
                // Проверка прав на редактирование
                $stmt = $db->prepare("SELECT user_id FROM companies WHERE id = ?");
                $stmt->execute([$id]);
                $company = $stmt->fetch();
                
                if (!$company) {
                    setFlash('Компания не найдена', 'danger');
                } elseif (!canEdit($company['user_id'])) {
                    setFlash('У вас нет прав на редактирование этой компании', 'danger');
                } else {
                    $stmt = $db->prepare("
                        UPDATE companies 
                        SET name=?, email=?, phone=?, website=?, address=?, industry=?, status=?, updated_at=CURRENT_TIMESTAMP 
                        WHERE id=?
                    ");
                    $stmt->execute([$name, $email, $phone, $website, $address, $industry, $status, $id]);
                    setFlash('Компания обновлена', 'success');
                }
            } else {
                // Создание - автоматически присваивается текущему пользователю
                $stmt = $db->prepare("
                    INSERT INTO companies (name, email, phone, website, address, industry, status, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $email, $phone, $website, $address, $industry, $status, $_SESSION['user_id']]);
                setFlash('Компания создана', 'success');
            }
        }
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        // Проверка прав на удаление
        if (!canDelete()) {
            setFlash('У вас нет прав на удаление компаний', 'danger');
            header('Location: companies.php');
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        setFlash('Компания удалена', 'success');
    }
    
    header('Location: companies.php');
    exit;
}

// Фильтры и поиск
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$industry = $_GET['industry'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

// Получить список индустрий
$industries = $db->query("SELECT DISTINCT industry FROM companies WHERE industry IS NOT NULL AND industry != '' ORDER BY industry")->fetchAll(PDO::FETCH_COLUMN);

// Подсчёт
$user = getCurrentUser();
$userFilter = canViewAll() ? '' : " AND user_id = {$user['id']}";
$countSql = "SELECT COUNT(*) FROM companies WHERE 1=1" . $userFilter;
$params = [];

if ($search) {
    $countSql .= " AND (name ILIKE ? OR email ILIKE ? OR phone ILIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status) {
    $countSql .= " AND status = ?";
    $params[] = $status;
}

if ($industry) {
    $countSql .= " AND industry = ?";
    $params[] = $industry;
}

$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Получить компании
$sql = "SELECT c.*, u.name as user_name,
        (SELECT COUNT(*) FROM contacts WHERE company_id = c.id) as contacts_count,
        (SELECT COUNT(*) FROM deals WHERE company_id = c.id AND stage NOT IN ('won', 'lost')) as deals_count
        FROM companies c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE 1=1" . $userFilter;

if ($search) {
    $sql .= " AND (c.name ILIKE ? OR c.email ILIKE ? OR c.phone ILIKE ?)";
}

if ($status) {
    $sql .= " AND c.status = ?";
}

if ($industry) {
    $sql .= " AND c.industry = ?";
}

$sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $pagination['offset'];

$stmt = $db->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll();

ob_start();
?>

<div class="page-header">
    <div class="page-title">
        <h1>
            <span class="page-title-icon">◉</span>
            Компании
        </h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="openModal('companyModal')">
                + Добавить компанию
            </button>
        </div>
    </div>
    <p class="page-description">Управление клиентскими компаниями и организациями</p>
</div>

<!-- Фильтры -->
<div class="filter-bar">
    <form method="GET" class="d-flex gap-10 w-100" style="flex-wrap: wrap;">
        <div class="filter-group" style="flex: 1; min-width: 300px;">
            <input type="text" name="search" class="form-control" placeholder="🔍 Поиск по названию, email, телефону..." value="<?= e($search) ?>" style="margin: 0;">
        </div>
        
        <div class="filter-group">
            <select name="status" class="filter-select">
                <option value="">Все статусы</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Активные</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Неактивные</option>
            </select>
        </div>
        
        <div class="filter-group">
            <select name="industry" class="filter-select">
                <option value="">Все индустрии</option>
                <?php foreach ($industries as $ind): ?>
                    <option value="<?= e($ind) ?>" <?= $industry === $ind ? 'selected' : '' ?>><?= e($ind) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-secondary">Применить</button>
        <?php if ($search || $status || $industry): ?>
            <a href="companies.php" class="btn btn-outline">Сбросить</a>
        <?php endif; ?>
    </form>
</div>

<!-- Список компаний -->
<?php if ($companies): ?>
    <div class="card-list">
        <?php foreach ($companies as $company): ?>
            <div class="list-card">
                <div class="list-card-avatar">
                    <div class="avatar avatar-lg" style="background: <?= getAvatarColor($company['name']) ?>">
                        <?= getInitials($company['name']) ?>
                    </div>
                </div>
                <div class="list-card-content">
                    <a href="company_view.php?id=<?= $company['id'] ?>" class="list-card-title">
                        <?= e($company['name']) ?>
                    </a>
                    <div class="list-card-subtitle">
                        <?= e($company['industry'] ?? 'Не указана индустрия') ?>
                    </div>
                    <div class="list-card-meta">
                        <?php if ($company['email']): ?>
                            <span>📧 <?= e($company['email']) ?></span>
                        <?php endif; ?>
                        <?php if ($company['phone']): ?>
                            <span>📞 <?= e($company['phone']) ?></span>
                        <?php endif; ?>
                        <span><?= $company['contacts_count'] ?> контактов</span>
                        <span><?= $company['deals_count'] ?> сделок</span>
                    </div>
                </div>
                <div class="list-card-actions">
                    <span class="badge badge-<?= getBadgeClass('status', $company['status']) ?>">
                        <?= translateStatus($company['status']) ?>
                    </span>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline" onclick="toggleDropdown(this)">⋮</button>
                        <div class="dropdown-menu">
                            <a href="company_view.php?id=<?= $company['id'] ?>" class="dropdown-item">
                                Просмотр
                            </a>
                            <?php if (canEdit($company['user_id'])): ?>
                                <button class="dropdown-item" onclick="editCompany(<?= $company['id'] ?>)">
                                    Редактировать
                                </button>
                            <?php endif; ?>
                            <?php if (canDelete()): ?>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item danger" onclick="deleteCompany(<?= $company['id'] ?>, '<?= e($company['name']) ?>')">
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
                <a href="?page=<?= $pagination['current_page'] - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status ? '&status='.$status : '' ?><?= $industry ? '&industry='.urlencode($industry) : '' ?>" class="pagination-btn">← Назад</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>← Назад</button>
            <?php endif; ?>
            
            <span class="pagination-btn active"><?= $pagination['current_page'] ?> из <?= $pagination['total_pages'] ?></span>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status ? '&status='.$status : '' ?><?= $industry ? '&industry='.urlencode($industry) : '' ?>" class="pagination-btn">Вперёд →</a>
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
                <h3>Компании не найдены</h3>
                <p>Попробуйте изменить параметры поиска или создайте новую компанию</p>
                <button class="btn btn-primary mt-20" onclick="openModal('companyModal')">
                    + Создать компанию
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Модальное окно -->
<div class="modal-overlay" id="companyModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Новая компания</h3>
            <button class="modal-close" onclick="closeModal('companyModal')">&times;</button>
        </div>
        <form method="POST" id="companyForm">
            <input type="hidden" name="action" value="create" id="formAction">
            <input type="hidden" name="id" id="companyId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Название компании *</label>
                    <input type="text" name="name" class="form-control" required id="companyName">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" id="companyEmail">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-control" id="companyPhone">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Веб-сайт</label>
                        <input type="url" name="website" class="form-control" placeholder="https://" id="companyWebsite">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Индустрия</label>
                        <input type="text" name="industry" class="form-control" list="industries" id="companyIndustry">
                        <datalist id="industries">
                            <?php foreach ($industries as $ind): ?>
                                <option value="<?= e($ind) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Адрес</label>
                    <textarea name="address" class="form-control" rows="2" id="companyAddress"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Статус</label>
                    <select name="status" class="form-control" id="companyStatus">
                        <option value="active">Активна</option>
                        <option value="inactive">Неактивна</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('companyModal')">Отмена</button>
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
    document.getElementById('companyForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Новая компания';
}

function editCompany(id) {
    fetch(`api/company.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('formAction').value = 'update';
            document.getElementById('companyId').value = data.id;
            document.getElementById('companyName').value = data.name;
            document.getElementById('companyEmail').value = data.email || '';
            document.getElementById('companyPhone').value = data.phone || '';
            document.getElementById('companyWebsite').value = data.website || '';
            document.getElementById('companyIndustry').value = data.industry || '';
            document.getElementById('companyAddress').value = data.address || '';
            document.getElementById('companyStatus').value = data.status;
            document.getElementById('modalTitle').textContent = 'Редактировать компанию';
            openModal('companyModal');
        });
}

function deleteCompany(id, name) {
    if (!confirm(`Удалить компанию "${name}"?\n\nБудут также удалены все связанные контакты, сделки и задачи.`)) return;
    
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
    
    if (!isOpen) {
        dropdown.classList.add('show');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            if (m.classList.contains('show')) {
                closeModal(m.id);
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>