// Основная логика приложения
// Утилиты и вспомогательные функции

const App = {
    // Проверка авторизации (не блокирует интерфейс)
    async checkAuth() {
        if (!Config.isBackendAvailable()) {
            // Бэкенд недоступен, но интерфейс показываем
            return null;
        }
        
        try {
            const user = await api.getCurrentUser();
            if (user && user.id) {
                return user;
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            // Не перенаправляем на login, просто возвращаем null
            return null;
        }
        
        return null;
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
    
    // Обработка ошибок API (не блокирует интерфейс)
    handleApiError(error) {
        console.error('API Error:', error);
        // Просто логируем ошибку, не показываем сообщение пользователю
        // Интерфейс продолжает работать
        if (!error.message.includes('Backend API is not available')) {
            // Показываем уведомление только для других ошибок
            this.showNotification('Ошибка подключения к серверу', 'warning');
        }
    }
};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', async () => {
    // Проверяем авторизацию, но не блокируем интерфейс
    // Интерфейс всегда показывается, даже если бэкенд недоступен
    if (!window.location.pathname.includes('login.html') && 
        !window.location.pathname.includes('register.html')) {
        await App.checkAuth();
    }
});

