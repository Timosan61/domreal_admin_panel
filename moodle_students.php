<?php
require_once 'auth/session.php';
checkAuth();

$user_full_name = $_SESSION['full_name'] ?? 'Пользователь';
$user_role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Студенты Moodle - AILOCA Admin</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="moodle-students-page">
        <div class="moodle-students-content">
            <!-- Header -->
            <div class="students-header">
                <h1>🎓 Студенты курса "База знаний AILOCA"</h1>
                <p>Мониторинг прогресса и отправка рекомендаций студентам</p>
                <div class="students-actions">
                    <button id="btn-send-webhook" class="btn btn-primary">
                        📤 Отправить рекомендацию
                    </button>
                    <button id="btn-refresh" class="btn btn-secondary">
                        🔄 Обновить данные
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="students">Студенты Moodle</button>
                    <button class="tab-btn" data-tab="hrbot">🤖 HR Bot Кандидаты</button>
                    <button class="tab-btn" data-tab="webhooks">История Webhooks</button>
                    <button class="tab-btn" data-tab="blocks">Content Blocks</button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="students-body">
                <!-- Tab: Students (Moodle) -->
                <div id="tab-students" class="tab-content active">
                    <div id="students-table-container">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Загрузка студентов Moodle...</p>
                        </div>
                    </div>
                </div>

                <!-- Tab: HR Bot Candidates -->
                <div id="tab-hrbot" class="tab-content">
                    <div id="hrbot-stats" class="moodle-stats-margin">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Загрузка статистики HR Bot...</p>
                        </div>
                    </div>
                    <div id="hrbot-table-container">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Загрузка кандидатов HR Bot...</p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Webhooks -->
                <div id="tab-webhooks" class="tab-content">
                    <div id="webhook-stats" class="moodle-stats-margin">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Загрузка статистики...</p>
                        </div>
                    </div>
                    <div id="webhook-history-table-container">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Загрузка истории...</p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Content Blocks -->
                <div id="tab-blocks" class="tab-content">
                    <div class="blocks-search">
                        <input type="text" id="block-search" placeholder="🔍 Поиск блока по названию или anchor_id...">
                    </div>
                    <div id="content-blocks-tree">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Загрузка content blocks...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Student Details -->
    <div id="modal-student-details" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Детали студента</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div id="student-details-body">
                <!-- Динамически заполняется из JS -->
            </div>
        </div>
    </div>

    <!-- Modal: HR Bot Candidate Details -->
    <div id="modal-hrbot-details" class="modal">
        <div class="modal-content moodle-modal-wide">
            <div class="modal-header">
                <h3>🤖 Детали кандидата HR Bot</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div id="hrbot-details-body">
                <!-- Динамически заполняется из JS -->
            </div>
        </div>
    </div>

    <!-- Modal: Send Webhook -->
    <div id="modal-send-webhook" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Отправить рекомендацию студенту</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div id="send-webhook-body">
                <form id="form-send-webhook">
                    <div class="form-group">
                        <label>Студент</label>
                        <select id="webhook-user-id" required>
                            <option value="">Выберите студента...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Content Block</label>
                        <input type="text" id="webhook-anchor-search" placeholder="Начните вводить название блока..." autocomplete="off">
                        <input type="hidden" id="webhook-anchor-id" required>
                        <div id="anchor-suggestions" class="moodle-anchor-suggestions"></div>
                    </div>
                    <div class="form-group">
                        <label>Приоритет</label>
                        <select id="webhook-priority" required>
                            <option value="medium">Средний</option>
                            <option value="high">Высокий</option>
                            <option value="low">Низкий</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Причина (опционально)</label>
                        <textarea id="webhook-reason" placeholder="Например: Низкий балл в тесте"></textarea>
                    </div>
                    <div class="moodle-modal-footer">
                        <button type="button" class="btn btn-secondary close-modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Отправить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <span id="toast-message"></span>
    </div>

    <script src="assets/js/moodle_students.js?v=20251213"></script>
</body>
</html>
