/**
 * JavaScript для модального окна интеграций
 * Поддержка провайдеров: amoCRM, Bitrix24, Google Drive, INTRUM, Mango Office, UIVA
 */

// Глобальные переменные
let currentProvider = null;
let currentIntegrationData = null;
let modalElement = null;

/**
 * Конфигурация форм для каждого провайдера
 */
const PROVIDER_CONFIGS = {
    'amoCRM': {
        name: 'amoCRM',
        icon: '🔗',
        type: 'crm',
        fields: [
            {
                name: 'domain',
                label: 'Домен amoCRM',
                type: 'text',
                placeholder: 'example.amocrm.ru',
                required: true,
                help: 'Домен вашего аккаунта amoCRM (без https://)'
            },
            {
                name: 'client_id',
                label: 'Client ID',
                type: 'text',
                placeholder: 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                required: true,
                help: 'Получите в разделе "Интеграции" amoCRM'
            },
            {
                name: 'client_secret',
                label: 'Client Secret',
                type: 'password',
                placeholder: 'Секретный ключ',
                required: true,
                help: 'Секретный ключ для OAuth авторизации'
            },
            {
                name: 'redirect_uri',
                label: 'Redirect URI',
                type: 'text',
                placeholder: 'https://yourdomain.com/oauth/amocrm',
                required: true,
                help: 'URL для редиректа после авторизации'
            },
            {
                name: 'code',
                label: 'Authorization Code',
                type: 'text',
                placeholder: 'Код авторизации',
                required: false,
                help: 'Код для первичной авторизации (опционально)'
            }
        ]
    },
    'Bitrix24': {
        name: 'Bitrix24',
        icon: '💼',
        type: 'crm',
        fields: [
            {
                name: 'webhook_url',
                label: 'Webhook URL',
                type: 'text',
                placeholder: 'https://your-portal.bitrix24.ru/rest/1/xxxxxxxx/',
                required: true,
                help: 'Webhook URL из настроек вашего Bitrix24'
            },
            {
                name: 'user_id',
                label: 'User ID',
                type: 'text',
                placeholder: '1',
                required: false,
                help: 'ID пользователя для привязки событий'
            }
        ]
    },
    'Google Drive': {
        name: 'Google Drive',
        icon: '☁️',
        type: 'google_drive',
        fields: [
            {
                name: 'credentials_json',
                label: 'Service Account Credentials (JSON)',
                type: 'textarea',
                placeholder: '{\n  "type": "service_account",\n  ...\n}',
                required: true,
                help: 'JSON файл с credentials Service Account из Google Cloud Console'
            },
            {
                name: 'folder_id',
                label: 'Folder ID',
                type: 'text',
                placeholder: '1A2B3C4D5E6F7G8H9I0J',
                required: true,
                help: 'ID папки Google Drive для загрузки аудио'
            },
            {
                name: 'share_with_emails',
                label: 'Email для доступа',
                type: 'text',
                placeholder: 'user@example.com, team@example.com',
                required: false,
                help: 'Email адреса для автоматического предоставления доступа (через запятую)'
            }
        ]
    },
    'INTRUM': {
        name: 'INTRUM',
        icon: '📊',
        type: 'crm',
        fields: [
            {
                name: 'api_url',
                label: 'API URL',
                type: 'text',
                placeholder: 'https://api.intrum.com/v1',
                required: true,
                help: 'Base URL API INTRUM'
            },
            {
                name: 'api_key',
                label: 'API Key',
                type: 'password',
                placeholder: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                required: true,
                help: 'API ключ для авторизации'
            },
            {
                name: 'company_id',
                label: 'Company ID',
                type: 'text',
                placeholder: '12345',
                required: false,
                help: 'ID компании в системе INTRUM'
            }
        ]
    },
    'Mango Office': {
        name: 'Mango Office',
        icon: '☎️',
        type: 'telephony',
        fields: [
            {
                name: 'vpbx_api_key',
                label: 'VPBX API Key',
                type: 'password',
                placeholder: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                required: true,
                help: 'API ключ виртуальной АТС Mango Office'
            },
            {
                name: 'vpbx_api_salt',
                label: 'VPBX API Salt',
                type: 'password',
                placeholder: 'xxxxxxxxxxxx',
                required: true,
                help: 'Salt для подписи запросов'
            },
            {
                name: 'webhook_url',
                label: 'Webhook URL (для получения событий)',
                type: 'text',
                placeholder: 'https://yourdomain.com/webhook/mango',
                required: false,
                help: 'URL для получения событий от Mango Office'
            }
        ]
    },
    'UIVA': {
        name: 'UIVA',
        icon: '🤖',
        type: 'other',
        fields: [
            {
                name: 'api_url',
                label: 'API URL',
                type: 'text',
                placeholder: 'https://api.uiva.ai/v1',
                required: true,
                help: 'Base URL API UIVA'
            },
            {
                name: 'api_token',
                label: 'API Token',
                type: 'password',
                placeholder: 'Bearer xxxxxxxxxxxxxxxxxxxxx',
                required: true,
                help: 'Bearer токен для авторизации'
            },
            {
                name: 'workspace_id',
                label: 'Workspace ID',
                type: 'text',
                placeholder: 'ws_xxxxxxxxx',
                required: false,
                help: 'ID рабочего пространства UIVA'
            }
        ]
    }
};

