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
</head>
<body>
    <!-- Левая боковая панель -->
    <aside class="sidebar">
        <div class="sidebar-toggle">
            <button id="sidebar-toggle-btn" class="sidebar-toggle-btn" title="Свернуть/развернуть меню">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
        <nav class="sidebar-menu">
            <a href="index_new.php" class="sidebar-menu-item active">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <span class="sidebar-menu-text">Звонки</span>
            </a>
            <a href="analytics.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="20" x2="12" y2="10"></line>
                    <line x1="18" y1="20" x2="18" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="16"></line>
                </svg>
                <span class="sidebar-menu-text">Аналитика</span>
            </a>
            <a href="tags.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <span class="sidebar-menu-text">Теги</span>
            </a>
            <a href="#" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span class="sidebar-menu-text">Менеджеры</span>
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="admin_users.php" class="sidebar-menu-item" style="color: #dc3545;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 15v5m-3 0h6M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                </svg>
                <span class="sidebar-menu-text">ADMIN</span>
            </a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></div>
                <a href="auth/logout.php" style="font-size: 12px; color: #6c757d; text-decoration: none;">Выйти</a>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <!-- Заголовок страницы -->
        <header class="page-header">
            <h1>Звонки</h1>
            <div class="page-header-actions">
                <button class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Скачать в Excel
                </button>
                <button class="btn btn-primary">Сохраненные фильтры</button>
            </div>
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
                        <label for="call_type">Первый звонок</label>
                        <select id="call_type" name="call_type">
                            <option value="">—</option>
                            <option value="first_call">Да</option>
                            <option value="other">Нет</option>
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
                        <div style="opacity: 0; pointer-events: none;"></div>
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
                                        <input type="checkbox" name="call_results[]" value="показ">
                                        <span>🏠 Показ</span>
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
                    <div class="filter-group"></div>
                    <div class="filter-group"></div>
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
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all-calls" title="Выбрать все">
                        </th>
                        <th style="width: 50px;">Тег</th>
                        <th data-sort="employee_name">Менеджер <span class="sort-icon">↕</span></th>
                        <th>Результат</th>
                        <th data-sort="script_compliance_score">Оценка <span class="sort-icon">↕</span></th>
                        <th>Резюме</th>
                        <th data-sort="started_at_utc">Дата и время <span class="sort-icon">↓</span></th>
                        <th data-sort="direction">Направление <span class="sort-icon">↕</span></th>
                        <th data-sort="duration_sec">Длина <span class="sort-icon">↕</span></th>
                        <th>Номер</th>
                        <th>Действия</th>
                        <th>Тип звонка</th>
                        <th data-sort="department">Отдел <span class="sort-icon">↕</span></th>
                    </tr>
                </thead>
                <tbody id="calls-tbody">
                    <tr>
                        <td colspan="13" class="loading">Загрузка данных...</td>
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

    <script src="https://unpkg.com/wavesurfer.js@7"></script>
    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/multiselect.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/bulk_actions.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/calls_list.js?v=<?php echo time(); ?>"></script>
</body>
</html>
