/**
 * Money Tracker JavaScript
 * Управление данными обогащения клиентов
 */

let currentPage = 1;
let totalPages = 1;
let currentSort = { field: 'created_at', order: 'DESC' };
let enrichmentStats = {};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadEnrichmentData();
    setupEventListeners();
});

/**
 * Настройка обработчиков событий
 */
function setupEventListeners() {
    // Кнопки фильтрации
    document.getElementById('apply-filters').addEventListener('click', function() {
        currentPage = 1;
        loadEnrichmentData();
    });

    document.getElementById('reset-filters').addEventListener('click', function() {
        resetFilters();
        currentPage = 1;
        loadEnrichmentData();
    });

    // Пагинация
    document.getElementById('first-page').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage = 1;
            loadEnrichmentData();
        }
    });

    document.getElementById('prev-page').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadEnrichmentData();
        }
    });

    document.getElementById('next-page').addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadEnrichmentData();
        }
    });

    document.getElementById('last-page').addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage = totalPages;
            loadEnrichmentData();
        }
    });

    // Сортировка таблицы
    document.querySelectorAll('.sortable').forEach(th => {
        th.addEventListener('click', function() {
            const field = this.dataset.sort;
            if (currentSort.field === field) {
                currentSort.order = currentSort.order === 'DESC' ? 'ASC' : 'DESC';
            } else {
                currentSort.field = field;
                currentSort.order = 'DESC';
            }
            updateSortIcons();
            loadEnrichmentData();
        });
    });

    // Закрытие модального окна
    document.getElementById('modal-close').addEventListener('click', function() {
        closeDetailModal();
    });

    // Закрытие модального окна при клике вне его
    document.getElementById('detail-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailModal();
        }
    });
}

/**
 * Загрузка статистики
 */
