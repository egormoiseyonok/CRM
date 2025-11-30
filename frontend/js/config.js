// Конфигурация приложения
// Автоматически определяет, работает ли приложение локально или на GitHub Pages

const Config = {
    // Автоматическое определение API URL
    getApiUrl() {
        // Если мы на GitHub Pages (или другом статическом хостинге)
        if (window.location.hostname.includes('github.io') || 
            window.location.hostname.includes('github.com') ||
            window.location.protocol === 'file:') {
            // Для GitHub Pages можно указать URL вашего PHP бэкенда
            // Или оставить null, чтобы показывать сообщение о необходимости локального запуска
            return null; // null означает, что бэкенд недоступен
        }
        
        // Локальная разработка - используем относительный путь
        return window.location.origin;
    },
    
    // Проверка доступности бэкенда
    isBackendAvailable() {
        return this.getApiUrl() !== null;
    },
    
    // Получить полный URL для API запроса
    getApiEndpoint(path) {
        const baseUrl = this.getApiUrl();
        if (!baseUrl) {
            throw new Error('Backend API is not available. Please run the application locally.');
        }
        return `${baseUrl}/api/${path}`;
    }
};

// Экспорт для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Config;
}

