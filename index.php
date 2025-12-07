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
    <title>Звонки - Система оценки звонков</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Динамические колонки чеклистов */
        .compliance-column {
            text-align: center;
            min-width: 80px;
        }

        /* Форматирование compliance значений */
        .compliance-value {
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .compliance-high {
            color: #10b981;
            background-color: #d1fae5;
        }

        .compliance-medium {
            color: #f59e0b;
            background-color: #fef3c7;
        }

        .compliance-low {
            color: #ef4444;
            background-color: #fee2e2;
        }

        .compliance-na {
            color: #9ca3af;
        }

        /* Кнопка настройки колонок */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-settings {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
        }

        .btn-settings:hover {
            background: #e0e0e0;
            border-color: #ccc;
        }

        /* Модальное окно настройки колонок */
        .columns-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .columns-modal.active {
            display: flex;
        }

        .columns-modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .columns-modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .columns-modal-header h2 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .columns-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .columns-modal-close:hover {
            background: #f5f5f5;
            color: #333;
        }

        .columns-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .columns-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .column-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .column-item:hover {
            background: #f9f9f9;
            border-color: #2196F3;
        }

        .column-item input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
        }

        .column-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .column-item.disabled:hover {
            background: white;
            border-color: #e0e0e0;
        }

        .columns-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .btn-reset {
            padding: 10px 20px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        .btn-apply {
            padding: 10px 24px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-apply:hover {
            background: #1976D2;
        }
    </style>
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
            <h1>Звонки</h1>
            <button class="btn-settings" id="columns-settings-btn" title="Настроить отображение колонок">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 1v6m0 6v6m5.2-15.8l-4.2 4.2m0 6l4.2 4.2M23 12h-6m-6 0H1m15.8 5.2l-4.2-4.2m0-6l-4.2-4.2"></path>
                </svg>
                Настроить колонки
            </button>
        </header>

        <!-- Breadcrumb для возврата к аналитике -->
        <div class="analytics-breadcrumb" id="analytics-breadcrumb" style="display: none;">
            <a href="analytics.php" class="breadcrumb-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Вернуться к аналитике
            </a>
        </div>

        <!-- Панель фильтров -->
        <div class="filters-panel">
            <form id="filters-form">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Отдел</label>
                        <div class="multiselect" id="department-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <!-- Будет заполнено динамически -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>Менеджер</label>
                        <div class="multiselect" id="manager-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <!-- Будет заполнено динамически -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label for="client_phone">Номер клиента</label>
                        <input type="text" id="client_phone" name="client_phone" placeholder="">
                    </div>
                    <div class="filter-group">
                        <label for="call_type">Тип звонка</label>
                        <select id="call_type" name="call_type">
                            <option value="">Все</option>
                            <option value="first_call">1️⃣ Первичный</option>
                            <option value="repeat_call">🔁 Повторный</option>
                            <option value="failed_call">⏱️ Несостоявшийся (≤30 сек)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date_from">Дата звонка</label>
                        <input type="date" id="date_from" name="date_from">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <input type="date" id="date_to" name="date_to">
                    </div>
                    <div class="filter-group">
                        <label for="duration_range">Длительность звонка</label>
                        <select id="duration_range" name="duration_range">
                            <option value="">Любая</option>
                            <option value="0-60">До 1 мин</option>
                            <option value="60-180">1-3 мин</option>
                            <option value="180-600">3-10 мин</option>
                            <option value="600-1800">10-30 мин</option>
                            <option value="1800-999999">Более 30 мин</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="toggle-filter-wrapper">
                            <label class="toggle-switch">
                                <input type="checkbox" id="hide-short-calls" name="hide_short_calls" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label">Скрыть до 10 сек</span>
                        </div>
                    </div>
                </div>

                <div class="filters-row">
                    <div class="filter-group">
                        <label>Направление звонка</label>
                        <div class="multiselect" id="direction-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="directions[]" value="INBOUND">
                                        <span>Входящий</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="directions[]" value="OUTBOUND">
                                        <span>Исходящий</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>Результат</label>
                        <div class="multiselect" id="result-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <!-- Первый звонок -->
                                    <div class="multiselect-group-header">Первый звонок</div>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="квалификация">
                                        <span>📋 Квалификация</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="материалы">
                                        <span>📤 Материалы отправлены</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="назначен перезвон">
                                        <span>📞 Назначен перезвон</span>
                                    </label>

                                    <!-- Другие звонки -->
                                    <div class="multiselect-group-header">Другие звонки</div>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="показ назначен">
                                        <span>📅 Показ назначен</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="показ состоялся">
                                        <span>🏠 Показ состоялся</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="показ">
                                        <span>🔍 Показ (все)</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="перезвон">
                                        <span>⏰ Перезвон</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="думает">
                                        <span>💭 Думает</span>
                                    </label>

                                    <!-- Общие -->
                                    <div class="multiselect-group-header">Общие</div>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="отказ">
                                        <span>❌ Отказ</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="не целевой">
                                        <span>⛔ Не целевой</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="не дозвонились">
                                        <span>📵 Не дозвонились</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="личный">
                                        <span>👤 Личный</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>Оценка</label>
                        <div class="multiselect" id="rating-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="ratings[]" value="high">
                                        <span>Высокая (80-100%)</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="ratings[]" value="medium">
                                        <span>Средняя (60-79%)</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="ratings[]" value="low">
                                        <span>Низкая (0-59%)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>Теги</label>
                        <div class="multiselect" id="tags-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <!-- Будет заполнено динамически -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>CRM Этап</label>
                        <div class="multiselect" id="crm-stages-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <!-- Будет заполнено динамически -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>Платежеспособность</label>
                        <div class="multiselect" id="solvency-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="solvency_levels[]" value="green">
                                        <span>🟢 Высокая (>10%)</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="solvency_levels[]" value="blue">
                                        <span>🔵 Средняя (5-10%)</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="solvency_levels[]" value="yellow">
                                        <span>🟡 Низкая (-5 до 5%)</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="solvency_levels[]" value="red">
                                        <span>🔴 Очень низкая (<-5%)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>Статус клиента</label>
                        <div class="multiselect" id="client-status-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <optgroup label="🟢 Активные">
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Квалификация">
                                            <span>Квалификация</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Подбор объектов">
                                            <span>Подбор объектов</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Показ назначен">
                                            <span>Показ назначен</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Показ состоялся">
                                            <span>Показ состоялся</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Переговоры">
                                            <span>Переговоры</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Перезвон назначен">
                                            <span>Перезвон назначен</span>
                                        </label>
                                    </optgroup>
                                    <optgroup label="🔵 Ожидание">
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Думает">
                                            <span>Думает</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Ипотека в процессе">
                                            <span>Ипотека в процессе</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Долгосрочный интерес">
                                            <span>Долгосрочный интерес</span>
                                        </label>
                                    </optgroup>
                                    <optgroup label="🟡 Проблемные">
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Не дозвонились">
                                            <span>Не дозвонились</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Несоответствие бюджета">
                                            <span>Несоответствие бюджета</span>
                                        </label>
                                    </optgroup>
                                    <optgroup label="🔴 Закрытые">
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Отказ">
                                            <span>Отказ</span>
                                        </label>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="client_statuses[]" value="Не целевой">
                                            <span>Не целевой</span>
                                        </label>
                                    </optgroup>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Применить</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="button" id="reset-filters" class="btn btn-secondary" style="width: 100%;">Сбросить</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Таблица звонков -->
        <div class="table-container">
            <table class="calls-table" id="calls-table">
                <thead>
                    <tr>
                        <th style="width: 40px;" data-column-id="checkbox">
                            <input type="checkbox" id="select-all-calls" title="Выбрать все">
                        </th>
                        <th style="width: 50px;" data-column-id="tag">Тег</th>
                        <th data-sort="employee_name" data-column-id="manager">Менеджер <span class="sort-icon">↕</span></th>
                        <th data-column-id="result">Результат</th>
                        <!-- Динамические заголовки чеклистов (заполняется JS) -->
                        <th id="compliance-headers-placeholder" data-column-id="compliance"></th>
                        <th data-column-id="summary">Резюме</th>
                        <th data-column-id="solvency">Платежеспособность</th>
                        <th data-sort="started_at_utc" data-column-id="datetime">Дата и время <span class="sort-icon">↓</span></th>
                        <th data-sort="duration_sec" data-column-id="duration">Длина <span class="sort-icon">↕</span></th>
                        <th data-column-id="phone">Номер</th>
                        <th data-column-id="crm">CRM</th>
                        <th data-column-id="actions">Действия</th>
                        <th data-column-id="call_type">Тип звонка</th>
                        <th data-sort="department" data-column-id="department">Отдел <span class="sort-icon">↕</span></th>
                        <th data-sort="direction" data-column-id="direction">Направление <span class="sort-icon">↕</span></th>
                    </tr>
                </thead>
                <tbody id="calls-tbody">
                    <tr>
                        <td colspan="16" class="loading">Загрузка данных...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Пагинация и статистика -->
        <div class="table-footer">
            <div class="table-stats">
                <span>Показано <strong id="stat-page">0</strong> из <strong id="stat-total">0</strong> звонков</span>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- Глобальный аудиоплеер -->
    <div class="global-audio-player" id="global-audio-player" style="display: none;">
        <div class="player-container">
            <div class="player-info">
                <span class="player-label">Звонок:</span>
                <span id="player-callid" class="player-value">-</span>
                <span class="player-separator">|</span>
                <span id="player-employee" class="player-value">-</span>
                <span class="player-arrow">→</span>
                <span id="player-client" class="player-value">-</span>
            </div>

            <div class="player-controls">
                <button class="audio-btn" id="global-play-btn" title="Play/Pause">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                </button>

                <div class="waveform-wrapper">
                    <div id="global-waveform"></div>
                    <div class="player-time">
                        <span id="player-current-time">0:00</span>
                        <span id="player-total-time">0:00</span>
                    </div>
                </div>

                <div class="volume-control">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                        <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                    </svg>
                    <input type="range" id="volume-slider" min="0" max="100" value="80" title="Громкость">
                </div>

                <div class="speed-control">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <select id="global-speed" title="Скорость воспроизведения">
                        <option value="0.5">0.5x</option>
                        <option value="0.75">0.75x</option>
                        <option value="1" selected>1x</option>
                        <option value="1.25">1.25x</option>
                        <option value="1.5">1.5x</option>
                        <option value="2">2x</option>
                    </select>
                </div>

                <button class="player-close" id="player-close-btn" title="Закрыть плеер">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Панель массовых действий -->
    <div class="bulk-actions-bar" id="bulk-actions-bar" style="display: none;">
        <div class="bulk-actions-container">
            <div class="bulk-actions-info">
                <span>Выбрано: <strong id="selected-count">0</strong></span>
            </div>
            <div class="bulk-actions-buttons">
                <button type="button" class="bulk-action-btn bulk-action-good" id="bulk-tag-good" title="Хорошо">
                    <span class="bulk-action-icon">✅</span>
                    <span class="bulk-action-text">Хорошо</span>
                </button>
                <button type="button" class="bulk-action-btn bulk-action-bad" id="bulk-tag-bad" title="Плохо">
                    <span class="bulk-action-icon">❌</span>
                    <span class="bulk-action-text">Плохо</span>
                </button>
                <button type="button" class="bulk-action-btn bulk-action-question" id="bulk-tag-question" title="Вопрос">
                    <span class="bulk-action-icon">❓</span>
                    <span class="bulk-action-text">Вопрос</span>
                </button>
                <button type="button" class="bulk-action-btn bulk-action-problem" id="bulk-tag-problem" title="Проблемный">
                    <span class="bulk-action-icon">⚠️</span>
                    <span class="bulk-action-text">Проблемный</span>
                </button>
                <button type="button" class="bulk-action-btn bulk-action-remove" id="bulk-remove-tags" title="Снять теги">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    <span class="bulk-action-text">Снять теги</span>
                </button>
            </div>
            <button type="button" class="bulk-actions-close" id="bulk-actions-close" title="Очистить выбор">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- Модальное окно для тегов -->
    <div class="modal" id="tag-modal" style="display: none;">
        <div class="modal-overlay" id="tag-modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="tag-modal-title">Добавить тег</h3>
                <button type="button" class="modal-close" id="tag-modal-close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="tag-note">Заметка (опционально)</label>
                    <textarea id="tag-note" rows="4" placeholder="Введите дополнительную заметку к тегу..."></textarea>
                </div>
                <div class="modal-info">
                    <p>Тег будет применен к <strong id="tag-modal-count">0</strong> звонку(ам)</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="tag-modal-cancel">Отмена</button>
                <button type="button" class="btn btn-primary" id="tag-modal-submit">Применить тег</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно настройки колонок -->
    <div class="columns-modal" id="columns-modal">
        <div class="columns-modal-content">
            <div class="columns-modal-header">
                <h2>Настройка отображения колонок</h2>
                <button class="columns-modal-close" id="columns-modal-close">&times;</button>
            </div>
            <div class="columns-modal-body">
                <div class="columns-list" id="columns-list">
                    <!-- Будет заполнено через JavaScript -->
                </div>
            </div>
            <div class="columns-modal-footer">
                <button class="btn-reset" id="columns-reset-btn">Сбросить по умолчанию</button>
                <button class="btn-apply" id="columns-apply-btn">Применить</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/wavesurfer.js@7"></script>
    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/multiselect.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/bulk_actions.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/calls_list.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/column_manager.js?v=<?php echo time(); ?>"></script>
</body>
</html>
