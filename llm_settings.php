<?php
session_start();
require_once 'auth/session.php';
checkAuth(true); // Требуется роль администратора
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки LLM - Admin Panel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        body.sidebar-collapsed .main-content {
            margin-left: 70px;
        }

        .settings-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }

        .settings-card h2 {
            margin: 0 0 24px 0;
            font-size: 24px;
            font-weight: 600;
            color: #212529;
        }

        .settings-card h3 {
            margin: 24px 0 16px 0;
            font-size: 18px;
            font-weight: 600;
            color: #495057;
        }

        .setting-row {
            margin-bottom: 24px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .setting-row:last-child {
            margin-bottom: 0;
        }

        .setting-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: #212529;
            margin-bottom: 8px;
        }

        .setting-description {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .setting-select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            background: white;
            color: #212529;
            cursor: pointer;
            transition: all 0.2s;
        }

        .setting-select:hover {
            border-color: #007bff;
        }

        .setting-select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .save-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 32px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 24px;
        }

        .save-button:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .save-button:active {
            transform: translateY(0);
        }

        .save-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .mode-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .mode-cloud {
            background: #007bff;
            color: white;
        }

        .mode-local {
            background: #28a745;
            color: white;
        }

        .back-link {
            display: inline-block;
            color: #007bff;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher Button -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
    <div class="settings-container">
        <a href="index_new.php" class="back-link">← Вернуться к списку звонков</a>

        <div class="settings-card">
            <h2>⚙️ Настройки LLM моделей</h2>

            <div class="alert alert-info">
                <strong>Важно:</strong> Эти настройки определяют, какая LLM модель будет использоваться для анализа звонков.
                <br>• <strong>Cloud</strong> (GigaChat/Yandex) — высокая точность, требует API ключи
                <br>• <strong>Local</strong> (DeepSeek-R1) — приватность, экономия токенов, работает на GPU
            </div>

            <div id="alert-container"></div>

            <form id="llm-settings-form">
                <div class="setting-row">
                    <label class="setting-label">
                        🎯 Первые звонки (новые клиенты)
                        <span id="first-badge" class="mode-badge mode-cloud">Cloud</span>
                    </label>
                    <p class="setting-description">
                        Режим для анализа первого контакта с клиентом. Рекомендуется <strong>cloud</strong> для максимальной точности скрипта.
                    </p>
                    <select name="llm_mode_first_call" id="llm_mode_first_call" class="setting-select">
                        <option value="cloud">☁️ Cloud (GigaChat / Yandex GPT) — Высокая точность</option>
                        <option value="local">🖥️ Local (DeepSeek-R1 14B) — Приватность + экономия</option>
                    </select>
                </div>

                <div class="setting-row">
                    <label class="setting-label">
                        🔁 Повторные звонки (существующие клиенты)
                        <span id="repeat-badge" class="mode-badge mode-local">Local</span>
                    </label>
                    <p class="setting-description">
                        Режим для анализа последующих контактов. Рекомендуется <strong>local</strong> для экономии токенов (проще задача).
                    </p>
                    <select name="llm_mode_repeat_call" id="llm_mode_repeat_call" class="setting-select">
                        <option value="cloud">☁️ Cloud (GigaChat / Yandex GPT) — Высокая точность</option>
                        <option value="local">🖥️ Local (DeepSeek-R1 14B) — Приватность + экономия</option>
                    </select>
                </div>

                <button type="submit" class="save-button" id="save-button">
                    💾 Сохранить настройки
                </button>
            </form>
        </div>
    </div>

    <script>
        // Load current settings on page load
        async function loadSettings() {
            try {
                const response = await fetch('api/llm_settings.php');
                const data = await response.json();

                if (data.success) {
                    const settings = data.settings;

                    // Set select values
                    document.getElementById('llm_mode_first_call').value = settings.first_call || 'cloud';
                    document.getElementById('llm_mode_repeat_call').value = settings.repeat_call || 'local';

                    // Update badges
                    updateBadges();
                } else {
                    showAlert('Ошибка загрузки настроек: ' + (data.error || 'Неизвестная ошибка'), 'danger');
                }
            } catch (error) {
                console.error('Error loading settings:', error);
                showAlert('Ошибка загрузки настроек', 'danger');
            }
        }

        // Save settings
        document.getElementById('llm-settings-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const saveButton = document.getElementById('save-button');
            saveButton.disabled = true;
            saveButton.textContent = '⏳ Сохранение...';

            const formData = new FormData(e.target);
            const settings = {
                first_call: formData.get('llm_mode_first_call'),
                repeat_call: formData.get('llm_mode_repeat_call')
            };

            try {
                const response = await fetch('api/llm_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(settings)
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('✅ Настройки успешно сохранены! Изменения вступят в силу для новых анализов.', 'success');
                    updateBadges();
                } else {
                    showAlert('❌ Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'), 'danger');
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                showAlert('❌ Ошибка сохранения настроек', 'danger');
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = '💾 Сохранить настройки';
            }
        });

        // Update badges based on selection
        function updateBadges() {
            const firstSelect = document.getElementById('llm_mode_first_call');
            const repeatSelect = document.getElementById('llm_mode_repeat_call');
            const firstBadge = document.getElementById('first-badge');
            const repeatBadge = document.getElementById('repeat-badge');

            // Update first call badge
            firstBadge.textContent = firstSelect.value === 'cloud' ? 'Cloud' : 'Local';
            firstBadge.className = 'mode-badge ' + (firstSelect.value === 'cloud' ? 'mode-cloud' : 'mode-local');

            // Update repeat call badge
            repeatBadge.textContent = repeatSelect.value === 'cloud' ? 'Cloud' : 'Local';
            repeatBadge.className = 'mode-badge ' + (repeatSelect.value === 'cloud' ? 'mode-cloud' : 'mode-local');
        }

        // Show alert message
        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;

            // Auto-hide after 5 seconds
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        // Listen to select changes
        document.getElementById('llm_mode_first_call').addEventListener('change', updateBadges);
        document.getElementById('llm_mode_repeat_call').addEventListener('change', updateBadges);

        // Load settings on page load
        loadSettings();
    </script>
    </div><!-- .main-content -->

    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
</body>
</html>
