<?php
session_start();
require_once 'auth/session.php';

// Access only for administrators
checkAuth($require_admin = true);

$user_full_name = $_SESSION['full_name'] ?? 'Administrator';
$user_role = $_SESSION['role'];

// Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get webhook statistics
$stats_query = "
    SELECT
        COUNT(*) as total_webhooks,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
        SUM(phones_count) as total_phones,
        AVG(processing_time_ms) as avg_processing_time
    FROM webhook_log
    WHERE source = 'gck'
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get enrichment statistics for webhook sources
$enrichment_stats_query = "
    SELECT
        COUNT(*) as total_records,
        SUM(CASE WHEN enrichment_status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN enrichment_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN google_sheets_exported = 1 THEN 1 ELSE 0 END) as exported
    FROM client_enrichment
    WHERE webhook_source = 'gck'
";
$enrichment_stats_stmt = $db->prepare($enrichment_stats_query);
$enrichment_stats_stmt->execute();
$enrichment_stats = $enrichment_stats_stmt->fetch();

// Get settings
$settings_query = "SELECT setting_key, setting_value FROM money_tracker_settings";
$settings_stmt = $db->prepare($settings_query);
$settings_stmt->execute();
$settings_results = $settings_stmt->fetchAll();
$settings = [];
foreach ($settings_results as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get recent webhook events with enrichment status
$recent_webhooks_query = "
    SELECT
        wl.id,
        wl.source,
        wl.raw_payload,
        wl.phone_extracted,
        wl.phones_count,
        wl.status,
        wl.error_message,
        wl.enrichment_ids,
        wl.batch_id,
        wl.ip_address,
        wl.user_agent,
        wl.processing_time_ms,
        wl.created_at,
        COALESCE(SUM(CASE WHEN ce.enrichment_status = 'completed' THEN 1 ELSE 0 END), 0) as completed_count,
        COALESCE(SUM(CASE WHEN ce.enrichment_status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
        COALESCE(SUM(CASE WHEN ce.enrichment_status = 'error' THEN 1 ELSE 0 END), 0) as error_count,
        COALESCE(SUM(CASE WHEN ce.google_sheets_exported = 1 THEN 1 ELSE 0 END), 0) as exported_count,
        COALESCE(COUNT(ce.id), 0) as total_records
    FROM webhook_log wl
    LEFT JOIN client_enrichment ce ON FIND_IN_SET(ce.id, REPLACE(wl.enrichment_ids, ' ', '')) > 0
    GROUP BY wl.id
    ORDER BY wl.created_at DESC
    LIMIT 20
";
$recent_webhooks_stmt = $db->prepare($recent_webhooks_query);
$recent_webhooks_stmt->execute();
$recent_webhooks = $recent_webhooks_stmt->fetchAll();

// Debug: log query results
error_log("[Money Tracker Webhook] Loaded " . count($recent_webhooks) . " webhook events");

// Production webhook URL
$webhook_url = "http://195.239.161.77:18080/api/webhook_gck_money_tracker.php";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки Webhook - Money Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .webhook-page {
            display: flex;
            height: 100vh;
            overflow: hidden;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }

        body.sidebar-collapsed .webhook-page {
            margin-left: 70px;
        }

        .webhook-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 2rem;
        }

        .webhook-header {
            margin-bottom: 2rem;
        }

        .webhook-header h1 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .webhook-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 0.875rem;
            color: #666;
            margin: 0 0 0.5rem 0;
            text-transform: uppercase;
            font-weight: 500;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }

        .stat-card.success .stat-value {
            color: #28a745;
        }

        .stat-card.error .stat-value {
            color: #dc3545;
        }

        .stat-card.pending .stat-value {
            color: #ffc107;
        }

        .config-section {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .config-section h2 {
            font-size: 1.25rem;
            color: #333;
            margin: 0 0 1.5rem 0;
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group input[type="url"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            font-family: monospace;
        }

        .form-group .form-help {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .copy-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }

        .copy-button:hover {
            background: #0056b3;
        }

        .copy-button.copied {
            background: #28a745;
        }

        .webhook-log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .webhook-log-table th {
            background: #f8f9fa;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            font-size: 0.875rem;
        }

        .webhook-log-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9rem;
        }

        .webhook-log-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.error {
            background: #f8d7da;
            color: #721c24;
        }

        .instructions-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 1.5rem;
            margin-top: 1rem;
            border-radius: 4px;
        }

        .instructions-box h4 {
            margin: 0 0 1rem 0;
            color: #004085;
        }

        .instructions-box ol {
            margin: 0;
            padding-left: 1.5rem;
        }

        .instructions-box li {
            margin-bottom: 0.75rem;
            color: #004085;
        }

        .instructions-box code {
            background: #fff;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-family: monospace;
            color: #d63384;
        }

        .test-button {
            background: #ffc107;
            color: #000;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            margin-top: 1rem;
        }

        .test-button:hover {
            background: #e0a800;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #007bff;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .refresh-button {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 1rem;
        }

        .refresh-button:hover {
            background: #138496;
        }

        .view-payload-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .view-payload-btn:hover {
            background: #0056b3;
        }

        #payload-modal {
            display: none;
        }

        #payload-modal.show {
            display: flex !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="webhook-page">
        <div class="webhook-content">
            <a href="money_tracker.php" class="back-link">← Назад к Money Tracker</a>

            <div class="webhook-header">
                <h1>Конфигурация и Мониторинг Webhook</h1>
                <p>GCK webhook приёмник для Money Tracker с экспортом в Google Sheets
                    <button class="refresh-button" onclick="location.reload()">Обновить Статистику</button>
                </p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Всего Webhooks</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_webhooks'] ?? 0); ?></div>
                </div>
                <div class="stat-card success">
                    <h3>Успешных</h3>
                    <div class="stat-value"><?php echo number_format($stats['successful'] ?? 0); ?></div>
                </div>
                <div class="stat-card error">
                    <h3>Ошибок</h3>
                    <div class="stat-value"><?php echo number_format($stats['errors'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Получено Телефонов</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_phones'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Обогащено</h3>
                    <div class="stat-value"><?php echo number_format($enrichment_stats['completed'] ?? 0); ?></div>
                </div>
                <div class="stat-card pending">
                    <h3>В Обработке</h3>
                    <div class="stat-value"><?php echo number_format($enrichment_stats['pending'] ?? 0); ?></div>
                </div>
                <div class="stat-card success">
                    <h3>Экспортировано</h3>
                    <div class="stat-value"><?php echo number_format($enrichment_stats['exported'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Среднее Время</h3>
                    <div class="stat-value"><?php echo round($stats['avg_processing_time'] ?? 0); ?> мс</div>
                </div>
            </div>

            <!-- Webhook URL Configuration -->
            <div class="config-section">
                <h2>Адрес Webhook</h2>
                <div class="form-group">
                    <label>URL для настройки GCK:</label>
                    <div style="display: flex; align-items: center;">
                        <input type="text" id="webhook-url" value="<?php echo htmlspecialchars($webhook_url); ?>" readonly>
                        <button class="copy-button" onclick="copyWebhookUrl()">Копировать</button>
                    </div>
                    <div class="form-help">
                        Вставьте этот URL в настройки webhook GCK (GetCourse)
                    </div>
                </div>

                <div class="instructions-box">
                    <h4>Инструкция по настройке GCK:</h4>
                    <ol>
                        <li>Войдите в админ-панель GCK (GetCourse)</li>
                        <li>Перейдите в "Настройки" → "API и Webhooks"</li>
                        <li>Создайте новый webhook</li>
                        <li>Вставьте URL выше в поле "Webhook URL"</li>
                        <li>Выберите события: "Новый лид", "Отправка формы", "Получен телефон"</li>
                        <li>Сохраните настройки</li>
                        <li>Нажмите кнопку "Тест" ниже для проверки</li>
                    </ol>
                </div>

                <button class="test-button" onclick="testWebhook()">Тестировать Webhook</button>
                <div id="test-result" style="margin-top: 1rem;"></div>
            </div>

            <!-- Webhook Forwarding Settings -->
            <div class="config-section">
                <h2>Настройки переадресации вебхуков</h2>
                <p style="color: #666; margin-bottom: 1.5rem;">
                    Временная функция для дублирования вебхуков на старый URL во время тестирования
                </p>

                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 4px; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" id="forward-enabled" style="width: auto;">
                            <span>Включить переадресацию вебхуков</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>URL для переадресации:</label>
                        <input type="text" id="forward-url" placeholder="https://example.com/webhook">
                        <div class="form-help">
                            Вебхуки будут автоматически дублироваться на этот URL
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Таймаут (секунды):</label>
                        <input type="number" id="forward-timeout" min="1" max="60" value="10" style="width: 100px;">
                        <div class="form-help">
                            Максимальное время ожидания ответа (1-60 секунд)
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button class="test-button" onclick="saveForwardSettings()" style="background: #28a745;">Сохранить настройки</button>
                        <button class="test-button" onclick="testForwarding()">Тест переадресации</button>
                        <button class="test-button" onclick="viewForwardLogs()" style="background: #6c757d;">Просмотр логов</button>
                    </div>
                </div>

                <!-- Forward Logs Display -->
                <div id="forward-logs" style="display: none; background: #f8f9fa; padding: 1.5rem; border-radius: 4px; margin-top: 1rem;">
                    <h3 style="margin-top: 0;">Последние записи лога переадресации</h3>
                    <pre id="forward-logs-content" style="background: #fff; padding: 1rem; border-radius: 4px; max-height: 400px; overflow-y: auto; font-size: 12px;"></pre>
                </div>

                <!-- Test Result Display -->
                <div id="test-result" style="display: none; margin-top: 1rem;"></div>
            </div>

            <!-- Google Sheets Clients Management -->
            <div class="config-section">
                <h2>Управление клиентами Google Sheets</h2>
                <p style="color: #666; margin-bottom: 1.5rem;">
                    Добавьте клиентов и их Google Sheets ID для автоматического экспорта обогащенных телефонов
                </p>

                <!-- Add Client Form -->
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 4px; margin-bottom: 1.5rem;">
                    <h3 style="margin-top: 0;">Добавить нового клиента</h3>
                    <div class="form-group">
                        <label>Название клиента:</label>
                        <input type="text" id="client-name" placeholder="Например: ACME Corp" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div class="form-group">
                        <label>Google Sheets ID:</label>
                        <input type="text" id="sheets-id" placeholder="1abc...xyz (из URL таблицы)" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;">
                        <div class="form-help">
                            Скопируйте из URL: https://docs.google.com/spreadsheets/d/<strong>ВОТ_ЭТА_ЧАСТЬ</strong>/edit
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Заметки (опционально):</label>
                        <textarea id="client-notes" placeholder="Комментарии о клиенте..." style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; min-height: 80px;"></textarea>
                    </div>
                    <button class="test-button" onclick="addClient()" style="background: #28a745;">Добавить клиента</button>
                    <div id="add-client-result" style="margin-top: 1rem;"></div>
                </div>

                <!-- Clients List -->
                <h3>Список клиентов</h3>
                <div id="clients-list" style="margin-top: 1rem;">
                    <p style="color: #666;">Загрузка...</p>
                </div>
            </div>

            <!-- Recent Webhook Events -->
            <div class="config-section">
                <h2>Последние События Webhook</h2>
                <?php if (count($recent_webhooks) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="webhook-log-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Источник</th>
                                <th>Телефоны</th>
                                <th>Статус Webhook</th>
                                <th>Обогащение</th>
                                <th>Экспорт в Sheets</th>
                                <th>Batch ID</th>
                                <th>IP</th>
                                <th>Время (мс)</th>
                                <th>Дата</th>
                                <th>Payload</th>
                                <th>Ошибка</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_webhooks as $webhook):
                                $pending = $webhook['pending_count'] ?? 0;
                                $completed = $webhook['completed_count'] ?? 0;
                                $errors = $webhook['error_count'] ?? 0;
                                $exported = $webhook['exported_count'] ?? 0;
                                $total = $webhook['total_records'] ?? 0;

                                // Determine enrichment status badge
                                if ($total == 0) {
                                    $enrichment_badge = '<span class="status-badge error">Нет записей</span>';
                                } elseif ($errors > 0) {
                                    $enrichment_badge = '<span class="status-badge error">Ошибка (' . $errors . '/' . $total . ')</span>';
                                } elseif ($completed == $total) {
                                    $enrichment_badge = '<span class="status-badge success">Завершено (' . $completed . '/' . $total . ')</span>';
                                } elseif ($pending > 0) {
                                    $enrichment_badge = '<span class="status-badge pending">В обработке (' . $pending . '/' . $total . ')</span>';
                                } else {
                                    $enrichment_badge = '<span class="status-badge">Частично (' . $completed . '/' . $total . ')</span>';
                                }

                                // Determine export status badge
                                if ($total == 0) {
                                    $export_badge = '<span style="color: #999;">—</span>';
                                } elseif ($exported == $total && $completed == $total) {
                                    $export_badge = '<span class="status-badge success">✓ Экспортировано (' . $exported . '/' . $total . ')</span>';
                                } elseif ($exported > 0) {
                                    $export_badge = '<span class="status-badge pending">Частично (' . $exported . '/' . $total . ')</span>';
                                } elseif ($completed == $total) {
                                    $export_badge = '<span class="status-badge pending">Готово к экспорту</span>';
                                } else {
                                    $export_badge = '<span class="status-badge">Ожидание</span>';
                                }
                            ?>
                            <tr>
                                <td><?php echo $webhook['id']; ?></td>
                                <td><?php echo htmlspecialchars($webhook['source']); ?></td>
                                <td><?php echo $webhook['phones_count']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $webhook['status']; ?>">
                                        <?php echo $webhook['status'] === 'success' ? 'Успех' : 'Ошибка'; ?>
                                    </span>
                                </td>
                                <td><?php echo $enrichment_badge; ?></td>
                                <td><?php echo $export_badge; ?></td>
                                <td><?php echo $webhook['batch_id'] ?? '—'; ?></td>
                                <td><?php echo htmlspecialchars($webhook['ip_address'] ?? 'N/A'); ?></td>
                                <td><?php echo $webhook['processing_time_ms']; ?></td>
                                <td style="white-space: nowrap;"><?php echo date('d.m.Y H:i', strtotime($webhook['created_at'])); ?></td>
                                <td>
                                    <button class="view-payload-btn" onclick="showPayload(<?php echo $webhook['id']; ?>, <?php echo htmlspecialchars(json_encode($webhook['raw_payload']), ENT_QUOTES); ?>)">
                                        Показать
                                    </button>
                                </td>
                                <td style="color: #dc3545; font-size: 0.85rem; max-width: 200px; word-wrap: break-word;">
                                    <?php echo $webhook['error_message'] ? htmlspecialchars(substr($webhook['error_message'], 0, 100)) : '—'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="color: #666; padding: 1rem;">Пока нет событий webhook. Отправьте тестовый webhook для начала работы.</p>
                <?php endif; ?>
            </div>

            <!-- Payload Modal -->
            <div id="payload-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
                <div style="background: white; border-radius: 8px; padding: 2rem; max-width: 800px; max-height: 80vh; overflow-y: auto; position: relative;">
                    <button onclick="closePayloadModal()" style="position: absolute; top: 1rem; right: 1rem; background: #dc3545; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">
                        Закрыть
                    </button>
                    <h3 style="margin-bottom: 1rem;">Webhook Payload</h3>
                    <pre id="payload-content" style="background: #f8f9fa; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.85rem; font-family: monospace;"></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyWebhookUrl() {
            const input = document.getElementById('webhook-url');
            input.select();
            document.execCommand('copy');

            const button = event.target;
            button.textContent = 'Скопировано!';
            button.classList.add('copied');

            setTimeout(() => {
                button.textContent = 'Копировать';
                button.classList.remove('copied');
            }, 2000);
        }

        function testWebhook() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.innerHTML = '<p style="color: #666;">Отправка тестового webhook...</p>';

            const testPayload = {
                phones: ['+79001234567'],
                batch_name: 'Тестовый Webhook ' + new Date().toISOString(),
                vid: 'test_' + Date.now(),
                utm: {
                    utm_source: 'test',
                    utm_campaign: 'webhook_test'
                }
            };

            fetch('<?php echo $webhook_url; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(testPayload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 4px; color: #155724;">
                            <strong>Тест успешно выполнен!</strong><br>
                            Добавлено телефонов: ${data.added}<br>
                            ID партии: ${data.batch_id}<br>
                            Время обработки: ${data.processing_time_ms} мс<br>
                            <small>Обновите страницу, чтобы увидеть новое событие в таблице ниже</small>
                        </div>
                    `;
                    // Auto refresh after 2 seconds
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.innerHTML = `
                        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 4px; color: #721c24;">
                            <strong>Тест провален</strong><br>
                            ${data.error || 'Неизвестная ошибка'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 4px; color: #721c24;">
                        <strong>Ошибка подключения</strong><br>
                        ${error.message}
                    </div>
                `;
            });
        }

        // Payload modal functions
        function showPayload(webhookId, rawPayload) {
            const modal = document.getElementById('payload-modal');
            const content = document.getElementById('payload-content');

            try {
                // Try to parse and format JSON
                const parsed = JSON.parse(rawPayload);
                content.textContent = JSON.stringify(parsed, null, 2);
            } catch (e) {
                // If not valid JSON, show as-is
                content.textContent = rawPayload;
            }

            modal.classList.add('show');
        }

        function closePayloadModal() {
            const modal = document.getElementById('payload-modal');
            modal.classList.remove('show');
        }

        // Close modal on outside click
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('payload-modal');
            if (event.target === modal) {
                closePayloadModal();
            }
        });

        // Google Sheets Clients Management Functions
        function loadClients() {
            fetch('api/google_sheets_clients.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderClients(data.clients);
                    } else {
                        document.getElementById('clients-list').innerHTML =
                            `<p style="color: #dc3545;">Ошибка загрузки: ${data.error}</p>`;
                    }
                })
                .catch(error => {
                    document.getElementById('clients-list').innerHTML =
                        `<p style="color: #dc3545;">Ошибка подключения: ${error.message}</p>`;
                });
        }

        function renderClients(clients) {
            if (clients.length === 0) {
                document.getElementById('clients-list').innerHTML =
                    '<p style="color: #666;">Клиенты не найдены. Добавьте первого клиента выше.</p>';
                return;
            }

            let html = '<table class="webhook-log-table"><thead><tr>';
            html += '<th>ID</th><th>Название</th><th>Google Sheets ID</th><th>Статус</th><th>Создан</th><th>Действия</th>';
            html += '</tr></thead><tbody>';

            clients.forEach(client => {
                const statusBadge = client.is_active
                    ? '<span class="status-badge success">Активен</span>'
                    : '<span class="status-badge">Неактивен</span>';

                const shortSheetsId = client.sheets_id.substring(0, 20) + '...';
                const created = new Date(client.created_at).toLocaleString('ru-RU');

                html += `<tr>
                    <td>${client.id}</td>
                    <td>${escapeHtml(client.client_name)}</td>
                    <td title="${escapeHtml(client.sheets_id)}">${escapeHtml(shortSheetsId)}</td>
                    <td>${statusBadge}</td>
                    <td style="white-space: nowrap;">${created}</td>
                    <td>
                        <button onclick="toggleClient(${client.id})" class="copy-button" style="padding: 0.3rem 0.6rem; font-size: 0.85rem; margin-right: 0.5rem;">
                            ${client.is_active ? 'Выключить' : 'Включить'}
                        </button>
                        <button onclick="deleteClient(${client.id}, '${escapeHtml(client.client_name)}')" class="copy-button" style="padding: 0.3rem 0.6rem; font-size: 0.85rem; background: #dc3545;">
                            Удалить
                        </button>
                    </td>
                </tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('clients-list').innerHTML = html;
        }

        function addClient() {
            const name = document.getElementById('client-name').value.trim();
            const sheetsId = document.getElementById('sheets-id').value.trim();
            const notes = document.getElementById('client-notes').value.trim();
            const resultDiv = document.getElementById('add-client-result');

            if (!name || !sheetsId) {
                resultDiv.innerHTML = '<p style="color: #dc3545;">Заполните название и Sheets ID</p>';
                return;
            }

            resultDiv.innerHTML = '<p style="color: #666;">Добавление...</p>';

            fetch('api/google_sheets_clients.php?action=add', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ client_name: name, sheets_id: sheetsId, notes: notes })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<p style="color: #28a745;">Клиент успешно добавлен!</p>';
                    document.getElementById('client-name').value = '';
                    document.getElementById('sheets-id').value = '';
                    document.getElementById('client-notes').value = '';
                    loadClients();
                    setTimeout(() => resultDiv.innerHTML = '', 3000);
                } else {
                    resultDiv.innerHTML = `<p style="color: #dc3545;">Ошибка: ${data.error}</p>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<p style="color: #dc3545;">Ошибка подключения: ${error.message}</p>`;
            });
        }

        function toggleClient(id) {
            fetch('api/google_sheets_clients.php?action=toggle', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadClients();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            })
            .catch(error => {
                alert('Ошибка подключения: ' + error.message);
            });
        }

        function deleteClient(id, name) {
            if (!confirm(`Удалить клиента "${name}"?\n\nЭто действие нельзя отменить.`)) {
                return;
            }

            fetch('api/google_sheets_clients.php?action=delete', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadClients();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            })
            .catch(error => {
                alert('Ошибка подключения: ' + error.message);
            });
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Load clients on page load
            loadClients();
            const toggleBtn = document.getElementById('sidebar-toggle-btn');
            const sidebar = document.querySelector('.sidebar');
            const body = document.body;

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    body.classList.toggle('sidebar-collapsed');
                    // Save state to localStorage
                    if (body.classList.contains('sidebar-collapsed')) {
                        localStorage.setItem('sidebarCollapsed', 'true');
                    } else {
                        localStorage.setItem('sidebarCollapsed', 'false');
                    }
                });
            }

            // Restore sidebar state from localStorage
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
            }
        });

        // ========================================
        // Webhook Forwarding Functions
        // ========================================

        // Load forward settings on page load
        function loadForwardSettings() {
            fetch('api/webhook_forward_settings.php?action=get')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const settings = data.settings;
                        document.getElementById('forward-enabled').checked = settings.webhook_forward_enabled?.value === 'true';
                        document.getElementById('forward-url').value = settings.webhook_forward_url?.value || '';
                        document.getElementById('forward-timeout').value = settings.webhook_forward_timeout?.value || '10';
                    }
                })
                .catch(error => console.error('Error loading forward settings:', error));
        }

        // Save forward settings
        function saveForwardSettings() {
            const enabled = document.getElementById('forward-enabled').checked;
            const url = document.getElementById('forward-url').value.trim();
            const timeout = parseInt(document.getElementById('forward-timeout').value);

            fetch('api/webhook_forward_settings.php?action=update', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({enabled, url, timeout})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Настройки переадресации сохранены', 'success');
                } else {
                    showToast('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Ошибка сохранения: ' + error.message, 'error');
            });
        }

        // Test forwarding
        function testForwarding() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div style="padding: 1rem; background: #fff3cd; border-radius: 4px;">Отправка тестового запроса...</div>';

            fetch('api/webhook_forward_settings.php?action=test', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div style="padding: 1rem; background: #d4edda; border-radius: 4px; color: #155724;">
                            <strong>✅ Тест успешен</strong><br>
                            HTTP код: ${data.http_code}<br>
                            Время ответа: ${data.response_time_ms}ms<br>
                            ${data.response_body ? '<pre style="margin-top: 0.5rem; white-space: pre-wrap; word-wrap: break-word;">' + data.response_body + '</pre>' : ''}
                        </div>
                    `;
                    showToast('Тест переадресации успешен', 'success');
                } else {
                    resultDiv.innerHTML = `
                        <div style="padding: 1rem; background: #f8d7da; border-radius: 4px; color: #721c24;">
                            <strong>❌ Ошибка теста</strong><br>
                            ${data.error}
                        </div>
                    `;
                    showToast('Ошибка теста: ' + data.error, 'error');
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div style="padding: 1rem; background: #f8d7da; border-radius: 4px; color: #721c24;">
                        <strong>❌ Ошибка</strong><br>
                        ${error.message}
                    </div>
                `;
                showToast('Ошибка теста: ' + error.message, 'error');
            });
        }

        // View forward logs
        function viewForwardLogs() {
            const logsDiv = document.getElementById('forward-logs');
            const logsContent = document.getElementById('forward-logs-content');

            if (logsDiv.style.display === 'none') {
                // Load logs
                fetch('api/webhook_forward_settings.php?action=get_logs')
                    .then(response => response.text())
                    .then(data => {
                        logsContent.textContent = data || 'Лог пуст';
                        logsDiv.style.display = 'block';
                    })
                    .catch(error => {
                        logsContent.textContent = 'Ошибка загрузки логов: ' + error.message;
                        logsDiv.style.display = 'block';
                    });
            } else {
                logsDiv.style.display = 'none';
            }
        }

        // Auto-load forward settings on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadForwardSettings();
        });
    </script>
</body>
</html>
