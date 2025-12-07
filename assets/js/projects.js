/**
 * Projects Page JavaScript
 * Управление проектами и источниками данных
 */

(function() {
    'use strict';

    // DOM Elements (будут инициализированы в init())
    let tabBtns, tabContents, createProjectCard, createProjectModal;
    let modalOverlay, modalClose, cancelBtn, submitProjectBtn;
    let projectActionsMenu, projectsList;

    // Mock data для демонстрации
    const mockProjects = [
        {
            id: 1,
            name: 'Отдел продаж - Ноябрь 2025',
            totalCalls: 1247,
            evaluatedCalls: 892,
            evaluatedPercent: 72,
            checklists: [
                { id: 1, name: 'Приветствие и представление', icon: '👋' },
                { id: 2, name: 'Выявление потребностей', icon: '🎯' },
                { id: 3, name: 'Презентация решения', icon: '💼' }
            ],
            datasource: {
                name: 'API Beeline',
                status: 'active',
                statusLabel: 'Активен'
            },
            aiConfigured: false
        },
        {
            id: 2,
            name: 'Обучение новых менеджеров',
            totalCalls: 324,
            evaluatedCalls: 324,
            evaluatedPercent: 100,
            checklists: [
                { id: 1, name: 'Скрипт приветствия', icon: '📞' },
                { id: 2, name: 'Работа с возражениями', icon: '🛡️' }
            ],
            datasource: {
                name: 'Ручная загрузка',
                status: 'manual',
                statusLabel: 'Вручную'
            },
            aiConfigured: true
        }
    ];

    // Инициализация
    function init() {
        console.log('[Projects] Initializing...');

        // Получаем DOM элементы после загрузки страницы
        tabBtns = document.querySelectorAll('.tab-btn');
        tabContents = document.querySelectorAll('.tab-content');
        createProjectCard = document.getElementById('create-project-card');
        createProjectModal = document.getElementById('create-project-modal');
        modalOverlay = document.getElementById('modal-overlay');
        modalClose = document.getElementById('modal-close');
        cancelBtn = document.getElementById('cancel-btn');
        submitProjectBtn = document.getElementById('submit-project-btn');
        projectActionsMenu = document.getElementById('project-actions-menu');
        projectsList = document.getElementById('projects-list');

        console.log('[Projects] Elements:', {
            createProjectCard: !!createProjectCard,
            createProjectModal: !!createProjectModal,
            submitProjectBtn: !!submitProjectBtn
        });

        setupEventListeners();
        loadProjects();

        console.log('[Projects] Initialization complete');
    }

    // Event Listeners
    function setupEventListeners() {
        // Переключение табов
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        // Открытие модального окна создания проекта
        if (createProjectCard) {
            createProjectCard.addEventListener('click', openCreateModal);
        }

        // Закрытие модального окна
        if (modalOverlay) {
            modalOverlay.addEventListener('click', closeCreateModal);
        }
        if (modalClose) {
            modalClose.addEventListener('click', closeCreateModal);
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeCreateModal);
        }

        // Отправка формы создания проекта
        if (submitProjectBtn) {
            submitProjectBtn.addEventListener('click', createProject);
        }

        // ESC для закрытия модалки
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && createProjectModal.style.display === 'block') {
                closeCreateModal();
            }
        });

        // Закрытие меню действий при клике вне его
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.project-actions-btn') && !e.target.closest('.dropdown-menu')) {
                closeActionsMenu();
            }
        });
    }

    // Переключение табов
    function switchTab(tabName) {
        // Деактивируем все табы
        tabBtns.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));

        // Активируем нужный таб
        const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
        const activeContent = document.getElementById(`${tabName}-content`);

        if (activeBtn && activeContent) {
            activeBtn.classList.add('active');
            activeContent.classList.add('active');
        }
    }

    // Загрузка проектов
    async function loadProjects() {
        if (!projectsList) return;

        // Показываем индикатор загрузки
        projectsList.innerHTML = `
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Загрузка проектов...</p>
            </div>
        `;

        try {
            // Загружаем проекты с сервера
            const response = await fetch('/api/projects.php', {
                credentials: 'same-origin'  // Включаем отправку cookies
            });
            const result = await response.json();

            if (result.success) {
                const projects = result.data;
                console.log('Projects loaded:', projects.length);
                renderProjects(projects);
            } else {
                throw new Error(result.error || 'Ошибка загрузки проектов');
            }
        } catch (error) {
            console.error('Error loading projects:', error);
            projectsList.innerHTML = `
                <div class="loading-state">
                    <p style="color: var(--color-danger);">Ошибка загрузки проектов: ${error.message}</p>
                    <button class="btn btn-secondary" onclick="location.reload()">Обновить страницу</button>
                </div>
            `;
        }
    }

    // Рендер списка проектов
    function renderProjects(projects) {
        if (!projectsList) return;

        if (projects.length === 0) {
            projectsList.innerHTML = `
                <div class="loading-state">
                    <p>Проектов пока нет. Создайте первый проект!</p>
                </div>
            `;
            return;
        }

        projectsList.innerHTML = projects.map(project => renderProjectCard(project)).join('');

        // Добавляем обработчики на карточки
        attachProjectCardListeners();
    }

    // Создание HTML карточки проекта
    function renderProjectCard(project) {
        // Адаптация под API данные
        const checklistsCount = project.checklists_count || 0;
        const integrationsCount = project.integrations_count || 0;
        const callsCount = project.calls_count || 0;
        const aiConfigured = project.ai_configured || false;

        // Заглушка для чеклистов (если нужно детальное отображение - загружать отдельно)
        const checklistsHTML = checklistsCount > 0 ? `
            <div class="checklist-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Чеклистов: ${checklistsCount}</span>
            </div>
        ` : '<div class="checklist-item" style="color: var(--text-muted);">Чеклисты не добавлены</div>';

        const statusClass = integrationsCount > 0 ? 'success' : 'warning';
        const datasourceLabel = integrationsCount > 0 ? 'Настроен' : 'Не настроен';
        const datasourceName = integrationsCount > 0 ? `Интеграций: ${integrationsCount}` : 'Нет интеграций';

        return `
            <div class="project-card" data-project-id="${project.id}">
                <div class="project-card-header">
                    <h2 class="project-card-title">${project.name}</h2>
                    <button class="project-actions-btn" data-project-id="${project.id}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="12" r="1.5"></circle>
                            <circle cx="12" cy="6" r="1.5"></circle>
                            <circle cx="12" cy="18" r="1.5"></circle>
                        </svg>
                    </button>
                </div>

                <div class="project-card-tabs">
                    <button class="project-card-tab active" data-project-tab="ai">Оценка ИИ</button>
                    <button class="project-card-tab" data-project-tab="calls">Звонки</button>
                </div>

                <div class="project-stats">
                    <div class="project-stats-item">
                        <span class="project-stats-value">${callsCount}</span>
                        <span>звонков</span>
                    </div>
                    <div class="project-stats-item">
                        <span class="project-stats-value">${integrationsCount}</span>
                        <span>интеграций</span>
                    </div>
                </div>

                <div class="project-checklists">
                    <div class="checklists-header">
                        <div class="checklists-title">Чек-листы</div>
                        <button class="edit-checklists-btn" data-project-id="${project.id}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="checklist-items">
                        ${checklistsHTML}
                    </div>
                </div>

                <div class="project-datasource">
                    <div class="datasource-info">
                        <span>Источник:</span>
                        <span class="datasource-badge ${statusClass}">${datasourceLabel}</span>
                        <strong>${datasourceName}</strong>
                    </div>
                    <button class="datasource-settings-btn" data-project-id="${project.id}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6m0 6v6m5.2-15.8l-4.2 4.2m0 6l4.2 4.2M23 12h-6m-6 0H1m15.8 5.2l-4.2-4.2m0-6l-4.2-4.2"></path>
                        </svg>
                    </button>
                </div>

                <div class="project-ai-section">
                    <div class="ai-section-header">ИИ оценка</div>
                    ${aiConfigured ? `
                        <div style="color: var(--success-color); font-size: 14px;">✓ Настроено (${project.llm_provider || 'N/A'})</div>
                    ` : `
                        <div class="ai-not-configured">
                            <span class="ai-status-badge">Не настроено</span>
                            <a href="#" class="ai-configure-link" data-project-id="${project.id}">Настроить</a>
                        </div>
                    `}
                </div>
            </div>
        `;
    }

    // Добавление обработчиков на карточки проектов
    function attachProjectCardListeners() {
        // Кнопки меню действий
        document.querySelectorAll('.project-actions-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleActionsMenu(btn, btn.dataset.projectId);
            });
        });

        // Табы внутри карточек
        document.querySelectorAll('.project-card-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const card = e.target.closest('.project-card');
                const tabs = card.querySelectorAll('.project-card-tab');
                tabs.forEach(t => t.classList.remove('active'));
                e.target.classList.add('active');
            });
        });

        // Кнопки редактирования чек-листов
        document.querySelectorAll('.edit-checklists-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                console.log('Edit checklists for project:', btn.dataset.projectId);
                // TODO: Открыть модалку редактирования чек-листов
            });
        });

        // Кнопки настроек источника данных
        document.querySelectorAll('.datasource-settings-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                console.log('Configure datasource for project:', btn.dataset.projectId);
                // TODO: Открыть модалку настроек источника
            });
        });

        // Ссылки настройки ИИ
        document.querySelectorAll('.ai-configure-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Configure AI for project:', link.dataset.projectId);
                // TODO: Открыть модалку настроек ИИ
            });
        });
    }

    // Открытие/закрытие меню действий
    function toggleActionsMenu(button, projectId) {
        const rect = button.getBoundingClientRect();

        projectActionsMenu.style.display = 'block';
        projectActionsMenu.style.top = `${rect.bottom + 4}px`;
        projectActionsMenu.style.left = `${rect.right - 180}px`;
        projectActionsMenu.dataset.projectId = projectId;

        // Обработчики действий
        projectActionsMenu.querySelectorAll('.dropdown-item').forEach(item => {
            item.onclick = (e) => {
                e.stopPropagation();
                handleProjectAction(item.dataset.action, projectId);
                closeActionsMenu();
            };
        });
    }

    function closeActionsMenu() {
        if (projectActionsMenu) {
            projectActionsMenu.style.display = 'none';
        }
    }

    // Обработка действий над проектом
    function handleProjectAction(action, projectId) {
        console.log(`Action: ${action}, Project ID: ${projectId}`);

        switch(action) {
            case 'edit':
                // TODO: Открыть модалку редактирования
                alert(`Редактирование проекта ${projectId}`);
                break;
            case 'duplicate':
                // TODO: Дублировать проект
                alert(`Дублирование проекта ${projectId}`);
                break;
            case 'archive':
                // TODO: Архивировать проект
                if (confirm('Архивировать проект?')) {
                    console.log(`Archiving project ${projectId}`);
                }
                break;
            case 'delete':
                // TODO: Удалить проект
                if (confirm('Удалить проект? Это действие необратимо.')) {
                    console.log(`Deleting project ${projectId}`);
                }
                break;
        }
    }

    // Модальное окно создания проекта
    function openCreateModal() {
        createProjectModal.style.display = 'block';
        document.getElementById('project-name').focus();
    }

    function closeCreateModal() {
        createProjectModal.style.display = 'none';
        document.getElementById('create-project-form').reset();
    }

    // Создание проекта
    async function createProject() {
        const projectName = document.getElementById('project-name').value.trim();
        const description = document.getElementById('project-description').value.trim();

        if (!projectName) {
            alert('Введите название проекта');
            return;
        }

        // Собираем выбранные чек-листы
        const checklists = [];
        document.querySelectorAll('input[name="checklists[]"]:checked').forEach(checkbox => {
            checklists.push(parseInt(checkbox.value));
        });

        console.log('Creating project:', { projectName, description, checklists });

        // Блокируем кнопку отправки
        if (submitProjectBtn) {
            submitProjectBtn.disabled = true;
            submitProjectBtn.textContent = 'Создание...';
        }

        try {
            console.log('Sending POST request...');
            // Отправляем запрос на сервер
            const response = await fetch('/api/projects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',  // Включаем отправку cookies
                body: JSON.stringify({
                    name: projectName,
                    description: description,
                    checklists: checklists
                })
            });

            const result = await response.json();
            console.log('API response:', result);

            if (result.success) {
                // Проект создан успешно
                console.log('Project created:', result.data);

                // Закрываем модалку
                closeCreateModal();

                // Показываем уведомление
                showNotification('Проект успешно создан!', 'success');

                // Перезагружаем список проектов
                await loadProjects();
            } else {
                // Ошибка при создании
                alert(result.error || 'Ошибка при создании проекта');
            }
        } catch (error) {
            console.error('Error creating project:', error);
            console.error('Error stack:', error.stack);
            alert('Ошибка при создании проекта: ' + error.message);
        } finally {
            // Разблокируем кнопку
            if (submitProjectBtn) {
                submitProjectBtn.disabled = false;
                submitProjectBtn.textContent = 'Создать проект';
            }
        }
    }

    // Простое уведомление (можно заменить на toast)
    function showNotification(message, type = 'info') {
        alert(message);
        // TODO: Заменить на нормальный toast notification
    }

    // Запуск при загрузке страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Экспортируем функции в window для отладки и тестирования
    window._projects = {
        openCreateModal: openCreateModal,
        closeCreateModal: closeCreateModal,
        createProject: createProject,
        loadProjects: loadProjects
    };
})();
