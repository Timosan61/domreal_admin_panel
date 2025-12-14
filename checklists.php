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
                <svg class="svg-icon-mr" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
            <div id="loading-state" class="checklists-loading-state">
                <p>Загрузка шаблонов...</p>
            </div>

            <!-- Grid с карточками шаблонов -->
            <div class="checklists-grid d-none" id="templates-grid">
                <!-- Карточки будут загружены через JavaScript -->
            </div>
        </div>
    </div>

    <!-- Модальное окно просмотра шаблона -->
    <div id="template-modal" class="modal d-none">
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
    <div id="create-template-modal" class="modal d-none">
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
                        <div id="editable-questions-container" class="mt-3">
                            <p class="editable-questions-empty">
                                После создания шаблона вы сможете добавить вопросы для анализа.
                            </p>
                        </div>
                        <button type="button" onclick="addNewQuestion()" class="btn-secondary btn-add-question">
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

    <script>
        // API Base URL - используем PHP API
        const API_BASE = 'api/templates.php';

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
                loadingState.classList.remove('d-none');
                templatesGrid.classList.add('d-none');

                const response = await fetch(`${API_BASE}?action=list`);

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

                loadingState.classList.add('d-none');
                templatesGrid.classList.remove('d-none');

                if (templates.length === 0) {
                    const emptyMsg = document.createElement('p');
                    emptyMsg.className = 'checklists-empty-msg';
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
                loadingState.innerHTML = `<p class="checklists-error">Ошибка загрузки: ${error.message}</p>`;
                loadingState.classList.remove('d-none');
                templatesGrid.classList.add('d-none');
            }
        }

        // Создание карточки шаблона
        function createTemplateCard(template) {
            const card = document.createElement('div');
            card.className = 'checklist-card';

            // Для системных шаблонов показываем иконку замка вместо кнопки удаления
            const deleteButtonHTML = template.is_system
                ? `<div class="icon-btn system-lock" title="Системный шаблон (неудаляемый)">
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
                const response = await fetch(`${API_BASE}?action=toggle&id=${templateId}`, {
                    method: 'POST',
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

                const response = await fetch(`${API_BASE}?action=delete&id=${templateId}`, {
                    method: 'POST',
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
            const modal = document.getElementById('create-template-modal');
            modal.classList.remove('d-none');
            modal.classList.add('d-flex');
            document.getElementById('create-template-form').reset();
        }

        // Закрыть модальное окно создания
        function closeCreateTemplateModal() {
            const modal = document.getElementById('create-template-modal');
            modal.classList.add('d-none');
            modal.classList.remove('d-flex');

            // Сбросить состояние редактирования
            editingTemplateId = null;
            editingQuestions = [];

            // Очистить контейнер вопросов
            document.getElementById('editable-questions-container').innerHTML = `
                <p class="editable-questions-empty">
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
                const response = await fetch(`${API_BASE}?action=create`, {
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

            modal.classList.remove('d-none');
            modal.classList.add('d-flex');

            try {
                const response = await fetch(`${API_BASE}?action=get&id=${templateId}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                const template = result.data;

                modalTitle.textContent = template.name;

                modalInfo.innerHTML = `
                    <p><strong>Описание:</strong> ${escapeHtml(template.description || 'Нет описания')}</p>
                    <p><strong>Вопросов:</strong> ${template.questions ? template.questions.length : 0}</p>
                    <p><strong>Статус:</strong> ${template.is_active ? '✅ Активен' : '⏸️ Неактивен'}</p>
                `;

                if (template.questions && template.questions.length > 0) {
                    modalQuestions.innerHTML = '<h3 class="questions-list-header">Вопросы:</h3>';
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
                    modalQuestions.innerHTML = '<p class="questions-empty-msg">Нет вопросов. Добавьте через редактирование.</p>';
                }

            } catch (error) {
                console.error('Ошибка загрузки шаблона:', error);
                modalInfo.innerHTML = `<p class="checklists-error">Ошибка: ${error.message}</p>`;
                modalQuestions.innerHTML = '';
            }
        }

        // Закрыть модальное окно просмотра
        function closeTemplateModal() {
            const modal = document.getElementById('template-modal');
            modal.classList.add('d-none');
            modal.classList.remove('d-flex');
        }

        // Открыть модальное окно редактирования шаблона
        let editingTemplateId = null;
        let editingQuestions = [];

        async function openEditTemplateModal(event, templateId) {
            event?.stopPropagation();
            editingTemplateId = templateId;

            try {
                // Загрузить данные шаблона
                const response = await fetch(`${API_BASE}?action=get&id=${templateId}`);

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
                const modal = document.getElementById('create-template-modal');
                modal.classList.remove('d-none');
                modal.classList.add('d-flex');

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
                container.innerHTML = '<p class="editable-questions-empty">Нет вопросов. Добавьте новый вопрос ниже.</p>';
                return;
            }

            editingQuestions.forEach((question, index) => {
                const questionDiv = document.createElement('div');
                questionDiv.className = 'question-item-editable';

                questionDiv.innerHTML = `
                    <div class="question-item-editable-header">
                        <strong>Вопрос ${index + 1}</strong>
                        <button type="button" class="icon-btn" onclick="removeQuestion(${index})" title="Удалить вопрос">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="question-input-group">
                        <label class="question-input-label">Текст вопроса:</label>
                        <input type="text" class="question-text-input question-input-field" data-index="${index}" value="${escapeHtml(question.question_text)}" />
                    </div>
                    <div class="question-input-group">
                        <label class="question-input-label">Код вопроса (question_code):</label>
                        <input type="text" class="question-code-input question-input-field" data-index="${index}" value="${escapeHtml(question.question_code || '')}" />
                    </div>
                    <div>
                        <label class="question-input-label">Подсказка (опционально):</label>
                        <input type="text" class="question-hint-input question-input-field" data-index="${index}" value="${escapeHtml(question.hint_text || '')}" />
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
                const response = await fetch(`${API_BASE}?action=update&id=${editingTemplateId}`, {
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
