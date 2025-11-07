/**
 * Money Tracker JavaScript
 * Управление данными обогащения клиентов
 */

let currentPage = 1;
let totalPages = 1;
let currentSort = { field: 'created_at', order: 'DESC' };
let enrichmentStats = {};
let isLoading = false; // Флаг для предотвращения race condition

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

    // Инициализация чекбоксов и новых функций
    initCheckboxes();
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

    fetch('api/enrichment_data.php?stats=1', {
        signal: controller.signal,
        credentials: 'same-origin'
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
    // Защита от race condition: если уже идет загрузка, игнорируем повторный вызов
    if (isLoading) {
        console.log('[Money Tracker] Загрузка уже в процессе, пропускаем запрос');
        return;
    }

    isLoading = true;
    showLoading(true);
    updatePaginationButtonsState(true); // Дисаблим кнопки во время загрузки

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

    fetch(url, {
        signal: controller.signal,
        credentials: 'same-origin'
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
            isLoading = false;
            showLoading(false);
            updatePaginationButtonsState(false); // Активируем кнопки после загрузки
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

    const filters = {
        date_from: getValue('date_from'),
        date_to: getValue('date_to'),
        enriched_date_from: getValue('enriched_date_from'),
        enriched_date_to: getValue('enriched_date_to'),
        status: getValue('status_filter'),
        inn_filter: getValue('inn_filter'),
        phone_search: getValue('phone_search'),
        webhook_source_filter: getValue('webhook_source_filter'), // NEW: Фильтр источника (GCK/Calls)
        solvency_levels: solvencyLevels
    };

    // Добавляем batch_id если выбран batch
    if (currentBatchId) {
        filters.batch_id = currentBatchId;
    }

    console.log('[getFilters] Фильтры:', filters);
    return filters;
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
    resetValue('webhook_source_filter'); // NEW: Сброс фильтра источника

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
                <td colspan="12" style="text-align: center; padding: 2rem; color: #999;">
                    Нет данных для отображения
                </td>
            </tr>
        `;
        document.getElementById('showing-count').textContent = '0';
        document.getElementById('total-count').textContent = '0';
        return;
    }

    records.forEach(record => {
        const row = document.createElement('tr');
        const isChecked = selectedRows.has(record.id);
        row.innerHTML = `
            <td style="text-align: center;">
                <input type="checkbox" class="row-checkbox" data-id="${record.id}" ${isChecked ? 'checked' : ''} style="cursor: pointer;">
            </td>
            <td>${record.id}</td>
            <td>${escapeHtml(record.client_phone)}</td>
            <td>${renderWebhookSourceBadge(record.webhook_source)}</td>
            <td>${record.inn ? escapeHtml(record.inn) : '<span class="text-muted">—</span>'}</td>
            <td>${record.dadata_companies_count ?? '<span class="text-muted">—</span>'}</td>
            <td>${record.dadata_total_revenue ? escapeHtml(record.dadata_total_revenue) : '<span class="text-muted">—</span>'}</td>
            <td>${record.dadata_total_profit ? escapeHtml(record.dadata_total_profit) : '<span class="text-muted">—</span>'}</td>
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

        // Добавляем обработчик на чекбокс
        const checkbox = row.querySelector('.row-checkbox');
        checkbox.addEventListener('change', function() {
            updateSelectedRows(record.id, this.checked);
        });
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
 * Отрисовка бейджа источника данных
 */
function renderWebhookSourceBadge(webhookSource) {
    if (webhookSource === 'gck') {
        return '<span class="badge badge-info">🟦 GCK Webhook</span>';
    } else {
        return '<span class="badge badge-secondary">📞 Beeline Звонки</span>';
    }
}

/**
 * Обновление пагинации
 */
function updatePagination(pagination) {
    currentPage = pagination.page;
    totalPages = pagination.total_pages;

    document.getElementById('current-page').textContent = currentPage;
    document.getElementById('total-pages').textContent = totalPages;

    // Обновляем общее количество записей
    const totalCount = pagination.total || 0;
    document.getElementById('total-count').textContent = totalCount.toLocaleString('ru-RU');

    // Обновление кнопок
    document.getElementById('first-page').disabled = currentPage === 1;
    document.getElementById('prev-page').disabled = currentPage === 1;
    document.getElementById('next-page').disabled = currentPage === totalPages;
    document.getElementById('last-page').disabled = currentPage === totalPages;

    // Отрисовка номеров страниц
    renderPageNumbers();
}

/**
 * Управление состоянием кнопок пагинации (защита от race condition)
 */
function updatePaginationButtonsState(isDisabled) {
    const buttons = ['first-page', 'prev-page', 'next-page', 'last-page'];
    buttons.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            if (isDisabled) {
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                btn.style.pointerEvents = 'none';
            } else {
                btn.style.opacity = '';
                btn.style.cursor = '';
                btn.style.pointerEvents = '';
            }
        }
    });

    // Также дисаблим цифровые кнопки страниц
    const pageNumberButtons = document.querySelectorAll('#page-numbers .pagination-btn');
    pageNumberButtons.forEach(btn => {
        if (isDisabled) {
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
            btn.style.pointerEvents = 'none';
        } else {
            btn.style.opacity = '';
            btn.style.cursor = '';
            btn.style.pointerEvents = '';
        }
    });
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

    fetch(`api/enrichment_data.php?${params.toString()}`, {
        credentials: 'same-origin'
    })
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

// =============================================================================
// НОВЫЕ ФУНКЦИИ: ЧЕКБОКСЫ, ЭКСПОРТ, ИМПОРТ, ПРОГРЕСС
// =============================================================================

// Состояние выбранных строк
let selectedRows = new Set();

/**
 * Инициализация чекбоксов (вызывается в setupEventListeners)
 */
function initCheckboxes() {
    // Чекбокс "Выбрать все"
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                updateSelectedRows(cb.dataset.id, this.checked);
            });
        });
    }

    // Кнопки действий
    document.getElementById('add-numbers-btn').addEventListener('click', openImportModal);
    document.getElementById('add-file-btn').addEventListener('click', openFileUploadModal);
    document.getElementById('export-filtered-btn').addEventListener('click', exportFiltered);
    document.getElementById('export-selected-btn').addEventListener('click', exportSelected);
    document.getElementById('delete-selected-btn').addEventListener('click', deleteSelected);
    document.getElementById('deselect-all-btn').addEventListener('click', deselectAll);

    // Модальное окно импорта
    document.getElementById('import-modal-close').addEventListener('click', closeImportModal);
    document.getElementById('import-cancel-btn').addEventListener('click', closeImportModal);
    document.getElementById('import-submit-btn').addEventListener('click', submitImportWithBatch);
    document.getElementById('import-modal').addEventListener('click', function(e) {
        if (e.target === this) closeImportModal();
    });

    // Модальное окно загрузки файла
    document.getElementById('file-upload-modal-close').addEventListener('click', closeFileUploadModal);
    document.getElementById('file-upload-cancel-btn').addEventListener('click', closeFileUploadModal);
    document.getElementById('file-upload-submit-btn').addEventListener('click', submitFileUpload);
    document.getElementById('file-upload-modal').addEventListener('click', function(e) {
        if (e.target === this) closeFileUploadModal();
    });
    document.getElementById('file-input').addEventListener('change', handleFileSelect);
    document.getElementById('phone-column-select').addEventListener('change', handleColumnChange);

    // Модальное окно прогресса
    document.getElementById('progress-modal-close').addEventListener('click', closeProgressModal);
    document.getElementById('progress-close-btn').addEventListener('click', closeProgressModal);
}

