<?php
/**
 * Страница управления правилами применения шаблонов
 */
session_start();
require_once 'auth/session.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Правила шаблонов - Система оценки звонков</title>
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
            <h1>Правила применения шаблонов</h1>
            <div class="rules-header-actions">
                <button class="btn-primary" onclick="openCreateRuleModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="rules-svg-icon">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Создать правило
                </button>
                <button class="btn-secondary" onclick="showTemplateFields('tpl-deal-dynamics-v1', 'Динамика сделки (унифицированный)')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="rules-svg-icon">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m8.66-10l-5.2 3m-3.46 2l-5.2 3M3.34 7l5.2 3m3.46 2l5.2 3"></path>
                    </svg>
                    ⚙️ Настройки полей "Динамика сделки"
                </button>
                <button class="btn-secondary rules-btn-conflict" onclick="showTemplateAlertSettings('tpl-conflict-of-interest-v1', 'Конфликт интересов')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="rules-svg-icon">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    🚨 Настройки тревог "Конфликт интересов"
                </button>
            </div>
        </header>

        <!-- Контент -->
        <div class="checklists-container">
            <div class="info-card">
                <h2>Условное применение шаблонов</h2>
                <p>Настройте правила для автоматического выбора шаблона анализа на основе данных из CRM.</p>
                <ul class="rules-info-list">
                    <li>Создавайте условия с операторами: =, !=, >, <, содержит, не содержит</li>
                    <li>Комбинируйте условия через И/ИЛИ</li>
                    <li>Приоритет определяет порядок проверки правил (выше = важнее)</li>
                </ul>
            </div>

            <!-- Loading состояние -->
            <div id="loading-state" class="rules-loading-state">
                <p>Загрузка правил...</p>
            </div>

            <!-- Grid с карточками правил -->
            <div class="rules-grid d-none" id="rules-grid">
                <!-- Карточки будут загружены через JavaScript -->
            </div>

            <!-- Настройки полей для шаблона "Динамика сделки" -->
            <div class="template-fields-section d-none rules-fields-section" id="template-fields-section">
                <div class="info-card">
                    <h2>⚙️ Настройки полей для шаблона "<span id="template-fields-name">Динамика сделки</span>"</h2>
                    <p>Управление стандартными и кастомными полями CRM для анализа звонков</p>

                    <div class="rules-fields-actions">
                        <button class="btn-primary mr-2" onclick="showAddCustomFieldModal()">
                            ➕ Добавить кастомное поле
                        </button>
                        <button class="btn-secondary" onclick="hideTemplateFields()">
                            ← Вернуться к правилам
                        </button>
                    </div>

                    <table class="data-table rules-fields-table">
                        <thead>
                            <tr>
                                <th width="40"></th>
                                <th>Название поля</th>
                                <th width="100">Тип</th>
                                <th width="100">Категория</th>
                                <th width="120" title="Учитывать в контексте при анализе">
                                    <div class="rules-th-center">
                                        📋<br>Контекст
                                    </div>
                                </th>
                                <th width="120" title="Проверять правильность заполнения">
                                    <div class="rules-th-center">
                                        ✅<br>Валидация
                                    </div>
                                </th>
                                <th width="120" title="Заполнять автоматически если пусто">
                                    <div class="rules-th-center">
                                        🤖<br>Авто-заполнение
                                    </div>
                                </th>
                                <th width="100">Действия</th>
                            </tr>
                        </thead>
                        <tbody id="template-fields-tbody">
                            <!-- Поля загружаются через JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Настройки тревожных флагов для шаблона "Конфликт интересов" -->
            <div class="template-alert-settings-section d-none rules-fields-section" id="template-alert-settings-section">
                <div class="info-card">
                    <h2>🚨 Настройки тревожных флагов для шаблона "<span id="template-alert-name">Конфликт интересов</span>"</h2>
                    <p>Управление отправкой тревожных флагов в CRM и настройками уведомлений</p>

                    <div class="rules-fields-actions">
                        <button class="btn-secondary" onclick="hideTemplateAlertSettings()">
                            ← Вернуться к правилам
                        </button>
                    </div>

                    <form id="alert-settings-form" onsubmit="saveAlertSettings(event)" class="rules-alert-form">
                        <!-- Отправка в CRM -->
                        <div class="form-section rules-form-section">
                            <h3 class="rules-form-section-title">📤 Отправка в CRM</h3>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="alert-send-to-crm">
                                    <span>Отправлять тревожные флаги в CRM (скрытое поле для руководителей)</span>
                                </label>
                            </div>

                            <div class="form-group d-none" id="crm-field-group">
                                <label>Название поля в CRM *</label>
                                <input type="text" id="alert-crm-field" placeholder="Например: UF_CRM_ALERT">
                                <small class="rules-form-hint">
                                    Это поле должно существовать в вашей CRM. Обычно начинается с UF_ (Bitrix24) или специальный ID (AmoCRM)
                                </small>
                            </div>
                        </div>

                        <!-- Пороги тревоги -->
                        <div class="form-section rules-form-section">
                            <h3 class="rules-form-section-title">🎯 Пороги уровней тревоги</h3>
                            <p class="rules-form-description">
                                Количество обнаруженных признаков для каждого уровня тревоги
                            </p>

                            <div class="rules-threshold-grid">
                                <div class="form-group">
                                    <label>🟢 НИЗКИЙ</label>
                                    <input type="number" id="alert-low-threshold" min="1" max="10" value="1" required>
                                    <small>От этого числа флагов</small>
                                </div>

                                <div class="form-group">
                                    <label>🟡 СРЕДНИЙ</label>
                                    <input type="number" id="alert-medium-threshold" min="1" max="10" value="2" required>
                                    <small>От этого числа флагов</small>
                                </div>

                                <div class="form-group">
                                    <label>🟠 ВЫСОКИЙ</label>
                                    <input type="number" id="alert-high-threshold" min="1" max="10" value="4" required>
                                    <small>От этого числа флагов</small>
                                </div>

                                <div class="form-group">
                                    <label>🔴 КРИТИЧЕСКИЙ</label>
                                    <input type="number" id="alert-critical-threshold" min="1" max="10" value="6" required>
                                    <small>От этого числа флагов</small>
                                </div>
                            </div>
                        </div>

                        <!-- Автоматические уведомления -->
                        <div class="form-section rules-form-section">
                            <h3 class="rules-form-section-title">📧 Автоматические уведомления</h3>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="alert-notify-high" checked>
                                    <span>Отправлять уведомление при уровне 🟠 ВЫСОКИЙ</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="alert-notify-critical" checked>
                                    <span>Отправлять уведомление при уровне 🔴 КРИТИЧЕСКИЙ</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label>Email адреса для уведомлений</label>
                                <textarea id="alert-notification-emails" rows="3" placeholder="admin@company.ru&#10;supervisor@company.ru&#10;security@company.ru"></textarea>
                                <small class="rules-form-hint">
                                    Укажите email адреса по одному на строку. На эти адреса будут приходить уведомления о тревожных флагах.
                                </small>
                            </div>
                        </div>

                        <div class="form-actions rules-form-actions">
                            <button type="submit" class="btn-primary">💾 Сохранить настройки</button>
                            <button type="button" onclick="hideTemplateAlertSettings()" class="btn-secondary">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<!-- Modal: Создание правила -->