function loadStats() {
    showLoading(true);
    console.log('[Money Tracker] Загрузка статистики...');

    // Добавляем timeout для предотвращения зависания
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        controller.abort();
        console.error('⏱️ Timeout: запрос статистики превысил 30 секунд');
    }, 30000); // 30 секунд

    fetchWithRetry('api/enrichment_data.php?stats=1', {
        signal: controller.signal
    })
        .then(response => {
            clearTimeout(timeoutId);
            console.log('[Money Tracker] Ответ статистики:', response.status, response.statusText);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('[Money Tracker] Данные статистики:', data);
            if (data.success) {
                enrichmentStats = data.stats;
                // Передаём и stats и solvency_levels (они на разных уровнях в response)
                updateStatsCards(data.stats, data.solvency_levels);
            } else {
                showError('Ошибка загрузки статистики: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            console.error('[Money Tracker] Ошибка загрузки статистики:', error);
            if (error.name === 'AbortError') {
                showError('⏱️ Превышено время ожидания загрузки статистики (30 сек).');
            } else {
                showError('Ошибка загрузки статистики: ' + error.message);
            }
        })
        .finally(() => {
            showLoading(false);
        });
}

/**
 * Обновление карточек статистики
 */
function updateStatsCards(stats, solvencyLevels) {
    console.log('[updateStatsCards] stats:', stats);
    console.log('[updateStatsCards] solvencyLevels:', solvencyLevels);

    document.getElementById('stat-total').textContent = stats.total.toLocaleString('ru-RU');

    document.getElementById('stat-inn').innerHTML =
        `${stats.with_inn.toLocaleString('ru-RU')} <span style="font-size: 1rem; color: #666;">из ${stats.userbox_searched.toLocaleString('ru-RU')} обработано</span>`;

    // Маппинг уровней платежеспособности (строка -> цвет)
    const levelToColor = {
        'green': 'green',
        'blue': 'blue',
        'yellow': 'yellow',
        'red': 'red',
        'purple': 'purple'
    };

    // Инициализируем счетчики для всех уровней
    const solvencyCounts = {
        green: 0,
        blue: 0,
        yellow: 0,
        red: 0,
        purple: 0
    };

    // Подсчитываем количество записей для каждого уровня
    if (solvencyLevels && Array.isArray(solvencyLevels)) {
        solvencyLevels.forEach(level => {
            // solvency_level в базе хранится как строка: 'green', 'blue', и т.д.
            const color = levelToColor[level.solvency_level];
            if (color) {
                solvencyCounts[color] = parseInt(level.count) || 0;
            }
        });
    }

    console.log('[updateStatsCards] solvencyCounts:', solvencyCounts);

    // Обновляем карточки для каждого уровня платежеспособности
    Object.keys(solvencyCounts).forEach(color => {
        const count = solvencyCounts[color];
        const percentage = stats.with_inn > 0 ? ((count / stats.with_inn) * 100).toFixed(1) : '0.0';

        const countElement = document.getElementById(`stat-solvency-${color}`);
        const pctElement = document.getElementById(`stat-solvency-${color}-pct`);

        if (countElement) {
            countElement.textContent = count.toLocaleString('ru-RU');
        }
        if (pctElement) {
            pctElement.textContent = `${percentage}% от найденных ИНН`;
        }
    });
}


/**
 * Загрузка данных обогащения
 */
function loadEnrichmentData() {
    showLoading(true);

    const filters = getFilters();
    const params = new URLSearchParams({
        ...filters,
        page: currentPage,
        per_page: 50,
        sort_by: currentSort.field,
        sort_order: currentSort.order
    });

    const url = `api/enrichment_data.php?${params.toString()}`;
    console.log('[Money Tracker] Загрузка данных:', url);

    // Добавляем timeout для предотвращения зависания
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        controller.abort();
        console.error('⏱️ Timeout: запрос превысил 30 секунд');
    }, 30000); // 30 секунд

    fetchWithRetry(url, {
        signal: controller.signal
    })
        .then(response => {
            clearTimeout(timeoutId);
            console.log('[Money Tracker] Ответ данных:', response.status, response.statusText, response.url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('[Money Tracker] Данные получены:', data);
            if (data.success) {
                renderTable(data.data);
                updatePagination(data.pagination);
            } else {
                showError('Ошибка загрузки данных: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            console.error('[Money Tracker] Ошибка загрузки данных:', error);
            if (error.name === 'AbortError') {
                showError('⏱️ Превышено время ожидания (30 сек). Попробуйте упростить фильтры или обратитесь к администратору.');
            } else {
                showError('Ошибка загрузки данных: ' + error.message);
            }
        })
        .finally(() => {
            showLoading(false);
        });
}

/**
 * Получение фильтров из формы
 */
function getFilters() {
    // Получаем выбранные уровни платежеспособности
    const solvencyLevels = Array.from(document.querySelectorAll('.solvency-level-checkbox:checked'))
        .map(cb => cb.value)
        .join(',');

    // Безопасное получение значений элементов (защита от null)
    const getValue = (id) => {
        const el = document.getElementById(id);
        return el ? el.value : '';
    };

    return {
        date_from: getValue('date_from'),
        date_to: getValue('date_to'),
        enriched_date_from: getValue('enriched_date_from'),
        enriched_date_to: getValue('enriched_date_to'),
        status: getValue('status_filter'),
        inn_filter: getValue('inn_filter'),
        phone_search: getValue('phone_search'),
        solvency_levels: solvencyLevels
    };
}

/**
 * Сброс фильтров
 */
function resetFilters() {
    // Безопасный сброс значений (защита от null)
    const resetValue = (id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    };

    resetValue('date_from');
    resetValue('date_to');
    resetValue('enriched_date_from');
    resetValue('enriched_date_to');
    resetValue('status_filter');
    resetValue('inn_filter');
    resetValue('phone_search');

    // Сброс чекбоксов платежеспособности
    document.querySelectorAll('.solvency-level-checkbox').forEach(cb => {
        cb.checked = false;
    });
}

/**
 * Отрисовка таблицы
 */
function renderTable(records) {
    const tbody = document.getElementById('enrichment-tbody');
    tbody.innerHTML = '';

    // Сохраняем данные текущей страницы для кеша
    window.currentPageData = records;

    if (records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" style="text-align: center; padding: 2rem; color: #999;">
                    Нет данных для отображения
                </td>
            </tr>
        `;
        document.getElementById('showing-count').textContent = '0';
        return;
    }

    records.forEach(record => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${record.id}</td>
            <td>${escapeHtml(record.client_phone)}</td>
            <td>${record.inn ? escapeHtml(record.inn) : '<span class="text-muted">—</span>'}</td>
            <td>${record.dadata_companies_count || record.dadata_companies_count === 0 ? record.dadata_companies_count : (record.apifns_companies_count || record.apifns_companies_count === 0 ? record.apifns_companies_count : '<span class="text-muted">—</span>')}</td>
            <td>${record.dadata_total_revenue || record.apifns_total_revenue ? escapeHtml(record.dadata_total_revenue || record.apifns_total_revenue) : '<span class="text-muted">—</span>'}</td>
            <td>${record.dadata_total_profit || record.apifns_total_profit ? escapeHtml(record.dadata_total_profit || record.apifns_total_profit) : '<span class="text-muted">—</span>'}</td>
            <td>${renderSolvencyBadge(record.solvency_level)}</td>
            <td>${renderStatusBadge(record.enrichment_status)}</td>
            <td>${formatDateTime(record.created_at)}</td>
            <td>${formatDateTime(record.updated_at)}</td>
            <td>
                <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;"
                        onclick="showDetailModal(${record.id})">
                    Детали
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('showing-count').textContent = records.length.toLocaleString('ru-RU');
}

/**
 * Отрисовка бейджа платежеспособности
 */
function renderSolvencyBadge(level) {
    if (!level) {
        return '<span class="text-muted">—</span>';
    }

    const badges = {
        'green': { text: '🟢 Низкая (до 10 млн)', class: 'success' },
        'blue': { text: '🔵 Средняя (до 100 млн)', class: 'info' },
        'yellow': { text: '🟡 Высокая (до 500 млн)', class: 'warning' },
        'red': { text: '🔴 Очень высокая (до 2 млрд)', class: 'danger' },
        'purple': { text: '🟣 Премиальная (свыше 2 млрд)', class: 'primary' }
    };

    const badge = badges[level] || { text: level, class: 'secondary' };
    return `<span class="badge badge-${badge.class}">${badge.text}</span>`;
}

/**
 * Отрисовка бейджа статуса
 */
function renderStatusBadge(status) {
    const badges = {
        'completed': { text: 'Завершено', class: 'success' },
        'in_progress': { text: 'В процессе', class: 'warning' },
        'error': { text: 'Ошибка', class: 'danger' },
        'pending': { text: 'Ожидание', class: 'secondary' }
    };

    const badge = badges[status] || { text: status, class: 'secondary' };
    return `<span class="badge badge-${badge.class}">${badge.text}</span>`;
}

/**
 * Обновление пагинации
 */
function updatePagination(pagination) {
    currentPage = pagination.page;
    totalPages = pagination.total_pages;

    document.getElementById('current-page').textContent = currentPage;
    document.getElementById('total-pages').textContent = totalPages;

    // Обновление кнопок
    document.getElementById('first-page').disabled = currentPage === 1;
    document.getElementById('prev-page').disabled = currentPage === 1;
    document.getElementById('next-page').disabled = currentPage === totalPages;
    document.getElementById('last-page').disabled = currentPage === totalPages;

    // Отрисовка номеров страниц
    renderPageNumbers();
}

/**
 * Отрисовка номеров страниц
 */
function renderPageNumbers() {
    const container = document.getElementById('page-numbers');
    container.innerHTML = '';

    const maxVisible = 5;
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);

    if (end - start < maxVisible - 1) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        const btn = document.createElement('button');
        btn.className = 'pagination-btn';
        if (i === currentPage) {
            btn.classList.add('active');
        }
        btn.textContent = i;
        btn.addEventListener('click', function() {
            currentPage = i;
            loadEnrichmentData();
        });
        container.appendChild(btn);
    }
}

/**
 * Обновление иконок сортировки
 */
function updateSortIcons() {
    document.querySelectorAll('.sortable').forEach(th => {
        th.classList.remove('asc', 'desc');
        if (th.dataset.sort === currentSort.field) {
            th.classList.add(currentSort.order.toLowerCase());
        }
    });
}

/**
 * Показать модальное окно с деталями
 */
function showDetailModal(recordId) {
    showLoading(true);

    // ОПТИМИЗАЦИЯ: Ищем запись в уже загруженных данных (currentPageData)
    // Если не найдена - делаем отдельный запрос только для этой записи
    const cachedRecord = window.currentPageData?.find(r => r.id === recordId);

    if (cachedRecord) {
        // Используем кешированные данные
        renderDetailModal(cachedRecord);
        showLoading(false);
        return;
    }

    // Если нет в кеше - запрашиваем по ID (добавим поддержку в API позже)
    // Пока используем фильтр по всем данным, но с минимальным per_page
    const params = new URLSearchParams({
        page: 1,
        per_page: 50,
        sort_by: 'id',
        sort_order: 'DESC'
    });

    fetchWithRetry(`api/enrichment_data.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const record = data.data.find(r => r.id === recordId);
                if (record) {
                    renderDetailModal(record);
                } else {
                    showError('Запись не найдена на текущей странице');
                }
            } else {
                showError('Ошибка загрузки деталей: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки деталей:', error);
            showError('Ошибка загрузки деталей: ' + error.message);
        })
        .finally(() => {
            showLoading(false);
        });
}

/**
 * Отрисовка модального окна с деталями
 */
function renderDetailModal(record) {
    const modalBody = document.getElementById('modal-body');

    modalBody.innerHTML = `
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">ID записи</div>
                <div class="detail-value">${record.id}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Телефон клиента</div>
                <div class="detail-value">${escapeHtml(record.client_phone)}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">ИНН</div>
                <div class="detail-value">${record.inn ? escapeHtml(record.inn) : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Источник ИНН</div>
                <div class="detail-value">${record.inn_source ? escapeHtml(record.inn_source) : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Статус обогащения</div>
                <div class="detail-value">${renderStatusBadge(record.enrichment_status)}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Дата обогащения</div>
                <div class="detail-value">${formatDateTime(record.created_at)}</div>
            </div>

            <div class="detail-item full-width" style="margin-top: 1rem; border-top: 1px solid #e0e0e0; padding-top: 1rem;">
                <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: #333;">Userbox API</h3>
            </div>

            <div class="detail-item">
                <div class="detail-label">Userbox обработан</div>
                <div class="detail-value">${record.userbox_searched ? '✅ Да' : '❌ Нет'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Баз найдено</div>
                <div class="detail-value">${record.databases_found || 0}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Баз проверено</div>
                <div class="detail-value">${record.databases_checked || 0}</div>
            </div>

            <div class="detail-item full-width" style="margin-top: 1rem; border-top: 1px solid #e0e0e0; padding-top: 1rem;">
                <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: #333;">📊 DaData (Финансовые данные)</h3>
            </div>

            <div class="detail-item">
                <div class="detail-label">Источник данных</div>
                <div class="detail-value">${record.data_source ? escapeHtml(record.data_source) : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">ИНН основной компании</div>
                <div class="detail-value">${record.company_inn ? escapeHtml(record.company_inn) : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Количество компаний (DaData)</div>
                <div class="detail-value">${record.dadata_companies_count || record.dadata_companies_count === 0 ? record.dadata_companies_count : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Score платежеспособности</div>
                <div class="detail-value">${record.company_solvency_score ? renderSolvencyScoreBadge(record.company_solvency_score) : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Суммарная выручка (DaData)</div>
                <div class="detail-value">${record.dadata_total_revenue ? formatRevenue(record.dadata_total_revenue) : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Суммарная прибыль (DaData)</div>
                <div class="detail-value">${record.dadata_total_profit ? formatRevenue(record.dadata_total_profit) : '—'}</div>
            </div>
            ${renderDaDataContacts(record)}
            ${record.dadata_address ? `
            <div class="detail-item full-width">
                <div class="detail-label">Адрес регистрации</div>
                <div class="detail-value">${escapeHtml(record.dadata_address)}</div>
            </div>
            ` : ''}
            ${record.dadata_registration_date ? `
            <div class="detail-item">
                <div class="detail-label">Дата регистрации</div>
                <div class="detail-value">${escapeHtml(record.dadata_registration_date)}</div>
            </div>
            ` : ''}

            <div class="detail-item full-width" style="margin-top: 1rem; border-top: 1px solid #e0e0e0; padding-top: 1rem;">
                <h3 style="margin: 0 0 1rem 0; font-size: 1rem; color: #333;">GigaChat Анализ</h3>
            </div>

            <div class="detail-item">
                <div class="detail-label">GigaChat обработан</div>
                <div class="detail-value">${record.solvency_analyzed ? '✅ Да' : '❌ Нет'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Дата анализа</div>
                <div class="detail-value">${record.solvency_analyzed_at ? formatDateTime(record.solvency_analyzed_at) : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Уровень платежеспособности</div>
                <div class="detail-value">${renderSolvencyBadge(record.solvency_level)}</div>
            </div>
            <div class="detail-item full-width">
                <div class="detail-label">Краткий анализ</div>
                <div class="detail-value">${record.solvency_summary ? escapeHtml(record.solvency_summary) : '—'}</div>
            </div>
        </div>
    `;

    document.getElementById('detail-modal').classList.add('active');
}

/**
 * Закрыть модальное окно
 */
function closeDetailModal() {
    document.getElementById('detail-modal').classList.remove('active');
}


/**
 * Показать/скрыть загрузку
 */
function showLoading(show) {
    const overlay = document.getElementById('loading-overlay');
    if (show) {
        overlay.classList.add('active');
    } else {
        overlay.classList.remove('active');
    }
}

/**
 * Показать ошибку
 */
function showError(message) {
    alert(message);
}

/**
 * Форматирование даты и времени
 */
function formatDateTime(dateString) {
    if (!dateString) return '—';

    const date = new Date(dateString);
    return date.toLocaleString('ru-RU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Форматирование числа
 */
function formatNumber(num) {
    return parseInt(num).toLocaleString('ru-RU');
}

/**
 * Форматирование выручки
 */
function formatRevenue(revenue) {
    if (!revenue) return '—';
    // Убираем все нечисловые символы кроме точки и запятой
    const numStr = String(revenue).replace(/[^\d.,]/g, '');
    if (!numStr) return revenue;

    const num = parseFloat(numStr.replace(',', '.'));
    if (isNaN(num)) return revenue;

    return num.toLocaleString('ru-RU') + ' ₽';
}

/**
 * Экранирование HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Отрисовка бейджа числового score платежеспособности (1-5)
 */
function renderSolvencyScoreBadge(score) {
    if (!score) return '—';

    const badges = {
        1: { text: '🟢 1 - Низкая (до 10 млн)', class: 'success' },
        2: { text: '🔵 2 - Средняя (до 100 млн)', class: 'info' },
        3: { text: '🟡 3 - Высокая (до 500 млн)', class: 'warning' },
        4: { text: '🔴 4 - Очень высокая (до 2 млрд)', class: 'danger' },
        5: { text: '🟣 5 - Премиальная (свыше 2 млрд)', class: 'secondary' }
    };

    const badge = badges[score] || { text: score, class: 'secondary' };
    return `<span class="badge badge-${badge.class}">${badge.text}</span>`;
}

/**
 * Отрисовка контактов из DaData (учредители, руководители, телефоны, email)
 */
function renderDaDataContacts(record) {
    let html = '';

    // Учредители
    if (record.dadata_founders) {
        try {
            const founders = JSON.parse(record.dadata_founders);
            if (founders && founders.length > 0) {
                html += `
                <div class="detail-item full-width">
                    <div class="detail-label">Учредители (${founders.length})</div>
                    <div class="detail-value" style="font-size: 0.8rem;">
                        ${founders.slice(0, 5).map(f => escapeHtml(f)).join(', ')}
                        ${founders.length > 5 ? ` и ещё ${founders.length - 5}...` : ''}
                    </div>
                </div>`;
            }
        } catch (e) {
            console.error('Error parsing dadata_founders:', e);
        }
    }

    // Руководители
    if (record.dadata_managers) {
        try {
            const managers = JSON.parse(record.dadata_managers);
            if (managers && managers.length > 0) {
                html += `
                <div class="detail-item full-width">
                    <div class="detail-label">Руководители (${managers.length})</div>
                    <div class="detail-value" style="font-size: 0.8rem;">
                        ${managers.slice(0, 3).map(m => escapeHtml(m)).join(', ')}
                        ${managers.length > 3 ? ` и ещё ${managers.length - 3}...` : ''}
                    </div>
                </div>`;
            }
        } catch (e) {
            console.error('Error parsing dadata_managers:', e);
        }
    }

    // Телефоны
    if (record.dadata_phones) {
        try {
            const phones = JSON.parse(record.dadata_phones);
            if (phones && phones.length > 0) {
                html += `
                <div class="detail-item">
                    <div class="detail-label">Телефоны компании (${phones.length})</div>
                    <div class="detail-value" style="font-size: 0.8rem;">
                        ${phones.slice(0, 3).map(p => escapeHtml(p)).join(', ')}
                        ${phones.length > 3 ? ` +${phones.length - 3}` : ''}
                    </div>
                </div>`;
            }
        } catch (e) {
            console.error('Error parsing dadata_phones:', e);
        }
    }

    // Email
    if (record.dadata_emails) {
        try {
            const emails = JSON.parse(record.dadata_emails);
            if (emails && emails.length > 0) {
                html += `
                <div class="detail-item">
                    <div class="detail-label">Email компании (${emails.length})</div>
                    <div class="detail-value" style="font-size: 0.8rem;">
                        ${emails.slice(0, 2).map(e => escapeHtml(e)).join(', ')}
                        ${emails.length > 2 ? ` +${emails.length - 2}` : ''}
                    </div>
                </div>`;
            }
        } catch (e) {
            console.error('Error parsing dadata_emails:', e);
        }
    }

    return html;
}