/**
 * Обновление состояния выбранных строк
 */
function updateSelectedRows(id, checked) {
    if (checked) {
        selectedRows.add(parseInt(id));
    } else {
        selectedRows.delete(parseInt(id));
    }

    // Обновление панели массовых действий
    updateBulkActionsPanel();
}

/**
 * Обновление панели массовых действий
 */
function updateBulkActionsPanel() {
    const panel = document.getElementById('bulk-actions-panel');
    const count = document.getElementById('selected-count');

    count.textContent = selectedRows.size;

    if (selectedRows.size > 0) {
        panel.style.display = 'block';
    } else {
        panel.style.display = 'none';
    }
}

/**
 * Снять выделение со всех строк
 */
function deselectAll() {
    selectedRows.clear();
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('select-all-checkbox').checked = false;
    updateBulkActionsPanel();
}

/**
 * Экспорт отфильтрованных данных
 */
function exportFiltered() {
    console.log('[Export] Экспорт отфильтрованных данных');

    // Получаем текущие фильтры
    const filters = getFilters();

    // Формируем URL с параметрами
    const params = new URLSearchParams(filters);
    const url = `api/enrichment_export_xlsx.php?${params.toString()}`;

    // Открываем в новом окне для скачивания
    window.open(url, '_blank');
}

/**
 * Экспорт выбранных записей
 */
function exportSelected() {
    if (selectedRows.size === 0) {
        alert('Не выбрано ни одной записи');
        return;
    }

    console.log('[Export] Экспорт выбранных записей:', selectedRows.size);

    // Создаем форму для POST запроса
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api/enrichment_export_xlsx.php';
    form.target = '_blank';

    // Добавляем selected_ids
    selectedRows.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_ids[]';
        input.value = id;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

/**
 * Удаление выбранных записей
 */
function deleteSelected() {
    if (selectedRows.size === 0) {
        alert('Не выбрано ни одной записи');
        return;
    }

    if (!confirm(`Вы уверены, что хотите удалить ${selectedRows.size} записей? Это действие нельзя отменить.`)) {
        return;
    }

    console.log('[Delete] Удаление выбранных записей:', selectedRows.size);
    showLoading(true);

    fetch('api/enrichment_bulk_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete',
            ids: Array.from(selectedRows)
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);
        if (data.success) {
            alert(data.message);
            deselectAll();
            loadEnrichmentData(); // Перезагрузка таблицы
            loadStats(); // Обновление статистики
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('[Delete] Ошибка:', error);
        alert('Ошибка при удалении записей');
    });
}

/**
 * Открыть модальное окно импорта
 */
function openImportModal() {
    document.getElementById('import-modal').classList.add('active');
    document.getElementById('import-phones-textarea').value = '';
    document.getElementById('import-phones-textarea').focus();
}

/**
 * Закрыть модальное окно импорта
 */
function closeImportModal() {
    document.getElementById('import-modal').classList.remove('active');
}

/**
 * Отправка импорта телефонов
 */
function submitImport() {
    const textarea = document.getElementById('import-phones-textarea');
    const phonesText = textarea.value.trim();

    if (!phonesText) {
        alert('Введите хотя бы один телефонный номер');
        return;
    }

    console.log('[Import] Отправка номеров на импорт');
    showLoading(true);

    fetch('api/enrichment_import.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            phones: phonesText
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);
        closeImportModal();

        if (data.success) {
            const message = `✅ Импорт завершен:\n\n` +
                `• Добавлено: ${data.added}\n` +
                `• Дубликаты: ${data.duplicates}\n` +
                `• Невалидные: ${data.invalid || 0}\n` +
                `• Всего обработано: ${data.total_parsed}`;

            alert(message);

            // Обновляем таблицу и статистику
            loadEnrichmentData();
            loadStats();

            // Запускаем Worker автоматически через Manual Trigger API
            if (data.added > 0) {
                fetch('api/enrichment_trigger_worker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_size: Math.min(data.added, 100) }),
                    credentials: 'same-origin'  // ✅ Включаем cookies для авторизации
                })
                .then(response => {
                    if (!response.ok) {
                        console.warn('[Worker Trigger] HTTP error:', response.status);
                        return { triggered: false };
                    }
                    return response.json();
                })
                .then(triggerData => {
                    if (triggerData.triggered) {
                        console.log('[Worker Trigger] Успешно запущен:', triggerData.message);
                        showNotification(`Worker запущен - обработка началась (${data.added} номеров)`, 'success');
                    }
                })
                .catch(err => {
                    console.warn('[Worker Trigger] Ошибка, но не критично - основной worker обработает позже:', err);
                });
            }
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка') + '\n' + (data.hint || ''));
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('[Import] Ошибка:', error);
        alert('Ошибка при импорте номеров');
    });
}

