<?php
session_start();
require_once '../auth/session.php';
checkAuth();

$user_full_name = $_SESSION['full_name'] ?? 'Пользователь';
$user_role = $_SESSION['role'] ?? 'user';

// Подключение к БД
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Получение фильтров
$source = $_GET['source'] ?? '';
$status = $_GET['status'] ?? '';
$period = $_GET['period'] ?? 'today';

// Построение WHERE условий
$where = [];
$params = [];

if ($source) {
    $where[] = "source = :source";
    $params['source'] = $source;
}

if ($status) {
    $where[] = "status = :status";
    $params['status'] = $status;
}

// Глобальный поиск (Migration 006)
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $searchPattern = '%' . $search . '%';
    $where[] = "(
        site_name LIKE :search OR
        site_url LIKE :search OR
        form_name LIKE :search OR
        browser LIKE :search OR
        device LIKE :search OR
        platform LIKE :search OR
        quiz_name LIKE :search OR
        page_url LIKE :search OR
        client_comment LIKE :search OR
        name LIKE :search OR
        phone LIKE :search OR
        email LIKE :search
    )";
    $params['search'] = $searchPattern;
}

// Период
$dateCondition = match($period) {
    'today' => "DATE(created_at) = CURDATE()",
    'yesterday' => "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
    '7days' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30days' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    'all' => "1=1",
    default => "DATE(created_at) = CURDATE()"
};
$where[] = $dateCondition;

$whereClause = implode(' AND ', $where);

// Запрос лидов (с полными данными)
$query = "
    SELECT
        id,
        source,
        phone,
        name,
        email,
        status,
        validation_status,
        created_at,
        joywork_client_id,
        is_duplicate,
        external_id,
        utm_source,
        utm_medium,
        utm_campaign,
        utm_content,
        utm_term,
        ip_address,
        user_agent,
        geolocation,
        referer,
        page_url,
        -- Новые поля (Migration 006)
        site_name,
        site_url,
        page_name,
        form_name,
        browser,
        device,
        platform,
        country,
        region,
        city,
        roistat_visit,
        client_comment,
        quiz_id,
        quiz_name,
        quiz_answers,
        quiz_result,
        ab_test,
        timezone,
        lang,
        cookies,
        discount,
        discount_type
    FROM leads
    WHERE $whereClause
    ORDER BY created_at DESC
    LIMIT 100
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика
$statsQuery = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicate_count
    FROM leads
    WHERE $whereClause
";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Функция для форматирования источника
function formatSource($source) {
    $sources = [
        'creatium' => '<span style="background: #e3f2fd; color: #1976d2; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">CREATIUM</span>',
        'gck' => '<span style="background: #f3e5f5; color: #7b1fa2; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">GCK</span>',
        'marquiz' => '<span style="background: #fff3e0; color: #f57c00; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">MARQUIZ</span>',
    ];
    return $sources[$source] ?? $source;
}

// Функция для форматирования статуса
function formatStatus($status) {
    $statuses = [
        'new' => '<span style="background: #28a745; color: white; padding: 4px 12px; border-radius: 4px; font-size: 12px;">Новый</span>',
        'processing' => '<span style="background: #ffc107; color: black; padding: 4px 12px; border-radius: 4px; font-size: 12px;">Обработка</span>',
        'sent' => '<span style="background: #007bff; color: white; padding: 4px 12px; border-radius: 4px; font-size: 12px;">Отправлен</span>',
        'failed' => '<span style="background: #dc3545; color: white; padding: 4px 12px; border-radius: 4px; font-size: 12px;">Ошибка</span>',
        'duplicate' => '<span style="background: #6c757d; color: white; padding: 4px 12px; border-radius: 4px; font-size: 12px;">Дубликат</span>',
    ];
    return $statuses[$status] ?? $status;
}

