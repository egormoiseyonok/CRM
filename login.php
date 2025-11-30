<?php
require_once 'config.php';

// Если уже авторизован
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Проверка пароля через pgcrypto
        if ($user) {
            $stmt = $db->prepare("SELECT password = crypt(?, password) as valid FROM users WHERE id = ?");
            $stmt->execute([$password, $user['id']]);
            $isValid = $stmt->fetchColumn();
            
            if ($isValid) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Добавить активность
                $stmt = $db->prepare("INSERT INTO activities (type, subject, user_id) VALUES (?, ?, ?)");
                $stmt->execute(['login', 'Вход в систему', $user['id']]);
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Неверный email или пароль';
            }
        } else {
            $error = 'Неверный email или пароль';
        }
    } else {
        $error = 'Заполните все поля';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Simple CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .login-logo {
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
        
        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 15px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 24px;
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
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #ef4444;
        }
        
        .demo-info {
            margin-top: 30px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 10px;
            font-size: 13px;
            color: #6b7280;
        }
        
        .demo-info strong {
            display: block;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .demo-info code {
            display: block;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            margin: 6px 0;
            font-family: 'Courier New', monospace;
            color: #667eea;
        }
        
        .register-link {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .features {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #6b7280;
        }
        
        .feature-icon {
            font-size: 20px;
        }
        
        @media (max-width: 480px) {
            .login-body {
                padding: 30px 20px;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">CRM</div>
            <h1>Simple CRM</h1>
            <p>Система управления клиентами</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="your@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required 
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">Пароль</label>
                    <input 
                        type="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Введите пароль"
                        required
                    >
                </div>
                
                <button type="submit" class="btn-login">
                    Войти в систему
                </button>
            </form>
            
            <div class="register-link">
                Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
            </div>
            
            <div class="demo-info">
                <strong>🔐 Тестовые аккаунты:</strong>
                <code>Админ: admin@crm.local / admin123</code>
                <code>Пользователь: manager@crm.local / manager123</code>
            </div>
            
            <div class="features">
                <div class="feature">
                    <span class="feature-icon">—</span>
                    <span>Управление компаниями</span>
                </div>
                <div class="feature">
                    <span class="feature-icon">—</span>
                    <span>База контактов</span>
                </div>
                <div class="feature">
                    <span class="feature-icon">—</span>
                    <span>Воронка продаж</span>
                </div>
                <div class="feature">
                    <span class="feature-icon">—</span>
                    <span>Задачи и напоминания</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>