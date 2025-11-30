<?php
// includes/layout.php - Основной шаблон с сайдбаром
if (!function_exists('getCurrentUser')) {
    require_once __DIR__ . '/../config.php';
}

$user = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$db = getDB();

// Получить уведомления (просроченные задачи и срочные дела)
$notifications = [];
$isAdmin = isAdmin();
$isManager = isManager();
$isAdminOrManager = isAdminOrManager();
$userFilter = $isAdminOrManager ? '' : " AND user_id = {$user['id']}";
$userFilterDeals = $isAdminOrManager ? '' : " AND d.user_id = {$user['id']}";

// Просроченные задачи
$overdueTasks = $db->query("
    SELECT COUNT(*) FROM tasks 
    WHERE status != 'completed' 
    AND due_date < CURRENT_DATE
    $userFilter
")->fetchColumn();

if ($overdueTasks > 0) {
    $notifications[] = [
        'type' => 'danger',
        'icon' => '',
        'text' => "Просрочено задач: $overdueTasks",
        'link' => 'tasks.php?status=pending'
    ];
}

// Задачи на сегодня
$todayTasks = $db->query("
    SELECT COUNT(*) FROM tasks 
    WHERE status != 'completed' 
    AND due_date = CURRENT_DATE
    $userFilter
")->fetchColumn();

if ($todayTasks > 0) {
    $notifications[] = [
        'type' => 'warning',
        'icon' => '',
        'text' => "Задач на сегодня: $todayTasks",
        'link' => 'tasks.php'
    ];
}

// Сделки требующие внимания
$urgentDeals = $db->query("
    SELECT COUNT(*) FROM deals d
    WHERE d.stage IN ('negotiation', 'proposal')
    AND d.expected_close_date <= CURRENT_DATE + INTERVAL '7 days'
    $userFilterDeals
")->fetchColumn();

if ($urgentDeals > 0) {
    $notifications[] = [
        'type' => 'info',
        'icon' => '',
        'text' => "Срочные сделки: $urgentDeals",
        'link' => 'deals.php?stage=negotiation'
    ];
}

$notificationCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>Portata</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <div class="sidebar-logo-icon">P</div>
                    <span>Portata</span>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Главное</div>
                    <a href="index.php" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Панель управления</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">CRM</div>
                    <a href="companies.php" class="nav-item <?= $currentPage === 'companies' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Компании</span>
                        <?php
                        $companyCount = $db->query("SELECT COUNT(*) FROM companies WHERE status='active'")->fetchColumn();
                        if ($companyCount > 0): ?>
                            <span class="nav-badge"><?= $companyCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="contacts.php" class="nav-item <?= $currentPage === 'contacts' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Контакты</span>
                    </a>
                    <a href="deals.php" class="nav-item <?= $currentPage === 'deals' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Сделки</span>
                    </a>
                    <a href="tasks.php" class="nav-item <?= $currentPage === 'tasks' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Задачи</span>
                        <?php
                        $taskCount = $db->query("SELECT COUNT(*) FROM tasks WHERE status!='completed'")->fetchColumn();
                        if ($taskCount > 0): ?>
                            <span class="nav-badge"><?= $taskCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Аналитика</div>
                    <a href="activities.php" class="nav-item <?= $currentPage === 'activities' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Активности</span>
                    </a>
                    <a href="calendar.php" class="nav-item <?= $currentPage === 'calendar' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Календарь</span>
                    </a>
                    <?php if (isAdminOrManager()): ?>
                    <a href="reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Отчёты и экспорт</span>
                    </a>
                    <?php endif; ?>
                    <?php if (isAdmin()): ?>
                    <a href="users.php" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Пользователи</span>
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Настройки</div>
                    <a href="profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                        <span class="nav-icon">—</span>
                        <span>Профиль</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <a href="profile.php" class="sidebar-user" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.background='transparent'">
                    <div class="sidebar-user-avatar" style="background: <?= getAvatarColor($user['name']) ?>">
                        <?= getInitials($user['name']) ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= e($user['name']) ?></div>
                        <div class="sidebar-user-role"><?= e($user['role']) ?></div>
                    </div>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="topbar-menu-btn" onclick="toggleSidebar()">☰</button>
                    <div class="topbar-breadcrumb">
                        <span>CRM</span>
                        <span class="breadcrumb-separator">/</span>
                        <span class="breadcrumb-current"><?= isset($pageTitle) ? e($pageTitle) : 'Панель' ?></span>
                    </div>
                </div>
                
                <div class="topbar-right">
                    <div class="topbar-search">
                        <span class="topbar-search-icon">⌕</span>
                        <input type="text" placeholder="Поиск..." id="globalSearch">
                    </div>
                    
                    <div class="topbar-actions">
                        <div class="dropdown">
                            <button class="topbar-icon-btn" title="Уведомления" onclick="toggleDropdown(this)">
                                •
                                <?php if ($notificationCount > 0): ?>
                                    <span class="badge badge-danger"><?= $notificationCount ?></span>
                                <?php endif; ?>
                            </button>
                            <div class="dropdown-menu" style="min-width: 300px;">
                                <?php if ($notifications): ?>
                                    <div style="padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600;">
                                        Уведомления (<?= $notificationCount ?>)
                                    </div>
                                    <?php foreach ($notifications as $notif): ?>
                                        <a href="<?= $notif['link'] ?>" class="dropdown-item">
                                            <span style="font-size: 14px; color: var(--text-secondary);">•</span>
                                            <span><?= $notif['text'] ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                    <div class="dropdown-divider"></div>
                                    <a href="tasks.php" class="dropdown-item" style="text-align: center; color: var(--primary);">
                                        Посмотреть все задачи
                                    </a>
                                <?php else: ?>
                                    <div style="padding: 20px; text-align: center; color: #9ca3af;">
                                        <div style="font-size: 14px; margin-bottom: 8px; opacity: 0.5;">—</div>
                                        <div>Нет новых уведомлений</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="logout.php" class="topbar-icon-btn" title="Выйти">
                            🚪
                        </a>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <?php
                $flash = getFlash();
                if ($flash):
                ?>
                    <div class="alert alert-<?= $flash['type'] ?> fade-in">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>
                
                <?= isset($content) ? $content : '' ?>
            </div>
        </main>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Search Results Modal -->
    <div class="modal-overlay" id="searchModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Результаты поиска</h3>
                <button class="modal-close" onclick="closeSearchModal()">&times;</button>
            </div>
            <div class="modal-body" id="searchResults">
                <div class="loading">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('mobileOverlay').classList.toggle('show');
        }

        function toggleDropdown(btn) {
            const dropdown = btn.closest('.dropdown');
            const isOpen = dropdown.classList.contains('show');
            
            // Закрыть все dropdown
            document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
            
            if (!isOpen) {
                dropdown.classList.add('show');
            }
        }

        // Закрытие dropdown при клике вне
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
            }
        });

        // Глобальный поиск
        let searchTimeout;
        document.getElementById('globalSearch')?.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                closeSearchModal();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 500);
        });

        function performSearch(query) {
            const modal = document.getElementById('searchModal');
            const results = document.getElementById('searchResults');
            
            modal.classList.add('show');
            results.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            
            fetch(`api/search.php?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.companies.length === 0 && data.contacts.length === 0 && data.deals.length === 0) {
                        results.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon">🔍</div>
                                <p>Ничего не найдено по запросу "${query}"</p>
                            </div>
                        `;
                        return;
                    }
                    
                    let html = '';
                    
                    if (data.companies.length > 0) {
                        html += '<h4 style="margin: 0 0 12px 0; padding: 0 0 8px 0; border-bottom: 1px solid var(--border);">Компании</h4>';
                        data.companies.forEach(company => {
                            html += `
                                <a href="company_view.php?id=${company.id}" class="dropdown-item">
                                    ${company.name}
                                    ${company.industry ? '<span style="color: #9ca3af;"> • ' + company.industry + '</span>' : ''}
                                </a>
                            `;
                        });
                    }
                    
                    if (data.contacts.length > 0) {
                        html += '<h4 style="margin: 20px 0 12px 0; padding: 8px 0 8px 0; border-bottom: 1px solid var(--border);">Контакты</h4>';
                        data.contacts.forEach(contact => {
                            html += `
                                <div class="dropdown-item">
                                    👤 ${contact.first_name} ${contact.last_name}
                                    ${contact.position ? '<span style="color: #9ca3af;"> • ' + contact.position + '</span>' : ''}
                                </div>
                            `;
                        });
                    }
                    
                    if (data.deals.length > 0) {
                        html += '<h4 style="margin: 20px 0 12px 0; padding: 8px 0 8px 0; border-bottom: 1px solid var(--border);">Сделки</h4>';
                        data.deals.forEach(deal => {
                            html += `
                                <div class="dropdown-item">
                                    ${deal.title}
                                    <span style="color: var(--success); font-weight: 600;"> • ${formatMoney(deal.amount)}</span>
                                </div>
                            `;
                        });
                    }
                    
                    results.innerHTML = html;
                })
                .catch(err => {
                    results.innerHTML = `
                        <div class="alert alert-danger">
                            Ошибка поиска. Попробуйте ещё раз.
                        </div>
                    `;
                });
        }

        function closeSearchModal() {
            document.getElementById('searchModal').classList.remove('show');
        }

        function formatMoney(amount) {
            return new Intl.NumberFormat('ru-RU', {
                style: 'currency',
                currency: 'RUB',
                minimumFractionDigits: 0
            }).format(amount);
        }
        
        // Автоскрытие алертов
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Закрытие модалок по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.show').forEach(m => {
                    m.classList.remove('show');
                });
            }
        });
    </script>
</body>
</html>