// Функция для форматирования даты
function formatDate($datetime) {
    if (!$datetime) return '-';
    $date = new DateTime($datetime);
    return $date->format('d.m.Y H:i:s');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список лидов - LidTracker</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }

        .stat-card .label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .lead-row:hover {
            background: #f8f9fa;
        }

        .lead-row {
            cursor: pointer;
        }

        .lead-details {
            display: none;
            background: #f8f9fa;
        }

        .lead-details.active {
            display: table-row;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            padding: 15px;
        }

        .detail-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }

        .detail-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 13px;
            color: #333;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Левая боковая панель -->
    <aside class="sidebar">
        <div class="sidebar-toggle">
            <button id="sidebar-toggle-btn" class="sidebar-toggle-btn" title="Свернуть/развернуть меню">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
        <nav class="sidebar-menu">
            <a href="../index_new.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <span class="sidebar-menu-text">Звонки</span>
            </a>
            <a href="../analytics.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="20" x2="12" y2="10"></line>
                    <line x1="18" y1="20" x2="18" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="16"></line>
                </svg>
                <span class="sidebar-menu-text">Аналитика</span>
            </a>
            <a href="../tags.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <span class="sidebar-menu-text">Теги</span>
            </a>
            <a href="index.php" class="sidebar-menu-item active">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <polyline points="17 11 19 13 23 9"></polyline>
                </svg>
                <span class="sidebar-menu-text">LidTracker</span>
            </a>
            <?php if ($user_role === 'admin'): ?>
            <a href="../admin_users.php" class="sidebar-menu-item" style="color: #dc3545;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 15v5m-3 0h6M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                </svg>
                <span class="sidebar-menu-text">ADMIN</span>
            </a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($user_full_name) ?></div>
                <a href="../auth/logout.php" style="font-size: 12px; color: #6c757d; text-decoration: none;">Выйти</a>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <!-- Заголовок страницы -->
        <header class="page-header">
            <h1>Список лидов</h1>
        </header>

        <!-- Навигация табов -->
        <div style="padding: 20px; background: white; border-bottom: 1px solid #e0e0e0; margin-bottom: 20px;">
            <div style="display: flex; gap: 10px;">
                <a href="index.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">📊 Дашборд</a>
                <a href="leads.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">📋 Список лидов</a>
            </div>
        </div>

        <div style="padding: 0 20px;">
            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="value"><?= $stats['total'] ?></div>
                    <div class="label">Всего лидов</div>
                </div>
                <div class="stat-card">
                    <div class="value" style="color: #28a745;"><?= $stats['new_count'] ?></div>
                    <div class="label">Новые</div>
                </div>
                <div class="stat-card">
                    <div class="value" style="color: #007bff;"><?= $stats['sent_count'] ?></div>
                    <div class="label">Отправлено</div>
                </div>
                <div class="stat-card">
                    <div class="value" style="color: #dc3545;"><?= $stats['failed_count'] ?></div>
                    <div class="label">Ошибок</div>
                </div>
                <div class="stat-card">
                    <div class="value" style="color: #6c757d;"><?= $stats['duplicate_count'] ?></div>
                    <div class="label">Дубликаты</div>
                </div>
            </div>

            <!-- Фильтры -->
            <div style="background: white; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0;">Фильтры</h3>
                <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 14px; color: #666;">Источник:</label>
                        <select name="source" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                            <option value="">Все</option>
                            <option value="creatium" <?= $source === 'creatium' ? 'selected' : '' ?>>Creatium</option>
                            <option value="gck" <?= $source === 'gck' ? 'selected' : '' ?>>GCK</option>
                            <option value="marquiz" <?= $source === 'marquiz' ? 'selected' : '' ?>>Marquiz</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 14px; color: #666;">Статус:</label>
                        <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                            <option value="">Все</option>
                            <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>Новые</option>
                            <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Отправлено</option>
                            <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Ошибка</option>
                            <option value="duplicate" <?= $status === 'duplicate' ? 'selected' : '' ?>>Дубликат</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-size: 14px; color: #666;">Период:</label>
                        <select name="period" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                            <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Сегодня</option>
                            <option value="yesterday" <?= $period === 'yesterday' ? 'selected' : '' ?>>Вчера</option>
                            <option value="7days" <?= $period === '7days' ? 'selected' : '' ?>>7 дней</option>
                            <option value="30days" <?= $period === '30days' ? 'selected' : '' ?>>30 дней</option>
                            <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>Все время</option>
                        </select>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label style="display: block; margin-bottom: 5px; font-size: 14px; color: #666;">🔍 Глобальный поиск:</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Поиск по сайту, форме, квизу, устройству, телефону, имени..."
                               style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;">
                        <div style="font-size: 11px; color: #999; margin-top: 3px;">
                            Ищет по: названию сайта, форме, квизу, браузеру, устройству, телефону, имени, email и комментариям
                        </div>
                    </div>
                    <div style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" style="padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">🔍 Найти</button>
                        <a href="leads.php" style="padding: 8px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">Сбросить</a>
                    </div>
                </form>
            </div>

            <!-- Таблица лидов -->
            <div style="background: white; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px;">
                <h3 style="margin: 0 0 15px 0;">Лиды (<?= count($leads) ?>)</h3>

                <?php if (empty($leads)): ?>
                    <div style="background: #e7f3ff; border: 1px solid #2196F3; border-radius: 5px; padding: 20px; text-align: center;">
                        <p style="margin: 0; color: #1976d2;">
                            📭 Нет лидов для отображения.
                        </p>
                        <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">
                            Измените фильтры или отправьте тестовый вебхук.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                            💡 Нажмите на строку, чтобы посмотреть детали (UTM метки, IP, геолокация и т.д.)
                        </p>
                        <table style="width: 100%; border-collapse: collapse;" id="leadsTable">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">ID</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">Дата/Время</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">Источник</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">Телефон</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">Имя</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">Email</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">UTM Source</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">IP</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">Статус</th>
                                    <th style="padding: 12px; text-align: left; font-size: 13px; color: #495057;">JoyWork</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leads as $lead): ?>
                                <!-- Основная строка -->
                                <tr class="lead-row" style="border-bottom: 1px solid #dee2e6;" onclick="toggleDetails(<?= $lead['id'] ?>)">
                                    <td style="padding: 12px; font-size: 13px;"><strong>#<?= $lead['id'] ?></strong></td>
                                    <td style="padding: 12px; font-size: 13px;"><?= formatDate($lead['created_at']) ?></td>
                                    <td style="padding: 12px; font-size: 13px;"><?= formatSource($lead['source']) ?></td>
                                    <td style="padding: 12px; font-size: 13px; font-weight: 500;">
                                        <?= htmlspecialchars($lead['phone'] ?? '-') ?>
                                        <?php if ($lead['validation_status'] === 'invalid'): ?>
                                            <span style="color: #dc3545; font-size: 11px;">⚠️ невалидный</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 13px;"><?= htmlspecialchars($lead['name'] ?? '-') ?></td>
                                    <td style="padding: 12px; font-size: 13px;"><?= htmlspecialchars($lead['email'] ?? '-') ?></td>
                                    <td style="padding: 12px; font-size: 13px;">
                                        <?php if ($lead['utm_source']): ?>
                                            <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 3px; font-size: 11px;">
                                                <?= htmlspecialchars($lead['utm_source']) ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 13px;">
                                        <?= htmlspecialchars($lead['ip_address'] ?? '-') ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 13px;"><?= formatStatus($lead['status']) ?></td>
                                    <td style="padding: 12px; font-size: 13px;">
                                        <?php if ($lead['joywork_client_id']): ?>
                                            <span style="color: #28a745;">✓ Отправлен</span>
                                        <?php else: ?>
                                            <span style="color: #ffc107;">⏳ В очереди</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- Детали (expandable) -->
                                <tr class="lead-details" id="details-<?= $lead['id'] ?>">
                                    <td colspan="10" style="padding: 0;">
                                        <div class="details-grid">
                                            <?php if ($lead['external_id']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">External ID</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['external_id']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['utm_medium']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">UTM Medium</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['utm_medium']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['utm_campaign']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">UTM Campaign</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['utm_campaign']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['utm_content']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">UTM Content</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['utm_content']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['utm_term']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">UTM Term</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['utm_term']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['user_agent']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">User Agent (Browser)</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['user_agent']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['geolocation']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">Геолокация</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['geolocation']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['referer']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">Referer</div>
                                                <div class="detail-value" style="word-break: break-all;">
                                                    <a href="<?= htmlspecialchars($lead['referer']) ?>" target="_blank" style="color: #007bff; text-decoration: none;">
                                                        <?= htmlspecialchars($lead['referer']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['page_url']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">Page URL</div>
                                                <div class="detail-value" style="word-break: break-all;">
                                                    <a href="<?= htmlspecialchars($lead['page_url']) ?>" target="_blank" style="color: #007bff; text-decoration: none;">
                                                        <?= htmlspecialchars($lead['page_url']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- НОВЫЕ ПОЛЯ (Migration 006) -->

                                            <!-- Информация о сайте (Creatium) -->
                                            <?php if ($lead['site_name'] || $lead['site_url']): ?>
                                            <div class="detail-item" style="background: #e3f2fd; border-left: 3px solid #1976d2;">
                                                <div class="detail-label" style="color: #1976d2; font-weight: bold;">🌐 Сайт</div>
                                                <div class="detail-value">
                                                    <?php if ($lead['site_name']): ?>
                                                        <strong><?= htmlspecialchars($lead['site_name']) ?></strong><br>
                                                    <?php endif; ?>
                                                    <?php if ($lead['site_url']): ?>
                                                        <a href="<?= htmlspecialchars($lead['site_url']) ?>" target="_blank" style="color: #007bff; font-size: 12px;">
                                                            <?= htmlspecialchars($lead['site_url']) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['page_name']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">📄 Страница</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['page_name']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['form_name']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">📝 Форма/Блок</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['form_name']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Устройство (GCK) -->
                                            <?php if ($lead['browser'] || $lead['device'] || $lead['platform']): ?>
                                            <div class="detail-item" style="background: #f3e5f5; border-left: 3px solid #7b1fa2;">
                                                <div class="detail-label" style="color: #7b1fa2; font-weight: bold;">💻 Устройство</div>
                                                <div class="detail-value">
                                                    <?php if ($lead['browser']): ?>
                                                        Браузер: <strong><?= htmlspecialchars($lead['browser']) ?></strong><br>
                                                    <?php endif; ?>
                                                    <?php if ($lead['device']): ?>
                                                        Устройство: <strong><?= htmlspecialchars($lead['device']) ?></strong><br>
                                                    <?php endif; ?>
                                                    <?php if ($lead['platform']): ?>
                                                        ОС: <strong><?= htmlspecialchars($lead['platform']) ?></strong>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Детальная геолокация (GCK) -->
                                            <?php if ($lead['country'] || $lead['region'] || $lead['city']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">🌍 Геолокация (детально)</div>
                                                <div class="detail-value">
                                                    <?= htmlspecialchars(implode(', ', array_filter([$lead['city'], $lead['region'], $lead['country']]))) ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Tracking -->
                                            <?php if ($lead['roistat_visit']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">📊 Roistat Visit ID</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['roistat_visit']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['client_comment']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">💬 Комментарий/ID в РК</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['client_comment']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Квиз (Marquiz) -->
                                            <?php if ($lead['quiz_name']): ?>
                                            <div class="detail-item" style="background: #fff3e0; border-left: 3px solid #f57c00;">
                                                <div class="detail-label" style="color: #f57c00; font-weight: bold;">🎯 Квиз</div>
                                                <div class="detail-value">
                                                    <strong><?= htmlspecialchars($lead['quiz_name']) ?></strong>
                                                    <?php if ($lead['quiz_id']): ?>
                                                        <br><span style="font-size: 11px; color: #999;">ID: <?= htmlspecialchars($lead['quiz_id']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['quiz_answers']): ?>
                                            <div class="detail-item" style="grid-column: span 2;">
                                                <div class="detail-label">📋 Ответы на квиз</div>
                                                <div class="detail-value" style="max-height: 200px; overflow-y: auto;">
                                                    <?php
                                                    $answers = json_decode($lead['quiz_answers'], true);
                                                    if ($answers && is_array($answers)) {
                                                        echo '<ul style="margin: 0; padding-left: 20px;">';
                                                        foreach ($answers as $answer) {
                                                            if (isset($answer['q']) && isset($answer['a'])) {
                                                                echo '<li style="margin-bottom: 5px;">';
                                                                echo '<strong>' . htmlspecialchars($answer['q']) . ':</strong> ';
                                                                echo htmlspecialchars($answer['a']);
                                                                echo '</li>';
                                                            }
                                                        }
                                                        echo '</ul>';
                                                    } else {
                                                        echo htmlspecialchars($lead['quiz_answers']);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['quiz_result']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">🏆 Результат квиза</div>
                                                <div class="detail-value">
                                                    <?php
                                                    $result = json_decode($lead['quiz_result'], true);
                                                    if ($result && is_array($result)) {
                                                        if (isset($result['title'])) echo '<strong>' . htmlspecialchars($result['title']) . '</strong><br>';
                                                        if (isset($result['cost'])) echo 'Стоимость: ' . number_format($result['cost'], 0, ',', ' ') . ' ₽';
                                                    } else {
                                                        echo htmlspecialchars($lead['quiz_result']);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Дополнительные данные (Marquiz) -->
                                            <?php if ($lead['ab_test']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">🧪 AB-тест</div>
                                                <div class="detail-value"><?= htmlspecialchars($lead['ab_test']) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['timezone']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">🕐 Часовой пояс</div>
                                                <div class="detail-value">UTC<?= $lead['timezone'] > 0 ? '+' : '' ?><?= $lead['timezone'] ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['lang']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">🌐 Язык</div>
                                                <div class="detail-value"><?= strtoupper(htmlspecialchars($lead['lang'])) ?></div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['discount']): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">🎁 Скидка</div>
                                                <div class="detail-value">
                                                    <?= number_format($lead['discount'], 0, ',', ' ') ?>
                                                    <?php if ($lead['discount_type']): ?>
                                                        (<?= htmlspecialchars($lead['discount_type']) ?>)
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($lead['cookies']): ?>
                                            <div class="detail-item" style="grid-column: span 2;">
                                                <div class="detail-label">🍪 Cookies</div>
                                                <div class="detail-value" style="font-size: 11px; max-height: 100px; overflow-y: auto;">
                                                    <?php
                                                    $cookies = json_decode($lead['cookies'], true);
                                                    if ($cookies && is_array($cookies)) {
                                                        echo '<ul style="margin: 0; padding-left: 20px;">';
                                                        foreach ($cookies as $key => $value) {
                                                            echo '<li>' . htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '</li>';
                                                        }
                                                        echo '</ul>';
                                                    } else {
                                                        echo htmlspecialchars($lead['cookies']);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/sidebar.js"></script>
    <script>
        function toggleDetails(leadId) {
            const detailsRow = document.getElementById('details-' + leadId);
            detailsRow.classList.toggle('active');
        }
    </script>
</body>
</html>
