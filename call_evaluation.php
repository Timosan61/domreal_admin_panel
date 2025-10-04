<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оценка звонка - Система оценки звонков</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div class="header-nav">
                <a href="index.php" class="btn btn-secondary">← Назад к списку</a>
                <h1>🎯 Оценка звонка</h1>
            </div>
        </header>

        <!-- Основная информация о звонке -->
        <div class="call-info-panel" id="call-info">
            <div class="loading">Загрузка данных...</div>
        </div>

        <!-- Аудиоплеер -->
        <div class="audio-panel">
            <h2>🎧 Аудиозапись звонка</h2>
            <div id="audio-player-container">
                <audio id="audio-player" controls controlsList="nodownload">
                    <source id="audio-source" src="" type="audio/mpeg">
                    Ваш браузер не поддерживает аудио элемент.
                </audio>
                <div class="audio-waveform" id="waveform">
                    <!-- Визуализация волны будет здесь -->
                </div>
                <div class="audio-controls">
                    <button id="play-pause" class="btn btn-primary">▶ Воспроизвести</button>
                    <span id="current-time">00:00</span> / <span id="total-time">00:00</span>
                    <input type="range" id="seek-bar" value="0" min="0" max="100" step="0.1">
                    <input type="range" id="volume-bar" value="100" min="0" max="100" step="1" title="Громкость">
                </div>
            </div>
        </div>

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

        <!-- Результат анализа -->
        <div class="analysis-panel">
            <h2>🤖 Анализ звонка (AI)</h2>
            <div id="analysis-result">
                <div class="loading">Загрузка результатов анализа...</div>
            </div>
        </div>
    </div>

    <script src="assets/js/call_evaluation.js"></script>
</body>
</html>
