// Страница панели управления (dashboard)

const Dashboard = {
    async init() {
        if (!Config.isBackendAvailable()) {
            this.showStaticContent();
            return;
        }
        
        try {
            await this.loadDashboard();
        } catch (error) {
            App.handleApiError(error);
        }
    },
    
    showStaticContent() {
        const statsGrid = document.getElementById('statsGrid');
        const mainContent = document.getElementById('mainContent');
        
        if (statsGrid) {
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-icon primary"></div>
                    <div class="stat-content">
                        <div class="stat-label">Активные компании</div>
                        <div class="stat-value">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"></div>
                    <div class="stat-content">
                        <div class="stat-label">Активные сделки</div>
                        <div class="stat-value">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"></div>
                    <div class="stat-content">
                        <div class="stat-label">Открытые задачи</div>
                        <div class="stat-value">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"></div>
                    <div class="stat-content">
                        <div class="stat-label">Выручка</div>
                        <div class="stat-value">—</div>
                    </div>
                </div>
            `;
        }
        
        if (mainContent) {
            mainContent.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">📊</div>
                            <h3>Статистика недоступна</h3>
                            <p>Для просмотра статистики запустите приложение локально</p>
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

