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
    <title>Проекты - Система оценки звонков</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/projects.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Theme Switcher Button -->
    <div class="theme-switcher-container">
        <button id="theme-switcher-btn" aria-label="Переключить тему" title="Темная тема"></button>
    </div>

    <!-- Левая боковая панель -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Заголовок страницы -->
        <header class="page-header">
            <h1>Проекты</h1>
            <button class="help-icon-btn" id="help-btn" title="Помощь">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </button>
        </header>

        <!-- Табы -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab-btn active" data-tab="projects">Проекты</button>
                <button class="tab-btn" data-tab="datasources">Источники данных</button>
            </div>
        </div>

        <!-- Контент: Проекты -->
        <div class="tab-content active" id="projects-content">
            <div class="projects-layout">
                <!-- Левая колонка: список проектов -->
                <div class="projects-list" id="projects-list">
                    <!-- Карточки проектов будут загружены динамически через JS -->
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Загрузка проектов...</p>
                    </div>
                </div>

                <!-- Правая колонка: создать проект -->
                <div class="create-project-panel">
                    <div class="create-project-card" id="create-project-card" style="cursor: pointer;">
                        <div class="create-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </div>
                        <h3>Создать проект</h3>
                        <p>Новый проект для оценки звонков</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Контент: Источники данных -->
        <div class="tab-content" id="datasources-content">
            <div class="datasources-container">
                <p style="color: var(--text-muted); padding: 20px;">Раздел источников данных в разработке...</p>
            </div>
        </div>
    </div>

    <!-- Модальное окно: Создание проекта -->
    <div class="modal" id="create-project-modal" style="display: none;">
        <div class="modal-overlay" id="modal-overlay"></div>
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h3>Создать новый проект</h3>
                <button type="button" class="modal-close" id="modal-close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="create-project-form">
                    <div class="form-group">
                        <label for="project-name">Название проекта *</label>
                        <input type="text" id="project-name" name="project_name" required placeholder="Например: Отдел продаж - Ноябрь 2025">
                    </div>
                    <div class="form-group">
                        <label for="project-description">Описание (опционально)</label>
                        <textarea id="project-description" name="description" rows="3" placeholder="Краткое описание проекта..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Чек-листы (опционально)</label>
                        <div class="checklists-grid">
                            <label class="checkbox-label">
                                <input type="checkbox" name="checklists[]" value="1">
                                <span>Приветствие и представление</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="checklists[]" value="2">
                                <span>Выявление потребностей</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="checklists[]" value="3">
                                <span>Презентация продукта</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="checklists[]" value="4">
                                <span>Работа с возражениями</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="checklists[]" value="5">
                                <span>Завершение звонка</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="checklists[]" value="6">
                                <span>Соблюдение регламента</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-btn">Отмена</button>
                <button type="button" class="btn btn-primary" id="submit-project-btn">Создать проект</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно: Меню действий проекта -->
    <div class="dropdown-menu" id="project-actions-menu" style="display: none;">
        <button class="dropdown-item" data-action="edit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Редактировать
        </button>
        <button class="dropdown-item" data-action="duplicate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            Дублировать
        </button>
        <button class="dropdown-item" data-action="archive">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="21 8 21 21 3 21 3 8"></polyline>
                <rect x="1" y="3" width="22" height="5"></rect>
                <line x1="10" y1="12" x2="14" y2="12"></line>
            </svg>
            Архивировать
        </button>
        <div class="dropdown-divider"></div>
        <button class="dropdown-item danger" data-action="delete">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            Удалить
        </button>
    </div>

    <script src="assets/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/projects.js?v=<?php echo time(); ?>"></script>
</body>
</html>
