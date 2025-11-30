<?php
require_once 'config.php';
checkAuth();
checkAdmin(); // Только администраторы могут управлять пользователями

$db = getDB();
$pageTitle = 'Управление пользователями';

// CRUD операции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        // Валидация
        if (empty($name)) {
            setFlash('Введите имя пользователя', 'danger');
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('Введите корректный email', 'danger');
        } elseif (!in_array($role, ['admin', 'manager', 'user'])) {
            setFlash('Некорректная роль', 'danger');
        } else {
            if ($id) {
                // Обновление
                $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $existingUser = $stmt->fetch();
                
                if (!$existingUser) {
                    setFlash('Пользователь не найден', 'danger');
                } else {
                    // Проверка email на дубликат (кроме текущего пользователя)
                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        setFlash('Email уже используется', 'danger');
                    } else {
                        if ($password) {
                            // Обновление с паролем
                            $stmt = $db->prepare("
                                UPDATE users 
                                SET name=?, email=?, password=crypt(?, gen_salt('bf')), role=?, updated_at=CURRENT_TIMESTAMP 
                                WHERE id=?
                            ");
                            $stmt->execute([$name, $email, $password, $role, $id]);
                        } else {
                            // Обновление без пароля
                            $stmt = $db->prepare("
                                UPDATE users 
                                SET name=?, email=?, role=?, updated_at=CURRENT_TIMESTAMP 
                                WHERE id=?
                            ");
                            $stmt->execute([$name, $email, $role, $id]);
                        }
                        setFlash('Пользователь обновлен', 'success');
                    }
                }
            } else {
                // Создание
                if (empty($password)) {
                    setFlash('Введите пароль для нового пользователя', 'danger');
                } else {
                    // Проверка email на дубликат
                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetchColumn() > 0) {
                        setFlash('Email уже используется', 'danger');
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO users (name, email, password, role) 
                            VALUES (?, ?, crypt(?, gen_salt('bf')), ?)
                        ");
                        $stmt->execute([$name, $email, $password, $role]);
                        setFlash('Пользователь создан', 'success');
                    }
                }
            }
        }
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        
        // Нельзя удалить самого себя
        if ($id == $_SESSION['user_id']) {
            setFlash('Нельзя удалить самого себя', 'danger');
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('Пользователь удален', 'success');
        }
    }
    
    header('Location: users.php');
    exit;
}

// Получить всех пользователей
$users = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM companies WHERE user_id = u.id) as companies_count,
           (SELECT COUNT(*) FROM deals WHERE user_id = u.id) as deals_count,
           (SELECT COUNT(*) FROM tasks WHERE user_id = u.id) as tasks_count
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll();

ob_start();
?>

<div class="page-header">
    <div>
        <h1>Управление пользователями</h1>
        <p class="page-description">Создание и управление пользователями системы</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openUserModal()">Создать пользователя</button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Имя</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Компании</th>
                        <th>Сделки</th>
                        <th>Задачи</th>
                        <th>Дата регистрации</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $userRow): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div class="avatar" style="background: <?= getAvatarColor($userRow['name']) ?>">
                                        <?= getInitials($userRow['name']) ?>
                                    </div>
                                    <?= e($userRow['name']) ?>
                                </div>
                            </td>
                            <td><?= e($userRow['email']) ?></td>
                            <td>
                                <span class="badge badge-<?= $userRow['role'] === 'admin' ? 'danger' : ($userRow['role'] === 'manager' ? 'warning' : 'secondary') ?>">
                                    <?= $userRow['role'] === 'admin' ? 'Администратор' : ($userRow['role'] === 'manager' ? 'Менеджер' : 'Пользователь') ?>
                                </span>
                            </td>
                            <td><?= $userRow['companies_count'] ?></td>
                            <td><?= $userRow['deals_count'] ?></td>
                            <td><?= $userRow['tasks_count'] ?></td>
                            <td><?= formatDate($userRow['created_at']) ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline" onclick="toggleDropdown(this)">⋮</button>
                                    <div class="dropdown-menu">
                                        <button class="dropdown-item" onclick="editUser(<?= $userRow['id'] ?>)">
                                            Редактировать
                                        </button>
                                        <?php if ($userRow['id'] != $_SESSION['user_id']): ?>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item danger" onclick="deleteUser(<?= $userRow['id'] ?>, '<?= e($userRow['name']) ?>')">
                                                Удалить
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Модальное окно для создания/редактирования пользователя -->
<div class="modal-overlay" id="userModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle">Создать пользователя</h3>
            <button class="modal-close" onclick="closeUserModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="userForm">
                <input type="hidden" name="action" id="userAction" value="create">
                <input type="hidden" name="id" id="userId">
                
                <div class="form-group">
                    <label class="form-label">Имя *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" id="passwordLabel">Пароль *</label>
                    <input type="password" name="password" class="form-control" id="passwordInput">
                    <div class="password-hint" id="passwordHint">Минимум 6 символов</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Роль *</label>
                    <select name="role" class="form-control" required>
                        <option value="user">Пользователь</option>
                        <option value="manager">Менеджер</option>
                        <option value="admin">Администратор</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Отмена</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUserModal(id = null) {
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const passwordInput = document.getElementById('passwordInput');
    const passwordLabel = document.getElementById('passwordLabel');
    const passwordHint = document.getElementById('passwordHint');
    
    if (id) {
        fetch(`api/user.php?id=${id}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('userModalTitle').textContent = 'Редактировать пользователя';
                document.getElementById('userAction').value = 'update';
                document.getElementById('userId').value = data.id;
                form.name.value = data.name;
                form.email.value = data.email;
                form.role.value = data.role;
                passwordInput.required = false;
                passwordLabel.textContent = 'Пароль (оставьте пустым, чтобы не менять)';
                passwordHint.textContent = 'Оставьте пустым, чтобы не изменять пароль';
                modal.classList.add('show');
            })
            .catch(() => {
                alert('Ошибка загрузки данных');
            });
    } else {
        document.getElementById('userModalTitle').textContent = 'Создать пользователя';
        document.getElementById('userAction').value = 'create';
        document.getElementById('userId').value = '';
        form.reset();
        passwordInput.required = true;
        passwordLabel.textContent = 'Пароль *';
        passwordHint.textContent = 'Минимум 6 символов';
        modal.classList.add('show');
    }
}

function editUser(id) {
    openUserModal(id);
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('show');
}

function deleteUser(id, name) {
    if (!confirm(`Удалить пользователя "${name}"?\n\nВсе связанные данные (компании, сделки, задачи) будут сохранены, но не будут привязаны к пользователю.`)) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>