/**
 * Закрыть модальное окно прогресса
 */
function closeProgressModal() {
    document.getElementById('progress-modal').classList.remove('active');
}

// ============================================================================
// BATCH MANAGEMENT SYSTEM
// ============================================================================

let currentBatchId = null;
let batchProgressSSE = null;
let allBatches = [];

/**
 * Инициализация системы управления загрузками
 */
function initBatchManagement() {
    // Загрузка списка batches
    loadBatchList();

    // Batch selector change
    document.getElementById('batch-selector').addEventListener('change', function() {
        currentBatchId = this.value ? parseInt(this.value) : null;
        onBatchSelectorChange();
    });

    // Кнопка "Детали загрузки"
    document.getElementById('batch-details-btn').addEventListener('click', openBatchDetailsModal);

    // Модальное окно деталей
    document.getElementById('batch-details-modal-close').addEventListener('click', closeBatchDetailsModal);
    document.getElementById('batch-details-close-btn').addEventListener('click', closeBatchDetailsModal);
    document.getElementById('batch-details-export-btn').addEventListener('click', function() {
        exportBatchResults();
        closeBatchDetailsModal();
    });
}

/**
 * Загрузить список всех batches
 */
function loadBatchList() {
    fetch('api/enrichment_batch_list.php', {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allBatches = data.batches;
                renderBatchSelector(data.batches);
            } else {
                console.error('[Batch] Ошибка загрузки списка:', data.error);
            }
        })
        .catch(error => {
            console.error('[Batch] Ошибка:', error);
        });
}

/**
 * Отрисовать dropdown селектор batches
 */
function renderBatchSelector(batches) {
    const selector = document.getElementById('batch-selector');
    const currentValue = selector.value;

    // Очищаем, оставляя только первый option
    selector.innerHTML = '<option value="">Все записи (без фильтра)</option>';

    batches.forEach(batch => {
        const option = document.createElement('option');
        option.value = batch.id;

        const status = batch.status === 'completed' ? '✅' :
                      batch.status === 'error' ? '❌' : '⏳';

        const progress = batch.progress_percent;
        option.textContent = `${status} ${batch.batch_name} (${batch.total_records} зап., ${progress}%)`;

        selector.appendChild(option);
    });

    // Восстанавливаем выбор
    if (currentValue) {
        selector.value = currentValue;
    }
}

/**
 * Обработчик изменения batch selector
 */
function onBatchSelectorChange() {
    const detailsBtn = document.getElementById('batch-details-btn');

    if (currentBatchId) {
        // Активируем кнопку деталей
        detailsBtn.disabled = false;

        // Применяем фильтр к таблице
        currentPage = 1;
        loadEnrichmentData();
    } else {
        detailsBtn.disabled = false; // Остаётся активной для просмотра списка

        // Сбрасываем фильтр
        currentPage = 1;
        loadEnrichmentData();
    }
}

/**
 * Открыть модальное окно деталей batch
 */
function openBatchDetailsModal() {
    if (!currentBatchId) {
        // Если batch не выбран, показываем список всех batches
        alert('Выберите загрузку из списка');
        return;
    }

    const batch = allBatches.find(b => b.id === currentBatchId);
    if (!batch) {
        alert('Загрузка не найдена');
        return;
    }

    // Заполняем данные
    document.getElementById('batch-details-name').textContent = batch.batch_name;
    document.getElementById('batch-details-created').textContent = batch.created_at;
    document.getElementById('batch-details-author').textContent = batch.created_by || 'Система';

    const statusBadge = document.getElementById('batch-details-status-badge');
    statusBadge.textContent = batch.status === 'completed' ? 'Завершено' :
                             batch.status === 'error' ? 'Ошибка' : 'Обрабатывается';
    statusBadge.className = 'badge ' + (batch.status === 'completed' ? 'badge-success' :
                                        batch.status === 'error' ? 'badge-danger' : 'badge-warning');

    document.getElementById('batch-details-completed').textContent = batch.completed_at || 'В процессе';

    // Статистика
    document.getElementById('batch-details-total').textContent = batch.total_records;
    document.getElementById('batch-details-processed').textContent = batch.processed_records;
    document.getElementById('batch-details-completed-count').textContent = batch.completed_records;
    document.getElementById('batch-details-error').textContent = batch.error_records;
    document.getElementById('batch-details-pending').textContent = batch.pending_records;
    document.getElementById('batch-details-inn').textContent = batch.inn_found_count;

    // Progress bar
    const progressBar = document.getElementById('batch-details-progress-bar');
    progressBar.style.width = batch.progress_percent + '%';
    progressBar.textContent = batch.progress_percent + '%';

    // Открываем модальное окно
    document.getElementById('batch-details-modal').classList.add('active');

    // Автоматически запускаем real-time обновление если batch обрабатывается
    if (batch.status === 'processing' && batch.progress_percent < 100) {
        startBatchProgressWatch();
    }
}

/**
 * Закрыть модальное окно деталей batch
 */
function closeBatchDetailsModal() {
    document.getElementById('batch-details-modal').classList.remove('active');
    stopBatchProgressWatch();
}

/**
 * Выбрать все записи из текущего batch
 */
