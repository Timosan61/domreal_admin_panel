/**
 * JavaScript для страницы создания проекта
 * Поддержка многошагового wizard: Свойства → Интеграции → Настройка ИИ
 */

// Глобальные переменные
let currentStep = 1;
let currentProjectId = null;
let projectData = {};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initStep(1);
});

/**
 * Инициализация шага wizard
 * @param {number} stepNumber - Номер шага (1, 2, 3)
 */
function initStep(stepNumber) {
    currentStep = stepNumber;

    // Обновляем индикаторы шагов
    updateStepIndicators();

    // Показываем нужный шаг
    showStep(stepNumber);

    // Инициализируем контент шага
    switch(stepNumber) {
        case 1:
            initStep1();
            break;
        case 2:
            initStep2();
            break;
        case 3:
            initStep3();
            break;
    }
}

/**
 * Обновление визуальных индикаторов шагов
 */
function updateStepIndicators() {
    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById(`step-indicator-${i}`);
        if (indicator) {
            if (i < currentStep) {
                indicator.classList.add('completed');
                indicator.classList.remove('active');
            } else if (i === currentStep) {
                indicator.classList.add('active');
                indicator.classList.remove('completed');
            } else {
                indicator.classList.remove('active', 'completed');
            }
        }
    }
}

/**
 * Показать определенный шаг wizard
 * @param {number} stepNumber - Номер шага
 */
function showStep(stepNumber) {
    // Скрываем все шаги
    document.querySelectorAll('.wizard-step').forEach(step => {
        step.style.display = 'none';
    });

    // Показываем нужный шаг
    const currentStepElement = document.getElementById(`step-${stepNumber}`);
    if (currentStepElement) {
        currentStepElement.style.display = 'block';
    }
}

/**
 * Инициализация шага 1: Свойства проекта
 */
function initStep1() {
    const form = document.getElementById('project-properties-form');
    if (!form) return;

    // Обработчик отправки формы
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        saveStep1();
    });

    // Кнопка "Далее"
    const nextBtn = document.getElementById('step1-next');
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            saveStep1();
        });
    }
}

/**
 * Сохранить свойства проекта (Шаг 1)
 */
async function saveStep1() {
    const form = document.getElementById('project-properties-form');
    const formData = new FormData(form);

    // Валидация
    const name = formData.get('name');
    if (!name || name.trim() === '') {
        showNotification('Укажите название проекта', 'error');
        return;
    }

    // Собираем данные
    const data = {
        name: formData.get('name'),
        description: formData.get('description') || '',
        is_active: formData.get('is_active') === '1',
        stt_balance_seconds: parseInt(formData.get('stt_balance_seconds') || 0),
        ai_balance_seconds: parseInt(formData.get('ai_balance_seconds') || 0)
    };

    try {
        // Показываем индикатор загрузки
        showLoading('Создание проекта...');

        // Отправляем POST запрос
        const response = await fetch('api/projects.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        hideLoading();

        if (result.success) {
            currentProjectId = result.project_id;
            projectData = { ...data, id: currentProjectId };

            showNotification('Проект успешно создан', 'success');

            // Переходим к шагу 2
            setTimeout(() => {
                initStep(2);
            }, 500);
        } else {
            showNotification(result.error || 'Ошибка создания проекта', 'error');
        }

    } catch (error) {
        hideLoading();
        console.error('Ошибка сохранения проекта:', error);
        showNotification('Ошибка подключения к серверу', 'error');
    }
}

/**
 * Инициализация шага 2: Интеграции
 */
function initStep2() {
    // Загружаем список интеграций
    loadIntegrations();

    // Кнопки навигации
    const prevBtn = document.getElementById('step2-prev');
    const nextBtn = document.getElementById('step2-next');

    if (prevBtn) {
        prevBtn.addEventListener('click', () => initStep(1));
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => initStep(3));
    }

    // Кнопки "Подключить" для каждой интеграции
    document.querySelectorAll('[data-connect-integration]').forEach(btn => {
        btn.addEventListener('click', function() {
            const provider = this.getAttribute('data-connect-integration');
            connectIntegration(provider);
        });
    });
}

