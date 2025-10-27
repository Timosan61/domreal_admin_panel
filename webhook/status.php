<?php
/**
 * Страница статуса вебхуков LidTracker
 * URL: http://195.239.161.77/admin_panel/webhook/status.php
 */

require_once '../config/database.php';

// Подключение к БД
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Ошибка подключения к базе данных");
}

// Получение статистики
$stats = [
    'total_leads' => 0,
    'by_source' => [],
    'recent_leads' => [],
    'recent_logs' => [],
    'queue_status' => [],
    'last_hour_count' => 0
];

try {
    // Общее количество лидов
    $query = "SELECT COUNT(*) as total FROM leads";
    $stmt = $db->query($query);
    $stats['total_leads'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Статистика по источникам
    $query = "
        SELECT
            source,
            COUNT(*) as total_leads,
            SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_leads,
            SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicates,
            SUM(CASE WHEN validation_status = 'valid' THEN 1 ELSE 0 END) as valid_phones,
            SUM(CASE WHEN validation_status = 'invalid' THEN 1 ELSE 0 END) as invalid_phones,
            MAX(created_at) as last_lead_at
        FROM leads
        GROUP BY source
    ";
    $stmt = $db->query($query);
    $stats['by_source'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Последние 10 лидов
    $query = "
        SELECT
            id,
            source,
            phone,
            name,
            status,
            validation_status,
            created_at
        FROM leads
        ORDER BY created_at DESC
        LIMIT 10
    ";
    $stmt = $db->query($query);
    $stats['recent_leads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Последние логи
    $query = "
        SELECT
            l.lead_id,
            ld.source,
            ld.phone,
            l.step,
            l.status,
            l.message,
            l.created_at
        FROM lead_processing_log l
        LEFT JOIN leads ld ON l.lead_id = ld.id
        ORDER BY l.created_at DESC
        LIMIT 15
    ";
    $stmt = $db->query($query);
    $stats['recent_logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Лиды за последний час
    $query = "
        SELECT COUNT(*) as count
        FROM leads
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    $stmt = $db->query($query);
    $stats['last_hour_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (Exception $e) {
    error_log("Error fetching webhook stats: " . $e->getMessage());
}

// Функция для форматирования даты
function formatDate($datetime) {
    if (!$datetime) return '-';
    $date = new DateTime($datetime);
    return $date->format('d.m.Y H:i:s');
}

// Функция для получения Badge цвета
function getStatusBadge($status) {
    $badges = [
        'new' => '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Новый</span>',
        'processing' => '<span style="background: #ffc107; color: black; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Обработка</span>',
        'sent' => '<span style="background: #007bff; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Отправлен</span>',
        'failed' => '<span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Ошибка</span>',
        'duplicate' => '<span style="background: #6c757d; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Дубликат</span>',
        'success' => '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">✓</span>',
        'error' => '<span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">✗</span>',
    ];
    return $badges[$status] ?? $status;
}

function getValidationBadge($status) {
    $badges = [
        'valid' => '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">✓ Валидный</span>',
        'invalid' => '<span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">✗ Невалидный</span>',
        'pending' => '<span style="background: #ffc107; color: black; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Ожидает</span>',
    ];
    return $badges[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статус Вебхуков - LidTracker</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #333;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 12px;
            color: #999;
        }

        .section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 13px;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,123,255,0.3);
        }

        .refresh-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .refresh-btn:hover {
            background: #218838;
        }

        .source-badge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .source-creatium { background: #e3f2fd; color: #1976d2; }
        .source-gck { background: #f3e5f5; color: #7b1fa2; }
        .source-marquiz { background: #fff3e0; color: #f57c00; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .instructions {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .instructions h4 {
            margin-bottom: 10px;
            color: #856404;
        }

        .instructions ol {
            margin-left: 20px;
            color: #856404;
        }

        .instructions li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auto-refresh" id="autoRefresh">
            🔄 Авто-обновление: <span id="countdown">30</span>с
        </div>

        <h1>🔍 Статус Вебхуков LidTracker</h1>
        <p class="subtitle">Мониторинг входящих лидов в реальном времени</p>

        <button class="refresh-btn" onclick="location.reload()">🔄 Обновить сейчас</button>

        <?php if ($stats['total_leads'] == 0): ?>
        <div class="instructions">
            <h4>📋 Как проверить, что вебхук работает:</h4>
            <ol>
                <li>Отправьте тестовый лид из Creatium (заполните форму на вашем квизе)</li>
                <li>Подождите 5-10 секунд</li>
                <li>Обновите эту страницу (она обновляется автоматически каждые 30 секунд)</li>
                <li>Проверьте раздел "Последние лиды" ниже</li>
            </ol>
            <p style="margin-top: 10px;"><strong>URL вебхука:</strong> http://195.239.161.77/admin_panel/webhook/creatium.php</p>
        </div>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Всего лидов</h3>
                <div class="value"><?= number_format($stats['total_leads']) ?></div>
                <div class="label">За все время</div>
            </div>

            <div class="stat-card">
                <h3>За последний час</h3>
                <div class="value"><?= $stats['last_hour_count'] ?></div>
                <div class="label">Новых лидов</div>
            </div>

            <div class="stat-card">
                <h3>Creatium</h3>
                <div class="value">
                    <?php
                    $creatium = array_filter($stats['by_source'], fn($s) => $s['source'] === 'creatium');
                    echo $creatium ? array_values($creatium)[0]['total_leads'] : 0;
                    ?>
                </div>
                <div class="label">Лидов</div>
            </div>

            <div class="stat-card">
                <h3>GCK</h3>
                <div class="value">
                    <?php
                    $gck = array_filter($stats['by_source'], fn($s) => $s['source'] === 'gck');
                    echo $gck ? array_values($gck)[0]['total_leads'] : 0;
                    ?>
                </div>
                <div class="label">Лидов</div>
            </div>

            <div class="stat-card">
                <h3>Marquiz</h3>
                <div class="value">
                    <?php
                    $marquiz = array_filter($stats['by_source'], fn($s) => $s['source'] === 'marquiz');
                    echo $marquiz ? array_values($marquiz)[0]['total_leads'] : 0;
                    ?>
                </div>
                <div class="label">Лидов</div>
            </div>
        </div>

        <!-- Статистика по источникам -->
        <?php if (!empty($stats['by_source'])): ?>
        <div class="section">
            <h2>📊 Детальная статистика по источникам</h2>
            <table>
                <thead>
                    <tr>
                        <th>Источник</th>
                        <th>Всего</th>
                        <th>Новые</th>
                        <th>Дубликаты</th>
                        <th>Валидные</th>
                        <th>Невалидные</th>
                        <th>Последний лид</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['by_source'] as $source): ?>
                    <tr>
                        <td>
                            <span class="source-badge source-<?= htmlspecialchars($source['source']) ?>">
                                <?= strtoupper(htmlspecialchars($source['source'])) ?>
                            </span>
                        </td>
                        <td><strong><?= $source['total_leads'] ?></strong></td>
                        <td><?= $source['new_leads'] ?></td>
                        <td><?= $source['duplicates'] ?></td>
                        <td><span style="color: #28a745;"><?= $source['valid_phones'] ?></span></td>
                        <td><span style="color: #dc3545;"><?= $source['invalid_phones'] ?></span></td>
                        <td><?= formatDate($source['last_lead_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Последние лиды -->
        <div class="section">
            <h2>📋 Последние 10 лидов</h2>
            <?php if (empty($stats['recent_leads'])): ?>
                <div class="empty-state">
                    <p>Лидов пока нет. Отправьте тестовый лид из Creatium.</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Источник</th>
                        <th>Телефон</th>
                        <th>Имя</th>
                        <th>Статус</th>
                        <th>Валидация</th>
                        <th>Создан</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent_leads'] as $lead): ?>
                    <tr>
                        <td><strong>#<?= $lead['id'] ?></strong></td>
                        <td>
                            <span class="source-badge source-<?= htmlspecialchars($lead['source']) ?>">
                                <?= strtoupper(htmlspecialchars($lead['source'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($lead['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($lead['name'] ?? '-') ?></td>
                        <td><?= getStatusBadge($lead['status']) ?></td>
                        <td><?= getValidationBadge($lead['validation_status']) ?></td>
                        <td><?= formatDate($lead['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Логи обработки -->
        <div class="section">
            <h2>📝 Лог обработки (последние 15 записей)</h2>
            <?php if (empty($stats['recent_logs'])): ?>
                <div class="empty-state">
                    <p>Логов пока нет.</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Lead ID</th>
                        <th>Источник</th>
                        <th>Телефон</th>
                        <th>Шаг</th>
                        <th>Статус</th>
                        <th>Сообщение</th>
                        <th>Время</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent_logs'] as $log): ?>
                    <tr>
                        <td><strong>#<?= $log['lead_id'] ?></strong></td>
                        <td>
                            <?php if ($log['source']): ?>
                            <span class="source-badge source-<?= htmlspecialchars($log['source']) ?>">
                                <?= strtoupper(htmlspecialchars($log['source'])) ?>
                            </span>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($log['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($log['step']) ?></td>
                        <td><?= getStatusBadge($log['status']) ?></td>
                        <td><?= htmlspecialchars($log['message'] ?? '-') ?></td>
                        <td><?= formatDate($log['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Авто-обновление каждые 30 секунд
        let countdown = 30;

        setInterval(() => {
            countdown--;
            document.getElementById('countdown').textContent = countdown;

            if (countdown <= 0) {
                location.reload();
            }
        }, 1000);
    </script>
</body>
</html>
