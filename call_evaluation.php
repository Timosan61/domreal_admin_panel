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
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/assets/css/call_analysis_block.css?v=<?php echo time(); ?>">
    <script src="/assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher Button -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header page-header-with-info">
            <div class="header-left">
                <?php
                // Определяем URL для кнопки "Назад"
                if (isset($_GET['from']) && $_GET['from'] === 'batch' && isset($_GET['batch_id'])) {
                    $backURL = 'batch_calls.php?batch_id=' . urlencode($_GET['batch_id']);
                } else {
                    $returnState = isset($_GET['returnState']) ? htmlspecialchars($_GET['returnState']) : '';
                    $backURL = 'index_new.php' . $returnState;
                }
                ?>
                <a href="<?= $backURL ?>" class="btn btn-primary btn-back">← Назад</a>
                <h1>Оценка звонка</h1>
            </div>
            <div class="header-right" id="call-info">
                <div class="loading-inline">Загрузка...</div>
            </div>
        </header>

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
                <h2 class="panel-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    Транскрипция разговора
                </h2>
                <div class="transcript-container" id="transcript">
                    <div class="loading">Загрузка транскрипции...</div>
                </div>
            </div>

            <!-- Чеклист для оценки -->
            <div class="checklist-panel">
                <h2 class="panel-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4"></path>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                    Чеклист для оценки
                    <a href="#" id="template-name-btn" class="template-name-btn" target="_blank" title="Открыть настройки чек-листа" style="display: none;">
                        <span id="template-name-text"></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                    </a>
                </h2>
                <div id="checklist-container">
                    <div class="loading">Загрузка чеклиста...</div>
                </div>
                <div class="compliance-score" id="compliance-score">
                    <!-- Общая оценка будет здесь -->
                </div>
            </div>
        </div>

        <!-- Блок анализа звонка (резюме, соотношение речи, перебивания, эмоции) -->
        <div id="emotion-analysis-container">
            <!-- CallAnalysisBlock будет загружен здесь -->
        </div>

    </div>

    <script src="https://unpkg.com/wavesurfer.js@7"></script>
    <script src="/assets/js/call_analysis_block.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/call_evaluation.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
</body>
</html>