function selectAllFromBatch() {
    if (!currentBatchId) {
        alert('Выберите загрузку из списка');
        return;
    }

    showLoading(true);

    fetch('api/enrichment_batch_select.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ batch_id: currentBatchId }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);
        if (data.success) {
            // Выбираем все чекбоксы с этими ID
            const checkboxes = document.querySelectorAll('.row-checkbox');
            let selectedCount = 0;

            checkboxes.forEach(checkbox => {
                const rowId = parseInt(checkbox.dataset.id);
                if (data.ids.includes(rowId)) {
                    checkbox.checked = true;
                    selectedCount++;
                }
            });

            updateSelectedRows();
            showNotification(`Выбрано ${selectedCount} записей из загрузки "${allBatches.find(b => b.id === currentBatchId).batch_name}"`, 'success');
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('[Batch Select] Ошибка:', error);
        alert('Ошибка при выборе записей');
    });
}

/**
 * Экспортировать результаты batch
 */
function exportBatchResults() {
    if (!currentBatchId) {
        alert('Выберите загрузку из списка');
        return;
    }

    const batch = allBatches.find(b => b.id === currentBatchId);
    const params = new URLSearchParams({ batch_id: currentBatchId });

    window.location.href = `api/enrichment_export_xlsx.php?${params.toString()}`;
    showNotification(`Экспорт загрузки "${batch.batch_name}" начат`, 'info');
}

/**
 * Запустить real-time отслеживание прогресса batch
 */
function startBatchProgressWatch() {
    if (!currentBatchId) return;

    stopBatchProgressWatch(); // На всякий случай останавливаем предыдущее

    const url = `api/enrichment_batch_progress.php?batch_id=${currentBatchId}`;
    batchProgressSSE = new EventSource(url);

    batchProgressSSE.onmessage = function(event) {
        const data = JSON.parse(event.data);

        if (data.error) {
            console.error('[SSE] Ошибка:', data.error);
            stopBatchProgressWatch();
            return;
        }

        // Обновляем UI
        document.getElementById('batch-details-total').textContent = data.total;
        document.getElementById('batch-details-processed').textContent = data.processed;
        document.getElementById('batch-details-completed-count').textContent = data.completed;
        document.getElementById('batch-details-error').textContent = data.error;
        document.getElementById('batch-details-pending').textContent = data.pending;
        document.getElementById('batch-details-inn').textContent = data.inn_found;

        const progressBar = document.getElementById('batch-details-progress-bar');
        progressBar.style.width = data.percent + '%';
        progressBar.textContent = data.percent + '%';

        // Если завершено
        if (data.status === 'completed' || data.percent >= 100) {
            stopBatchProgressWatch();
            showNotification('Обработка загрузки завершена!', 'success');

            // Browser notification
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Money Tracker', {
                    body: `Загрузка "${allBatches.find(b => b.id === currentBatchId).batch_name}" обработана`,
                    icon: '/favicon.ico'
                });
            }

            // Перезагружаем список batches
            loadBatchList();
        }
    };

    batchProgressSSE.onerror = function(error) {
        console.error('[SSE] Ошибка соединения:', error);
        stopBatchProgressWatch();
    };

    // Меняем текст кнопки
    const watchBtn = document.getElementById('batch-details-watch-btn');
    watchBtn.textContent = '🔴 Остановить отслеживание';
    watchBtn.onclick = stopBatchProgressWatch;

    showNotification('Отслеживание прогресса запущено', 'info');
}

/**
 * Остановить real-time отслеживание прогресса
 */
function stopBatchProgressWatch() {
    if (batchProgressSSE) {
        batchProgressSSE.close();
        batchProgressSSE = null;

        const watchBtn = document.getElementById('batch-details-watch-btn');
        watchBtn.textContent = '🔄 Следить за прогрессом (real-time)';
        watchBtn.onclick = startBatchProgressWatch;
    }
}

/**
 * Импорт с названием batch (заменяет старый submitImport)
 */
function submitImportWithBatch() {
    const batchName = document.getElementById('import-batch-name').value.trim();
    const phonesText = document.getElementById('import-phones-textarea').value.trim();

    if (!batchName) {
        alert('Введите название загрузки');
        return;
    }

    if (!phonesText) {
        alert('Вставьте номера телефонов');
        return;
    }

    // Закрываем модальное окно импорта
    document.getElementById('import-modal').classList.remove('active');

    showLoading(true);

    fetch('api/enrichment_batch_create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            batch_name: batchName,
            phones: phonesText
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);

        if (data.success) {
            // Очищаем форму
            document.getElementById('import-batch-name').value = '';
            document.getElementById('import-phones-textarea').value = '';

            // Перезагружаем список batches
            loadBatchList();

            // Вызываем manual trigger worker API (fallback на случай если PHP auto-trigger не сработал)
            if (data.added > 0) {
                fetch('api/enrichment_trigger_worker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_size: Math.min(data.added, 50) }),
                    credentials: 'same-origin'  // ✅ Включаем cookies для авторизации
                })
                .then(response => {
                    if (!response.ok) {
                        console.warn('[Worker Trigger] HTTP error:', response.status);
                        return { triggered: false };
                    }
                    return response.json();
                })
                .then(triggerData => {
                    if (triggerData.triggered) {
                        console.log('[Worker Trigger] Успешно запущен:', triggerData.message);
                    }
                })
                .catch(err => {
                    console.warn('[Worker Trigger] Ошибка, но не критично - основной worker обработает позже:', err);
                });
            }

            // Показываем результат
            let message = `✅ Загрузка создана: "${data.batch_name}"\n\n`;
            message += `Добавлено: ${data.added}\n`;
            if (data.duplicates > 0) {
                message += `Дубликатов: ${data.duplicates}\n`;
            }
            if (data.invalid > 0) {
                message += `Невалидных: ${data.invalid}\n`;
            }

            if (data.worker_triggered) {
                message += `\n🚀 Worker запущен - обработка началась!`;
                showNotification(`Загрузка "${data.batch_name}" создана, обработка началась (${data.added} номеров)`, 'success');
            } else {
                message += `\nНомера добавлены в очередь обработки.`;
                showNotification(`Создана загрузка "${data.batch_name}" (${data.added} номеров)`, 'success');
            }

            alert(message);

            // Запрашиваем разрешение на browser notifications
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка') + '\n' + (data.hint || ''));
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('[Import Batch] Ошибка:', error);
        alert('Ошибка при создании загрузки');
    });
}