<div id="create-rule-modal" class="modal d-none">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Создать правило</h2>
            <button class="modal-close" onclick="closeCreateRuleModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="create-rule-form" onsubmit="createRule(event)">
                <div class="form-group">
                    <label>Название правила *</label>
                    <input type="text" id="rule-name" required placeholder="Например: Первый звонок VIP клиента">
                </div>

                <div class="form-group">
                    <label>Описание</label>
                    <textarea id="rule-description" rows="2" placeholder="Краткое описание правила"></textarea>
                </div>

                <div class="form-group">
                    <label>Шаблон для применения *</label>
                    <select id="rule-template" required>
                        <option value="">Выберите шаблон...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Приоритет (0-1000)</label>
                    <input type="number" id="rule-priority" value="100" min="0" max="1000">
                    <small class="rules-form-hint">Чем выше значение, тем выше приоритет правила</small>
                </div>

                <div class="form-section">
                    <h3>Условия</h3>
                    <div id="conditions-container">
                        <!-- Условия будут добавляться динамически -->
                    </div>
                    <button type="button" class="btn-secondary mt-3" onclick="addCondition()">
                        + Добавить условие
                    </button>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeCreateRuleModal()">Отмена</button>
            <button type="submit" class="btn-primary" onclick="document.getElementById('create-rule-form').requestSubmit()">Создать</button>
        </div>
    </div>
</div>

<!-- Modal: Просмотр правила -->
<div id="rule-modal" class="modal d-none">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="rule-modal-title">Правило</h2>
            <button class="modal-close" onclick="closeRuleModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="rule-modal-content">
                <!-- Контент загружается динамически -->
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeRuleModal()" class="btn-secondary">Закрыть</button>
        </div>
    </div>
</div>

