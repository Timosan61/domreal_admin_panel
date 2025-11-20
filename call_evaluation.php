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
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/assets/css/emotion_display.css?v=<?php echo time(); ?>">
    <script src="/assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher Button -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <!-- Левая боковая панель -->
    <aside class="sidebar">
        <nav class="sidebar-menu">
            <a href="index_new.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                Звонки
            </a>
            <a href="#" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                Теги
            </a>
            <a href="#" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Менеджеры
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="money_tracker.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                <span class="sidebar-menu-text">Money Tracker</span>
            </a>
            <a href="admin_users.php" class="sidebar-menu-item" style="color: #dc3545;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 15v5m-3 0h6M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                </svg>
                ADMIN
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
        <header class="page-header">
            <div class="header-nav">
                <?php
                // Получаем returnState для сохранения состояния фильтров
                $returnState = isset($_GET['returnState']) ? htmlspecialchars($_GET['returnState']) : '';
                $backURL = 'index_new.php' . $returnState;
                ?>
                <a href="<?= $backURL ?>" class="btn btn-secondary">← Назад к списку</a>
                <h1>🎯 Оценка звонка</h1>
            </div>
        </header>

        <!-- Основная информация о звонке -->
        <div class="call-info-panel" id="call-info">
            <div class="loading">Загрузка данных...</div>
        </div>

        <!-- Аудиоплеер -->
        <div class="audio-panel">
            <div class="evaluation-audio-player" id="evaluation-audio-player">
                <div class="player-container">
                    <div class="player-info">
                        <span class="player-label">Звонок:</span>
                        <span id="eval-player-callid" class="player-value">-</span>
                        <span class="player-separator">|</span>
                        <span id="eval-player-employee" class="player-value">-</span>
                        <span class="player-arrow">→</span>
                        <span id="eval-player-client" class="player-value">-</span>
                    </div>

                    <div class="player-controls">
                        <button class="audio-btn" id="eval-play-btn" title="Play/Pause">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                        </button>

                        <div class="waveform-wrapper">
                            <div id="eval-waveform"></div>
                            <div class="player-time">
                                <span id="eval-current-time">0:00</span>
                                <span id="eval-total-time">0:00</span>
                            </div>
                        </div>

                        <div class="volume-control">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                            </svg>
                            <input type="range" id="eval-volume-slider" min="0" max="100" value="80" title="Громкость">
                        </div>

                        <div class="speed-control">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <select id="eval-speed" title="Скорость воспроизведения">
                                <option value="0.5">0.5x</option>
                                <option value="0.75">0.75x</option>
                                <option value="1" selected>1x</option>
                                <option value="1.25">1.25x</option>
                                <option value="1.5">1.5x</option>
                                <option value="2">2x</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Двухколоночная компоновка: Транскрипция слева, Чеклист справа -->
        <div class="evaluation-layout">
            <!-- Транскрипция с диаризацией -->
            <div class="transcript-panel">
                <h2>📝 Транскрипция разговора</h2>
                <div class="transcript-container" id="transcript">
                    <div class="loading">Загрузка транскрипции...</div>
                </div>
            </div>

            <!-- Чеклист для оценки -->
            <div class="checklist-panel">
                <h2>✅ Чеклист для оценки</h2>
                <div id="checklist-container">
                    <div class="loading">Загрузка чеклиста...</div>
                </div>
                <div class="compliance-score" id="compliance-score">
                    <!-- Общая оценка будет здесь -->
                </div>
            </div>
        </div>

        <!-- Результат анализа -->
        <div class="analysis-panel">
            <h2>🤖 Анализ звонка (AI)</h2>
            <div id="analysis-result">
                <div class="loading">Загрузка результатов анализа...</div>
            </div>
        </div>

        <!-- Гибридный анализ эмоций -->
        <div class="analysis-panel" id="emotion-analysis-container">
            <!-- Emotion display component будет загружен здесь -->
        </div>
    </div>

    <script src="https://unpkg.com/wavesurfer.js@7"></script>
    <script src="/assets/js/call_evaluation.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/emotion_display.js?v=<?php echo time(); ?>"></script>
</body>
</html>
