// API клиент для работы с бэкендом
// Использует Config для определения URL API

class ApiClient {
    constructor() {
        this.baseUrl = Config.getApiUrl();
        this.isAvailable = Config.isBackendAvailable();
    }
    
    // Базовый метод для выполнения запросов
    async request(endpoint, options = {}) {
        if (!this.isAvailable) {
            throw new Error('Backend API is not available. Please run the application locally with PHP server.');
        }
        
        const url = Config.getApiEndpoint(endpoint);
        const defaultOptions = {
            credentials: 'include', // Для отправки cookies (сессии)
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        const config = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        try {
            const response = await fetch(url, config);
            
            // Если ответ не JSON (например, редирект на login)
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                if (response.redirected || response.status === 302) {
                    window.location.href = '/login.html';
                    return null;
                }
                throw new Error('Invalid response format');
            }
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP error! status: ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }
    
    // GET запрос
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }
    
    // POST запрос
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    // PUT запрос
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    // DELETE запрос
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
    
    // Авторизация
    async login(email, password) {
        // Для логина используем обычную форму, так как это требует сессию
        // Но можно сделать через API если добавить токены
        const form = new FormData();
        form.append('email', email);
        form.append('password', password);
        
        const response = await fetch(`${this.baseUrl}/login.php`, {
            method: 'POST',
            credentials: 'include',
            body: form
        });
        
        if (response.redirected || response.ok) {
            return { success: true };
        }
        
        throw new Error('Login failed');
    }
    
    // Получить текущего пользователя
    async getCurrentUser() {
        return this.get('user.php');
    }
    
    // Поиск
    async search(query) {
        return this.get('search.php', { q: query });
    }
    
    // Компании
    async getCompany(id) {
        return this.get('company.php', { id });
    }
    
    // Контакты
    async getContact(id) {
        return this.get('contact.php', { id });
    }
    
    // Встречи
    async getMeeting(id) {
        return this.get('meeting.php', { id });
    }
}

// Создаем глобальный экземпляр
const api = new ApiClient();

// Экспорт для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ApiClient;
}

