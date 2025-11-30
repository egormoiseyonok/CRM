<?php
require_once 'config.php';

// Если уже авторизован
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Валидация
    if (empty($name)) {
        $error = 'Введите ваше имя';
    } elseif (empty($email)) {
        $error = 'Введите email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email адрес';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $error = 'Email содержит недопустимые символы';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен содержать минимум 6 символов';
    } elseif ($password !== $confirm_password) {
        $error = 'Пароли не совпадают';
    } else {
        try {
            $db = getDB();
            
            // Проверка существования email
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email уже зарегистрирован';
            } else {
                // Регистрация нового пользователя с использованием pgcrypto
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password, role) 
                    VALUES (?, ?, crypt(?, gen_salt('bf')), 'user')
                    RETURNING id
                ");
                $stmt->execute([$name, $email, $password]);
                $userId = $stmt->fetchColumn();
                
                if ($userId) {
                    // Автоматический вход после регистрации
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_role'] = 'user';
                    
                    // Добавить активность
                    $stmt = $db->prepare("INSERT INTO activities (type, subject, user_id) VALUES (?, ?, ?)");
                    $stmt->execute(['register', 'Регистрация в системе', $userId]);
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Ошибка при регистрации. Попробуйте ещё раз';
                }
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Portata</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #4A90E2 0%, #6BB6FF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, #4A90E2 0%, #6BB6FF 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .register-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            backdrop-filter: blur(10px);
        }
        
        .register-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .register-header p {
            opacity: 0.9;
            font-size: 15px;
        }
        
        .register-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1f2937;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4A90E2;
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
        }
        
        .btn-register {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4A90E2 0%, #6BB6FF 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4);
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.5);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .login-link {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        
        .login-link a {
            color: #4A90E2;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-hint {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
        }
        
        @media (max-width: 480px) {
            .register-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="register-logo">P</div>
            <h1>Регистрация</h1>
            <p>Создайте учетную запись в Portata</p>
        </div>
        
        <div class="register-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span>!</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span>◉</span>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Ваше имя *</label>
                    <input 
                        type="text" 
                        name="name" 
                        class="form-control" 
                        placeholder="Иван Иванов"
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        required 
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="your@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                        pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
                        oninvalid="this.setCustomValidity('Введите корректный email адрес')"
                        oninput="this.setCustomValidity('')"
                    >
                    <div class="password-hint">Пример: user@example.com</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Пароль *</label>
                    <input 
                        type="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Минимум 6 символов"
                        required
                        minlength="6"
                    >
                    <div class="password-hint">Минимум 6 символов</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Подтвердите пароль *</label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        class="form-control" 
                        placeholder="Повторите пароль"
                        required
                        minlength="6"
                    >
                </div>
                
                <button type="submit" class="btn-register">
                    Зарегистрироваться
                </button>
            </form>
            
            <div class="login-link">
                Уже есть аккаунт? <a href="login.php">Войти</a>
            </div>
        </div>
    </div>
</body>
</html>