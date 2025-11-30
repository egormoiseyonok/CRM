// Управление layout (сайдбар, навигация, пользователь)

const Layout = {
    currentUser: null,
    
    async init() {
        // Всегда показываем навигацию, даже без бэкенда
        this.renderSidebar();
        this.setupSearch();
        
        // Пытаемся загрузить данные пользователя, если бэкенд доступен
        if (Config.isBackendAvailable()) {
            try {
                this.currentUser = await api.getCurrentUser();
                this.renderUserInfo();
            } catch (error) {
                console.error('Layout init failed:', error);
                // Показываем дефолтную информацию пользователя
                this.renderDefaultUserInfo();
            }
        } else {
            // Показываем дефолтную информацию пользователя
            this.renderDefaultUserInfo();
        }
    },
    
    renderSidebar() {
        const nav = document.getElementById('sidebarNav');
        if (!nav) return;
        
        const currentPage = window.location.pathname.split('/').pop() || 'index.html';
        
        nav.innerHTML = `
            <div class="nav-section">
                <div class="nav-section-title">Главное</div>
                <a href="index.html" class="nav-item ${currentPage === 'index.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Панель управления</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">CRM</div>
                <a href="companies.html" class="nav-item ${currentPage === 'companies.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Компании</span>
                </a>
                <a href="contacts.html" class="nav-item ${currentPage === 'contacts.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Контакты</span>
                </a>
                <a href="deals.html" class="nav-item ${currentPage === 'deals.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Сделки</span>
                </a>
                <a href="tasks.html" class="nav-item ${currentPage === 'tasks.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Задачи</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Аналитика</div>
                <a href="activities.html" class="nav-item ${currentPage === 'activities.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Активности</span>
                </a>
                <a href="calendar.html" class="nav-item ${currentPage === 'calendar.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Календарь</span>
                </a>
                <a href="reports.html" class="nav-item ${currentPage === 'reports.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Отчёты и экспорт</span>
                </a>
                <a href="users.html" class="nav-item ${currentPage === 'users.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Пользователи</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Настройки</div>
                <a href="profile.html" class="nav-item ${currentPage === 'profile.html' ? 'active' : ''}">
                    <span class="nav-icon">—</span>
                    <span>Профиль</span>
                </a>
            </div>
        `;
    },
    
    renderUserInfo() {
        const footer = document.getElementById('sidebarFooter');
        if (!footer || !this.currentUser) return;
        
        const initials = this.currentUser.name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
        
        footer.innerHTML = `
            <a href="profile.html" class="sidebar-user" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.background='transparent'">
                <div class="sidebar-user-avatar" style="background: ${this.getAvatarColor(this.currentUser.name)}">
                    ${initials}
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">${App.escapeHtml(this.currentUser.name)}</div>
                    <div class="sidebar-user-role">${App.escapeHtml(this.currentUser.role)}</div>
                </div>
            </a>
        `;
    },
    
    renderDefaultUserInfo() {
        const footer = document.getElementById('sidebarFooter');
        if (!footer) return;
        
        footer.innerHTML = `
            <a href="profile.html" class="sidebar-user" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.background='transparent'">
                <div class="sidebar-user-avatar" style="background: #667eea">
                    GU
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">Гость</div>
                    <div class="sidebar-user-role">Пользователь</div>
                </div>
            </a>
        `;
    },
    
    getAvatarColor(string) {
        const colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#fee140', '#30cfd0'];
        let hash = 0;
        for (let i = 0; i < string.length; i++) {
            hash = string.charCodeAt(i) + ((hash << 5) - hash);
        }
        return colors[Math.abs(hash) % colors.length];
    },
    
    setupSearch() {
        const searchInput = document.getElementById('globalSearch');
        if (!searchInput) return;
        
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                closeSearchModal();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 500);
        });
    },
    
    async performSearch(query) {
        const modal = document.getElementById('searchModal');
        const results = document.getElementById('searchResults');
        
        if (modal) modal.classList.add('show');
        if (results) results.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        if (!Config.isBackendAvailable()) {
            // Бэкенд недоступен - показываем пустой результат
            if (results) {
                results.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <p>Поиск недоступен без локального бэкенда</p>
                    </div>
                `;
            }
            return;
        }
        
        try {
            const data = await api.search(query);
            
            if (!results) return;
            
            if (data.companies.length === 0 && data.contacts.length === 0 && data.deals.length === 0) {
                results.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <p>Ничего не найдено по запросу "${App.escapeHtml(query)}"</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            
            if (data.companies.length > 0) {
                html += '<h4 style="margin: 0 0 12px 0; padding: 0 0 8px 0; border-bottom: 1px solid var(--border);">Компании</h4>';
                data.companies.forEach(company => {
                    html += `
                        <a href="company_view.html?id=${company.id}" class="dropdown-item">
                            🏢 ${App.escapeHtml(company.name)}
                            ${company.industry ? '<span style="color: #9ca3af;"> • ' + App.escapeHtml(company.industry) + '</span>' : ''}
                        </a>
                    `;
                });
            }
            
            if (data.contacts.length > 0) {
                html += '<h4 style="margin: 20px 0 12px 0; padding: 8px 0 8px 0; border-bottom: 1px solid var(--border);">Контакты</h4>';
                data.contacts.forEach(contact => {
                    html += `
                        <div class="dropdown-item">
                            👤 ${App.escapeHtml(contact.first_name)} ${App.escapeHtml(contact.last_name)}
                            ${contact.position ? '<span style="color: #9ca3af;"> • ' + App.escapeHtml(contact.position) + '</span>' : ''}
                        </div>
                    `;
                });
            }
            
            if (data.deals.length > 0) {
                html += '<h4 style="margin: 20px 0 12px 0; padding: 8px 0 8px 0; border-bottom: 1px solid var(--border);">Сделки</h4>';
                data.deals.forEach(deal => {
                    html += `
                        <div class="dropdown-item">
                            💼 ${App.escapeHtml(deal.title)}
                            <span style="color: var(--success); font-weight: 600;"> • ${App.formatMoney(deal.amount)}</span>
                        </div>
                    `;
                });
            }
            
            results.innerHTML = html;
        } catch (error) {
            // Молча обрабатываем ошибку, показываем пустой результат
            if (results) {
                results.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <p>Ошибка поиска</p>
                    </div>
                `;
            }
            console.error('Search error:', error);
        }
    }
};

// Глобальные функции для layout
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    if (sidebar) sidebar.classList.toggle('show');
    if (overlay) overlay.classList.toggle('show');
}

function toggleDropdown(btn) {
    const dropdown = btn.closest('.dropdown');
    const isOpen = dropdown.classList.contains('show');
    
    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
    
    if (!isOpen && dropdown) {
        dropdown.classList.add('show');
    }
}

function closeSearchModal() {
    const modal = document.getElementById('searchModal');
    if (modal) modal.classList.remove('show');
}

// Инициализация layout при загрузке
document.addEventListener('DOMContentLoaded', () => {
    Layout.init();
    
    // Закрытие dropdown при клике вне
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('show'));
        }
    });
    
    // Закрытие модалок по Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show').forEach(m => {
                m.classList.remove('show');
            });
        }
    });
});

