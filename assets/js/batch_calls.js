/**
 * JavaScript для страницы звонков пакетного анализа
 * Упрощённая версия calls_list.js
 */

// Глобальные переменные
let currentPage = 1;
let currentSort = { by: 'started_at_utc', order: 'DESC' };

// Глобальный аудиоплеер
let globalWaveSurfer = null;
let currentPlayingCallId = null;

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

/**
 * Инициализация страницы
 */
async function initializePage() {
    initTableSort();
    initGlobalAudioPlayer();
    await loadCalls();
}

/**
 * Инициализация компонента сортировки таблицы
 */
let tableSortInstance = null;

function initTableSort() {
    const callsTable = document.getElementById('calls-table');
    if (!callsTable) return;

    tableSortInstance = new TableSort(callsTable, {
        defaultSort: { field: 'started_at_utc', order: 'DESC' },
        clearText: 'Очистить',
        onSort: (field, order) => {
            if (field && order) {
                currentSort.by = field;
                currentSort.order = order;
            } else {
                currentSort.by = 'started_at_utc';
                currentSort.order = 'DESC';
            }
            loadCalls();
        }
    });
}

/**
 * Загрузка списка звонков батча
 */
async function loadCalls() {
    const tbody = document.getElementById('calls-tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="loading">Загрузка данных...</td></tr>';

    try {
        const params = new URLSearchParams({
            batch_id: BATCH_ID,
            page: currentPage,
            per_page: 20,
            sort_by: currentSort.by,
            sort_order: currentSort.order
        });

        const response = await fetch(`/api/batch_calls.php?${params}`);
        const result = await response.json();

        if (result.success) {
            renderCalls(result.data);
            renderPagination(result.pagination);
            updateStats(result.pagination);
        } else {
            tbody.innerHTML = `<tr><td colspan="9" class="error">Ошибка: ${escapeHtml(result.error)}</td></tr>`;
        }
    } catch (error) {
        console.error('Ошибка загрузки звонков:', error);
        tbody.innerHTML = '<tr><td colspan="9" class="error">Ошибка подключения к серверу</td></tr>';
    }
}

/**
 * Отрисовка списка звонков
 */
function renderCalls(calls) {
    const tbody = document.getElementById('calls-tbody');

    if (calls.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">Звонки не найдены</td></tr>';
        return;
    }

    tbody.innerHTML = calls.map(call => `
        <tr data-callid="${call.callid || ''}">
            <td>${escapeHtml(call.employee_name || call.file_name || '-')}</td>
            <td>${formatBatchItemStatus(call.batch_item_status)}</td>
            <td>${formatL1Result(call.l1_parsed_results)}</td>
            <td class="text-center">-</td>
            <td class="summary-cell" title="${escapeHtml(call.l1_summary || '')}">${formatSummary(call.l1_summary)}</td>
            <td>${formatDateTime(call.started_at_utc)}</td>
            <td class="text-center">${formatDuration(call.duration_sec)}</td>
            <td>${escapeHtml(call.client_phone || '-')}</td>
            <td class="actions-cell">
                ${call.callid ? `
                <button class="btn-play-audio ${currentPlayingCallId === call.callid ? 'playing' : ''}"
                        data-callid="${call.callid}"
                        data-employee="${escapeHtml(call.employee_name || '')}"
                        data-client="${escapeHtml(call.client_phone || '')}"
                        title="Проиграть запись">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                </button>
                <a href="call_evaluation.php?callid=${encodeURIComponent(call.callid)}&from=batch&batch_id=${encodeURIComponent(BATCH_ID)}"
                   class="btn btn-primary btn-sm">
                    Открыть
                </a>
                ` : '<span class="text-muted">-</span>'}
            </td>
        </tr>
    `).join('');

    initPlayButtons();
}

/**
 * Форматирование результата L1 анализа
 */
function formatL1Result(parsed) {
    if (!parsed) return '-';
    if (parsed.is_successful === true) {
        return '<span class="badge badge-success">Успешный</span>';
    } else if (parsed.is_successful === false) {
        return '<span class="badge badge-danger">Неуспешный</span>';
    }
    return '-';
}

/**
 * Форматирование статуса элемента батча
 */
function formatBatchItemStatus(status) {
    const statuses = {
        'pending': '<span class="badge badge-secondary">Ожидает</span>',
        'transcribing': '<span class="badge badge-info">Транскрибация</span>',
        'transcribed': '<span class="badge badge-info">Транскрибирован</span>',
        'analyzing': '<span class="badge badge-warning">Анализ</span>',
        'completed': '<span class="badge badge-success">Готов</span>',
        'failed': '<span class="badge badge-danger">Ошибка</span>',
        'skipped': '<span class="badge badge-secondary">Пропущен</span>'
    };
    return statuses[status] || `<span class="badge">${escapeHtml(status || '-')}</span>`;
}

/**
 * Форматирование оценки compliance
 */
function formatComplianceScore(score) {
    if (score === null || score === undefined) {
        return '<span class="text-muted">—</span>';
    }

    const percent = parseInt(score);
    let className = 'rating-low';
    if (percent >= 80) {
        className = 'rating-high';
    } else if (percent >= 60) {
        className = 'rating-medium';
    }

    return `<span class="rating-badge ${className}">${percent}%</span>`;
}

/**
 * Форматирование результата звонка
 */
function formatCallResult(result, isSuccessful) {
    if (!result && isSuccessful === null) return '-';

    if (result) {
        let cleanResult = result.replace(/^Результат:\s*/i, '').trim();
        let badgeClass = isSuccessful ? 'badge-success' : 'badge-danger';
        return `<span class="badge ${badgeClass}">${escapeHtml(cleanResult)}</span>`;
    }

    const badgeClass = isSuccessful ? 'badge-success' : 'badge-danger';
    const text = isSuccessful ? 'Успешный' : 'Неуспешный';
    return `<span class="badge ${badgeClass}">${text}</span>`;
}

/**
 * Форматирование резюме
 */
function formatSummary(text) {
    if (!text || text.trim() === '') return '-';
    const maxLength = 50;
    if (text.length > maxLength) {
        return escapeHtml(text.substring(0, maxLength)) + '...';
    }
    return escapeHtml(text);
}

/**
 * Форматирование даты и времени
 */
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${minutes}`;
}

/**
 * Форматирование длительности
 */
function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) return '-';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}м ${secs}с`;
}