/**
 * Показать уведомление
 */
function showNotification(message, type = 'info') {
    // Простая реализация toast notification
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        border-radius: 0.375rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    toast.textContent = message;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ==============================================
// FILE UPLOAD FUNCTIONALITY
// ==============================================

// Global variable to store extracted phones
let extractedPhones = [];
let currentFileData = null;

/**
 * Open file upload modal
 */
function openFileUploadModal() {
    document.getElementById('file-upload-modal').classList.add('active');
    document.getElementById('file-batch-name').value = '';
    document.getElementById('file-input').value = '';
    document.getElementById('column-selection-container').style.display = 'none';
    document.getElementById('phone-preview-container').style.display = 'none';
    document.getElementById('file-upload-submit-btn').disabled = true;
    extractedPhones = [];
    currentFileData = null;
}

/**
 * Close file upload modal
 */
function closeFileUploadModal() {
    document.getElementById('file-upload-modal').classList.remove('active');
    extractedPhones = [];
    currentFileData = null;
}

/**
 * Handle file selection
 */
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    const fileName = file.name.toLowerCase();
    const reader = new FileReader();

    if (fileName.endsWith('.xlsx') || fileName.endsWith('.xls')) {
        // Excel file
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                parseExcelFile(workbook);
            } catch (error) {
                alert('Ошибка чтения Excel файла: ' + error.message);
                console.error('Excel parse error:', error);
            }
        };
        reader.readAsArrayBuffer(file);
    } else if (fileName.endsWith('.csv')) {
        // CSV file
        reader.onload = function(e) {
            try {
                parseCSVFile(e.target.result);
            } catch (error) {
                alert('Ошибка чтения CSV файла: ' + error.message);
                console.error('CSV parse error:', error);
            }
        };
        reader.readAsText(file);
    } else if (fileName.endsWith('.txt')) {
        // TXT file
        reader.onload = function(e) {
            try {
                parseTXTFile(e.target.result);
            } catch (error) {
                alert('Ошибка чтения TXT файла: ' + error.message);
                console.error('TXT parse error:', error);
            }
        };
        reader.readAsText(file);
    } else {
        alert('Неподдерживаемый формат файла');
    }
}

/**
 * Parse Excel file
 */
function parseExcelFile(workbook) {
    const sheetName = workbook.SheetNames[0];
    const sheet = workbook.Sheets[sheetName];

    // Important: defval for empty cells, raw: true to get raw numbers
    // This is important for phone numbers stored as numbers in Excel
    const jsonData = XLSX.utils.sheet_to_json(sheet, {
        header: 1,
        defval: '',
        raw: true  // Get raw values (numbers will be numbers, not formatted strings)
    });

    console.log('[Parse Excel] Sheet name:', sheetName);
    console.log('[Parse Excel] Rows:', jsonData.length);
    console.log('[Parse Excel] Headers:', jsonData[0]);
    console.log('[Parse Excel] First data row:', jsonData[1]);

    if (jsonData.length === 0) {
        alert('Файл пуст');
        return;
    }

    currentFileData = {
        type: 'excel',
        rows: jsonData,
        headers: jsonData[0] || []
    };

    detectPhoneColumns(currentFileData);
}

/**
 * Parse CSV file
 */
