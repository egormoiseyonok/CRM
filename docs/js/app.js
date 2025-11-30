// Основная логика приложения
// Утилиты и вспомогательные функции

const App = {
    // Проверка авторизации
    async checkAuth() {
        if (!Config.isBackendAvailable()) {
            this.showBackendUnavailable();
            return false;
        }
        
        try {
            const user = await api.getCurrentUser();
            if (user && user.id) {
                return user;
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            window.location.href = 'login.html';
            return false;
        }
        
        return false;
    },
    
    // Показать сообщение о недоступности бэкенда
    showBackendUnavailable() {
        const message = document.createElement('div');
        message.className = 'backend-unavailable';
        message.innerHTML = `
            <div style="max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <h2 style="color: #667eea; margin-bottom: 16px;">🔧 Бэкенд недоступен</h2>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Вы просматриваете статическую версию приложения на GitHub Pages. 
                    Для полной функциональности (вход, работа с данными) необходимо запустить приложение локально.
                </p>
                <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="font-size: 16px; margin-bottom: 12px; color: #1f2937;">Как запустить локально:</h3>
                    <ol style="margin-left: 20px; color: #6b7280; line-height: 1.8;">
                        <li>Установите XAMPP или другой PHP сервер</li>
                        <li>Склонируйте репозиторий в папку <code style="background: white; padding: 2px 6px; border-radius: 4px;">htdocs</code></li>
                        <li>Настройте базу данных PostgreSQL</li>
                        <li>Откройте <code style="background: white; padding: 2px 6px; border-radius: 4px;">http://localhost/CRM</code></li>
                    </ol>
                </div>
                <p style="color: #9ca3af; font-size: 14px;">
                    Статическая версия показывает только интерфейс без функциональности.
                </p>
            </div>
        `;
        document.body.innerHTML = '';
        document.body.appendChild(message);
    },
    
    // Форматирование даты
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    },
    
    // Форматирование суммы
    formatMoney(amount) {
        return new Intl.NumberFormat('ru-RU', {
            style: 'currency',
            currency: 'RUB',
            minimumFractionDigits: 0
        }).format(amount);
    },
    
    // Относительное время
    timeAgo(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        
        if (seconds < 60) return 'только что';
        if (minutes < 60) return `${minutes} мин. назад`;
        if (hours < 24) return `${hours} ч. назад`;
        if (days < 7) return `${days} дн. назад`;
        
        return this.formatDate(dateString);
    },
    
    // Перевод статусов
    translateStatus(status) {
        const translations = {
            'active': 'Активна',
            'inactive': 'Неактивна',
            'pending': 'В ожидании',
            'in_progress': 'В работе',
            'completed': 'Завершено',
            'lead': 'Лид',
            'qualified': 'Квалификация',
            'proposal': 'Предложение',
            'negotiation': 'Переговоры',
            'won': 'Выиграна',
            'lost': 'Проиграна',
            'low': 'Низкий',
            'medium': 'Средний',
            'high': 'Высокий'
        };
        return translations[status] || status;
    },
    
    // Получить класс бейджа
    getBadgeClass(type, value) {
        const classes = {
            status: {
                'active': 'success',
                'inactive': 'danger',
                'pending': 'warning',
                'in_progress': 'info',
                'completed': 'success'
            },
            priority: {
                'low': 'secondary',
                'medium': 'warning',
                'high': 'danger'
            },
            stage: {
                'lead': 'secondary',
                'qualified': 'info',
                'proposal': 'primary',
                'negotiation': 'warning',
                'won': 'success',
                'lost': 'danger'
            }
        };
        return classes[type]?.[value] || 'secondary';
    },
    
    // Безопасный вывод HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    // Показать уведомление
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} fade-in`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    },
    
    // Обработка ошибок API
    handleApiError(error) {
        console.error('API Error:', error);
        if (error.message.includes('Backend API is not available')) {
            this.showBackendUnavailable();
        } else {
            this.showNotification('Ошибка: ' + error.message, 'danger');
        }
    }
};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', async () => {
    // Проверяем, не на странице логина
    if (!window.location.pathname.includes('login.html') && 
        !window.location.pathname.includes('register.html')) {
        await App.checkAuth();
    }
});

