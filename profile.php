<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$pageTitle = 'Профиль';
$user = getCurrentUser();

$errors = [];
$success = '';

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Обновление основных данных
    if ($action === 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        
        if (empty($name)) {
            $errors[] = 'Имя обязательно для заполнения';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email';
        }
        
        // Проверка уникальности email
        if ($email !== $user['email']) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Email уже используется другим пользователем';
            }
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $user['id']]);
            
            // Добавить активность
            $db->prepare("INSERT INTO activities (type, subject, user_id) VALUES (?, ?, ?)")
               ->execute(['profile_update', 'Обновление профиля', $user['id']]);
            
            setFlash('Профиль успешно обновлен', 'success');
            header('Location: profile.php');
            exit;
        }
    }
    
    // Смена пароля
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Проверить текущий пароль через pgcrypto
        $stmt = $db->prepare("SELECT password = crypt(?, password) as valid FROM users WHERE id = ?");
        $stmt->execute([$current_password, $user['id']]);
        $isValid = $stmt->fetchColumn();
        
        if (!$isValid) {
            $errors[] = 'Текущий пароль неверен';
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = 'Новый пароль должен быть не менее 6 символов';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Пароли не совпадают';
        }
        
        if (empty($errors)) {
            // Обновить пароль через pgcrypto
            $stmt = $db->prepare("UPDATE users SET password = crypt(?, gen_salt('bf')) WHERE id = ?");
            $stmt->execute([$new_password, $user['id']]);
            
            // Добавить активность
            $db->prepare("INSERT INTO activities (type, subject, user_id) VALUES (?, ?, ?)")
               ->execute(['password_change', 'Смена пароля', $user['id']]);
            
            setFlash('Пароль успешно изменен', 'success');
            header('Location: profile.php');
            exit;
        }
    }
}

// Статистика активности пользователя
$stats = [
    'companies' => $db->prepare("SELECT COUNT(*) FROM companies WHERE user_id = ?")->execute([$user['id']]) ? $db->query("SELECT COUNT(*) FROM companies WHERE user_id = {$user['id']}")->fetchColumn() : 0,
    'contacts' => $db->prepare("SELECT COUNT(*) FROM contacts WHERE user_id = ?")->execute([$user['id']]) ? $db->query("SELECT COUNT(*) FROM contacts WHERE user_id = {$user['id']}")->fetchColumn() : 0,
    'deals' => $db->prepare("SELECT COUNT(*) FROM deals WHERE user_id = ?")->execute([$user['id']]) ? $db->query("SELECT COUNT(*) FROM deals WHERE user_id = {$user['id']}")->fetchColumn() : 0,
    'tasks' => $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?")->execute([$user['id']]) ? $db->query("SELECT COUNT(*) FROM tasks WHERE user_id = {$user['id']}")->fetchColumn() : 0,
];

// Последние активности
$activities = $db->prepare("
    SELECT type, subject, created_at 
    FROM activities 
    WHERE user_id = ?
    ORDER BY created_at DESC 
    LIMIT 10
");
$activities->execute([$user['id']]);
$recentActivities = $activities->fetchAll();

ob_start();
?>

<div class="page-header">
    <div class="page-title">
        <h1>
            <span class="page-title-icon">◉</span>
            Профиль пользователя
        </h1>
    </div>
    <p class="page-description">Управление вашей учетной записью и настройками</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <!-- Основная информация -->
    <div>
        <div class="card mb-20">
            <div class="card-header">
                <h3 class="card-title">Основная информация</h3>
            </div>
            <div class="card-body">
                <div style="text-align: center; margin-bottom: 30px;">
                    <div class="avatar" style="width: 100px; height: 100px; font-size: 40px; margin: 0 auto 15px; background: <?= getAvatarColor($user['name']) ?>">
                        <?= getInitials($user['name']) ?>
                    </div>
                    <h2 style="margin: 0 0 5px 0; font-size: 24px;"><?= e($user['name']) ?></h2>
                    <span class="badge badge-<?= $user['role'] === 'admin' ? 'primary' : 'secondary' ?>" style="font-size: 12px;">
                        <?= $user['role'] === 'admin' ? 'Администратор' : 'Пользователь' ?>
                    </span>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label class="form-label">Имя *</label>
                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Роль</label>
                        <input type="text" class="form-control" value="<?= $user['role'] === 'admin' ? 'Администратор' : 'Пользователь' ?>" disabled>
                        <small style="color: #6b7280; font-size: 12px;">Роль может изменить только администратор</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        Сохранить изменения
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Статистика -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ваша активность</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-2" style="gap: 15px;">
                    <div style="text-align: center; padding: 20px; background: #f9fafb; border-radius: 10px;">
                        <div style="font-size: 32px; font-weight: 700; color: var(--primary);">
                            <?= $stats['companies'] ?>
                        </div>
                        <div style="color: #6b7280; font-size: 14px; margin-top: 5px;">
                            Компаний
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f9fafb; border-radius: 10px;">
                        <div style="font-size: 32px; font-weight: 700; color: var(--success);">
                            <?= $stats['contacts'] ?>
                        </div>
                        <div style="color: #6b7280; font-size: 14px; margin-top: 5px;">
                            Контактов
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f9fafb; border-radius: 10px;">
                        <div style="font-size: 32px; font-weight: 700; color: var(--warning);">
                            <?= $stats['deals'] ?>
                        </div>
                        <div style="color: #6b7280; font-size: 14px; margin-top: 5px;">
                            Сделок
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f9fafb; border-radius: 10px;">
                        <div style="font-size: 32px; font-weight: 700; color: var(--info);">
                            <?= $stats['tasks'] ?>
                        </div>
                        <div style="color: #6b7280; font-size: 14px; margin-top: 5px;">
                            Задач
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Безопасность и активность -->
    <div>
        <!-- Смена пароля -->
        <div class="card mb-20">
            <div class="card-header">
                <h3 class="card-title">Изменить пароль</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label">Текущий пароль *</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Новый пароль *</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                        <small style="color: #6b7280; font-size: 12px;">Минимум 6 символов</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Подтвердите пароль *</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100">
                        Изменить пароль
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Последние действия -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Последние действия</h3>
            </div>
            <div class="card-body">
                <?php if ($recentActivities): ?>
                    <div class="timeline">
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker <?= $activity['type'] === 'login' ? 'success' : 'info' ?>"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <div class="timeline-title">
                                            <?php
                                            $icons = [
                                                'login' => '',
                                                'logout' => '',
                                                'profile_update' => '',
                                                'password_change' => '',
                                                'note' => '',
                                                'call' => '',
                                                'email' => '',
                                                'meeting' => '',
                                            ];
                                            $icon = $icons[$activity['type']] ?? '';
                                            ?>
                                            <?= $icon ?> <?= e($activity['subject']) ?>
                                        </div>
                                        <div class="timeline-time"><?= timeAgo($activity['created_at']) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"></div>
                        <p>Нет записей об активности</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>