<?php
session_start();
require_once 'auth/session.php';

// ВАЖНО: Доступ только для администраторов!
checkAuth($require_admin = true);

$user_full_name = $_SESSION['full_name'] ?? 'Администратор';
$user_role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💰 Money Tracker - Domreal Admin</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher Button -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <div class="money-tracker-page">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="money-tracker-content">
            <!-- Header with Filters -->
            <div class="money-tracker-header">
                <!-- Batch Selector -->
                <div class="mt-batch-selector">
                    <div class="mt-batch-selector-row">
                        <label class="font-semibold m-0 text-nowrap">📦 Мои загрузки:</label>
                        <select id="batch-selector" class="mt-batch-selector select">
                            <option value="">Все записи (без фильтра)</option>
                            <!-- Options loaded dynamically via JS -->
                        </select>
                        <button id="batch-details-btn" class="btn btn-secondary text-nowrap">
                            📊 Детали загрузки
                        </button>
                        <button class="btn btn-success text-nowrap" id="add-numbers-btn">➕ Добавить номера</button>
                        <button class="btn btn-success text-nowrap" id="add-file-btn">📎 Добавить файл</button>
                    </div>
                </div>

                <!-- Worker Status Indicator -->
                <div id="worker-status-panel" class="mt-worker-status">
                    <!-- Status Indicator -->
                    <div class="d-flex align-center gap-2">
                        <div id="worker-status-led" class="mt-worker-led"></div>
                        <span class="font-semibold text-sm" id="worker-status-text">Проверка worker...</span>
                    </div>

                    <!-- Queue Info -->
                    <div id="worker-queue-info" class="d-none mt-worker-info">
                        Очередь: <strong id="worker-queue-count">0</strong> записей
                    </div>

                    <!-- Processing Speed -->
                    <div id="worker-speed-info" class="d-none mt-worker-info">
                        Скорость: <strong id="worker-speed-value">0</strong> зап/мин
                    </div>

                    <!-- Active Batches -->
                    <div id="worker-batches-info" class="d-none mt-worker-info">
                        Активных загрузок: <strong id="worker-batches-count">0</strong>
                    </div>

                    <!-- Error Display -->
                    <div id="worker-error-container" class="d-none mt-worker-error">
                        <div class="mt-worker-error-title">⚠️ Проблемы с worker:</div>
                        <ul id="worker-error-list" class="mt-worker-error-list">
                            <!-- Errors populated by JS -->
                        </ul>
                    </div>

                    <!-- Last Update -->
                    <div class="ml-auto text-xs text-muted" id="worker-last-update">
                        Обновлено: никогда
                    </div>
                </div>

                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="date_from">Дата создания с:</label>
                        <input type="date" id="date_from" value="">
                    </div>

                    <div class="filter-group">
                        <label for="date_to">Дата создания по:</label>
                        <input type="date" id="date_to" value="">
                    </div>

                    <div class="filter-group">
                        <label for="enriched_date_from">Дата обогащения с:</label>
                        <input type="date" id="enriched_date_from" value="">
                    </div>

                    <div class="filter-group">
                        <label for="enriched_date_to">Дата обогащения по:</label>
                        <input type="date" id="enriched_date_to" value="">
                    </div>

                    <div class="filter-group">
                        <label for="status_filter">Статус обогащения:</label>
                        <select id="status_filter">
                            <option value="">Все статусы</option>
                            <option value="completed">Завершено</option>
                            <option value="in_progress">В процессе</option>
                            <option value="error">Ошибка</option>
                            <option value="pending">Ожидание</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="inn_filter">Наличие ИНН:</label>
                        <select id="inn_filter">
                            <option value="">Все записи</option>
                            <option value="yes">Только с ИНН</option>
                            <option value="no">Без ИНН</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="phone_search">Поиск по телефону:</label>
                        <input type="text" id="phone_search" placeholder="+79001234567">
                    </div>

                    <div class="filter-group">
                        <label for="webhook_source_filter">Источник данных:</label>
                        <select id="webhook_source_filter">
                            <option value="">Все источники</option>
                            <option value="gck">🟦 GCK Webhook</option>
                            <option value="calls">📞 Beeline Звонки</option>
                        </select>
                    </div>

                    <div class="filter-group grid-full-width">
                        <div class="mt-solvency-checkboxes">
                            <label class="mt-solvency-checkbox-label">
                                <input type="checkbox" class="solvency-level-checkbox cursor-pointer" value="green">
                                <span>🟢 Низкая (до 10 млн)</span>
                            </label>
                            <label class="mt-solvency-checkbox-label">
                                <input type="checkbox" class="solvency-level-checkbox cursor-pointer" value="blue">
                                <span>🔵 Средняя (до 100 млн)</span>
                            </label>
                            <label class="mt-solvency-checkbox-label">
                                <input type="checkbox" class="solvency-level-checkbox cursor-pointer" value="yellow">
                                <span>🟡 Высокая (до 500 млн)</span>
                            </label>
                            <label class="mt-solvency-checkbox-label">
                                <input type="checkbox" class="solvency-level-checkbox cursor-pointer" value="red">
                                <span>🔴 Очень высокая (до 2 млрд)</span>
                            </label>
                            <label class="mt-solvency-checkbox-label">
                                <input type="checkbox" class="solvency-level-checkbox cursor-pointer" value="purple">
                                <span>🟣 Премиальная (свыше 2 млрд)</span>
                            </label>
                        </div>
                    </div>

                    <div class="filter-actions grid-full-width d-flex gap-2 align-center">
                        <button class="btn btn-primary" id="apply-filters">Применить</button>
                        <button class="btn btn-secondary" id="reset-filters">Сбросить</button>
                        <button class="btn btn-success" id="export-filtered-btn">📊 Экспортировать XLSX</button>
                    </div>
                </div>
            </div>

            <!-- Stats & Table Body -->
            <div class="money-tracker-body">
                <!-- Bulk Actions Panel (hidden by default) -->
                <div id="bulk-actions-panel" class="d-none mt-bulk-actions">
                    <div class="mt-bulk-actions-row">
                        <span class="mt-bulk-actions-count">
                            <span id="selected-count">0</span> выбрано
                        </span>
                        <button class="btn btn-primary btn-sm" id="export-selected-btn">📊 Экспорт выбранных</button>
                        <button class="btn btn-danger btn-sm" id="delete-selected-btn">🗑️ Удалить выбранные</button>
                        <button class="btn btn-secondary btn-sm" id="deselect-all-btn">✖ Снять выделение</button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-cards d-none" id="stats-cards-container">
                    <div class="stat-card">
                        <div class="stat-card-title">Всего записей</div>
                        <div class="stat-card-value" id="stat-total">-</div>
                        <div class="stat-card-subtitle">в базе данных</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-title">ИНН найдено</div>
                        <div class="stat-card-value" id="stat-inn">-</div>
                        <div class="stat-card-subtitle">процент покрытия</div>
                    </div>
                    <div class="stat-card border-left-success">
                        <div class="stat-card-title">🟢 Низкая (до 10 млн)</div>
                        <div class="stat-card-value" id="stat-solvency-green">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-green-pct">-</div>
                    </div>
                    <div class="stat-card border-left-primary">
                        <div class="stat-card-title">🔵 Средняя (до 100 млн)</div>
                        <div class="stat-card-value" id="stat-solvency-blue">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-blue-pct">-</div>
                    </div>
                    <div class="stat-card border-left-warning">
                        <div class="stat-card-title">🟡 Высокая (до 500 млн)</div>
                        <div class="stat-card-value" id="stat-solvency-yellow">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-yellow-pct">-</div>
                    </div>
                    <div class="stat-card border-left-danger">
                        <div class="stat-card-title">🔴 Очень высокая (до 2 млрд)</div>
                        <div class="stat-card-value" id="stat-solvency-red">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-red-pct">-</div>
                    </div>
                    <div class="stat-card border-left-purple">
                        <div class="stat-card-title">🟣 Премиальная (свыше 2 млрд)</div>
                        <div class="stat-card-value" id="stat-solvency-purple">-</div>
                        <div class="stat-card-subtitle" id="stat-solvency-purple-pct">-</div>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-container">
                    <div class="table-header">
                        <div class="mt-table-info">
                            Показано: <span id="showing-count">0</span> из <span id="total-count">0</span>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table id="enrichment-table">
                            <thead>
                                <tr>
                                    <th class="text-center w-40">
                                        <input type="checkbox" id="select-all-checkbox" class="cursor-pointer" title="Выбрать все">
                                    </th>
                                    <th class="sortable" data-sort="id">ID</th>
                                    <th class="sortable" data-sort="client_phone">Телефон</th>
                                    <th class="w-150">Источник</th>
                                    <th class="sortable" data-sort="inn">ИНН</th>
                                    <th class="w-80">Компаний</th>
                                    <th class="w-150">Выручка (₽)</th>
                                    <th class="w-150">Прибыль (₽)</th>
                                    <th class="sortable" data-sort="solvency_level">Платежеспособность</th>
                                    <th class="sortable" data-sort="enrichment_status">Статус</th>
                                    <th class="sortable" data-sort="created_at">Создано</th>
                                    <th class="sortable" data-sort="updated_at">Обновлено</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="enrichment-tbody">
                                <!-- Заполняется через JS -->
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination">
                        <div class="pagination-info">
                            Страница <span id="current-page">1</span> из <span id="total-pages">1</span>
                        </div>
                        <div class="pagination-controls">
                            <button class="pagination-btn" id="first-page">Первая</button>
                            <button class="pagination-btn" id="prev-page">← Назад</button>
                            <span id="page-numbers"></span>
                            <button class="pagination-btn" id="next-page">Вперед →</button>
                            <button class="pagination-btn" id="last-page">Последняя</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal" id="detail-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Детали обогащения</h2>
                <button class="modal-close" id="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modal-body">
                <!-- Заполняется через JS -->
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal" id="import-modal">
        <div class="modal-content max-w-600">
            <div class="modal-header">
                <h2 class="modal-title">📥 Добавить номера телефонов</h2>
                <button class="modal-close" id="import-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mt-form-group mb-6">
                    <label for="import-batch-name" class="mt-form-label">
                        📦 Название загрузки:
                    </label>
                    <input
                        type="text"
                        id="import-batch-name"
                        placeholder="Клиенты Москва январь 2025"
                        class="mt-form-input"
                        required
                    />
                    <small class="mt-form-hint">
                        Введите понятное название для этой загрузки, чтобы позже легко найти её в списке
                    </small>
                </div>
                <p class="mt-form-text">
                    Вставьте список телефонных номеров (по одному на строку или через запятую):
                </p>
                <textarea
                    id="import-phones-textarea"
                    placeholder="+79001234567&#10;+79001234568&#10;+79001234569"
                    class="mt-form-textarea"
                ></textarea>
                <div class="mt-info-box">
                    <strong>Форматы номеров:</strong> +79001234567, 89001234567, 9001234567
                </div>
                <div class="mt-form-actions">
                    <button class="btn btn-secondary" id="import-cancel-btn">Отмена</button>
                    <button class="btn btn-success" id="import-submit-btn">➕ Добавить в базу</button>
                </div>
            </div>
        </div>
    </div>

    <!-- File Upload Modal -->
    <div class="modal" id="file-upload-modal">
        <div class="modal-content max-w-700">
            <div class="modal-header">
                <h2 class="modal-title">📎 Загрузить файл с номерами</h2>
                <button class="modal-close" id="file-upload-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Batch Name -->
                <div class="mt-form-group">
                    <label for="file-batch-name" class="mt-form-label">
                        Название загрузки:
                    </label>
                    <input
                        type="text"
                        id="file-batch-name"
                        placeholder="Клиенты Москва январь 2025"
                        class="mt-form-input"
                    >
                </div>

                <!-- File Input -->
                <div class="mt-form-group">
                    <label for="file-input" class="mt-form-label">
                        Выберите файл:
                    </label>
                    <input
                        type="file"
                        id="file-input"
                        accept=".xlsx,.xls,.csv,.txt"
                        class="mt-form-input"
                    >
                    <div class="mt-file-hint">
                        Поддерживаемые форматы: Excel (.xlsx, .xls), CSV (.csv), текстовый файл (.txt)
                    </div>
                </div>

                <!-- Column Selection (hidden initially) -->
                <div id="column-selection-container" class="d-none mt-form-group">
                    <label for="phone-column-select" class="mt-form-label">
                        Выберите столбец с телефонами:
                    </label>
                    <select id="phone-column-select" class="mt-form-input">
                        <!-- Options will be populated by JS -->
                    </select>
                </div>

                <!-- Phone Preview -->
                <div id="phone-preview-container" class="d-none mt-form-group">
                    <div class="font-semibold mb-2">
                        Найдено номеров: <span id="phone-count" class="text-success">0</span>
                    </div>
                    <div class="mt-phone-preview">
                        <div id="phone-preview-list" class="mt-phone-preview-list">
                            <!-- Phone numbers will be populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mt-info-box bg-warning-light rounded">
                    <strong>💡 Форматы номеров:</strong><br>
                    Система автоматически распознает номера в форматах:<br>
                    +79001234567, 89001234567, 79001234567, 9001234567
                </div>

                <!-- Actions -->
                <div class="mt-form-actions">
                    <button class="btn btn-secondary" id="file-upload-cancel-btn">Отмена</button>
                    <button class="btn btn-success" id="file-upload-submit-btn" disabled>➕ Добавить в базу</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div class="modal" id="progress-modal">
        <div class="modal-content max-w-700">
            <div class="modal-header">
                <h2 class="modal-title">⏳ Обработка номеров</h2>
                <button class="modal-close d-none" id="progress-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mb-6">
                    <div class="d-flex justify-between mb-2 text-sm">
                        <span class="text-muted">Прогресс:</span>
                        <span class="font-semibold text-primary">
                            <span id="progress-processed">0</span> / <span id="progress-total">0</span>
                        </span>
                    </div>
                    <div class="mt-progress-bar-container">
                        <div id="progress-bar" class="mt-progress-bar" style="width: 0%;">
                            0%
                        </div>
                    </div>
                </div>

                <div class="mt-progress-log">
                    <div id="progress-log">
                        <div class="text-muted">Ожидание запуска...</div>
                    </div>
                </div>

                <div class="mt-form-actions">
                    <button class="btn btn-secondary d-none" id="progress-close-btn">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Details Modal -->
    <div class="modal" id="batch-details-modal">
        <div class="modal-content max-w-900">
            <div class="modal-header">
                <h2 class="modal-title">📊 Детали загрузки</h2>
                <button class="modal-close" id="batch-details-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Batch Info Header -->
                <div class="mt-batch-details-header">
                    <h3 id="batch-details-name" class="m-0 mb-4 text-lg">-</h3>
                    <div class="mt-batch-details-grid">
                        <div>
                            <strong class="text-muted">Дата создания:</strong><br>
                            <span id="batch-details-created">-</span>
                        </div>
                        <div>
                            <strong class="text-muted">Автор:</strong><br>
                            <span id="batch-details-author">-</span>
                        </div>
                        <div>
                            <strong class="text-muted">Статус:</strong><br>
                            <span id="batch-details-status-badge" class="badge">-</span>
                        </div>
                        <div>
                            <strong class="text-muted">Завершено:</strong><br>
                            <span id="batch-details-completed">-</span>
                        </div>
                    </div>
                </div>

                <!-- Progress Section -->
                <div class="mb-6">
                    <h4 class="m-0 mb-4 text-base">📈 Прогресс обработки</h4>
                    <div class="mt-batch-progress-cards">
                        <div class="mt-batch-progress-card bg-info-light">
                            <div class="mt-batch-progress-card-value text-primary" id="batch-details-total">-</div>
                            <div class="mt-batch-progress-card-label">Всего записей</div>
                        </div>
                        <div class="mt-batch-progress-card bg-success-light">
                            <div class="mt-batch-progress-card-value text-success" id="batch-details-processed">-</div>
                            <div class="mt-batch-progress-card-label">Обработано</div>
                        </div>
                        <div class="mt-batch-progress-card" style="background: #d1ecf1;">
                            <div class="mt-batch-progress-card-value" style="color: #17a2b8;" id="batch-details-completed-count">-</div>
                            <div class="mt-batch-progress-card-label">Успешно</div>
                        </div>
                        <div class="mt-batch-progress-card bg-danger-light">
                            <div class="mt-batch-progress-card-value text-danger" id="batch-details-error">-</div>
                            <div class="mt-batch-progress-card-label">Ошибки</div>
                        </div>
                        <div class="mt-batch-progress-card bg-warning-light">
                            <div class="mt-batch-progress-card-value text-warning" id="batch-details-pending">-</div>
                            <div class="mt-batch-progress-card-label">Ожидают</div>
                        </div>
                        <div class="mt-batch-progress-card" style="background: #e2e3e5;">
                            <div class="mt-batch-progress-card-value text-muted" id="batch-details-inn">-</div>
                            <div class="mt-batch-progress-card-label">ИНН найдено</div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-batch-progress-bar">
                        <div id="batch-details-progress-bar" class="mt-batch-progress-bar-fill" style="width: 0%;">
                            0%
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-flex gap-3 justify-end pt-4 border-top">
                    <button id="batch-details-export-btn" class="btn btn-success">
                        📥 Экспортировать XLSX
                    </button>
                    <button class="btn btn-secondary" id="batch-details-close-btn">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Scripts -->
    <!-- SheetJS Library for Excel file parsing -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/fetch_retry.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/money_tracker.js?v=<?php echo time(); ?>"></script>
</body>
</html>
