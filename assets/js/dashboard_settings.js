/**
 * Dashboard Settings JS
 * UI для управления настраиваемыми дашбордами
 */

class DashboardSettings {
    constructor() {
        this.dashboards = [];
        this.currentEditingDashboard = null;
        this.currentEditingWidgets = [];
        this.init();
    }

    async init() {
        // Загрузка дашбордов
        await this.loadDashboards();

        // Обработчики событий
        document.getElementById('create-dashboard-btn').addEventListener('click', () => {
            this.openCreateDashboardModal();
        });

        document.getElementById('refresh-btn').addEventListener('click', () => {
            this.loadDashboards();
        });

        document.getElementById('add-widget-btn').addEventListener('click', () => {
            this.openAddWidgetModal();
        });

        document.getElementById('save-dashboard-btn').addEventListener('click', () => {
            this.saveDashboard();
        });

        document.getElementById('save-widget-btn').addEventListener('click', () => {
            this.saveWidget();
        });
    }

    async loadDashboards() {
        try {
            const response = await fetch('/api/dashboards.php?action=list');
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load dashboards');
            }

            this.dashboards = result.data;
            this.renderDashboardList();
        } catch (error) {
            console.error('Error loading dashboards:', error);
            this.showError('Ошибка загрузки дашбордов: ' + error.message);
        }
    }

    renderDashboardList() {
        const container = document.getElementById('dashboards-container');

        if (this.dashboards.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <div>Нет дашбордов. Создайте первый дашборд.</div>
                </div>
            `;
            return;
        }

        container.innerHTML = '';

        this.dashboards.forEach(dashboard => {
            const item = document.createElement('div');
            item.className = 'dashboard-item';

            const defaultBadge = dashboard.is_default ? '<span class="badge badge-default">По умолчанию</span>' : '';

            item.innerHTML = `
                <div class="dashboard-info">
                    <div class="dashboard-name">
                        ${dashboard.name}
                        ${defaultBadge}
                    </div>
                    <div class="dashboard-meta">
                        ID: ${dashboard.dashboard_id} |
                        Виджетов: ${dashboard.widgets_count || 0} |
                        Раскладка: ${dashboard.layout_type}
                    </div>
                </div>
                <div class="dashboard-actions">
                    <button class="btn btn-secondary btn-small" onclick="dashboardSettings.editDashboard('${dashboard.dashboard_id}')">
                        ✏️ Редактировать
                    </button>
                    ${!dashboard.is_default ? `
                    <button class="btn btn-success btn-small" onclick="dashboardSettings.setDefault('${dashboard.dashboard_id}')">
                        ⭐ Сделать основным
                    </button>
                    ` : ''}
                    <button class="btn btn-danger btn-small" onclick="dashboardSettings.deleteDashboard('${dashboard.dashboard_id}')">
                        🗑️ Удалить
                    </button>
                </div>
            `;

            container.appendChild(item);
        });
    }

    openCreateDashboardModal() {
        this.currentEditingDashboard = null;
        this.currentEditingWidgets = [];

        document.getElementById('modal-title').textContent = 'Создать дашборд';
        document.getElementById('edit-dashboard-id').value = '';
        document.getElementById('dashboard-name').value = '';
        document.getElementById('dashboard-layout').value = 'grid';
        document.getElementById('dashboard-default').value = '0';

        this.renderWidgetsList();

        document.getElementById('dashboard-modal').classList.add('active');
    }

    async editDashboard(dashboardId) {
        try {
            const response = await fetch(`/api/dashboards.php?action=get&id=${dashboardId}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load dashboard');
            }

            this.currentEditingDashboard = result.data;
            this.currentEditingWidgets = result.data.widgets || [];

            document.getElementById('modal-title').textContent = 'Редактировать дашборд';
            document.getElementById('edit-dashboard-id').value = result.data.dashboard_id;
            document.getElementById('dashboard-name').value = result.data.name;
            document.getElementById('dashboard-layout').value = result.data.layout_type;
            document.getElementById('dashboard-default').value = result.data.is_default ? '1' : '0';

            this.renderWidgetsList();

            document.getElementById('dashboard-modal').classList.add('active');
        } catch (error) {
            console.error('Error loading dashboard:', error);
            alert('Ошибка загрузки дашборда: ' + error.message);
        }
    }

    renderWidgetsList() {
        const container = document.getElementById('widgets-list');

        if (this.currentEditingWidgets.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <div>Нет виджетов. Добавьте виджет чтобы начать.</div>
                </div>
            `;
            return;
        }

        container.innerHTML = '';

        this.currentEditingWidgets.forEach((widget, index) => {
            const item = document.createElement('div');
            item.className = 'widget-item';

            const config = typeof widget.config === 'string' ? JSON.parse(widget.config) : widget.config;

            item.innerHTML = `
                <div class="widget-item-info">
                    <div class="widget-title">${widget.title}</div>
                    <div class="widget-details">
                        Тип: ${widget.widget_type} |
                        Источник: ${widget.data_source} |
                        Размер: ${widget.size_width}x${widget.size_height} |
                        Порядок: ${widget.widget_order}
                    </div>
                </div>
                <div class="widget-item-actions">
                    <button class="btn-icon" onclick="dashboardSettings.editWidget(${index})">✏️</button>
                    <button class="btn-icon" onclick="dashboardSettings.moveWidgetUp(${index})">▲</button>
                    <button class="btn-icon" onclick="dashboardSettings.moveWidgetDown(${index})">▼</button>
                    <button class="btn-icon" onclick="dashboardSettings.deleteWidget(${index})">🗑️</button>
                </div>
            `;

            container.appendChild(item);
        });
    }

    openAddWidgetModal() {
        document.getElementById('widget-modal-title').textContent = 'Добавить виджет';
        document.getElementById('edit-widget-index').value = '';
        document.getElementById('widget-title').value = '';
        document.getElementById('widget-type').value = '';
        document.getElementById('widget-datasource').value = '';
        document.getElementById('widget-width').value = '4';
        document.getElementById('widget-height').value = '1';
        document.getElementById('widget-order').value = this.currentEditingWidgets.length;
        document.getElementById('widget-config').value = '{}';

        document.getElementById('save-widget-btn').textContent = 'Добавить';

        document.getElementById('widget-modal').classList.add('active');
    }

    editWidget(index) {
        const widget = this.currentEditingWidgets[index];

        document.getElementById('widget-modal-title').textContent = 'Редактировать виджет';
        document.getElementById('edit-widget-index').value = index;
        document.getElementById('widget-title').value = widget.title;
        document.getElementById('widget-type').value = widget.widget_type;
        document.getElementById('widget-datasource').value = widget.data_source;
        document.getElementById('widget-width').value = widget.size_width;
        document.getElementById('widget-height').value = widget.size_height;
        document.getElementById('widget-order').value = widget.widget_order;

        const config = typeof widget.config === 'string' ? widget.config : JSON.stringify(widget.config, null, 2);
        document.getElementById('widget-config').value = config;

        document.getElementById('save-widget-btn').textContent = 'Сохранить';

        document.getElementById('widget-modal').classList.add('active');
    }

    saveWidget() {
        const index = document.getElementById('edit-widget-index').value;
        const title = document.getElementById('widget-title').value.trim();
        const type = document.getElementById('widget-type').value;
        const datasource = document.getElementById('widget-datasource').value;
        const width = parseInt(document.getElementById('widget-width').value);
        const height = parseInt(document.getElementById('widget-height').value);
        const order = parseInt(document.getElementById('widget-order').value);
        const configText = document.getElementById('widget-config').value.trim();

        if (!title || !type || !datasource) {
            alert('Пожалуйста, заполните обязательные поля');
            return;
        }

        // Валидация JSON
        let config;
        try {
            config = configText ? JSON.parse(configText) : {};
        } catch (error) {
            alert('Ошибка в JSON конфигурации: ' + error.message);
            return;
        }

        const widget = {
            widget_id: index !== '' ? this.currentEditingWidgets[index].widget_id : `widget-${Date.now()}`,
            title: title,
            widget_type: type,
            data_source: datasource,
            size_width: width,
            size_height: height,
            widget_order: order,
            is_visible: 1,
            config: config
        };

        if (index !== '') {
            // Редактирование
            this.currentEditingWidgets[index] = widget;
        } else {
            // Добавление
            this.currentEditingWidgets.push(widget);
        }

        this.renderWidgetsList();
        this.closeWidgetModal();
    }

    deleteWidget(index) {
        if (confirm('Удалить виджет?')) {
            this.currentEditingWidgets.splice(index, 1);
            this.renderWidgetsList();
        }
    }

    moveWidgetUp(index) {
        if (index > 0) {
            [this.currentEditingWidgets[index - 1], this.currentEditingWidgets[index]] =
            [this.currentEditingWidgets[index], this.currentEditingWidgets[index - 1]];

            this.reorderWidgets();
            this.renderWidgetsList();
        }
    }

    moveWidgetDown(index) {
        if (index < this.currentEditingWidgets.length - 1) {
            [this.currentEditingWidgets[index], this.currentEditingWidgets[index + 1]] =
            [this.currentEditingWidgets[index + 1], this.currentEditingWidgets[index]];

            this.reorderWidgets();
            this.renderWidgetsList();
        }
    }

    reorderWidgets() {
        this.currentEditingWidgets.forEach((widget, index) => {
            widget.widget_order = index;
        });
    }

    async saveDashboard() {
        const dashboardId = document.getElementById('edit-dashboard-id').value;
        const name = document.getElementById('dashboard-name').value.trim();
        const layout = document.getElementById('dashboard-layout').value;
        const isDefault = document.getElementById('dashboard-default').value === '1';

        if (!name) {
            this.showModalError('Пожалуйста, введите название дашборда');
            return;
        }

        const data = {
            dashboard_id: dashboardId || `dashboard-${Date.now()}`,
            org_id: 'org-legacy',
            name: name,
            layout_type: layout,
            is_default: isDefault,
            widgets: this.currentEditingWidgets
        };

        try {
            let response;
            if (dashboardId) {
                // Обновление
                response = await fetch(`/api/dashboards.php?action=update&id=${dashboardId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
            } else {
                // Создание
                response = await fetch('/api/dashboards.php?action=create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to save dashboard');
            }

            this.closeDashboardModal();
            this.loadDashboards();
            this.showSuccess('Дашборд успешно сохранен');
        } catch (error) {
            console.error('Error saving dashboard:', error);
            this.showModalError('Ошибка сохранения: ' + error.message);
        }
    }

    async setDefault(dashboardId) {
        if (!confirm('Сделать этот дашборд основным?')) {
            return;
        }

        try {
            const response = await fetch(`/api/dashboards.php?action=set_default&id=${dashboardId}`, {
                method: 'PATCH'
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to set default');
            }

            this.loadDashboards();
            this.showSuccess('Дашборд установлен как основной');
        } catch (error) {
            console.error('Error setting default:', error);
            alert('Ошибка: ' + error.message);
        }
    }

    async deleteDashboard(dashboardId) {
        if (!confirm('Удалить дашборд? Это действие необратимо.')) {
            return;
        }

        try {
            const response = await fetch(`/api/dashboards.php?action=delete&id=${dashboardId}`, {
                method: 'DELETE'
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to delete dashboard');
            }

            this.loadDashboards();
            this.showSuccess('Дашборд удален');
        } catch (error) {
            console.error('Error deleting dashboard:', error);
            alert('Ошибка удаления: ' + error.message);
        }
    }

    closeDashboardModal() {
        document.getElementById('dashboard-modal').classList.remove('active');
        document.getElementById('modal-error').innerHTML = '';
    }

    closeWidgetModal() {
        document.getElementById('widget-modal').classList.remove('active');
        document.getElementById('widget-modal-error').innerHTML = '';
    }

    showModalError(message) {
        document.getElementById('modal-error').innerHTML = `<div class="error-message">${message}</div>`;
    }

    showError(message) {
        const container = document.getElementById('dashboards-container');
        container.innerHTML = `<div class="error-message" style="margin: 20px;">${message}</div>`;
    }

    showSuccess(message) {
        // Простое уведомление
        const notification = document.createElement('div');
        notification.className = 'success-message';
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}

// Глобальный объект для вызова методов из onclick
window.dashboardSettings = null;

// Закрытие модальных окон (для onclick кнопок)
function closeDashboardModal() {
    dashboardSettings.closeDashboardModal();
}

function closeWidgetModal() {
    dashboardSettings.closeWidgetModal();
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    window.dashboardSettings = new DashboardSettings();
});