/**
 * Экранирование HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Отрисовка пагинации
 */
function renderPagination(pagination) {
    const container = document.getElementById('pagination');
    const { page, total_pages } = pagination;

    if (total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = `
        <button ${page === 1 ? 'disabled' : ''} onclick="goToPage(1)">Первая</button>
        <button ${page === 1 ? 'disabled' : ''} onclick="goToPage(${page - 1})">← Назад</button>
        <span class="page-info">Страница ${page} из ${total_pages}</span>
        <button ${page === total_pages ? 'disabled' : ''} onclick="goToPage(${page + 1})">Вперёд →</button>
        <button ${page === total_pages ? 'disabled' : ''} onclick="goToPage(${total_pages})">Последняя</button>
    `;
}

/**
 * Обновление статистики
 */
function updateStats(pagination) {
    document.getElementById('stat-total').textContent = pagination.total;
    document.getElementById('stat-page').textContent = Math.min(pagination.per_page, pagination.total - (pagination.page - 1) * pagination.per_page);
}

/**
 * Переход на страницу
 */
function goToPage(page) {
    currentPage = page;
    loadCalls();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ========================================
   Глобальный аудиоплеер
   ======================================== */

/**
 * Инициализация глобального аудиоплеера
 */
function initGlobalAudioPlayer() {
    if (typeof WaveSurfer === 'undefined') {
        console.error('WaveSurfer.js не загружен');
        return;
    }

    globalWaveSurfer = WaveSurfer.create({
        container: '#global-waveform',
        waveColor: '#ddd',
        progressColor: '#007AFF',
        cursorColor: '#007AFF',
        barWidth: 2,
        barRadius: 3,
        responsive: true,
        height: 60,
        normalize: true,
        backend: 'WebAudio'
    });

    globalWaveSurfer.on('ready', function() {
        const duration = globalWaveSurfer.getDuration();
        document.getElementById('player-total-time').textContent = formatTime(duration);
    });

    globalWaveSurfer.on('audioprocess', function() {
        const currentTime = globalWaveSurfer.getCurrentTime();
        document.getElementById('player-current-time').textContent = formatTime(currentTime);
    });

    globalWaveSurfer.on('finish', function() {
        updatePlayPauseButton(false);
    });

    document.getElementById('global-play-btn').addEventListener('click', function() {
        if (globalWaveSurfer) {
            globalWaveSurfer.playPause();
            updatePlayPauseButton(globalWaveSurfer.isPlaying());
        }
    });

    document.getElementById('volume-slider').addEventListener('input', function() {
        if (globalWaveSurfer) {
            globalWaveSurfer.setVolume(this.value / 100);
        }
    });

    document.getElementById('global-speed').addEventListener('change', function() {
        if (globalWaveSurfer) {
            globalWaveSurfer.setPlaybackRate(parseFloat(this.value));
        }
    });

    document.getElementById('player-close-btn').addEventListener('click', function() {
        closeGlobalPlayer();
    });
}

/**
 * Инициализация обработчиков для кнопок Play
 */
function initPlayButtons() {
    const playButtons = document.querySelectorAll('.btn-play-audio');
    playButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const callid = this.getAttribute('data-callid');
            const employee = this.getAttribute('data-employee');
            const client = this.getAttribute('data-client');
            playAudioInGlobalPlayer(callid, employee, client);
        });
    });
}

