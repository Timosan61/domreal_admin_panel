<?php
session_start();
require_once 'auth/session.php';
checkAuth(true); // Только для администраторов
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Playground - A/B Testing LLM</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/playground.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Sidebar -->
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
            <a href="playground.php" class="sidebar-menu-item active">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 6v6l4 2"></path>
                </svg>
                <span class="sidebar-menu-text">🧪 Playground</span>
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
        <header class="page-header">
            <h1>🧪 Playground - A/B Testing LLM Models</h1>
            <div class="page-header-actions">
                <span id="analysis-status" class="badge badge-secondary">Готов к работе</span>
            </div>
        </header>

        <!-- Фильтры -->
        <div class="filters-container" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">

                <!-- Дата -->
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Дата звонков</label>
                    <input type="date" id="filter-date" value="2025-10-22" class="form-control" style="width: 100%;">
                </div>

                <!-- Лимит -->
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Количество звонков</label>
                    <input type="number" id="filter-limit" value="20" min="5" max="100" class="form-control" style="width: 100%;">
                </div>

                <!-- Выбор моделей -->
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Модели для сравнения</label>
                    <div style="display: flex; gap: 15px; margin-top: 8px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="model-gigachat" value="gigachat" checked style="margin-right: 5px;">
                            GigaChat
                        </label>
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="model-openai" value="openai" checked style="margin-right: 5px;">
                            OpenAI
                        </label>
                        <label style="display: flex; align-items: center; cursor: pointer; opacity: 0.5;">
                            <input type="checkbox" id="model-gigachat-pro" value="gigachat_pro" disabled style="margin-right: 5px;">
                            GigaChat Pro <span class="badge badge-secondary" style="margin-left: 5px;">Soon</span>
                        </label>
                    </div>
                </div>

                <!-- Кнопки -->
                <div style="display: flex; gap: 10px;">
                    <button id="btn-load-calls" class="btn btn-secondary">
                        Загрузить звонки
                    </button>
                    <button id="btn-start-analysis" class="btn btn-primary" disabled>
                        ▶ Запустить анализ
                    </button>
                </div>
            </div>

            <!-- Прогресс бар -->
            <div id="progress-container" style="display: none; margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span id="progress-text">Анализ...</span>
                    <span id="progress-percent">0%</span>
                </div>
                <div style="background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden;">
                    <div id="progress-bar" style="background: #007bff; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
            </div>
        </div>

        <!-- Таблица результатов -->
        <div class="results-container" style="background: white; padding: 20px; border-radius: 8px;">
            <h3 style="margin-bottom: 15px;">Результаты сравнения</h3>
            <div id="results-table-container" style="overflow-x: auto;">
                <table id="results-table" class="table table-striped" style="display: none;">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Дата/время</th>
                            <th>Телефон</th>
                            <th>Менеджер</th>
                            <th>Длительность</th>
                            <th colspan="2">Production</th>
                            <th colspan="4">GigaChat</th>
                            <th colspan="4">OpenAI</th>
                        </tr>
                        <tr class="sub-header">
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th>Резюме</th>
                            <th>Результат</th>
                            <th>Резюме</th>
                            <th>Результат</th>
                            <th>Успех</th>
                            <th>Скрипт%</th>
                            <th>Резюме</th>
                            <th>Результат</th>
                            <th>Успех</th>
                            <th>Скрипт%</th>
                        </tr>
                    </thead>
                    <tbody id="results-tbody">
                        <!-- Заполняется через JavaScript -->
                    </tbody>
                </table>
                <div id="results-empty" style="text-align: center; padding: 40px; color: #6c757d;">
                    👆 Загрузите звонки и запустите анализ для начала работы
                </div>
            </div>
        </div>

        <!-- Агрегации клиентов -->
        <div class="aggregations-container" id="aggregations-container" style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px; display: none;">
            <h3 style="margin-bottom: 15px;">Агрегированные резюме клиентов</h3>
            <div id="aggregations-list">
                <!-- Заполняется через JavaScript -->
            </div>
        </div>
    </div>

    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/playground.js?v=<?php echo time(); ?>"></script>
</body>
</html>