/**
 * Загрузить список интеграций проекта
 */
async function loadIntegrations() {
    if (!currentProjectId) {
        console.error('Project ID не установлен');
        return;
    }

    try {
        const response = await fetch(`api/project_integrations.php?project_id=${currentProjectId}`);
        const result = await response.json();

        if (result.success) {
            renderIntegrations(result.data);
        } else {
            console.error('Ошибка загрузки интеграций:', result.error);
        }
    } catch (error) {
        console.error('Ошибка загрузки интеграций:', error);
    }
}

/**
 * Отрисовка карточек интеграций
 * @param {Array} integrations - Массив интеграций
 */
function renderIntegrations(integrations) {
    const container = document.getElementById('integrations-list');
    if (!container) return;

    // Список доступных интеграций
    const availableIntegrations = [
        { provider: 'amoCRM', icon: '🔗', name: 'amoCRM', type: 'crm' },
        { provider: 'Bitrix24', icon: '💼', name: 'Bitrix24', type: 'crm' },
        { provider: 'Google Drive', icon: '☁️', name: 'Google Drive', type: 'google_drive' },
        { provider: 'INTRUM', icon: '📊', name: 'INTRUM', type: 'crm' },
        { provider: 'Mango Office', icon: '☎️', name: 'Mango Office', type: 'telephony' },
        { provider: 'UIVA', icon: '🤖', name: 'UIVA', type: 'other' }
    ];

    const html = availableIntegrations.map(item => {
        // Проверяем, подключена ли интеграция
        const connected = integrations.find(int => int.provider_name === item.provider);

        return renderIntegrationCard(item, connected);
    }).join('');

    container.innerHTML = html;

    // Переназначаем обработчики после рендера
    initIntegrationHandlers();
}

/**
 * Рендер карточки интеграции
 * @param {Object} integration - Данные интеграции
 * @param {Object|null} connected - Данные подключенной интеграции (если есть)
 */
function renderIntegrationCard(integration, connected) {
    const isConnected = !!connected;
    const status = isConnected
        ? (connected.is_active ? 'Активна' : 'Неактивна')
        : 'Не подключена';
    const statusClass = isConnected && connected.is_active ? 'badge-success' : 'badge-secondary';

    return `
        <div class="integration-card ${isConnected ? 'connected' : ''}" data-provider="${escapeHtml(integration.provider)}">
            <div class="integration-icon">${integration.icon}</div>
            <h4 class="integration-name">${escapeHtml(integration.name)}</h4>
            <div class="integration-status">
                <span class="badge ${statusClass}">${status}</span>
            </div>
            <div class="integration-actions">
                ${isConnected
                    ? `<button class="btn btn-sm btn-outline-primary" onclick="editIntegration('${escapeHtml(integration.provider)}')">
                        Настроить
                       </button>
                       <button class="btn btn-sm btn-outline-danger" onclick="disconnectIntegration('${escapeHtml(integration.provider)}')">
                        Отключить
                       </button>`
                    : `<button class="btn btn-sm btn-primary" onclick="connectIntegration('${escapeHtml(integration.provider)}')">
                        Подключить
                       </button>`
                }
            </div>
        </div>
    `;
}

/**
 * Инициализация обработчиков для карточек интеграций
 */
function initIntegrationHandlers() {
    // Обработчики уже назначены через onclick в HTML
    console.log('Integration handlers initialized');
}

/**
 * Открыть модальное окно подключения интеграции
 * @param {string} provider - Название провайдера
 */
function connectIntegration(provider) {
    // Открываем модальное окно из integration_modal.js
    if (typeof openModal === 'function') {
        openModal(provider);
    } else {
        console.error('integration_modal.js не загружен');
        showNotification('Ошибка: модальное окно недоступно', 'error');
    }
}

/**
 * Редактировать интеграцию
 * @param {string} provider - Название провайдера
 */
