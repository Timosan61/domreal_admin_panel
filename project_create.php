<?php
session_start();
require_once 'auth/session.php';
checkAuth();

// Получаем параметры из URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

// Валидация шага
if ($step < 1 || $step > 3) {
    $step = 1;
}

// Если есть project_id, загружаем данные проекта
$project_name = 'Новый проект';
if ($project_id) {
    // TODO: Загрузка данных проекта из БД
    // $project = getProjectById($project_id);
    // $project_name = $project['name'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project_name) ?> - Создание проекта</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Прогресс-индикатор */
        .step-tabs {
            display: flex;
            background: var(--surface-color);
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .step-tab {
            flex: 1;
            padding: 16px 24px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-muted);
            background: var(--surface-color);
            border-right: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .step-tab:last-child {
            border-right: none;
        }

        .step-tab.active {
            background: var(--primary-color);
            color: white;
        }

        .step-tab.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .step-tab:not(.active):hover {
            background: var(--bg-color);
        }

        /* Номер шага */
        .step-number {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            margin-right: 8px;
            font-weight: 600;
        }

        .step-tab.active .step-number {
            background: rgba(255,255,255,0.3);
        }

        /* Контейнер формы */
        .form-container {
            background: var(--surface-color);
            padding: 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            max-width: 900px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .form-group label.required::after {
            content: " *";
            color: var(--danger-color);
        }

        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: white;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        /* Чекбоксы */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: var(--bg-color);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }

        .checkbox-item:hover {
            border-color: var(--primary-color);
            background: rgba(0, 122, 255, 0.05);
        }

        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-color);
        }

        /* Шаг 2: Источники данных */
        .sources-section {
            margin-bottom: 32px;
        }

        .sources-section h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 16px;
        }

        .connected-sources {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .source-card {
            padding: 16px;
            background: var(--bg-color);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .source-card .source-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .source-card .source-logo {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .source-card .source-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-color);
        }

        .new-sources-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .new-source-card {
            padding: 24px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .new-source-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .new-source-card .source-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 12px;
            background: var(--bg-color);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .new-source-card .source-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .new-source-card .btn {
            margin-top: 12px;
        }

        /* Шаг 3: Настройка ИИ */
        .ai-config-container {
            display: none;
            margin-top: 24px;
        }

        .ai-config-container.active {
            display: block;
        }

        .ai-config-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .config-column h4 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 16px;
        }

        .balance-slider {
            margin: 24px 0;
        }

        .slider-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .slider-track {
            width: 100%;
            height: 6px;
            -webkit-appearance: none;
            appearance: none;
            background: linear-gradient(to right, #3b82f6, #f59e0b);
            border-radius: 3px;
            outline: none;
        }

        .slider-track::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            background: white;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .slider-track::-moz-range-thumb {
            width: 20px;
            height: 20px;
            background: white;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Кнопки действий */
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .btn-group {
            display: flex;
            gap: 12px;
        }

        /* Подсказка для шага 3 */
        .hint-box {
            padding: 16px;
            background: #f0f9ff;
            border-left: 4px solid var(--primary-color);
            border-radius: var(--radius-md);
            margin-bottom: 24px;
        }

        .hint-box p {
            margin: 0;
            font-size: 14px;
            color: #0369a1;
            line-height: 1.6;
        }

        /* Темная тема */
        [data-theme="dark"] .form-group input[type="text"],
        [data-theme="dark"] .form-group select {
            background-color: #2c2c2e;
            color: var(--text-color);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .checkbox-item {
            background: #2c2c2e;
        }

        [data-theme="dark"] .new-source-card {
            background: #2c2c2e;
        }

        [data-theme="dark"] .hint-box {
            background: #1e3a5f;
            border-color: var(--primary-color);
        }

        [data-theme="dark"] .hint-box p {
            color: #60a5fa;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .step-tabs {
                flex-direction: column;
            }

            .step-tab {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }

            .checkbox-grid {
                grid-template-columns: 1fr;
            }

            .new-sources-grid {
                grid-template-columns: 1fr;
            }

            .ai-config-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <header class="page-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <a href="projects.php" style="color: var(--text-muted); text-decoration: none; display: flex; align-items: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h1><?= htmlspecialchars($project_name) ?></h1>
            </div>
        </header>

        <!-- Прогресс-индикатор (табы) -->
        <div class="step-tabs">
            <div class="step-tab <?= $step === 1 ? 'active' : ($step > 1 ? 'completed' : '') ?>" onclick="goToStep(1)">
                <span class="step-number">1</span>
                Свойства проекта
            </div>
            <div class="step-tab <?= $step === 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>" onclick="goToStep(2)">
                <span class="step-number">2</span>
                Настройка импорта звонков
            </div>
            <div class="step-tab <?= $step === 3 ? 'active' : '' ?>" onclick="goToStep(3)">
                <span class="step-number">3</span>
                Настройка ИИ
            </div>
        </div>

        <!-- Форма -->
        <div class="form-container">
            <!-- Шаг 1: Свойства проекта -->
            <?php if ($step === 1): ?>
            <form id="step1-form" method="POST" action="api/project_save.php">
                <input type="hidden" name="step" value="1">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">

                <div class="form-group">
                    <label for="project_name" class="required">Название проекта</label>
                    <input type="text" id="project_name" name="project_name" required placeholder="Введите название проекта">
                </div>

                <div class="form-group">
                    <label for="project_type" class="required">Тип проекта</label>
                    <select id="project_type" name="project_type" required>
                        <option value="">Выберите тип</option>
                        <option value="calls">Звонки</option>
                        <option value="chat">Чат</option>
                        <option value="email">Email</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Чек-листы</label>
                    <div class="checkbox-grid" id="checklists-grid">
                        <!-- Будет заполнено через JS -->
                        <div class="checkbox-item">
                            <input type="checkbox" id="checklist_1" name="checklists[]" value="1">
                            <label for="checklist_1">Чек-лист 1: Приветствие и представление</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="checklist_2" name="checklists[]" value="2">
                            <label for="checklist_2">Чек-лист 2: Выявление потребностей</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="checklist_3" name="checklists[]" value="3">
                            <label for="checklist_3">Чек-лист 3: Презентация продукта</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="checklist_4" name="checklists[]" value="4">
                            <label for="checklist_4">Чек-лист 4: Работа с возражениями</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="checklist_5" name="checklists[]" value="5">
                            <label for="checklist_5">Чек-лист 5: Завершение звонка</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="checklist_6" name="checklists[]" value="6">
                            <label for="checklist_6">Чек-лист 6: Соблюдение регламента</label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="projects.php" class="btn btn-secondary">Отмена</a>
                    <button type="submit" class="btn btn-primary">Сохранить и продолжить</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- Шаг 2: Настройка импорта звонков -->
            <?php if ($step === 2): ?>
            <div class="sources-section">
                <h3>Уже настроенные источники данных</h3>
                <div class="connected-sources">
                    <div class="source-card">
                        <div class="source-info">
                            <div class="source-logo">B</div>
                            <div>
                                <div class="source-name">Beeline API</div>
                                <div style="font-size: 12px; color: var(--text-muted);">Активно</div>
                            </div>
                        </div>
                        <span class="badge badge-success">Подключено</span>
                    </div>
                </div>
            </div>

            <div class="sources-section">
                <h3>Подключить новый источник данных</h3>
                <div class="new-sources-grid">
                    <div class="new-source-card" onclick="openSourceModal('amocrm')">
                        <div class="source-icon">📊</div>
                        <div class="source-label">amoCRM</div>
                        <button type="button" class="btn btn-sm btn-primary">Создать</button>
                    </div>
                    <div class="new-source-card" onclick="openSourceModal('bitrix24')">
                        <div class="source-icon">💼</div>
                        <div class="source-label">Bitrix24</div>
                        <button type="button" class="btn btn-sm btn-primary">Создать</button>
                    </div>
                    <div class="new-source-card" onclick="openSourceModal('google_drive')">
                        <div class="source-icon">📁</div>
                        <div class="source-label">Google Drive</div>
                        <button type="button" class="btn btn-sm btn-primary">Создать</button>
                    </div>
                    <div class="new-source-card" onclick="openSourceModal('intrum')">
                        <div class="source-icon">🎧</div>
                        <div class="source-label">INTRUM</div>
                        <button type="button" class="btn btn-sm btn-primary">Создать</button>
                    </div>
                    <div class="new-source-card" onclick="openSourceModal('mango')">
                        <div class="source-icon">🥭</div>
                        <div class="source-label">MANGO OFFICE</div>
                        <button type="button" class="btn btn-sm btn-primary">Создать</button>
                    </div>
                    <div class="new-source-card" onclick="openSourceModal('uiva')">
                        <div class="source-icon">📞</div>
                        <div class="source-label">UIVA</div>
                        <button type="button" class="btn btn-sm btn-primary">Создать</button>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="goToStep(1)">Назад</button>
                <button type="button" class="btn btn-primary" onclick="goToStep(3)">Продолжить</button>
            </div>
            <?php endif; ?>

            <!-- Шаг 3: Настройка ИИ -->
            <?php if ($step === 3): ?>
            <div class="hint-box">
                <p>Настройте параметры ИИ-анализа для вашего проекта. Вы можете протестировать конфигурацию перед запуском полной оценки.</p>
            </div>

            <button type="button" class="btn btn-primary" id="create-config-btn" onclick="toggleAiConfig()">
                Создать конфигурацию
            </button>

            <div class="ai-config-container" id="ai-config-container">
                <form id="step3-form" method="POST" action="api/project_save.php">
                    <input type="hidden" name="step" value="3">
                    <input type="hidden" name="project_id" value="<?= $project_id ?>">

                    <div class="ai-config-layout">
                        <!-- Левая колонка: Фильтры -->
                        <div class="config-column">
                            <h4>Параметры фильтрации</h4>

                            <div class="form-group">
                                <label>Чек-листы</label>
                                <select name="ai_checklists[]" multiple size="4" style="height: auto;">
                                    <option value="1">Чек-лист 1: Приветствие</option>
                                    <option value="2">Чек-лист 2: Потребности</option>
                                    <option value="3">Чек-лист 3: Презентация</option>
                                    <option value="4">Чек-лист 4: Возражения</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Период</label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                    <input type="date" name="ai_date_from" placeholder="С">
                                    <input type="date" name="ai_date_to" placeholder="По">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Длительность (сек)</label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                    <input type="number" name="ai_duration_min" placeholder="От" min="0">
                                    <input type="number" name="ai_duration_max" placeholder="До" min="0">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Направление</label>
                                <select name="ai_direction">
                                    <option value="">Все</option>
                                    <option value="INBOUND">Входящий</option>
                                    <option value="OUTBOUND">Исходящий</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Первый звонок</label>
                                <select name="ai_first_call">
                                    <option value="">Все</option>
                                    <option value="1">Да</option>
                                    <option value="0">Нет</option>
                                </select>
                            </div>
                        </div>

                        <!-- Правая колонка: Баланс и кнопки -->
                        <div class="config-column">
                            <h4>Баланс STT/ИИ</h4>

                            <div class="balance-slider">
                                <div class="slider-label">
                                    <span>STT (транскрибация)</span>
                                    <span>ИИ (анализ)</span>
                                </div>
                                <input type="range" class="slider-track" name="ai_balance" min="0" max="100" value="50" id="balance-slider">
                                <div style="text-align: center; margin-top: 12px; font-size: 14px; color: var(--text-color);">
                                    <strong id="balance-value">50%</strong> STT / <strong id="balance-value-ai">50%</strong> ИИ
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="button" class="btn btn-secondary" style="width: 100%;">
                                    Проверить конфигурацию
                                </button>
                            </div>

                            <div class="form-group">
                                <button type="button" class="btn btn-danger" style="width: 100%;">
                                    Остановить оценку ИИ
                                </button>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    Запустить оценку ИИ
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="goToStep(2)">Назад</button>
                <a href="projects.php" class="btn btn-success">Завершить</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Модальное окно для подключения источника -->
    <div class="modal" id="source-modal" style="display: none;">
        <div class="modal-overlay" onclick="closeSourceModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="source-modal-title">Подключить источник данных</h3>
                <button type="button" class="modal-close" onclick="closeSourceModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="source-form">
                    <input type="hidden" id="source-type" name="source_type">

                    <div class="form-group">
                        <label for="source_api_key">API ключ</label>
                        <input type="text" id="source_api_key" name="api_key" placeholder="Введите API ключ">
                    </div>

                    <div class="form-group">
                        <label for="source_endpoint">Endpoint</label>
                        <input type="text" id="source_endpoint" name="endpoint" placeholder="https://api.example.com">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSourceModal()">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="saveSource()">Подключить</button>
            </div>
        </div>
    </div>

    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        // Навигация по шагам
        function goToStep(step) {
            const projectId = new URLSearchParams(window.location.search).get('project_id');
            const url = projectId
                ? `project_create.php?project_id=${projectId}&step=${step}`
                : `project_create.php?step=${step}`;
            window.location.href = url;
        }

        // Обработка формы шага 1
        const step1Form = document.getElementById('step1-form');

        if (step1Form) {
            step1Form.addEventListener('submit', async function(e) {
                e.preventDefault();

            // Валидация
            const projectName = document.getElementById('project_name').value.trim();
            const projectType = document.getElementById('project_type').value;

            if (!projectName) {
                alert('Введите название проекта');
                return;
            }

            if (!projectType) {
                alert('Выберите тип проекта');
                return;
            }

            // Собираем выбранные чек-листы
            const checklists = [];
            document.querySelectorAll('input[name="checklists[]"]:checked').forEach(cb => {
                checklists.push(parseInt(cb.value));
            });

            // Сохранение через API
            const data = {
                name: projectName,
                description: '', // можно добавить поле description в форму
                project_type: projectType,
                checklists: checklists
            };

            try {
                const response = await fetch('api/projects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    // Переход на шаг 2 с project_id
                    const projectId = result.data?.id || result.project_id;
                    window.location.href = `project_create.php?project_id=${projectId}&step=2`;
                } else {
                    alert('Ошибка: ' + (result.error || 'Не удалось создать проект'));
                }
            } catch (error) {
                console.error('Ошибка сохранения:', error);
                alert('Ошибка подключения к серверу');
            }
            });
        }

        // Шаг 3: Показать/скрыть конфигурацию ИИ
        function toggleAiConfig() {
            const container = document.getElementById('ai-config-container');
            const btn = document.getElementById('create-config-btn');

            if (container.classList.contains('active')) {
                container.classList.remove('active');
                btn.textContent = 'Создать конфигурацию';
            } else {
                container.classList.add('active');
                btn.textContent = 'Скрыть конфигурацию';
            }
        }

        // Баланс слайдер
        const balanceSlider = document.getElementById('balance-slider');
        if (balanceSlider) {
            balanceSlider.addEventListener('input', function() {
                const value = this.value;
                const aiValue = 100 - value;
                document.getElementById('balance-value').textContent = value + '%';
                document.getElementById('balance-value-ai').textContent = aiValue + '%';
            });
        }

        // Модальное окно источника
        function openSourceModal(sourceType) {
            const modal = document.getElementById('source-modal');
            const title = document.getElementById('source-modal-title');
            const sourceInput = document.getElementById('source-type');

            const sourceNames = {
                'amocrm': 'amoCRM',
                'bitrix24': 'Bitrix24',
                'google_drive': 'Google Drive',
                'intrum': 'INTRUM',
                'mango': 'MANGO OFFICE',
                'uiva': 'UIVA'
            };

            title.textContent = 'Подключить ' + sourceNames[sourceType];
            sourceInput.value = sourceType;
            modal.style.display = 'flex';
        }

        function closeSourceModal() {
            document.getElementById('source-modal').style.display = 'none';
        }

        function saveSource() {
            const sourceType = document.getElementById('source-type').value;
            const apiKey = document.getElementById('source_api_key').value;
            const endpoint = document.getElementById('source_endpoint').value;

            // TODO: Сохранение через API
            console.log('Saving source:', { sourceType, apiKey, endpoint });

            alert('Источник данных подключен!');
            closeSourceModal();
        }

        // Загрузка чек-листов через API
        function loadChecklists() {
            // TODO: Загрузка через API
            // fetch('api/checklists.php')
            //     .then(res => res.json())
            //     .then(data => { ... });
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            // loadChecklists();
        });
    </script>

    <!-- project_create.js использует SPA подход, несовместимый с PHP multi-step формой -->
    <!-- <script src="assets/js/project_create.js?v=<?php echo time(); ?>"></script> -->
</body>
</html>
