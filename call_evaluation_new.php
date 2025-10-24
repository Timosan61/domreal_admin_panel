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
    <title>Оценка звонка - Система оценки звонков</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/wavesurfer.js@7"></script>
</head>
<body>
    <!-- Левая боковая панель -->
    <aside class="sidebar">
        <div class="sidebar-toggle">
            <button id="sidebar-toggle-btn" class="sidebar-toggle-btn" title="Свернуть/развернуть меню">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
        <nav class="sidebar-menu">
            <a href="index_new.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <span class="sidebar-menu-text">Звонки</span>
            </a>
            <a href="#" class="sidebar-menu-item">
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
        <!-- Топ-бар с основной информацией -->
        <header class="eval-header">
            <div class="eval-header-left">
                <div class="eval-info-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span id="call-datetime">Загрузка...</span>
                </div>
                <div class="eval-info-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <span id="client-phone">Загрузка...</span>
                </div>
            </div>
            <div class="eval-header-right">
                <button class="btn btn-icon" title="Настройки">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m-6-6h6m6 0h-6"></path>
                    </svg>
                </button>
                <button class="btn btn-icon" title="Закрыть" onclick="window.location.href='index_new.php'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Аудио-плеер с waveform -->
        <div class="audio-player-section">
            <div class="audio-controls">
                <button class="audio-btn" id="play-pause-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                </button>
                <button class="audio-btn" id="prev-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="19 20 9 12 19 4 19 20"></polygon>
                        <line x1="5" y1="19" x2="5" y2="5" stroke="currentColor" stroke-width="2"></line>
                    </svg>
                </button>
                <button class="audio-btn" id="next-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 4 15 12 5 20 5 4"></polygon>
                        <line x1="19" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="2"></line>
                    </svg>
                </button>
                <div class="audio-speed">
                    <select id="playback-speed">
                        <option value="0.5">0.5x</option>
                        <option value="1" selected>1x</option>
                        <option value="1.5">1.5x</option>
                        <option value="2">2x</option>
                    </select>
                </div>
                <div class="audio-label" id="audio-label">Запись #1</div>
            </div>
            <div class="waveform-container">
                <div id="waveform"></div>
                <div class="audio-time">
                    <span id="current-time">00:00</span>
                    <span id="total-time">00:00</span>
                </div>
            </div>
        </div>

        <!-- Трёхколоночная компоновка -->
        <div class="eval-content">
            <!-- Левая панель: Поля, Теги, Комментарии -->
            <aside class="eval-left-panel">
                <div class="panel-section">
                    <div class="panel-section-header" onclick="toggleSection(this)">
                        <h3>Поля</h3>
                        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    <div class="panel-section-content">
                        <div class="field-item">
                            <label>Проект:</label>
                            <div class="field-value" id="field-project">
                                <select class="field-select">
                                    <option>Битрикс</option>
                                </select>
                            </div>
                        </div>
                        <div class="field-item">
                            <label>Направление:</label>
                            <div class="field-value" id="field-direction">Исходящий</div>
                        </div>
                        <div class="field-item">
                            <label>CRM Сделка:</label>
                            <div class="field-value">
                                <a href="#" class="field-link" id="field-crm-deal">../deal/details/67088/</a>
                            </div>
                        </div>
                        <div class="field-item">
                            <label>CRM Контакт:</label>
                            <div class="field-value">
                                <a href="#" class="field-link" id="field-crm-contact">../nfact/details/91696/</a>
                            </div>
                        </div>
                        <div class="field-item">
                            <label>Скачать звонок:</label>
                            <div class="field-value">
                                <a href="#" class="field-link" id="download-link">https://crm.fabrik...</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel-section">
                    <div class="panel-section-header" onclick="toggleSection(this)">
                        <h3>Теги</h3>
                        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    <div class="panel-section-content">
                        <button class="btn btn-tag">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                <line x1="7" y1="7" x2="7.01" y2="7"></line>
                            </svg>
                            Теги
                        </button>
                    </div>
                </div>

                <div class="panel-section">
                    <div class="panel-section-header" onclick="toggleSection(this)">
                        <h3>Комментарии</h3>
                        <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    <div class="panel-section-content">
                        <p class="empty-state">Нет комментариев</p>
                    </div>
                </div>
            </aside>

            <!-- Центральная панель: Чеклист -->
            <div class="eval-center-panel">
                <div class="panel-section">
                    <div class="panel-section-header">
                        <h3>Нет ошибок</h3>
                    </div>
                    <div class="panel-section-content">
                        <div class="checklist-header">
                            <h4>Чек-лист:</h4>
                            <span class="checklist-badge" id="checklist-score">0/0</span>
                        </div>
                        <div id="checklist-container">
                            <!-- Чеклист будет заполнен через JS -->
                        </div>
                        <div class="eval-actions">
                            <button class="btn btn-primary btn-lg" id="save-evaluation">Сохранить оценку</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Правая панель: Транскрипция -->
            <div class="eval-right-panel">
                <div class="panel-section">
                    <div class="panel-section-header">
                        <h3>Транскрипция разговора</h3>
                    </div>
                    <div class="panel-section-content">
                        <div id="transcript-container">
                            <p class="loading">Загрузка транскрипции...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/call_evaluation_new.js"></script>
    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
</body>
</html>
