// Страница панели управления (dashboard)

const Dashboard = {
    async init() {
        // Всегда показываем контент, даже без бэкенда
        if (!Config.isBackendAvailable()) {
            this.showStaticContent();
            return;
        }
        
        try {
            await this.loadDashboard();
        } catch (error) {
            // Если не удалось загрузить данные, показываем статический контент
            console.error('Failed to load dashboard:', error);
            this.showStaticContent();
        }
    },
    
    showStaticContent() {
        const statsGrid = document.getElementById('statsGrid');
        const mainContent = document.getElementById('mainContent');
        
        // Показываем карточки статистики с дефолтными значениями
        if (statsGrid) {
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-icon primary"></div>
                    <div class="stat-content">
                        <div class="stat-label">Активные компании</div>
                        <div class="stat-value">0</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"></div>
                    <div class="stat-content">
                        <div class="stat-label">Активные сделки</div>
                        <div class="stat-value">0</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"></div>
                    <div class="stat-content">
                        <div class="stat-label">Открытые задачи</div>
                        <div class="stat-value">0</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"></div>
                    <div class="stat-content">
                        <div class="stat-label">Выручка</div>
                        <div class="stat-value">0 ₽</div>
                    </div>
                </div>
            `;
        }
        
        // Показываем нормальный контент с пустыми состояниями
        if (mainContent) {
            mainContent.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Воронка продаж</h3>
                    </div>
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">💼</div>
                            <p>Нет активных сделок</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Срочные задачи</h3>
                        <a href="tasks.html" class="btn btn-sm btn-outline">Все задачи →</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="empty-state">
                            <div class="empty-state-icon">✓</div>
                            <p>Нет срочных задач</p>
                        </div>
                    </div>
                </div>
            `;
        }
    },
    
    async loadDashboard() {
        // Здесь будет загрузка данных через API
        // Пока что показываем статический контент
        this.showStaticContent();
    }
};

// Инициализация при загрузке страницы
if (document.getElementById('statsGrid')) {
    document.addEventListener('DOMContentLoaded', () => {
        Dashboard.init();
    });
}