/**
 * Открыть модальное окно для подключения интеграции
 * @param {string} provider - Название провайдера
 * @param {Object|null} integrationData - Существующие данные интеграции (для редактирования)
 */
function openModal(provider, integrationData = null) {
    currentProvider = provider;
    currentIntegrationData = integrationData;

    // Создаем модальное окно если его нет
    if (!modalElement) {
        createModal();
    }

    // Рендерим форму для провайдера
    renderFormFields(provider);

    // Заполняем форму данными (если редактирование)
    if (integrationData && integrationData.config) {
        populateForm(integrationData.config);
    }

    // Показываем модальное окно
    modalElement.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Блокируем скролл страницы
}

/**
 * Создать структуру модального окна
 */
function createModal() {
    modalElement = document.createElement('div');
    modalElement.id = 'integration-modal';
    modalElement.className = 'modal-overlay';
    modalElement.style.cssText = `
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        overflow-y: auto;
    `;

    modalElement.innerHTML = `
        <div class="modal-container" style="
            background: white;
            max-width: 600px;
            margin: 50px auto;
            padding: 0;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        ">
            <div class="modal-header" style="
                padding: 20px 30px;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: space-between;
            ">
                <h3 id="modal-title" style="margin: 0; font-size: 20px; font-weight: 600;">
                    Подключение интеграции
                </h3>
                <button id="modal-close-btn" style="
                    background: none;
                    border: none;
                    font-size: 28px;
                    cursor: pointer;
                    color: #6b7280;
                    line-height: 1;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                ">&times;</button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <form id="integration-form">
                    <div id="form-fields-container"></div>

                    <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="modal-cancel-btn" class="btn btn-secondary">
                            Отмена
                        </button>
                        <button type="submit" id="modal-submit-btn" class="btn btn-primary">
                            Сохранить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.appendChild(modalElement);

    // Обработчики событий
    document.getElementById('modal-close-btn').addEventListener('click', closeModal);
    document.getElementById('modal-cancel-btn').addEventListener('click', closeModal);
    document.getElementById('integration-form').addEventListener('submit', submitIntegration);

    // Закрытие по клику вне модалки
    modalElement.addEventListener('click', function(e) {
        if (e.target === modalElement) {
            closeModal();
        }
    });
}

/**
 * Динамически генерировать поля формы для провайдера
 * @param {string} provider - Название провайдера
 */
function renderFormFields(provider) {
    const config = PROVIDER_CONFIGS[provider];

    if (!config) {
        console.error(`Конфигурация для провайдера ${provider} не найдена`);
        return;
    }

    // Обновляем заголовок
    document.getElementById('modal-title').innerHTML = `
        ${config.icon} Подключение ${config.name}
    `;

    // Генерируем HTML полей
    const container = document.getElementById('form-fields-container');
    const html = config.fields.map(field => renderField(field)).join('');

    container.innerHTML = html;
}

/**
 * Рендер одного поля формы
 * @param {Object} field - Конфигурация поля
 */
function renderField(field) {
    const required = field.required ? '<span style="color: #ef4444;">*</span>' : '';

    if (field.type === 'textarea') {
        return `
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="field-${field.name}" style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">
                    ${escapeHtml(field.label)} ${required}
                </label>
                <textarea
                    id="field-${field.name}"
                    name="${field.name}"
                    class="form-control"
                    placeholder="${escapeHtml(field.placeholder)}"
                    ${field.required ? 'required' : ''}
                    rows="6"
                    style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: monospace;"
                ></textarea>
                ${field.help ? `<small style="display: block; margin-top: 4px; color: #6b7280; font-size: 12px;">${escapeHtml(field.help)}</small>` : ''}
            </div>
        `;
    }

    return `
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="field-${field.name}" style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">
                ${escapeHtml(field.label)} ${required}
            </label>
            <input
                type="${field.type}"
                id="field-${field.name}"
                name="${field.name}"
                class="form-control"
                placeholder="${escapeHtml(field.placeholder)}"
                ${field.required ? 'required' : ''}
                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;"
            />
            ${field.help ? `<small style="display: block; margin-top: 4px; color: #6b7280; font-size: 12px;">${escapeHtml(field.help)}</small>` : ''}
        </div>
    `;
}

/**
 * Заполнить форму существующими данными
 * @param {Object} config - Конфигурация интеграции
 */
function populateForm(config) {
    if (!config || typeof config !== 'object') return;

    Object.keys(config).forEach(key => {
        const field = document.getElementById(`field-${key}`);
        if (field) {
            field.value = config[key] || '';
        }
    });
}

/**
 * Валидация формы
 * @returns {boolean} - true если форма валидна
 */
function validateForm() {
    const form = document.getElementById('integration-form');
    if (!form) return false;

    // HTML5 валидация
    if (!form.checkValidity()) {
        form.reportValidity();
        return false;
    }

    // Дополнительная валидация для JSON полей (например, credentials_json)
    const jsonFields = ['credentials_json'];
    for (const fieldName of jsonFields) {
        const field = document.getElementById(`field-${fieldName}`);
        if (field && field.value.trim() !== '') {
            try {
                JSON.parse(field.value);
            } catch (e) {
                showNotification(`Поле "${field.previousElementSibling.textContent.trim()}" содержит некорректный JSON`, 'error');
                field.focus();
                return false;
            }
        }
    }

    return true;
}

/**
 * Отправка формы интеграции
 * @param {Event} e - Событие submit
 */
async function submitIntegration(e) {
    e.preventDefault();

    // Валидация
    if (!validateForm()) {
        return;
    }

    const form = document.getElementById('integration-form');
    const formData = new FormData(form);

    // Собираем config объект
    const config = {};
    const providerConfig = PROVIDER_CONFIGS[currentProvider];

    providerConfig.fields.forEach(field => {
        const value = formData.get(field.name);
        if (value && value.trim() !== '') {
            // Для JSON полей парсим
            if (field.name === 'credentials_json') {
                try {
                    config[field.name] = JSON.parse(value);
                } catch (e) {
                    config[field.name] = value;
                }
            } else {
                config[field.name] = value.trim();
            }
        }
    });

    // Подготовка данных для отправки
    const data = {
        project_id: currentProjectId, // Из project_create.js
        integration_type: providerConfig.type,
        provider_name: currentProvider,
        config: config,
        is_active: true
    };

    try {
        showLoading('Сохранение интеграции...');

        let response;

        if (currentIntegrationData) {
            // Обновление существующей интеграции
            response = await fetch(`api/project_integrations.php?id=${currentIntegrationData.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    config: config,
                    is_active: true
                })
            });
        } else {
            // Создание новой интеграции
            response = await fetch('api/project_integrations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
        }

        const result = await response.json();

        hideLoading();

        if (result.success) {
            showNotification(
                currentIntegrationData ? 'Интеграция обновлена' : 'Интеграция подключена',
                'success'
            );

            closeModal();

            // Перезагружаем список интеграций
            if (typeof loadIntegrations === 'function') {
                loadIntegrations();
            }
        } else {
            showNotification(result.error || 'Ошибка сохранения интеграции', 'error');
        }

    } catch (error) {
        hideLoading();
        console.error('Ошибка отправки формы:', error);
        showNotification('Ошибка подключения к серверу', 'error');
    }
}

