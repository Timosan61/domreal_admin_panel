<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Звонки - Система оценки звонков</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Левая боковая панель -->
    <aside class="sidebar">
        <nav class="sidebar-menu">
            <a href="index.php" class="sidebar-menu-item active">
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
        </nav>
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">С</div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name">Сергей</div>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <!-- Заголовок страницы -->
        <header class="page-header">
            <h1>Звонки</h1>
            <div class="page-header-actions">
                <button class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Скачать в Excel
                </button>
                <button class="btn btn-primary">Сохраненные фильтры</button>
            </div>
        </header>

        <!-- Панель фильтров -->
        <div class="filters-panel">
            <form id="filters-form">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="department">Отдел</label>
                        <select id="department" name="department">
                            <option value="">—</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="manager">Менеджер</label>
                        <select id="manager" name="manager">
                            <option value="">—</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="client_phone">Номер клиента</label>
                        <input type="text" id="client_phone" name="client_phone" placeholder="">
                    </div>
                    <div class="filter-group">
                        <label for="first_call">Первый звонок</label>
                        <select id="first_call" name="first_call">
                            <option value="">—</option>
                            <option value="yes">Да</option>
                            <option value="no">Нет</option>
                        </select>
                    </div>
                </div>

                <div class="filters-row">
                    <div class="filter-group">
                        <label for="direction">Направление звонка</label>
                        <select id="direction" name="direction">
                            <option value="">—</option>
                            <option value="INBOUND">Входящий</option>
                            <option value="OUTBOUND">Исходящий</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="rating">Оценка</label>
                        <select id="rating" name="rating">
                            <option value="">—</option>
                            <option value="high">Высокая</option>
                            <option value="medium">Средняя</option>
                            <option value="low">Низкая</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status">Статус распознавания</label>
                        <select id="status" name="status">
                            <option value="">—</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="tags">Теги</label>
                        <select id="tags" name="tags">
                            <option value="">—</option>
                        </select>
                    </div>
                </div>

                <div class="filters-row">
                    <div class="filter-group">
                        <label for="date_from">Дата звонка</label>
                        <input type="date" id="date_from" name="date_from">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <input type="date" id="date_to" name="date_to">
                    </div>
                    <div class="filter-group">
                        <label for="date_eval_from">Дата оценки</label>
                        <input type="date" id="date_eval_from" name="date_eval_from">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <input type="date" id="date_eval_to" name="date_eval_to">
                    </div>
                </div>

                <div class="filters-row">
                    <div class="filter-group">
                        <label for="duration_min">Длительность звонка</label>
                        <input type="time" id="duration_min" name="duration_min" value="00:00">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <input type="time" id="duration_max" name="duration_max" value="00:00">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Применить</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="button" id="reset-filters" class="btn btn-secondary" style="width: 100%;">Сбросить</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Таблица звонков -->
        <div class="table-container">
            <table class="calls-table" id="calls-table">
                <thead>
                    <tr>
                        <th data-sort="started_at_utc">Дата и время звонка <span class="sort-icon">↓</span></th>
                        <th data-sort="employee_name">Менеджер <span class="sort-icon">↕</span></th>
                        <th data-sort="department">Отдел <span class="sort-icon">↕</span></th>
                        <th>Номер клиента</th>
                        <th data-sort="duration_sec">Длительность <span class="sort-icon">↕</span></th>
                        <th>Тип звонка</th>
                        <th data-sort="score_overall">Оценка <span class="sort-icon">↕</span></th>
                        <th>Анализ</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="calls-tbody">
                    <tr>
                        <td colspan="9" class="loading">Загрузка данных...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Пагинация и статистика -->
        <div class="table-footer">
            <div class="table-stats">
                <span>Показано <strong id="stat-page">0</strong> из <strong id="stat-total">0</strong> звонков</span>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <script src="assets/js/calls_list.js"></script>
</body>
</html>