<!-- Modal: Добавление кастомного поля -->
<div id="add-custom-field-modal" class="modal d-none">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Добавить кастомное поле CRM</h2>
            <button class="modal-close" onclick="closeAddCustomFieldModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="add-custom-field-form" onsubmit="saveCustomField(event)">
                <div class="form-group">
                    <label>Система CRM</label>
                    <select id="custom-field-crm-system" required>
                        <option value="bitrix24">Bitrix24</option>
                        <option value="amocrm">AmoCRM</option>
                        <option value="joywork">Joywork</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Код поля (из CRM)</label>
                    <input type="text" id="custom-field-code" placeholder="Например: UF_CRM_BUDGET" required>
                    <small>Технический код поля из CRM (например, UF_CRM_BUDGET для Bitrix24)</small>
                </div>

                <div class="form-group">
                    <label>Название поля</label>
                    <input type="text" id="custom-field-label" placeholder="Например: Бюджет клиента" required>
                </div>

                <div class="form-group">
                    <label>Тип поля</label>
                    <select id="custom-field-type" required>
                        <option value="string">Строка</option>
                        <option value="number">Число</option>
                        <option value="date">Дата</option>
                        <option value="boolean">Да/Нет</option>
                        <option value="select">Список</option>
                        <option value="text">Текст</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Эмодзи (опционально)</label>
                    <input type="text" id="custom-field-emoji" placeholder="💰" maxlength="10">
                </div>

                <div class="form-group">
                    <label>Подсказка</label>
                    <textarea id="custom-field-hint" rows="2" placeholder="Описание как использовать это поле в анализе"></textarea>
                </div>

                <div class="form-group">
                    <label>Порядок отображения</label>
                    <input type="number" id="custom-field-order" value="999" min="0">
                </div>

                <div class="form-section rules-custom-field-settings">
                    <h3>Настройки использования</h3>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="custom-field-include-context" checked>
                            📋 Учитывать в контексте при анализе
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="custom-field-validate">
                            ✅ Проверять правильность заполнения
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="custom-field-autofill">
                            🤖 Заполнять автоматически если пусто
                        </label>
                    </div>
                </div>

                <div class="modal-footer rules-modal-footer">
                    <button type="submit" class="btn-primary">Сохранить</button>
                    <button type="button" onclick="closeAddCustomFieldModal()" class="btn-secondary">Отмена</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // API Base URL
    const API_BASE = 'api/rules.php';

    let availableTemplates = [];
    let availableCRMFields = [];
    let conditionCounter = 0;

    // Загрузка данных при загрузке страницы
    document.addEventListener('DOMContentLoaded', async () => {
        await Promise.all([
            loadTemplates(),
            loadCRMFields(),
            loadRules()
        ]);
    });

    // Загрузка шаблонов
    async function loadTemplates() {
        try {
            const response = await fetch(`${API_BASE}?action=templates`);
            const result = await response.json();
            availableTemplates = Array.isArray(result) ? result : (result.data || []);

            // Заполнить select
            const select = document.getElementById('rule-template');
            availableTemplates.forEach(template => {
                const option = document.createElement('option');
                option.value = template.template_id;
                option.textContent = template.name;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Failed to load templates:', error);
        }
    }

    // Загрузка полей CRM
    async function loadCRMFields() {
        try {
            const response = await fetch(`${API_BASE}?action=crm-fields`);
            const result = await response.json();
            availableCRMFields = Array.isArray(result) ? result : (result.data || []);
        } catch (error) {
            console.error('Failed to load CRM fields:', error);
        }
    }

    // Загрузка правил
    async function loadRules() {
        const loadingState = document.getElementById('loading-state');
        const gridContainer = document.getElementById('rules-grid');

        try {
            const response = await fetch(`${API_BASE}?action=list`);
            const result = await response.json();
            const rules = Array.isArray(result) ? result : (result.data || []);

            loadingState.classList.add('d-none');
            gridContainer.classList.remove('d-none');

            if (rules.length === 0) {
                gridContainer.innerHTML = '<div class="rules-empty-state">Нет правил. Создайте первое правило.</div>';
                return;
            }

            gridContainer.innerHTML = '';

            rules.forEach(rule => {
                const card = createRuleCard(rule);
                gridContainer.appendChild(card);
            });
        } catch (error) {
            console.error('Failed to load rules:', error);
            loadingState.classList.add('d-none');
            gridContainer.classList.remove('d-none');
            gridContainer.innerHTML = '<div class="rules-error-state">Ошибка загрузки правил</div>';
        }
    }

    // Создать карточку правила
    function createRuleCard(rule) {
        const card = document.createElement('div');
        card.className = 'rule-card';

        // Найти название шаблона
        const template = availableTemplates.find(t => t.template_id === rule.template_id);
        const templateName = template ? template.name : rule.template_id;

        // Форматировать условия
        const conditionsText = formatRuleConditions(rule);

        card.innerHTML = `
            <div class="rule-card-actions">
                <div class="toggle-switch ${rule.is_active ? 'active' : ''}"
                     onclick="toggleRule(event, '${rule.rule_id}', ${rule.is_active})">
                </div>
                <button class="icon-btn"
                        onclick="openEditRuleModal(event, '${rule.rule_id}')"
                        title="Редактировать">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>
                <button class="icon-btn icon-btn-danger"
                        onclick="deleteRule(event, '${rule.rule_id}')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            </div>
            <div onclick="openRule('${rule.rule_id}')">
                <div class="rule-card-header">
                    <div class="rule-priority">Приоритет: ${rule.priority}</div>
                </div>
                <h3>${escapeHtml(rule.name)}</h3>
                <p>${escapeHtml(rule.description || 'Без описания')}</p>
                <div class="rule-template-name">${escapeHtml(templateName)}</div>
                ${!rule.is_active ? '<span class="badge-inactive">Неактивно</span>' : ''}
                <div class="rule-conditions">${conditionsText}</div>
            </div>
        `;

        return card;
    }

    // Форматировать условия правила
    function formatRuleConditions(rule) {
        if (!rule.condition_groups || rule.condition_groups.length === 0) {
            return '(пусто)';
        }

        const parts = [];

        rule.condition_groups.forEach(group => {
            const condParts = [];

            group.conditions.forEach(cond => {
                const field = availableCRMFields.find(f => f.field_name === cond.crm_field);
                const fieldLabel = field ? field.field_label : cond.crm_field;

                const opText = {
                    'equals': '=',
                    'not_equals': '!=',
                    'greater_than': '>',
                    'less_than': '<',
                    'contains': 'содержит',
                    'not_contains': 'не содержит'
                }[cond.operator] || cond.operator;

                condParts.push(`${fieldLabel} ${opText} "${cond.value}"`);
            });

            const groupText = condParts.join(` ${group.group_operator} `);
            parts.push(group.conditions.length > 1 ? `(${groupText})` : groupText);
        });

        return parts.join(' OR ');
    }

    // Добавить условие
    function addCondition() {
        const container = document.getElementById('conditions-container');
        const conditionId = conditionCounter++;

        const conditionDiv = document.createElement('div');
        conditionDiv.className = 'condition-builder';
        conditionDiv.id = `condition-${conditionId}`;

        conditionDiv.innerHTML = `
            <div class="condition-row">
                <select class="cond-field" required>
                    <option value="">Выберите поле...</option>
                    ${availableCRMFields.map(f =>
                        `<option value="${f.field_name}">${f.field_label}</option>`
                    ).join('')}
                </select>
                <select class="cond-operator" required>
                    <option value="equals">=</option>
                    <option value="not_equals">!=</option>
                    <option value="greater_than">></option>
                    <option value="less_than"><</option>
                    <option value="contains">содержит</option>
                    <option value="not_contains">не содержит</option>
                </select>
                <input type="text" class="cond-value" placeholder="Значение" required>
                <button type="button" class="btn-remove" onclick="removeCondition(${conditionId})">✕</button>
            </div>
        `;

        container.appendChild(conditionDiv);
    }

    // Удалить условие
    function removeCondition(conditionId) {
        const element = document.getElementById(`condition-${conditionId}`);
        if (element) {
            element.remove();
        }
    }

    // Открыть модальное окно создания
    function openCreateRuleModal() {
        document.getElementById('create-rule-modal').classList.remove('d-none');
        document.getElementById('create-rule-modal').classList.add('d-flex');
        document.getElementById('create-rule-form').reset();
        document.getElementById('conditions-container').innerHTML = '';
        conditionCounter = 0;

        // Добавить одно условие по умолчанию
        addCondition();
    }

    // Закрыть модальное окно создания
    function closeCreateRuleModal() {
        document.getElementById('create-rule-modal').classList.remove('d-flex');
        document.getElementById('create-rule-modal').classList.add('d-none');
    }

    // Создать правило
    async function createRule(event) {
        event.preventDefault();

        const name = document.getElementById('rule-name').value;
        const description = document.getElementById('rule-description').value;
        const templateId = document.getElementById('rule-template').value;
        const priority = parseInt(document.getElementById('rule-priority').value);

        // Собрать условия
        const conditions = [];
        document.querySelectorAll('.condition-builder').forEach((condBuilder, index) => {
            const field = condBuilder.querySelector('.cond-field').value;
            const operator = condBuilder.querySelector('.cond-operator').value;
            const value = condBuilder.querySelector('.cond-value').value;

            if (field && operator && value) {
                conditions.push({
                    crm_field: field,
                    operator: operator,
                    value: value,
                    condition_order: index
                });
            }
        });

        if (conditions.length === 0) {
            alert('Добавьте хотя бы одно условие');
            return;
        }

        try {
            const response = await fetch(`${API_BASE}?action=create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    template_id: templateId,
                    name: name,
                    description: description || null,
                    priority: priority,
                    is_active: true,
                    condition_groups: [{
                        group_operator: 'AND',
                        group_order: 0,
                        conditions: conditions
                    }]
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            console.log('Rule created:', result);

            closeCreateRuleModal();
            await loadRules();

            alert('Правило создано!');
        } catch (error) {
            console.error('Failed to create rule:', error);
            alert('Ошибка создания правила: ' + error.message);
        }
    }

    // Переключить активность правила
    async function toggleRule(event, ruleId, currentState) {
        event.stopPropagation();

        try {
            const response = await fetch(`${API_BASE}?action=toggle&id=${ruleId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            console.log('Rule toggled:', result);

            await loadRules();
        } catch (error) {
            console.error('Failed to toggle rule:', error);
            alert('Ошибка изменения статуса: ' + error.message);
        }
    }

    // Удалить правило
    async function deleteRule(event, ruleId) {
        event.stopPropagation();

        if (!confirm('Удалить это правило? Это действие нельзя отменить.')) {
            return;
        }

        try {
            const response = await fetch(`${API_BASE}?action=delete&id=${ruleId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            console.log('Rule deleted:', result);

            await loadRules();
        } catch (error) {
            console.error('Failed to delete rule:', error);
            alert('Ошибка удаления: ' + error.message);
        }
    }

    // Открыть детали правила
    async function openRule(ruleId) {
        // TODO: Implement rule details modal
        console.log('Open rule:', ruleId);
    }

    // Закрыть модальное окно деталей
    function closeRuleModal() {
        document.getElementById('rule-modal').classList.remove('d-flex');
        document.getElementById('rule-modal').classList.add('d-none');
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Редактирование правила
    let editingRuleId = null;

    async function openEditRuleModal(event, ruleId) {
        event?.stopPropagation();
        editingRuleId = ruleId;

        try {
            // Загрузить данные правила
            const response = await fetch(`${API_BASE}?action=get&id=${ruleId}`);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            const rule = result.data;

            // Заполнить форму
            document.getElementById('rule-name').value = rule.name;
            document.getElementById('rule-description').value = rule.description || '';
            document.getElementById('rule-template').value = rule.template_id;
            document.getElementById('rule-priority').value = rule.priority;

            // Очистить и заполнить условия
            const container = document.getElementById('conditions-container');
            container.innerHTML = '';
            conditionCounter = 0;

            // Добавить существующие условия
            if (rule.condition_groups && rule.condition_groups.length > 0) {
                rule.condition_groups[0].conditions.forEach(cond => {
                    const conditionId = conditionCounter++;
                    const conditionDiv = document.createElement('div');
                    conditionDiv.className = 'condition-builder';
                    conditionDiv.id = `condition-${conditionId}`;

                    conditionDiv.innerHTML = `
                        <div class="condition-row">
                            <select class="cond-field" required>
                                <option value="">Выберите поле...</option>
                                ${availableCRMFields.map(f =>
                                    `<option value="${f.field_name}" ${f.field_name === cond.crm_field ? 'selected' : ''}>${f.field_label}</option>`
                                ).join('')}
                            </select>
                            <select class="cond-operator" required>
                                <option value="equals" ${cond.operator === 'equals' ? 'selected' : ''}>=</option>
                                <option value="not_equals" ${cond.operator === 'not_equals' ? 'selected' : ''}>!=</option>
                                <option value="greater_than" ${cond.operator === 'greater_than' ? 'selected' : ''}>></option>
                                <option value="less_than" ${cond.operator === 'less_than' ? 'selected' : ''}><</option>
                                <option value="contains" ${cond.operator === 'contains' ? 'selected' : ''}>содержит</option>
                                <option value="not_contains" ${cond.operator === 'not_contains' ? 'selected' : ''}>не содержит</option>
                            </select>
                            <input type="text" class="cond-value" placeholder="Значение" required value="${escapeHtml(cond.value)}">
                            <button type="button" class="btn-remove" onclick="removeCondition(${conditionId})">✕</button>
                        </div>
                    `;

                    container.appendChild(conditionDiv);
                });
            } else {
                // Добавить одно пустое условие
                addCondition();
            }

            // Открыть модальное окно
            document.getElementById('create-rule-modal').classList.remove('d-none');
            document.getElementById('create-rule-modal').classList.add('d-flex');

            // Изменить заголовок
            document.querySelector('#create-rule-modal .modal-header h2').textContent = 'Редактировать правило';

            console.log('Loaded rule for editing:', rule);
        } catch (error) {
            console.error('Error loading rule:', error);
            alert('Ошибка загрузки правила: ' + error.message);
        }
    }

    // Изменить closeCreateRuleModal для сброса состояния
    const originalCloseCreateRuleModal = closeCreateRuleModal;
    closeCreateRuleModal = function() {
        document.getElementById('create-rule-modal').classList.remove('d-flex');
        document.getElementById('create-rule-modal').classList.add('d-none');
        editingRuleId = null;
        document.querySelector('#create-rule-modal .modal-header h2').textContent = 'Создать правило';
    };

    // Изменить createRule для поддержки редактирования
    const originalCreateRule = createRule;
    createRule = async function(event) {
        event.preventDefault();

        const name = document.getElementById('rule-name').value;
        const description = document.getElementById('rule-description').value;
        const templateId = document.getElementById('rule-template').value;
        const priority = parseInt(document.getElementById('rule-priority').value);

        // Собрать условия
        const conditions = [];
        document.querySelectorAll('.condition-builder').forEach((condBuilder, index) => {
            const field = condBuilder.querySelector('.cond-field').value;
            const operator = condBuilder.querySelector('.cond-operator').value;
            const value = condBuilder.querySelector('.cond-value').value;

            if (field && operator && value) {
                conditions.push({
                    crm_field: field,
                    operator: operator,
                    value: value,
                    condition_order: index
                });
            }
        });

        if (conditions.length === 0) {
            alert('Добавьте хотя бы одно условие');
            return;
        }

        try {
            const url = editingRuleId
                ? `${API_BASE}?action=update&id=${editingRuleId}`
                : `${API_BASE}?action=create`;
            const method = editingRuleId ? 'PATCH' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    template_id: templateId,
                    name: name,
                    description: description || null,
                    priority: priority,
                    is_active: true,
                    condition_groups: [{
                        group_operator: 'AND',
                        group_order: 0,
                        conditions: conditions
                    }]
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();
            console.log(editingRuleId ? 'Rule updated:' : 'Rule created:', result);

            closeCreateRuleModal();
            await loadRules();

            alert(editingRuleId ? 'Правило обновлено!' : 'Правило создано!');
        } catch (error) {
            console.error('Failed to save rule:', error);
            alert('Ошибка сохранения правила: ' + error.message);
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // УПРАВЛЕНИЕ ПОЛЯМИ ШАБЛОНА "ДИНАМИКА СДЕЛКИ"
    // ═══════════════════════════════════════════════════════════════

    let currentTemplateId = null;
    let templateFields = [];

    // Показать настройки полей для шаблона
    async function showTemplateFields(templateId, templateName) {
        currentTemplateId = templateId;

        // Скрыть правила, показать поля
        document.getElementById('rules-grid').classList.add('d-none');
        document.getElementById('loading-state').classList.add('d-none');
        document.getElementById('template-fields-section').classList.remove('d-none');
        document.getElementById('template-fields-name').textContent = templateName;

        await loadTemplateFields(templateId);
    }

    // Скрыть настройки полей, вернуться к правилам
    function hideTemplateFields() {
        document.getElementById('template-fields-section').classList.add('d-none');
        document.getElementById('rules-grid').classList.remove('d-none');
        currentTemplateId = null;
    }

    // Загрузить поля для шаблона
    async function loadTemplateFields(templateId) {
        try {
            const response = await fetch(`${API_BASE}/api/template-fields/${templateId}`);
            const result = await response.json();

            if (result.success) {
                templateFields = result.fields || [];
                renderTemplateFields();
            } else {
                throw new Error('Failed to load template fields');
            }
        } catch (error) {
            console.error('Failed to load template fields:', error);
            alert('Ошибка загрузки полей шаблона');
        }
    }

    // Отрисовать таблицу полей
    function renderTemplateFields() {
        const tbody = document.getElementById('template-fields-tbody');
        tbody.innerHTML = '';

        if (templateFields.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="rules-empty-state">Нет настроенных полей</td></tr>';
            return;
        }

        templateFields.forEach(field => {
            const row = document.createElement('tr');

            const categoryBadge = field.field_category === 'standard'
                ? '<span class="rules-field-category-standard">Стандартное</span>'
                : '<span class="rules-field-category-custom">Кастомное</span>';

            row.innerHTML = `
                <td class="rules-field-emoji">${field.emoji || '•'}</td>
                <td>
                    <strong>${escapeHtml(field.field_label)}</strong><br>
                    <small class="text-muted">${escapeHtml(field.field_code)}</small>
                    ${field.hint ? `<br><small class="text-muted">${escapeHtml(field.hint)}</small>` : ''}
                </td>
                <td>${escapeHtml(field.field_type)}</td>
                <td>${categoryBadge}</td>
                <td class="rules-checkbox-cell">
                    <input type="checkbox"
                           ${field.include_in_context ? 'checked' : ''}
                           onchange="updateFieldSetting('${field.field_code}', 'include_in_context', this.checked)">
                </td>
                <td class="rules-checkbox-cell">
                    <input type="checkbox"
                           ${field.validate_correctness ? 'checked' : ''}
                           onchange="updateFieldSetting('${field.field_code}', 'validate_correctness', this.checked)">
                </td>
                <td class="rules-checkbox-cell">
                    <input type="checkbox"
                           ${field.auto_fill_if_empty ? 'checked' : ''}
                           onchange="updateFieldSetting('${field.field_code}', 'auto_fill_if_empty', this.checked)">
                </td>
                <td class="rules-action-cell">
                    ${field.field_category === 'custom' ? `
                        <button onclick="deleteTemplateField('${field.field_code}')"
                                class="btn-icon-danger"
                                title="Удалить">🗑️</button>
                    ` : '<span class="rules-action-disabled">—</span>'}
                </td>
            `;

            tbody.appendChild(row);
        });
    }

    // Обновить настройку поля
    async function updateFieldSetting(fieldCode, settingName, value) {
        try {
            const response = await fetch(`${API_BASE}/api/template-fields/${currentTemplateId}/${fieldCode}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ [settingName]: value })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error('Failed to update field setting');
            }

            // Обновить локальные данные
            const field = templateFields.find(f => f.field_code === fieldCode);
            if (field) {
                field[settingName] = value;
            }

            console.log(`Updated ${settingName} for ${fieldCode}:`, value);

        } catch (error) {
            console.error('Failed to update field setting:', error);
            alert('Ошибка обновления настройки поля');
            // Перезагрузить поля чтобы восстановить правильное состояние
            await loadTemplateFields(currentTemplateId);
        }
    }

    // Показать модалку добавления кастомного поля
    function showAddCustomFieldModal() {
        document.getElementById('add-custom-field-modal').classList.remove('d-none');
        document.getElementById('add-custom-field-modal').classList.add('d-flex');
        document.getElementById('add-custom-field-form').reset();
    }

    // Закрыть модалку добавления кастомного поля
    function closeAddCustomFieldModal() {
        document.getElementById('add-custom-field-modal').classList.remove('d-flex');
        document.getElementById('add-custom-field-modal').classList.add('d-none');
    }

    // Сохранить кастомное поле
    async function saveCustomField(event) {
        event.preventDefault();

        const fieldData = {
            field_code: document.getElementById('custom-field-code').value.trim(),
            field_label: document.getElementById('custom-field-label').value.trim(),
            field_type: document.getElementById('custom-field-type').value,
            field_category: 'custom',
            crm_system: document.getElementById('custom-field-crm-system').value,
            emoji: document.getElementById('custom-field-emoji').value.trim() || null,
            hint: document.getElementById('custom-field-hint').value.trim() || null,
            display_order: parseInt(document.getElementById('custom-field-order').value) || 999,
            include_in_context: document.getElementById('custom-field-include-context').checked,
            validate_correctness: document.getElementById('custom-field-validate').checked,
            auto_fill_if_empty: document.getElementById('custom-field-autofill').checked
        };

        try {
            const response = await fetch(`${API_BASE}/api/template-fields/${currentTemplateId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(fieldData)
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.detail || 'Failed to create field');
            }

            closeAddCustomFieldModal();
            await loadTemplateFields(currentTemplateId);
            alert('Кастомное поле добавлено!');

        } catch (error) {
            console.error('Failed to create custom field:', error);
            alert('Ошибка создания поля: ' + error.message);
        }
    }

    // Удалить поле шаблона
    async function deleteTemplateField(fieldCode) {
        if (!confirm(`Удалить поле "${fieldCode}"?`)) {
            return;
        }

        try {
            const response = await fetch(`${API_BASE}/api/template-fields/${currentTemplateId}/${fieldCode}`, {
                method: 'DELETE'
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error('Failed to delete field');
            }

            await loadTemplateFields(currentTemplateId);
            alert('Поле удалено!');

        } catch (error) {
            console.error('Failed to delete field:', error);
            alert('Ошибка удаления поля: ' + error.message);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // НАСТРОЙКИ ТРЕВОЖНЫХ ФЛАГОВ
    // ═══════════════════════════════════════════════════════════════

    let currentAlertTemplateId = null;

    // Показать настройки тревожных флагов
    async function showTemplateAlertSettings(templateId, templateName) {
        currentAlertTemplateId = templateId;
        document.getElementById('template-alert-name').textContent = templateName;
        document.getElementById('rules-grid').classList.add('d-none');
        document.getElementById('template-fields-section').classList.add('d-none');
        document.getElementById('template-alert-settings-section').classList.remove('d-none');

        await loadAlertSettings(templateId);
    }

    // Скрыть настройки тревожных флагов
    function hideTemplateAlertSettings() {
        document.getElementById('template-alert-settings-section').classList.add('d-none');
        document.getElementById('rules-grid').classList.remove('d-none');
        currentAlertTemplateId = null;
    }

    // Загрузить настройки тревожных флагов
    async function loadAlertSettings(templateId) {
        try {
            const response = await fetch(`${API_BASE}/api/template-alerts/${templateId}?org_id=org-legacy`);
            const result = await response.json();

            if (!result.success) {
                console.error('Failed to load alert settings');
                return;
            }

            const settings = result.settings;

            // Заполнить форму
            document.getElementById('alert-send-to-crm').checked = settings.send_alerts_to_crm || false;
            document.getElementById('alert-crm-field').value = settings.crm_field_name || '';
            document.getElementById('alert-low-threshold').value = settings.low_threshold || 1;
            document.getElementById('alert-medium-threshold').value = settings.medium_threshold || 2;
            document.getElementById('alert-high-threshold').value = settings.high_threshold || 4;
            document.getElementById('alert-critical-threshold').value = settings.critical_threshold || 6;
            document.getElementById('alert-notify-high').checked = settings.auto_notify_on_high !== undefined ? settings.auto_notify_on_high : true;
            document.getElementById('alert-notify-critical').checked = settings.auto_notify_on_critical !== undefined ? settings.auto_notify_on_critical : true;

            // Email адреса - преобразовать массив в текст с переносами строк
            const emails = settings.notification_emails || [];
            document.getElementById('alert-notification-emails').value = emails.join('\n');

            // Показать/скрыть поле CRM
            toggleCrmFieldVisibility();

        } catch (error) {
            console.error('Failed to load alert settings:', error);
            alert('Ошибка загрузки настроек тревожных флагов');
        }
    }

    // Переключить видимость поля CRM
    function toggleCrmFieldVisibility() {
        const sendToCrmChecked = document.getElementById('alert-send-to-crm').checked;
        const crmFieldGroup = document.getElementById('crm-field-group');
        if (sendToCrmChecked) {
            crmFieldGroup.classList.remove('d-none');
        } else {
            crmFieldGroup.classList.add('d-none');
        }
    }

    // Обработчик изменения чекбокса "Отправлять в CRM"
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('alert-send-to-crm');
        if (checkbox) {
            checkbox.addEventListener('change', toggleCrmFieldVisibility);
        }
    });

    // Сохранить настройки тревожных флагов
    async function saveAlertSettings(event) {
        event.preventDefault();

        const sendToCrm = document.getElementById('alert-send-to-crm').checked;
        const crmField = document.getElementById('alert-crm-field').value.trim();

        // Валидация: если включено отправление в CRM, то поле должно быть заполнено
        if (sendToCrm && !crmField) {
            alert('Пожалуйста, укажите название поля в CRM');
            document.getElementById('alert-crm-field').focus();
            return;
        }

        // Собрать email адреса
        const emailsText = document.getElementById('alert-notification-emails').value.trim();
        const emails = emailsText ? emailsText.split('\n').map(e => e.trim()).filter(e => e) : [];

        const settingsData = {
            send_alerts_to_crm: sendToCrm,
            crm_field_name: crmField,
            low_threshold: parseInt(document.getElementById('alert-low-threshold').value),
            medium_threshold: parseInt(document.getElementById('alert-medium-threshold').value),
            high_threshold: parseInt(document.getElementById('alert-high-threshold').value),
            critical_threshold: parseInt(document.getElementById('alert-critical-threshold').value),
            auto_notify_on_high: document.getElementById('alert-notify-high').checked,
            auto_notify_on_critical: document.getElementById('alert-notify-critical').checked,
            notification_emails: emails
        };

        try {
            const response = await fetch(`${API_BASE}/api/template-alerts/${currentAlertTemplateId}?org_id=org-legacy`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settingsData)
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error('Failed to save alert settings');
            }

            alert('✅ Настройки тревожных флагов сохранены!');

        } catch (error) {
            console.error('Failed to save alert settings:', error);
            alert('Ошибка сохранения настроек: ' + error.message);
        }
    }
</script>

</body>
</html>
