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
</head>
<body>
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
                <a href="index_new.php" class="btn btn-secondary">← Назад к списку</a>
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