function parseCSVFile(content) {
    // Auto-detect delimiter (comma, semicolon, tab)
    const delimiters = [',', ';', '\t'];
    let bestDelimiter = ',';
    let maxColumns = 0;

    for (const delimiter of delimiters) {
        const lines = content.trim().split('\n');
        const cols = lines[0].split(delimiter).length;
        if (cols > maxColumns) {
            maxColumns = cols;
            bestDelimiter = delimiter;
        }
    }

    const rows = content.trim().split('\n').map(line =>
        line.split(bestDelimiter).map(cell => cell.trim().replace(/^["']|["']$/g, ''))
    );

    if (rows.length === 0) {
        alert('Файл пуст');
        return;
    }

    currentFileData = {
        type: 'csv',
        rows: rows,
        headers: rows[0] || []
    };

    detectPhoneColumns(currentFileData);
}

/**
 * Parse TXT file
 */
function parseTXTFile(content) {
    const lines = content.trim().split('\n').map(line => line.trim()).filter(line => line);

    if (lines.length === 0) {
        alert('Файл пуст');
        return;
    }

    // TXT files are treated as single column
    currentFileData = {
        type: 'txt',
        rows: lines.map(line => [line]),
        headers: ['Текст']
    };

    detectPhoneColumns(currentFileData);
}

/**
 * Detect phone columns
 */
function detectPhoneColumns(fileData) {
    const rows = fileData.rows;
    const headers = fileData.headers;

    console.log('[Detect Columns] Всего колонок:', headers.length);
    console.log('[Detect Columns] Заголовки:', headers);

    // Skip header row for detection
    const dataRows = rows.slice(1);

    if (dataRows.length === 0) {
        alert('Файл не содержит данных (только заголовки)');
        return;
    }

    console.log('[Detect Columns] Строк данных:', dataRows.length);

    // Count phone matches for each column
    const columnScores = [];
    const numColumns = Math.max(...rows.map(row => row.length));

    for (let colIndex = 0; colIndex < numColumns; colIndex++) {
        let phoneCount = 0;
        let totalCount = 0;
        let samples = [];

        for (let rowIdx = 0; rowIdx < Math.min(dataRows.length, 5); rowIdx++) {
            const row = dataRows[rowIdx];
            const cell = row[colIndex];
            if (cell !== undefined && cell !== null && cell !== '') {
                samples.push({
                    value: cell,
                    isPhone: looksLikePhone(cell)
                });
            }
        }

        for (const row of dataRows) {
            const cell = row[colIndex];
            if (cell !== undefined && cell !== null && cell !== '') {
                totalCount++;
                if (looksLikePhone(cell)) {
                    phoneCount++;
                }
            }
        }

        const score = totalCount > 0 ? phoneCount / totalCount : 0;
        const colName = headers[colIndex] || `Столбец ${colIndex + 1}`;

        console.log(`[Detect Columns] Колонка ${colIndex} "${colName}":`, {
            phoneCount,
            totalCount,
            score: Math.round(score * 100) + '%',
            samples: samples
        });

        columnScores.push({
            index: colIndex,
            name: colName,
            score: score,
            phoneCount: phoneCount,
            totalCount: totalCount
        });
    }

    // Sort by score
    columnScores.sort((a, b) => b.score - a.score);

    // If best column has >80% phones, auto-select it
    if (columnScores[0].score >= 0.8) {
        extractPhonesFromColumn(columnScores[0].index);
        // Hide column selector since we auto-detected
        document.getElementById('column-selection-container').style.display = 'none';
    } else {
        // Show column selector
        showColumnSelector(columnScores);
    }
}

/**
 * Check if string looks like a phone number
 */
function looksLikePhone(str) {
    // Handle undefined, null, empty
    if (str === undefined || str === null || str === '') {
        return false;
    }

    // Convert to string (handles numbers from Excel)
    let strValue;
    if (typeof str === 'number') {
        // For large numbers, use toFixed to avoid exponential notation
        strValue = str.toFixed(0);
    } else {
        strValue = String(str).trim();
    }

    // Remove all non-digits except +
    const cleaned = strValue.replace(/[^\d+]/g, '');

    // Check different formats:
    // 1. +79001234567 (12 chars with +)
    // 2. 79001234567 (11 digits starting with 7)
    // 3. 89001234567 (11 digits starting with 8)
    // 4. 9001234567 (10 digits starting with 9)

    // Check: 10-11 digits, optionally with + at start
    const isValid = /^[\+]?[78]?\d{10}$/.test(cleaned);

    // Additional check: if 11 digits, must start with 7 or 8
    if (cleaned.length === 11 && cleaned[0] !== '7' && cleaned[0] !== '8') {
        return false;
    }

    // Additional check: if 10 digits, must start with 9
    if (cleaned.length === 10 && cleaned[0] !== '9') {
        return false;
    }

    return isValid;
}

/**
 * Show column selector
 */
function showColumnSelector(columnScores) {
    const select = document.getElementById('phone-column-select');
    select.innerHTML = '';

    for (const col of columnScores) {
        const option = document.createElement('option');
        option.value = col.index;
        option.textContent = `${col.name} (${col.phoneCount}/${col.totalCount} номеров, ${Math.round(col.score * 100)}%)`;
        select.appendChild(option);
    }

    document.getElementById('column-selection-container').style.display = 'block';

    // Auto-extract from first option
    if (columnScores.length > 0) {
        extractPhonesFromColumn(columnScores[0].index);
    }
}

/**
 * Handle column change
 */
function handleColumnChange(event) {
    const columnIndex = parseInt(event.target.value);
    extractPhonesFromColumn(columnIndex);
}

/**
 * Extract phones from selected column
 */
function extractPhonesFromColumn(columnIndex) {
    const rows = currentFileData.rows.slice(1); // Skip header
    const phones = [];
    let invalidCount = 0;

    console.log('[Extract Phones] Обработка колонки:', columnIndex);
    console.log('[Extract Phones] Всего строк:', rows.length);

    for (const row of rows) {
        const cell = row[columnIndex];
        if (cell !== undefined && cell !== null && cell !== '') {
            const normalized = normalizePhone(String(cell));
            console.log('[Extract Phones] Ячейка:', cell, '→ Нормализовано:', normalized);
            if (normalized) {
                phones.push(normalized);
            } else {
                invalidCount++;
            }
        }
    }

    // Count duplicates before removing
    const totalPhonesBeforeDedup = phones.length;
    const duplicatesInFile = totalPhonesBeforeDedup - new Set(phones).size;

    // Remove duplicates
    extractedPhones = [...new Set(phones)];

    console.log('[Extract Phones] Итого номеров:', totalPhonesBeforeDedup);
    console.log('[Extract Phones] Уникальных:', extractedPhones.length);
    console.log('[Extract Phones] Дубликатов в файле:', duplicatesInFile);
    console.log('[Extract Phones] Невалидных:', invalidCount);

    // Store stats for preview
    currentFileData.stats = {
        total: totalPhonesBeforeDedup,
        unique: extractedPhones.length,
        duplicatesInFile: duplicatesInFile,
        invalid: invalidCount
    };

    // Update UI
    updatePhonePreview();
}

/**
 * Normalize phone number
 */
function normalizePhone(phone) {
    // Handle undefined, null, empty
    if (phone === undefined || phone === null || phone === '') {
        return null;
    }

    // If it's a number, convert carefully
    let phoneStr;
    if (typeof phone === 'number') {
        // For large numbers, use toFixed to avoid exponential notation
        // Then remove trailing .0
        phoneStr = phone.toFixed(0);
    } else {
        phoneStr = String(phone).trim();
    }

    // Remove all non-digits except +
    let cleaned = phoneStr.replace(/[^\d+]/g, '');

    // Remove leading +
    cleaned = cleaned.replace(/^\+/, '');

    // Handle different formats:
    // 11 digits starting with 8 or 7: 89001234567 or 79001234567
    if (cleaned.length === 11 && (cleaned[0] === '8' || cleaned[0] === '7')) {
        cleaned = cleaned.substring(1);
    }

    // Check if we have exactly 10 digits starting with 9
    if (cleaned.length === 10 && cleaned[0] === '9') {
        return '+7' + cleaned;
    }

    return null; // Invalid phone
}

/**
 * Update phone preview
 */
function updatePhonePreview() {
    const stats = currentFileData.stats || {
        total: extractedPhones.length,
        unique: extractedPhones.length,
        duplicatesInFile: 0,
        invalid: 0
    };

    // Update count with details
    let countText = `${stats.unique}`;
    if (stats.duplicatesInFile > 0) {
        countText += ` (из ${stats.total}, дубликатов: ${stats.duplicatesInFile})`;
    }
    if (stats.invalid > 0) {
        countText += ` | Невалидных: ${stats.invalid}`;
    }
    document.getElementById('phone-count').textContent = countText;

    const previewList = document.getElementById('phone-preview-list');
    if (extractedPhones.length === 0) {
        previewList.innerHTML = '<div style="color: #666;">Номера не найдены</div>';
        document.getElementById('file-upload-submit-btn').disabled = true;
    } else {
        const preview = extractedPhones.slice(0, 10);
        const remaining = extractedPhones.length - preview.length;

        let html = '';

        // Show duplicate info if present
        if (stats.duplicatesInFile > 0) {
            html += `<div style="background: #fff3cd; padding: 0.5rem; border-radius: 0.25rem; margin-bottom: 0.5rem; font-size: 0.875rem;">
                <strong>⚠️ Дубликаты удалены:</strong> ${stats.duplicatesInFile} повторяющихся номеров в файле
            </div>`;
        }

        // Show invalid info if present
        if (stats.invalid > 0) {
            html += `<div style="background: #f8d7da; padding: 0.5rem; border-radius: 0.25rem; margin-bottom: 0.5rem; font-size: 0.875rem;">
                <strong>❌ Невалидные номера:</strong> ${stats.invalid} не соответствуют формату
            </div>`;
        }

        html += preview.map(phone =>
            `<div style="color: #28a745;">✓ ${phone}</div>`
        ).join('');

        if (remaining > 0) {
            html += `<div style="color: #666; margin-top: 0.5rem;">... и ещё ${remaining} номеров</div>`;
        }

        previewList.innerHTML = html;
        document.getElementById('file-upload-submit-btn').disabled = false;
    }

    document.getElementById('phone-preview-container').style.display = 'block';
}

/**
 * Submit file upload
 */
function submitFileUpload() {
    const batchName = document.getElementById('file-batch-name').value.trim();

    if (!batchName) {
        alert('Введите название загрузки');
        document.getElementById('file-batch-name').focus();
        return;
    }

    if (extractedPhones.length === 0) {
        alert('Номера не найдены в файле');
        return;
    }

    // Convert phones array to text format (one per line)
    const phonesText = extractedPhones.join('\n');

    // ВАЖНО: Сохраняем статистику ДО закрытия модального окна
    const savedStats = currentFileData && currentFileData.stats ? {
        total: currentFileData.stats.total,
        unique: currentFileData.stats.unique,
        duplicatesInFile: currentFileData.stats.duplicatesInFile,
        invalid: currentFileData.stats.invalid
    } : {
        total: extractedPhones.length,
        unique: extractedPhones.length,
        duplicatesInFile: 0,
        invalid: 0
    };

    // Сохраняем количество номеров
    const savedPhonesCount = extractedPhones.length;
    const savedPhonesSample = extractedPhones.slice(0, 3);

    console.log('[File Upload] Отправка данных:');
    console.log('  Batch name:', batchName);
    console.log('  Phones count:', savedPhonesCount);
    console.log('  First 3 phones:', savedPhonesSample);
    console.log('  Stats:', savedStats);

    // Close file upload modal (это очистит currentFileData и extractedPhones)
    closeFileUploadModal();

    showLoading(true);

    fetch('api/enrichment_batch_create.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            batch_name: batchName,
            phones: phonesText
        }),
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('[File Upload] HTTP status:', response.status);
        console.log('[File Upload] Headers:', response.headers);

        if (!response.ok) {
            return response.text().then(text => {
                console.error('[File Upload] Ошибка HTTP:', response.status);
                console.error('[File Upload] Ответ сервера:', text);
                throw new Error(`HTTP error ${response.status}: ${text.substring(0, 200)}`);
            });
        }

        return response.text().then(text => {
            console.log('[File Upload] Сырой ответ (первые 500 символов):', text.substring(0, 500));
            console.log('[File Upload] Длина ответа:', text.length);

            // Проверяем, не пришел ли HTML вместо JSON
            if (text.trim().startsWith('<')) {
                console.error('[File Upload] Получен HTML вместо JSON!');
                console.error('[File Upload] Полный ответ:', text);
                throw new Error('Сервер вернул HTML вместо JSON. Возможно PHP ошибка.');
            }

            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('[File Upload] Ошибка парсинга JSON:', e);
                console.error('[File Upload] Полный ответ сервера:', text);
                throw new Error('Некорректный ответ от сервера: ' + e.message);
            }
        });
    })
    .then(data => {
        showLoading(false);
        console.log('[File Upload] Ответ от сервера:', data);

        if (data.success) {
            // Reload batch list
            loadBatchList();

            // Reload table data to show new records
            setTimeout(() => {
                loadEnrichmentData();
            }, 500);

            // Trigger worker (fallback)
            if (data.added > 0) {
                fetch('api/enrichment_trigger_worker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_size: Math.min(data.added, 50) }),
                    credentials: 'same-origin'
                })
                .then(response => response.ok ? response.json() : { triggered: false })
                .then(triggerData => {
                    if (triggerData.triggered) {
                        console.log('[Worker Trigger] Успешно запущен:', triggerData.message);
                    }
                })
                .catch(err => {
                    console.warn('[Worker Trigger] Ошибка, но не критично:', err);
                });
            }

            // Show success message with detailed stats
            // Используем сохранённые данные (savedStats, savedPhonesCount)
            let message = `✅ Загрузка создана из файла: "${data.batch_name}"\n\n`;

            message += `📊 Статистика:\n`;
            message += `• Уникальных номеров в файле: ${savedPhonesCount}\n`;

            if (savedStats.duplicatesInFile > 0) {
                message += `• Дубликатов в файле (удалено): ${savedStats.duplicatesInFile}\n`;
            }

            if (savedStats.invalid > 0) {
                message += `• Невалидных номеров (пропущено): ${savedStats.invalid}\n`;
            }

            message += `\n💾 Результат добавления в базу:\n`;
            message += `• Добавлено новых записей: ${data.added}\n`;

            if (data.duplicates > 0) {
                message += `• Уже существовало в базе: ${data.duplicates}\n`;
            }

            if (data.invalid > 0) {
                message += `• Невалидных: ${data.invalid}\n`;
            }

            if (data.worker_triggered) {
                message += `\n🚀 Worker запущен - обработка началась!`;
                showNotification(`Файл загружен: "${data.batch_name}" (${data.added} номеров)`, 'success');
            } else {
                message += `\nНомера добавлены в очередь обработки.`;
                showNotification(`Файл загружен: "${data.batch_name}" (${data.added} номеров)`, 'success');
            }

            alert(message);

            // Request browser notifications permission
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка') + '\n' + (data.hint || ''));
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('[File Upload] Ошибка:', error);
        console.error('[File Upload] Stack trace:', error.stack);

        let errorMessage = 'Ошибка при загрузке файла:\n\n';
        errorMessage += error.message || error.toString();
        errorMessage += '\n\nПроверьте консоль браузера (F12) для подробностей.';

        alert(errorMessage);
    });
}