async function editIntegration(provider) {
    // Получаем данные интеграции
    try {
        const response = await fetch(`api/project_integrations.php?project_id=${currentProjectId}`);
        const result = await response.json();

        if (result.success) {
            const integration = result.data.find(int => int.provider_name === provider);
            if (integration && typeof openModal === 'function') {
                openModal(provider, integration);
            }
        }
    } catch (error) {
        console.error('Ошибка загрузки данных интеграции:', error);
        showNotification('Ошибка загрузки данных', 'error');
    }
}

/**
 * Отключить интеграцию
 * @param {string} provider - Название провайдера
 */
async function disconnectIntegration(provider) {
    if (!confirm(`Вы уверены, что хотите отключить интеграцию ${provider}?`)) {
        return;
    }

    try {
        // Находим ID интеграции
        const response = await fetch(`api/project_integrations.php?project_id=${currentProjectId}`);
        const result = await response.json();

        if (result.success) {
            const integration = result.data.find(int => int.provider_name === provider);

            if (!integration) {
                showNotification('Интеграция не найдена', 'error');
                return;
            }

            // Удаляем интеграцию
            const deleteResponse = await fetch(`api/project_integrations.php?id=${integration.id}`, {
                method: 'DELETE'
            });

            const deleteResult = await deleteResponse.json();

            if (deleteResult.success) {
                showNotification('Интеграция отключена', 'success');
                loadIntegrations(); // Перезагружаем список
            } else {
                showNotification(deleteResult.error || 'Ошибка отключения', 'error');
            }
        }
    } catch (error) {
        console.error('Ошибка отключения интеграции:', error);
        showNotification('Ошибка подключения к серверу', 'error');
    }
}

/**
 * Загрузить список чек-листов проекта
 */
async function loadChecklists() {
    if (!currentProjectId) {
        console.error('Project ID не установлен');
        return;
    }

    try {
        const response = await fetch(`api/project_checklists.php?project_id=${currentProjectId}`);
        const result = await response.json();

        if (result.success) {
            renderChecklists(result.data);
        } else {
            console.error('Ошибка загрузки чек-листов:', result.error);
        }
    } catch (error) {
        console.error('Ошибка загрузки чек-листов:', error);
    }
}

/**
 * Отрисовка списка чек-листов
 * @param {Array} checklists - Массив чек-листов
 */
function renderChecklists(checklists) {
    const container = document.getElementById('checklists-list');
    if (!container) return;

    if (checklists.length === 0) {
        container.innerHTML = '<p class="text-muted">Чек-листы пока не созданы</p>';
        return;
    }

    const html = checklists.map(checklist => `
        <div class="checklist-item">
            <h5>${escapeHtml(checklist.name)}</h5>
            <p class="text-muted">${escapeHtml(checklist.type)}</p>
            <span class="badge badge-info">${checklist.items_count || 0} пунктов</span>
        </div>
    `).join('');

    container.innerHTML = html;
}

/**
 * Инициализация шага 3: Настройка ИИ
 */
function initStep3() {
    // Загружаем AI конфигурацию
    loadAIConfig();

    // Кнопки навигации
    const prevBtn = document.getElementById('step3-prev');
    const finishBtn = document.getElementById('step3-finish');

    if (prevBtn) {
        prevBtn.addEventListener('click', () => initStep(2));
    }

    if (finishBtn) {
        finishBtn.addEventListener('click', saveAIConfig);
    }

    // Кнопка "Проверить конфигурацию"
    const checkBtn = document.getElementById('check-config-btn');
    if (checkBtn) {
        checkBtn.addEventListener('click', checkConfiguration);
    }

    // Кнопки управления оценкой
    const startBtn = document.getElementById('start-evaluation-btn');
    const stopBtn = document.getElementById('stop-evaluation-btn');

    if (startBtn) {
        startBtn.addEventListener('click', startAIEvaluation);
    }

    if (stopBtn) {
        stopBtn.addEventListener('click', stopAIEvaluation);
    }

    // Загружаем баланс
    updateBalance();
}

/**
 * Загрузить AI конфигурацию проекта
 */
