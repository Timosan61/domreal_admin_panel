<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система оценки звонков - Список звонков</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>📞 Система оценки звонков</h1>
            <p class="subtitle">Список звонков с возможностью фильтрации и оценки</p>
        </header>

        <!-- Панель фильтров -->
        <div class="filters-panel">
            <h2>🔍 Фильтры</h2>
            <form id="filters-form" class="filters-grid">
                <div class="filter-group">
                    <label for="department">Отдел</label>
                    <select id="department" name="department">
                        <option value="">Все отделы</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="manager">Менеджер</label>
                    <select id="manager" name="manager">
                        <option value="">Все менеджеры</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">Дата с</label>
                    <input type="date" id="date_from" name="date_from">
                </div>

                <div class="filter-group">
                    <label for="date_to">Дата по</label>
                    <input type="date" id="date_to" name="date_to">
                </div>

                <div class="filter-group">
                    <label for="duration_min">Длительность (мин, сек)</label>
                    <input type="number" id="duration_min" name="duration_min" placeholder="От" min="0">
                </div>

                <div class="filter-group">
                    <label for="duration_max">Длительность (макс, сек)</label>
                    <input type="number" id="duration_max" name="duration_max" placeholder="До" min="0">
                </div>

                <div class="filter-group">
                    <label for="client_phone">Номер клиента</label>
                    <input type="text" id="client_phone" name="client_phone" placeholder="+7...">
                </div>

                <div class="filter-group">
                    <label for="rating_min">Оценка (мин, 0-1)</label>
                    <input type="number" id="rating_min" name="rating_min" placeholder="0" min="0" max="1" step="0.1">
                </div>

                <div class="filter-group">
                    <label for="rating_max">Оценка (макс, 0-1)</label>
                    <input type="number" id="rating_max" name="rating_max" placeholder="1" min="0" max="1" step="0.1">
                </div>

                <div class="filter-group">
                    <label for="call_type">Тип звонка</label>
                    <select id="call_type" name="call_type">
                        <option value="">Все типы</option>
                        <option value="first_call">Первый звонок</option>
                        <option value="other">Другое</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Применить фильтры</button>
                    <button type="button" id="reset-filters" class="btn btn-secondary">Сбросить</button>
                </div>
            </form>
        </div>

        <!-- Статистика -->
        <div class="stats-panel">
            <div class="stat-card">
                <div class="stat-label">Всего звонков</div>
                <div class="stat-value" id="stat-total">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">На текущей странице</div>
                <div class="stat-value" id="stat-page">0</div>
            </div>
        </div>

        <!-- Таблица звонков -->
        <div class="table-container">
            <table class="calls-table" id="calls-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Тег</th>
                        <th data-sort="employee_name">Менеджер</th>
                        <th>Результат</th>
                        <th data-sort="script_compliance_score">Оценка</th>
                        <th>Резюме</th>
                        <th>Агрегированный анализ</th>
                        <th>Платежеспособность</th>
                        <th data-sort="started_at_utc">Дата и время ↓</th>
                        <th data-sort="duration_sec">Длительность</th>
                        <th>Клиент</th>
                        <th>CRM этап</th>
                        <th>Действия</th>
                        <th>Тип</th>
                        <th data-sort="department">Отдел</th>
                        <th>Направление</th>
                    </tr>
                </thead>
                <tbody id="calls-tbody">
                    <tr>
                        <td colspan="16" class="loading">Загрузка данных...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Пагинация -->
        <div class="pagination" id="pagination">
            <!-- Будет заполнено через JavaScript -->
        </div>
    </div>

    <script src="assets/js/calls_list.js"></script>
</body>
</html>