// Инициализация batch management при загрузке
document.addEventListener('DOMContentLoaded', function() {
    initBatchManagement();
});

// ============================================================
// WORKER STATUS MONITORING
// ============================================================

let workerStatusCheckInterval = null;

/**
 * Check worker status and update UI
 */
async function checkWorkerStatus() {
    try {
        const response = await fetch('api/enrichment_worker_status.php', {
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Unknown error');
        }

        updateWorkerStatusUI(data);

    } catch (error) {
        console.error('[Worker Status] Error:', error);
        showWorkerError('Не удалось проверить статус worker: ' + error.message);
    }
}

/**
 * Update worker status UI elements
 */
function updateWorkerStatusUI(data) {
    const led = document.getElementById('worker-status-led');
    const statusText = document.getElementById('worker-status-text');
    const queueInfo = document.getElementById('worker-queue-info');
    const queueCount = document.getElementById('worker-queue-count');
    const speedInfo = document.getElementById('worker-speed-info');
    const speedValue = document.getElementById('worker-speed-value');
    const batchesInfo = document.getElementById('worker-batches-info');
    const batchesCount = document.getElementById('worker-batches-count');
    const errorContainer = document.getElementById('worker-error-container');
    const errorList = document.getElementById('worker-error-list');
    const lastUpdate = document.getElementById('worker-last-update');

    // Update last update timestamp
    const now = new Date();
    lastUpdate.textContent = `Обновлено: ${now.toLocaleTimeString('ru-RU')}`;

    // Update LED and status text based on worker health
    if (data.worker.healthy) {
        led.className = 'status-healthy';
        statusText.textContent = '✅ Worker работает';
        statusText.style.color = '#4CAF50';
    } else {
        led.className = 'status-error';
        statusText.textContent = '❌ Worker проблемы';
        statusText.style.color = '#f44336';
    }

    // Update queue info
    if (data.queue.total_pending > 0) {
        queueInfo.style.display = 'block';
        queueCount.textContent = data.queue.total_pending;
    } else {
        queueInfo.style.display = 'none';
    }

    // Update processing speed
    if (data.performance.speed_per_minute > 0) {
        speedInfo.style.display = 'block';
        speedValue.textContent = data.performance.speed_per_minute;
    } else {
        speedInfo.style.display = 'none';
    }

    // Update active batches
    if (data.active_batches && data.active_batches.length > 0) {
        batchesInfo.style.display = 'block';
        batchesCount.textContent = data.active_batches.length;
    } else {
        batchesInfo.style.display = 'none';
    }

    // Update errors
    if (data.worker.health_issues && data.worker.health_issues.length > 0) {
        errorContainer.style.display = 'block';
        errorList.innerHTML = '';

        data.worker.health_issues.forEach(issue => {
            const li = document.createElement('li');
            li.textContent = issue;
            errorList.appendChild(li);
        });

        // Add recent errors from logs
        if (data.errors && data.errors.length > 0) {
            data.errors.forEach(err => {
                const li = document.createElement('li');
                li.textContent = `[${err.timestamp}] ${err.message}`;
                errorList.appendChild(li);
            });
        }
    } else {
        errorContainer.style.display = 'none';
    }
}

/**
 * Show worker error message
 */
function showWorkerError(message) {
    const led = document.getElementById('worker-status-led');
    const statusText = document.getElementById('worker-status-text');
    const errorContainer = document.getElementById('worker-error-container');
    const errorList = document.getElementById('worker-error-list');

    led.className = 'status-error';
    statusText.textContent = '❌ Ошибка проверки';
    statusText.style.color = '#f44336';

    errorContainer.style.display = 'block';
    errorList.innerHTML = `<li>${message}</li>`;
}

/**
 * Start worker status monitoring
 */
function startWorkerStatusMonitoring() {
    // Initial check
    checkWorkerStatus();

    // Check every 10 seconds
    if (workerStatusCheckInterval) {
        clearInterval(workerStatusCheckInterval);
    }

    workerStatusCheckInterval = setInterval(checkWorkerStatus, 10000);

    console.log('[Worker Status] Monitoring started (every 10 seconds)');
}

/**
 * Stop worker status monitoring
 */
function stopWorkerStatusMonitoring() {
    if (workerStatusCheckInterval) {
        clearInterval(workerStatusCheckInterval);
        workerStatusCheckInterval = null;
        console.log('[Worker Status] Monitoring stopped');
    }
}

// Start monitoring on page load
document.addEventListener('DOMContentLoaded', function() {
    startWorkerStatusMonitoring();
});

// Stop monitoring when leaving page
window.addEventListener('beforeunload', function() {
    stopWorkerStatusMonitoring();
});
