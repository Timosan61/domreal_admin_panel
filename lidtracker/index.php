<?php
session_start();
require_once '../auth/session.php';
checkAuth();

$user_full_name = $_SESSION['full_name'] ?? 'Пользователь';
$user_role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LidTracker - Domreal Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
            <a href="../money_tracker.php" class="sidebar-menu-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                <span class="sidebar-menu-text">Money Tracker</span>
            </a>
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
            <h1>LidTracker - Интеграция лидогенерации</h1>
        </header>

        <!-- Навигация табов -->
        <div style="padding: 20px; background: white; border-bottom: 1px solid #e0e0e0; margin-bottom: 20px;">
            <div style="display: flex; gap: 10px;">
                <a href="index.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">📊 Дашборд</a>
                <a href="leads.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">📋 Список лидов</a>
                <a href="routing.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">🎯 Routing</a>
                <a href="mapping.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">🔧 Маппинг полей</a>
                <a href="managers.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">👥 Менеджеры</a>
            </div>
        </div>

        <!-- Уведомление о настройке -->
        <div style="padding: 0 20px;">
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #856404;">⚙️ Система в разработке</h3>
                <p style="margin: 10px 0; color: #856404;">
                    <strong>LidTracker</strong> — это система интеграции лидогенерации с JoyWork CRM.
                </p>
                <p style="margin: 10px 0; color: #856404;">
                    <strong>Источники лидов:</strong> Creatium, GCK, Marquiz
                </p>
                <p style="margin: 10px 0; color: #856404;">
                    <strong>Статус:</strong> Создана спецификация и документация. Требуется настройка базы данных и развертывание.
                </p>
                <div style="margin-top: 15px;">
                    <a href="../../LidTracker/ADMIN_PANEL_SPEC.md" target="_blank" style="padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;">
                        📄 Спецификация админ-панели
                    </a>
                    <a href="../../LidTracker/ARCHITECTURE.md" target="_blank" style="padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                        🏗️ Техническая архитектура
                    </a>
                </div>
            </div>

            <!-- Статистика (заглушка) -->
            <h2 style="margin: 20px 0;">Статистика</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background: #007bff; color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;">Всего лидов</h3>
                    <div style="font-size: 36px; font-weight: bold;">-</div>
                    <div style="font-size: 14px; opacity: 0.9; margin-top: 5px;">База данных не настроена</div>
                </div>
                <div style="background: #28a745; color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;">Отправлено в JoyWork</h3>
                    <div style="font-size: 36px; font-weight: bold;">-</div>
                    <div style="font-size: 14px; opacity: 0.9; margin-top: 5px;">База данных не настроена</div>
                </div>
                <div style="background: #dc3545; color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;">Ошибки</h3>
                    <div style="font-size: 36px; font-weight: bold;">-</div>
                    <div style="font-size: 14px; opacity: 0.9; margin-top: 5px;">База данных не настроена</div>
                </div>
                <div style="background: #ffc107; color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;">Дубликаты</h3>
                    <div style="font-size: 36px; font-weight: bold;">-</div>
                    <div style="font-size: 14px; opacity: 0.9; margin-top: 5px;">База данных не настроена</div>
                </div>
            </div>

            <!-- Информация о сервисах -->
            <h2 style="margin: 30px 0 20px 0;">Сервисы лидогенерации</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div style="background: white; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0; color: #7c3aed;">Creatium</h3>
                    <p style="color: #666; font-size: 14px; margin: 10px 0;">
                        Платформа для создания лендингов и сбора заявок
                    </p>
                    <div style="margin-top: 15px;">
                        <span style="background: #f0f0f0; padding: 5px 10px; border-radius: 5px; font-size: 12px; color: #666;">Webhook: /webhook/creatium</span>
                    </div>
                </div>
                <div style="background: white; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0; color: #0891b2;">GCK</h3>
                    <p style="color: #666; font-size: 14px; margin: 10px 0;">
                        Агрегатор заявок с расширенными метриками
                    </p>
                    <div style="margin-top: 15px;">
                        <span style="background: #f0f0f0; padding: 5px 10px; border-radius: 5px; font-size: 12px; color: #666;">Webhook: /webhook/gck</span>
                    </div>
                </div>
                <div style="background: white; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px;">
                    <h3 style="margin: 0 0 10px 0; color: #16a34a;">Marquiz</h3>
                    <p style="color: #666; font-size: 14px; margin: 10px 0;">
                        Платформа для создания интерактивных квизов
                    </p>
                    <div style="margin-top: 15px;">
                        <span style="background: #f0f0f0; padding: 5px 10px; border-radius: 5px; font-size: 12px; color: #666;">Webhook: /webhook/marquiz</span>
                    </div>
                </div>
            </div>

            <!-- Следующие шаги -->
            <div style="background: white; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px; margin-top: 30px; margin-bottom: 30px;">
                <h2 style="margin: 0 0 15px 0;">📋 Следующие шаги для запуска</h2>
                <ol style="margin: 0; padding-left: 20px; color: #333;">
                    <li style="margin-bottom: 10px;">Создать таблицы БД согласно <a href="../../LidTracker/ARCHITECTURE.md" target="_blank">ARCHITECTURE.md</a></li>
                    <li style="margin-bottom: 10px;">Настроить вебхук эндпоинты (<code>/webhook/creatium</code>, <code>/webhook/gck</code>, <code>/webhook/marquiz</code>)</li>
                    <li style="margin-bottom: 10px;">Настроить OAuth токены JoyWork API в <code>.env</code></li>
                    <li style="margin-bottom: 10px;">Настроить маппинг полей для каждого источника</li>
                    <li style="margin-bottom: 10px;">Настроить правила routing (распределение лидов по менеджерам)</li>
                    <li style="margin-bottom: 10px;">Протестировать интеграцию с тестовыми данными</li>
                </ol>
            </div>
        </div>
    </div>

    <script src="../assets/js/sidebar.js"></script>
</body>
</html>