/**
 * Проигрывание аудио в глобальном плеере
 */
function playAudioInGlobalPlayer(callid, employeeName, clientPhone) {
    if (!globalWaveSurfer) {
        console.error('Глобальный плеер не инициализирован');
        return;
    }

    const playerElement = document.getElementById('global-audio-player');
    playerElement.style.display = 'block';

    document.getElementById('player-callid').textContent = callid;
    document.getElementById('player-employee').textContent = employeeName || '-';
    document.getElementById('player-client').textContent = clientPhone || '-';

    const audioUrl = `api/audio_stream.php?callid=${encodeURIComponent(callid)}`;
    globalWaveSurfer.load(audioUrl);
    currentPlayingCallId = callid;

    updatePlayButtonsState();

    globalWaveSurfer.on('ready', function() {
        globalWaveSurfer.play();
        updatePlayPauseButton(true);
    }, { once: true });
}

/**
 * Обновление состояния кнопки Play/Pause
 */
function updatePlayPauseButton(isPlaying) {
    const playBtn = document.getElementById('global-play-btn');
    if (isPlaying) {
        playBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>
            </svg>
        `;
    } else {
        playBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="5 3 19 12 5 21 5 3"></polygon>
            </svg>
        `;
    }
}

/**
 * Обновление состояния кнопок Play в таблице
 */
function updatePlayButtonsState() {
    const playButtons = document.querySelectorAll('.btn-play-audio');
    playButtons.forEach(button => {
        const callid = button.getAttribute('data-callid');
        if (callid === currentPlayingCallId) {
            button.classList.add('playing');
        } else {
            button.classList.remove('playing');
        }
    });
}

/**
 * Закрытие глобального плеера
 */
function closeGlobalPlayer() {
    if (globalWaveSurfer) {
        globalWaveSurfer.stop();
    }
    document.getElementById('global-audio-player').style.display = 'none';
    currentPlayingCallId = null;
    updatePlayButtonsState();
}

/**
 * Форматирование времени (секунды -> мм:сс)
 */
function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return minutes + ':' + (secs < 10 ? '0' : '') + secs;
}
