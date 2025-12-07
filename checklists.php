<?php
session_start();
require_once 'auth/session.php';
checkAuth(); // Проверка авторизации
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Шаблоны анализа - Система оценки звонков</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher Button -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <!-- Левая боковая панель -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Заголовок страницы -->
        <header class="page-header">
            <h1>Шаблоны анализа звонков</h1>
            <button class="btn-primary" onclick="openCreateTemplateModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Создать шаблон
            </button>
        </header>

        <!-- Контент -->
        <div class="checklists-container">
            <div class="info-card">
                <h2>Универсальные шаблоны анализа</h2>
                <p>Создавайте кастомные наборы вопросов для анализа звонков. LLM отвечает ДА/НЕТ на каждый вопрос.</p>
                <p>Шаблоны применяются автоматически к звонкам вашей организации. Используйте переключатель для активации/деактивации.</p>
            </div>

            <!-- Loading состояние -->
            <div id="loading-state" style="text-align: center; padding: 40px;">
                <p>Загрузка шаблонов...</p>
            </div>

            <!-- Grid с карточками шаблонов -->
            <div class="checklists-grid" id="templates-grid" style="display: none;">
                <!-- Карточки будут загружены через JavaScript -->
            </div>
        </div>
    </div>

    <!-- Модальное окно просмотра шаблона -->
    <div id="template-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Шаблон</h2>
                <button class="modal-close" onclick="closeTemplateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modal-template-info"></div>
                <div id="modal-questions-list"></div>
            </div>
            <div class="modal-footer">
                <button onclick="closeTemplateModal()" class="btn-secondary">Закрыть</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно создания шаблона -->
    <div id="create-template-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Создать новый шаблон</h2>
                <button class="modal-close" onclick="closeCreateTemplateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="create-template-form" onsubmit="createTemplate(event)">
                    <div class="form-group">
                        <label for="template-name">Название шаблона *</label>
                        <input type="text" id="template-name" required placeholder="Например: Стандартный анализ продаж">
                    </div>
                    <div class="form-group">
                        <label for="template-description">Описание</label>
                        <textarea id="template-description" rows="3" placeholder="Краткое описание для чего используется"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="template-system-prompt">Системный промпт (опционально)</label>
                        <textarea id="template-system-prompt" rows="4" placeholder="Инструкции для LLM по анализу..."></textarea>
                    </div>

                    <!-- Редактируемые вопросы -->
                    <div class="form-group">
                        <label>Вопросы чеклиста</label>
                        <div id="editable-questions-container" style="margin-top: 12px;">
                            <p style="color: var(--text-muted); font-size: 13px;">
                                После создания шаблона вы сможете добавить вопросы для анализа.
                            </p>
                        </div>
                        <button type="button" onclick="addNewQuestion()" class="btn-secondary" style="margin-top: 12px; width: 100%;">
                            + Добавить вопрос
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeCreateTemplateModal()" class="btn-secondary">Отмена</button>
                <button form="create-template-form" type="submit" class="btn-primary" id="submit-template-btn">Создать</button>
            </div>
        </div>
    </div>

    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>

    <style>
        .checklists-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-primary {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .info-card {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .info-card h2 {
            margin: 0 0 12px 0;
            font-size: 20px;
            color: var(--text-color);
        }

        .info-card p {
            margin: 8px 0;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .checklists-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .checklist-card {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
            position: relative;
        }

        .checklist-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .card-actions {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            gap: 8px;
        }

        .icon-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--background-color);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .icon-btn svg {
            pointer-events: none;
        }

        .icon-btn:hover {
            background: var(--border-color);
        }

        .icon-btn-danger {
            color: #ff3b30;
        }

        .icon-btn-danger:hover {
            background: #ff3b30;
            color: white;
        }

        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
            background: #ccc;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .toggle-switch.active {
            background: var(--primary-color);
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: left 0.3s;
        }

        .toggle-switch.active::after {
            left: 22px;
        }

        .checklist-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .checklist-card-content {
            cursor: pointer;
        }

        .checklist-card h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: var(--text-color);
            padding-right: 80px;
        }

        .checklist-card p {
            margin: 0 0 16px 0;
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .checklist-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background-color: rgba(52, 199, 89, 0.1);
            color: #34c759;
        }

        .badge-warning {
            background-color: rgba(255, 149, 0, 0.1);
            color: #ff9500;
        }

        .badge-inactive {
            background-color: rgba(142, 142, 147, 0.1);
            color: #8e8e93;
        }

        /* Модальное окно */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: var(--surface-color);
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text-muted);
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--text-color);
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-secondary {
            padding: 8px 16px;
            background: var(--border-color);
            color: var(--text-color);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-secondary:hover {
            opacity: 0.8;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            background: var(--background-color);
            color: var(--text-color);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .question-item {
            padding: 12px;
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .question-item h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: var(--text-color);
        }

        .question-item p {
            margin: 0;
            font-size: 13px;
            color: var(--text-muted);
        }

        [data-theme="dark"] .info-card,
        [data-theme="dark"] .checklist-card,
        [data-theme="dark"] .modal-content {
            background-color: #2c2c2e;
        }
    </style>

    <script>
        // API Base URL
        const API_BASE = 'http://localhost:8001';

        // Загрузка шаблонов при загрузке страницы
        document.addEventListener('DOMContentLoaded', async () => {
            await loadTemplates();
        });

        // Загрузка шаблонов из API
        async function loadTemplates() {
            console.log('loadTemplates: Starting...');
            const loadingState = document.getElementById('loading-state');
            const templatesGrid = document.getElementById('templates-grid');

            try {
                // Показываем состояние загрузки
                loadingState.style.display = 'block';
                templatesGrid.style.display = 'none';

                const response = await fetch(`${API_BASE}/api/templates/test-list`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                const templates = result.data || [];

                console.log('loadTemplates: Loaded', templates.length, 'templates');

                // Полностью очищаем grid
                while (templatesGrid.firstChild) {
                    templatesGrid.removeChild(templatesGrid.firstChild);
                }

                loadingState.style.display = 'none';
                templatesGrid.style.display = 'grid';

                if (templates.length === 0) {
                    const emptyMsg = document.createElement('p');
                    emptyMsg.style.cssText = 'grid-column: 1/-1; text-align: center; color: var(--text-muted);';
                    emptyMsg.textContent = 'Нет шаблонов. Создайте первый шаблон!';
                    templatesGrid.appendChild(emptyMsg);
                    return;
                }

                templates.forEach((template, index) => {
                    console.log(`loadTemplates: Creating card ${index + 1}/${templates.length} for`, template.template_id);
                    const card = createTemplateCard(template);
                    templatesGrid.appendChild(card);
                });

                console.log('loadTemplates: Completed successfully');

            } catch (error) {
                console.error('Ошибка загрузки шаблонов:', error);
                loadingState.innerHTML = `<p style="color: red;">Ошибка загрузки: ${error.message}</p>`;
                loadingState.style.display = 'block';
                templatesGrid.style.display = 'none';
            }
        }

        // Создание карточки шаблона
        function createTemplateCard(template) {
            const card = document.createElement('div');
            card.className = 'checklist-card';

            // Для системных шаблонов показываем иконку замка вместо кнопки удаления
            const deleteButtonHTML = template.is_system
                ? `<div class="icon-btn system-lock" title="Системный шаблон (неудаляемый)" style="opacity: 0.5; cursor: not-allowed;">
                       <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                           <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                       </svg>
                   </div>`
                : `<button class="icon-btn icon-btn-danger delete-btn"
                           data-template-id="${template.template_id}"
                           title="Удалить">
                       <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                           <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                           <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                       </svg>
                   </button>`;

            card.innerHTML = `
                <div class="card-actions">
                    <div class="toggle-switch ${template.is_active ? 'active' : ''}"
                         data-template-id="${template.template_id}"
                         data-is-active="${template.is_active}"
                         title="${template.is_active ? 'Деактивировать' : 'Активировать'}">
                    </div>
                    <button class="icon-btn edit-btn"
                            data-template-id="${template.template_id}"
                            title="Редактировать">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                        </svg>
                    </button>
                    ${deleteButtonHTML}
                </div>
                <div class="checklist-card-content" data-template-id="${template.template_id}">
                    <div class="checklist-icon">${template.is_system ? '🔒' : '📋'}</div>
                    <h3>${escapeHtml(template.name)}</h3>
                    <p>${escapeHtml(template.description || 'Универсальный шаблон анализа')}</p>
                    <div class="checklist-meta">
                        <span class="badge badge-success">${template.questions_count} вопросов</span>
                        ${!template.is_active ? '<span class="badge badge-inactive">Неактивен</span>' : ''}
                        ${template.is_system ? '<span class="badge badge-info">Системный</span>' : ''}
                    </div>
                </div>
            `;

            // Добавляем event listeners напрямую
            const toggleSwitch = card.querySelector('.toggle-switch');
            const editBtn = card.querySelector('.edit-btn');
            const deleteBtn = card.querySelector('.delete-btn');
            const cardContent = card.querySelector('.checklist-card-content');

            toggleSwitch.addEventListener('click', (e) => {
                toggleTemplate(e, template.template_id, template.is_active);
            });

            editBtn.addEventListener('click', (e) => {
                openEditTemplateModal(e, template.template_id);
            });

            // Добавляем listener для кнопки удаления только если она есть (не системный шаблон)
            if (deleteBtn) {
                deleteBtn.addEventListener('click', (e) => {
                    console.log('Delete button clicked for:', template.template_id);
                    window.deleteTemplate(e, template.template_id);
                });
            }

            cardContent.addEventListener('click', () => {
                openTemplate(template.template_id);
            });

            return card;
        }

        // Переключение активности шаблона
        async function toggleTemplate(event, templateId, currentState) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }

            console.log('toggleTemplate called for:', templateId);

            // Блокируем повторные клики
            const toggle = event?.target?.closest('.toggle-switch');
            if (toggle) {
                toggle.style.pointerEvents = 'none';
                toggle.style.opacity = '0.5';
            }

            try {
                const response = await fetch(`${API_BASE}/api/templates/test-toggle/${templateId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                console.log('Template toggled:', result);

                // Небольшая задержка перед перезагрузкой
                await new Promise(resolve => setTimeout(resolve, 100));

                // Перезагружаем шаблоны
                await loadTemplates();

                console.log('Templates reloaded after toggle');
            } catch (error) {
                console.error('Toggle template error:', error);
                alert('Ошибка изменения статуса: ' + error.message);

                // Разблокируем toggle при ошибке
                if (toggle) {
                    toggle.style.pointerEvents = 'auto';
                    toggle.style.opacity = '1';
                }
            }
        }

        // Удаление шаблона
        let deletingInProgress = false;

        window.deleteTemplate = async function(event, templateId) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }

            console.log('deleteTemplate called with:', templateId);
            console.log('Event:', event);
            console.log('Event type:', event?.type);
            console.log('Event target:', event?.target);
            console.log('Deleting in progress:', deletingInProgress);

            // Защита от множественных вызовов
            if (deletingInProgress) {
                console.log('Deletion already in progress, ignoring...');
                return false;
            }

            const confirmResult = confirm('Удалить этот шаблон? Это действие нельзя отменить.');
            console.log('Confirm result:', confirmResult);

            if (!confirmResult) {
                console.log('User cancelled deletion');
                return false;
            }

            console.log('User confirmed deletion, proceeding...');

            deletingInProgress = true;

            try {
                console.log('Deleting template:', templateId);

                const response = await fetch(`${API_BASE}/api/templates/test-delete/${templateId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                console.log('Delete response status:', response.status);

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Delete error:', errorText);
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }

                const result = await response.json();
                console.log('Template deleted successfully:', result);

                // Перезагружаем шаблоны
                await loadTemplates();

                console.log('Templates reloaded after deletion');

                deletingInProgress = false;
                return true;
            } catch (error) {
                console.error('Delete template error:', error);
                alert('Ошибка удаления: ' + error.message);

                deletingInProgress = false;
                return false;
            }
        }

        // Открыть модальное окно создания
        function openCreateTemplateModal() {
            document.getElementById('create-template-modal').style.display = 'flex';
            document.getElementById('create-template-form').reset();
        }

        // Закрыть модальное окно создания
        function closeCreateTemplateModal() {
            document.getElementById('create-template-modal').style.display = 'none';

            // Сбросить состояние редактирования
            editingTemplateId = null;
            editingQuestions = [];

            // Очистить контейнер вопросов
            document.getElementById('editable-questions-container').innerHTML = `
                <p style="color: var(--text-muted); font-size: 13px;">
                    После создания шаблона вы сможете добавить вопросы для анализа.
                </p>
            `;

            // Восстановить заголовок и кнопку
            document.querySelector('#create-template-modal .modal-header h2').textContent = 'Создать новый шаблон';
            const submitBtn = document.querySelector('#create-template-modal .modal-footer .btn-primary');
            submitBtn.textContent = 'Создать';
            submitBtn.onclick = null; // Используем onsubmit формы
        }

        // Создать или обновить шаблон
        async function createTemplate(event) {
            event.preventDefault();

            // Проверяем режим - создание или редактирование
            if (editingTemplateId) {
                // Режим редактирования
                await updateTemplate();
                return;
            }

            // Режим создания
            const name = document.getElementById('template-name').value;
            const description = document.getElementById('template-description').value;
            const systemPrompt = document.getElementById('template-system-prompt').value;

            if (!name) {
                alert('Название шаблона обязательно');
                return;
            }

            // Валидация вопросов
            const invalidQuestions = editingQuestions.filter(q => !q.question_text || !q.question_code);
            if (invalidQuestions.length > 0) {
                alert('Все вопросы должны иметь текст и код вопроса');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/api/templates/test-create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: name,
                        description: description || null,
                        template_type: 'custom',
                        system_prompt: systemPrompt || null,
                        is_default: false,
                        questions: editingQuestions.map((q, index) => ({
                            question_order: index + 1,
                            question_text: q.question_text,
                            question_code: q.question_code,
                            hint_text: q.hint_text || null,
                            answer_type: q.answer_type || 'yes_no',
                            scoring_weight: q.scoring_weight || 1.0,
                            is_required: q.is_required !== undefined ? q.is_required : true
                        }))
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                console.log('Template created:', result);

                closeCreateTemplateModal();
                await loadTemplates();
            } catch (error) {
                alert('Ошибка создания: ' + error.message);
            }
        }

        // Открыть модальное окно просмотра
        async function openTemplate(templateId) {
            const modal = document.getElementById('template-modal');
            const modalTitle = document.getElementById('modal-title');
            const modalInfo = document.getElementById('modal-template-info');
            const modalQuestions = document.getElementById('modal-questions-list');

            modal.style.display = 'flex';

            try {
                const response = await fetch(`get_template.php?id=${templateId}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const template = await response.json();

                modalTitle.textContent = template.name;

                modalInfo.innerHTML = `
                    <p><strong>Описание:</strong> ${escapeHtml(template.description || 'Нет описания')}</p>
                    <p><strong>Вопросов:</strong> ${template.questions ? template.questions.length : 0}</p>
                    <p><strong>Статус:</strong> ${template.is_active ? '✅ Активен' : '⏸️ Неактивен'}</p>
                `;

                if (template.questions && template.questions.length > 0) {
                    modalQuestions.innerHTML = '<h3 style="margin-top: 20px;">Вопросы:</h3>';
                    template.questions.forEach((q) => {
                        const questionDiv = document.createElement('div');
                        questionDiv.className = 'question-item';
                        questionDiv.innerHTML = `
                            <h4>Q${q.question_order}: ${escapeHtml(q.question_text)}</h4>
                            ${q.hint_text ? `<p><em>${escapeHtml(q.hint_text)}</em></p>` : ''}
                        `;
                        modalQuestions.appendChild(questionDiv);
                    });
                } else {
                    modalQuestions.innerHTML = '<p style="color: var(--text-muted); margin-top: 20px;">Нет вопросов. Добавьте через API.</p>';
                }

            } catch (error) {
                console.error('Ошибка загрузки шаблона:', error);
                modalInfo.innerHTML = `<p style="color: red;">Ошибка: ${error.message}</p>`;
                modalQuestions.innerHTML = '';
            }
        }

        // Закрыть модальное окно просмотра
        function closeTemplateModal() {
            document.getElementById('template-modal').style.display = 'none';
        }

        // Открыть модальное окно редактирования шаблона
        let editingTemplateId = null;
        let editingQuestions = [];

        async function openEditTemplateModal(event, templateId) {
            event?.stopPropagation();
            editingTemplateId = templateId;

            try {
                // Загрузить данные шаблона
                const response = await fetch(`${API_BASE}/api/templates/test-get/${templateId}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                const template = result.data;

                // Сохранить вопросы для редактирования
                editingQuestions = template.questions || [];

                // Заполнить форму
                document.getElementById('template-name').value = template.name;
                document.getElementById('template-description').value = template.description || '';
                document.getElementById('template-system-prompt').value = template.system_prompt || '';

                // Отобразить вопросы для редактирования
                renderEditableQuestions();

                // Открыть модальное окно
                document.getElementById('create-template-modal').style.display = 'flex';

                // Изменить заголовок и кнопку
                document.querySelector('#create-template-modal .modal-header h2').textContent = 'Редактировать шаблон';
                const submitBtn = document.querySelector('#submit-template-btn');
                submitBtn.textContent = 'Сохранить';
                // Не нужно onclick - форма вызовет createTemplate который проверит editingTemplateId

                console.log('Loaded template for editing:', template);
            } catch (error) {
                console.error('Error loading template:', error);
                alert('Ошибка загрузки шаблона: ' + error.message);
            }
        }

        // Отобразить редактируемые вопросы
        function renderEditableQuestions() {
            const container = document.getElementById('editable-questions-container');
            container.innerHTML = '';

            if (editingQuestions.length === 0) {
                container.innerHTML = '<p style="color: var(--text-muted);">Нет вопросов. Добавьте новый вопрос ниже.</p>';
                return;
            }

            editingQuestions.forEach((question, index) => {
                const questionDiv = document.createElement('div');
                questionDiv.className = 'question-item-editable';
                questionDiv.style.cssText = 'margin-bottom: 16px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--background-color);';

                questionDiv.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <strong>Вопрос ${index + 1}</strong>
                        <button type="button" class="icon-btn" onclick="removeQuestion(${index})" title="Удалить вопрос">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                            </svg>
                        </button>
                    </div>
                    <div style="margin-bottom: 8px;">
                        <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-muted);">Текст вопроса:</label>
                        <input type="text" class="question-text-input" data-index="${index}" value="${escapeHtml(question.question_text)}"
                               style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--background-color); color: var(--text-color);" />
                    </div>
                    <div style="margin-bottom: 8px;">
                        <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-muted);">Код вопроса (question_code):</label>
                        <input type="text" class="question-code-input" data-index="${index}" value="${escapeHtml(question.question_code || '')}"
                               style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--background-color); color: var(--text-color);" />
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-size: 12px; color: var(--text-muted);">Подсказка (опционально):</label>
                        <input type="text" class="question-hint-input" data-index="${index}" value="${escapeHtml(question.hint_text || '')}"
                               style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--background-color); color: var(--text-color);" />
                    </div>
                `;

                container.appendChild(questionDiv);
            });

            // Добавляем обработчики изменений
            container.querySelectorAll('.question-text-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    editingQuestions[index].question_text = e.target.value;
                });
            });

            container.querySelectorAll('.question-code-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    editingQuestions[index].question_code = e.target.value;
                });
            });

            container.querySelectorAll('.question-hint-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    editingQuestions[index].hint_text = e.target.value;
                });
            });
        }

        // Добавить новый вопрос
        function addNewQuestion() {
            const newQuestion = {
                question_order: editingQuestions.length + 1,
                question_text: '',
                question_code: '',
                hint_text: '',
                answer_type: 'yes_no',
                scoring_weight: 1.0,
                is_required: true
            };

            editingQuestions.push(newQuestion);
            renderEditableQuestions();
        }

        // Удалить вопрос
        function removeQuestion(index) {
            if (confirm('Удалить этот вопрос?')) {
                editingQuestions.splice(index, 1);
                // Пересчитать порядковые номера
                editingQuestions.forEach((q, i) => {
                    q.question_order = i + 1;
                });
                renderEditableQuestions();
            }
        }

        // Обновить шаблон
        async function updateTemplate() {
            const name = document.getElementById('template-name').value;
            const description = document.getElementById('template-description').value;
            const systemPrompt = document.getElementById('template-system-prompt').value;

            if (!name) {
                alert('Название шаблона обязательно');
                return;
            }

            // Валидация вопросов
            const invalidQuestions = editingQuestions.filter(q => !q.question_text || !q.question_code);
            if (invalidQuestions.length > 0) {
                alert('Все вопросы должны иметь текст и код вопроса');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/api/templates/test-update/${editingTemplateId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: name,
                        description: description || null,
                        template_type: 'custom',
                        system_prompt: systemPrompt || null,
                        is_default: false,
                        questions: editingQuestions.map((q, index) => ({
                            question_order: index + 1,
                            question_text: q.question_text,
                            question_code: q.question_code,
                            hint_text: q.hint_text || null,
                            answer_type: q.answer_type || 'yes_no',
                            scoring_weight: q.scoring_weight || 1.0,
                            is_required: q.is_required !== undefined ? q.is_required : true
                        }))
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                console.log('Template updated:', result);

                closeCreateTemplateModal();
                await loadTemplates();

                alert('Шаблон обновлен!');
                editingTemplateId = null;
                editingQuestions = [];
            } catch (error) {
                alert('Ошибка обновления: ' + error.message);
            }
        }

        // Экранирование HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Закрытие модальных окон по клику вне
        document.getElementById('template-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'template-modal') closeTemplateModal();
        });

        document.getElementById('create-template-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'create-template-modal') closeCreateTemplateModal();
        });
    </script>
</body>
</html>