/**
 * Закрыть модальное окно
 */
function closeModal() {
    if (modalElement) {
        modalElement.style.display = 'none';
        document.body.style.overflow = ''; // Восстанавливаем скролл страницы
    }

    // Очистка состояния
    currentProvider = null;
    currentIntegrationData = null;

    // Очистка формы
    const form = document.getElementById('integration-form');
    if (form) {
        form.reset();
    }
}

/**
 * Показать уведомление
 * @param {string} message - Текст уведомления
 * @param {string} type - Тип (success, error, info, warning)
 */
function showNotification(message, type = 'info') {
    // Используем функцию из project_create.js если доступна
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
        return;
    }

    // Fallback: создаем свою
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
        z-index: 10001;
        font-size: 14px;
        max-width: 300px;
        animation: slideInRight 0.3s ease-out;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

/**
 * Показать индикатор загрузки
 * @param {string} message - Текст сообщения
 */
function showLoading(message) {
    if (typeof window.showLoading === 'function') {
        window.showLoading(message);
    }
}

/**
 * Скрыть индикатор загрузки
 */
function hideLoading() {
    if (typeof window.hideLoading === 'function') {
        window.hideLoading();
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

// CSS анимации (если еще не добавлены)
if (!document.getElementById('integration-modal-styles')) {
    const style = document.createElement('style');
    style.id = 'integration-modal-styles';
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

        /* Стили для кнопок в модальном окне */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    `;
    document.head.appendChild(style);
}