async function loadAIConfig() {
    if (!currentProjectId) {
        console.error('Project ID не установлен');
        return;
    }

    try {
        const response = await fetch(`api/ai_configurations.php?project_id=${currentProjectId}`);
        const result = await response.json();

        if (result.success && result.data) {
            // Заполняем форму
            const config = result.data;

            document.getElementById('auto_transcription').checked = config.auto_transcription;
            document.getElementById('auto_analysis').checked = config.auto_analysis;
            document.getElementById('auto_diarization').checked = config.auto_diarization;
            document.getElementById('llm_provider').value = config.llm_provider || 'openai';
            document.getElementById('llm_model').value = config.llm_model || 'gpt-4o-mini';
            document.getElementById('analysis_prompt_version').value = config.analysis_prompt_version || 'v4';
        }
    } catch (error) {
        console.error('Ошибка загрузки AI конфигурации:', error);
    }
}

/**
 * Сохранить AI конфигурацию
 */
async function saveAIConfig() {
    const data = {
        is_configured: true,
        auto_transcription: document.getElementById('auto_transcription').checked,
        auto_analysis: document.getElementById('auto_analysis').checked,
        auto_diarization: document.getElementById('auto_diarization').checked,
        llm_provider: document.getElementById('llm_provider').value,
        llm_model: document.getElementById('llm_model').value,
        analysis_prompt_version: document.getElementById('analysis_prompt_version').value
    };

    try {
        showLoading('Сохранение конфигурации...');

        // Получаем ID AI settings
        const getResponse = await fetch(`api/ai_configurations.php?project_id=${currentProjectId}`);
        const getResult = await getResponse.json();

        if (getResult.success && getResult.data) {
            // Обновляем существующую конфигурацию
            const response = await fetch(`api/ai_configurations.php?id=${getResult.data.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            hideLoading();

            if (result.success) {
                showNotification('Конфигурация сохранена', 'success');

                // Перенаправляем на страницу проекта через 1 секунду
                setTimeout(() => {
                    window.location.href = `project_details.php?id=${currentProjectId}`;
                }, 1000);
            } else {
                showNotification(result.error || 'Ошибка сохранения', 'error');
            }
        } else {
            hideLoading();
            showNotification('Ошибка получения конфигурации', 'error');
        }

    } catch (error) {
        hideLoading();
        console.error('Ошибка сохранения AI конфигурации:', error);
        showNotification('Ошибка подключения к серверу', 'error');
    }
}

/**
 * Проверка конфигурации ИИ
 */
async function checkConfiguration() {
    if (!currentProjectId) {
        showNotification('Project ID не установлен', 'error');
        return;
    }

    try {
        showLoading('Проверка конфигурации...');

        const response = await fetch(`api/ai_configurations.php?project_id=${currentProjectId}`);
        const result = await response.json();

        hideLoading();

        if (result.success && result.data) {
            const config = result.data;

            // Проверяем ключевые параметры
            const checks = [];

            if (config.auto_transcription) {
                checks.push('✅ Автоматическая транскрипция включена');
            } else {
                checks.push('⚠️ Автоматическая транскрипция отключена');
            }

            if (config.auto_analysis) {
                checks.push('✅ Автоматический анализ включен');
            } else {
                checks.push('⚠️ Автоматический анализ отключен');
            }

            if (config.llm_provider && config.llm_model) {
                checks.push(`✅ LLM провайдер: ${config.llm_provider} (${config.llm_model})`);
            } else {
                checks.push('❌ LLM провайдер не настроен');
            }

            // Показываем результат
            const message = checks.join('\n');
            alert('Результаты проверки:\n\n' + message);

        } else {
            showNotification('Конфигурация не найдена', 'error');
        }

    } catch (error) {
        hideLoading();
        console.error('Ошибка проверки конфигурации:', error);
        showNotification('Ошибка подключения к серверу', 'error');
    }
}

/**
 * Запустить AI оценку для проекта
 */
async function startAIEvaluation() {
    if (!currentProjectId) {
        showNotification('Project ID не установлен', 'error');
        return;
    }

    try {
        showLoading('Запуск оценки...');

        // Получаем ID AI settings
        const getResponse = await fetch(`api/ai_configurations.php?project_id=${currentProjectId}`);
        const getResult = await getResponse.json();

        if (getResult.success && getResult.data) {
            const response = await fetch(`api/ai_configurations.php?id=${getResult.data.id}&action=start`, {
                method: 'POST'
            });

            const result = await response.json();

            hideLoading();

            if (result.success) {
                showNotification('Оценка ИИ запущена', 'success');
                updateBalance(); // Обновляем баланс
            } else {
                showNotification(result.error || 'Ошибка запуска', 'error');
            }
        } else {
            hideLoading();
            showNotification('Конфигурация не найдена', 'error');
        }

    } catch (error) {
        hideLoading();
        console.error('Ошибка запуска оценки:', error);
        showNotification('Ошибка подключения к серверу', 'error');
    }
}

/**
 * Остановить AI оценку для проекта
 */
async function stopAIEvaluation() {
    if (!currentProjectId) {
        showNotification('Project ID не установлен', 'error');
        return;
    }

    if (!confirm('Вы уверены, что хотите остановить оценку ИИ?')) {
        return;
    }

    try {
        showLoading('Остановка оценки...');

        // Получаем ID AI settings
        const getResponse = await fetch(`api/ai_configurations.php?project_id=${currentProjectId}`);
        const getResult = await getResponse.json();

        if (getResult.success && getResult.data) {
            const response = await fetch(`api/ai_configurations.php?id=${getResult.data.id}&action=stop`, {
                method: 'POST'
            });

            const result = await response.json();

            hideLoading();

            if (result.success) {
                showNotification('Оценка ИИ остановлена', 'success');
                updateBalance(); // Обновляем баланс
            } else {
                showNotification(result.error || 'Ошибка остановки', 'error');
            }
        } else {
            hideLoading();
            showNotification('Конфигурация не найдена', 'error');
        }

    } catch (error) {
        hideLoading();
        console.error('Ошибка остановки оценки:', error);
        showNotification('Ошибка подключения к серверу', 'error');
    }
}

/**
 * Обновить отображение баланса STT и AI
 */
async function updateBalance() {
    if (!currentProjectId) return;

    try {
        const response = await fetch(`api/balance.php?project_id=${currentProjectId}`);
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data;

            // Обновляем баланс STT
            const sttBalance = document.getElementById('stt-balance');
            if (sttBalance) {
                sttBalance.textContent = data.stt_balance.formatted;
            }

            // Обновляем баланс AI
            const aiBalance = document.getElementById('ai-balance');
            if (aiBalance) {
                aiBalance.textContent = data.ai_balance.formatted;
            }

            // Обновляем статистику
            const totalCalls = document.getElementById('total-calls');
            if (totalCalls) {
                totalCalls.textContent = data.statistics.total_calls;
            }

            const transcribedCalls = document.getElementById('transcribed-calls');
            if (transcribedCalls) {
                transcribedCalls.textContent = data.statistics.transcribed_calls;
            }

            const analyzedCalls = document.getElementById('analyzed-calls');
            if (analyzedCalls) {
                analyzedCalls.textContent = data.statistics.analyzed_calls;
            }
        }
    } catch (error) {
        console.error('Ошибка обновления баланса:', error);
    }
}

/**
 * Показать уведомление
 * @param {string} message - Текст уведомления
 * @param {string} type - Тип (success, error, info, warning)
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        font-size: 14px;
        max-width: 300px;
        animation: slideInRight 0.3s ease-out;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

/**
 * Показать индикатор загрузки
 * @param {string} message - Текст сообщения
 */
function showLoading(message = 'Загрузка...') {
    let loader = document.getElementById('global-loader');

    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;

        loader.innerHTML = `
            <div style="background: white; padding: 30px; border-radius: 8px; text-align: center;">
                <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                <div id="loader-message" style="color: #333; font-size: 16px;">${message}</div>
            </div>
        `;

        document.body.appendChild(loader);
    } else {
        const messageElement = document.getElementById('loader-message');
        if (messageElement) {
            messageElement.textContent = message;
        }
        loader.style.display = 'flex';
    }
}

/**
 * Скрыть индикатор загрузки
 */
function hideLoading() {
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.style.display = 'none';
    }
}

/**
 * Экранирование HTML
 * @param {string} text - Текст для экранирования
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// CSS анимации
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
