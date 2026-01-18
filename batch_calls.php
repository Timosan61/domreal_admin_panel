<?php
session_start();
require_once 'auth/session.php';
checkAuth(); // Проверка авторизации

// Получаем batch_id из URL
$batch_id = isset($_GET['batch_id']) ? trim($_GET['batch_id']) : '';
if (empty($batch_id)) {
    header('Location: batch_analysis.php');
    exit();
}

// Получаем информацию о батче для заголовка
include_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$batch_name = 'Пакет';
if ($db) {
    $stmt = $db->prepare("SELECT batch_name FROM batch_analysis_jobs WHERE batch_id = :batch_id AND org_id = :org_id");
    $stmt->execute([':batch_id' => $batch_id, ':org_id' => $_SESSION['org_id'] ?? 'org-legacy']);
    $batch = $stmt->fetch();
    if ($batch && !empty($batch['batch_name'])) {
        $batch_name = $batch['batch_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Звонки пакета: <?php echo htmlspecialchars($batch_name); ?> - AILOCA</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/table-sort.css?v=<?php echo time(); ?>">
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
            <h1>Звонки пакета: <?php echo htmlspecialchars($batch_name); ?></h1>
            <button class="btn-settings" id="columns-settings-btn" title="Настроить отображение колонок">
                Настроить колонки
            </button>
        </header>

        <!-- Breadcrumb для возврата к пакетам -->
        <div class="analytics-breadcrumb" style="display: block; margin-bottom: 20px;">
            <a href="batch_analysis.php" class="breadcrumb-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Назад к пакетам
            </a>
        </div>

        <!-- Панель фильтров -->
        <div class="filters-panel">
            <form id="filters-form">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Менеджер</label>
                        <div class="multiselect" id="manager-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown d-none">
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
                        <label for="call_id">ID звонка</label>
                        <input type="text" id="call_id" name="call_id" placeholder="Введите ID">
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
                        <label>Результат</label>
                        <div class="multiselect" id="result-multiselect">
                            <div class="multiselect-trigger">
                                <span class="multiselect-value">—</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <div class="multiselect-dropdown d-none">
                                <div class="multiselect-header">
                                    <input type="text" class="multiselect-search" placeholder="Поиск">
                                    <div class="multiselect-header-buttons">
                                        <button type="button" class="multiselect-select-all">Выбрать все</button>
                                        <button type="button" class="multiselect-clear">Сбросить</button>
                                    </div>
                                </div>
                                <div class="multiselect-options">
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="successful">
                                        <span>✅ Успешный</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="call_results[]" value="unsuccessful">
                                        <span>❌ Неуспешный</span>
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
                            <div class="multiselect-dropdown d-none">
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
                                        <span>Высокая (60-100%)</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="ratings[]" value="medium">
                                        <span>Средняя (30-60%)</span>
                                    </label>
                                    <label class="multiselect-option">
                                        <input type="checkbox" name="ratings[]" value="low">
                                        <span>Низкая (0-30%)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Применить</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="button" id="reset-filters" class="btn btn-secondary w-100">Сбросить</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Таблица звонков -->
        <div class="table-container">
            <table class="calls-table" id="calls-table">
                <thead>
                    <tr>
                        <th class="col-checkbox" data-column-id="checkbox">
                            <input type="checkbox" id="select-all-calls" title="Выбрать все">
                        </th>
                        <th class="col-tag" data-sort="tag_type" data-column-id="tag">Тег</th>
                        <th data-sort="employee_name" data-column-id="manager">Менеджер</th>
                        <th data-sort="is_successful" data-column-id="result">Результат</th>
                        <!-- Динамические заголовки чеклистов (заполняется JS) -->
                        <th id="compliance-headers-placeholder" data-column-id="compliance"></th>
                        <th data-sort="success_score" data-column-id="success_score">Динамика сделки</th>
                        <th data-sort="summary_text" data-column-id="summary">Резюме</th>
                        <th data-sort="solvency_level" data-column-id="solvency">Платежеспособность</th>
                        <th data-sort="started_at_utc" data-column-id="datetime">Дата и время</th>
                        <th data-sort="duration_sec" data-column-id="duration">Длина</th>
                        <th data-sort="client_phone" data-column-id="phone">Номер</th>
                        <th data-sort="crm_step_name" data-column-id="crm">CRM</th>
                        <th data-column-id="actions">Действия</th>
                        <th data-sort="is_first_call" data-column-id="call_type">Тип звонка</th>
                        <th data-sort="department" data-column-id="department">Отдел</th>
                        <th data-sort="direction" data-column-id="direction">Направление</th>
                    </tr>
                </thead>
                <tbody id="calls-tbody">
                    <tr>
                        <td colspan="16" class="loading">Загрузка данных...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Sticky horizontal scrollbar -->
        <div class="sticky-scrollbar-wrapper d-none" id="sticky-scrollbar-wrapper">
            <div class="sticky-scrollbar-inner" id="sticky-scrollbar-inner"></div>
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
    <div class="global-audio-player d-none" id="global-audio-player">
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
    <div class="bulk-actions-bar d-none" id="bulk-actions-bar">
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
    <div class="modal d-none" id="tag-modal">
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

    <!-- Передаём batch_id в JS -->
    <script>
        const BATCH_ID = '<?php echo htmlspecialchars($batch_id, ENT_QUOTES); ?>';
    </script>
    <script src="https://unpkg.com/wavesurfer.js@7"></script>
    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/multiselect.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/bulk_actions.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/table-sort.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/calls_list.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/column_manager.js?v=<?php echo time(); ?>"></script>
</body>
</html>
