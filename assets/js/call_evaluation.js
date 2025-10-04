/**
 * JavaScript для страницы оценки звонка
 */

// Глобальные переменные
let callData = null;
let audioPlayer = null;

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

/**
 * Инициализация страницы
 */
async function initializePage() {
    // Получаем callid из URL
    const urlParams = new URLSearchParams(window.location.search);
    const callid = urlParams.get('callid');

    if (!callid) {
        showError('Не указан ID звонка');
        return;
    }

    await loadCallDetails(callid);
    setupAudioPlayer();
}

/**
 * Загрузка детальной информации о звонке
 */
async function loadCallDetails(callid) {
    try {
        const response = await fetch(`api/call_details.php?callid=${encodeURIComponent(callid)}`);
        const result = await response.json();

        if (result.success) {
            callData = result.data;
            renderCallInfo();
            renderTranscript();
            renderChecklist();
            renderAnalysis();
            setupAudioSource();
        } else {
            showError(result.error || 'Ошибка загрузки данных');
        }
    } catch (error) {
        console.error('Ошибка загрузки звонка:', error);
        showError('Ошибка подключения к серверу');
    }
}

/**
 * Отрисовка основной информации о звонке
 */
function renderCallInfo() {
    const container = document.getElementById('call-info');

    const html = `
        <div class="call-info-grid">
            <div class="info-item">
                <div class="info-label">ID звонка</div>
                <div class="info-value">${escapeHtml(callData.callid)}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Дата и время</div>
                <div class="info-value">${formatDateTime(callData.started_at_utc)}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Менеджер</div>
                <div class="info-value">${escapeHtml(callData.employee_name || '-')}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Отдел</div>
                <div class="info-value">${escapeHtml(callData.department || '-')}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Телефон клиента</div>
                <div class="info-value">${escapeHtml(callData.client_phone || '-')}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Направление</div>
                <div class="info-value">${formatDirection(callData.direction)}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Длительность</div>
                <div class="info-value">${formatDuration(callData.duration_sec)}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Тип звонка</div>
                <div class="info-value">${formatCallType(callData.call_type)}</div>
            </div>
        </div>
    `;

    container.innerHTML = html;
}

/**
 * Настройка источника аудио
 */
function setupAudioSource() {
    const audioSource = document.getElementById('audio-source');
    const audioPlayer = document.getElementById('audio-player');

    if (callData.audio_path && callData.audio_status === 'DONE') {
        // Здесь нужно добавить endpoint для стриминга аудио
        audioSource.src = `api/audio_stream.php?callid=${encodeURIComponent(callData.callid)}`;
        audioPlayer.load();
    } else {
        document.getElementById('audio-player-container').innerHTML = `
            <div class="error">Аудиозапись недоступна (статус: ${callData.audio_status || 'не найдено'})</div>
        `;
    }
}

/**
 * Настройка аудиоплеера
 */
function setupAudioPlayer() {
    audioPlayer = document.getElementById('audio-player');
    const playPauseBtn = document.getElementById('play-pause');
    const seekBar = document.getElementById('seek-bar');
    const volumeBar = document.getElementById('volume-bar');
    const currentTimeSpan = document.getElementById('current-time');
    const totalTimeSpan = document.getElementById('total-time');

    if (!audioPlayer) return;

    // Play/Pause
    playPauseBtn.addEventListener('click', function() {
        if (audioPlayer.paused) {
            audioPlayer.play();
            playPauseBtn.textContent = '⏸ Пауза';
        } else {
            audioPlayer.pause();
            playPauseBtn.textContent = '▶ Воспроизвести';
        }
    });

    // Обновление времени
    audioPlayer.addEventListener('timeupdate', function() {
        const percent = (audioPlayer.currentTime / audioPlayer.duration) * 100;
        seekBar.value = percent || 0;
        currentTimeSpan.textContent = formatTime(audioPlayer.currentTime);
    });

    // Установка общей длительности
    audioPlayer.addEventListener('loadedmetadata', function() {
        totalTimeSpan.textContent = formatTime(audioPlayer.duration);
    });

    // Перемотка
    seekBar.addEventListener('input', function() {
        const time = (seekBar.value / 100) * audioPlayer.duration;
        audioPlayer.currentTime = time;
    });

    // Громкость
    volumeBar.addEventListener('input', function() {
        audioPlayer.volume = volumeBar.value / 100;
    });

    // Когда закончилось воспроизведение
    audioPlayer.addEventListener('ended', function() {
        playPauseBtn.textContent = '▶ Воспроизвести';
    });
}

/**
 * Отрисовка транскрипции
 */
function renderTranscript() {
    const container = document.getElementById('transcript');

    if (!callData.diarization || !callData.diarization.segments) {
        container.innerHTML = '<div class="error">Транскрипция недоступна</div>';
        return;
    }

    const segments = callData.diarization.segments;

    const html = segments.map(segment => {
        const speakerClass = segment.speaker_role === 'Менеджер' ? 'speaker-manager' : 'speaker-client';
        return `
            <div class="transcript-segment ${speakerClass}">
                <div class="segment-header">
                    <span class="speaker-label">${escapeHtml(segment.speaker_role || segment.speaker)}</span>
                    <span class="segment-time">${formatTime(segment.start)} - ${formatTime(segment.end)}</span>
                </div>
                <div class="segment-text">${escapeHtml(segment.text)}</div>
            </div>
        `;
    }).join('');

    container.innerHTML = html || '<div class="error">Нет сегментов транскрипции</div>';
}

/**
 * Отрисовка чеклиста
 */
function renderChecklist() {
    const container = document.getElementById('checklist-container');

    if (!callData.checklist || callData.checklist.length === 0) {
        container.innerHTML = '<div class="info">Чеклист недоступен для данного типа звонка</div>';
        document.getElementById('compliance-score').style.display = 'none';
        return;
    }

    const html = callData.checklist.map(item => `
        <div class="checklist-item">
            <input type="checkbox" class="checklist-checkbox" ${item.checked ? 'checked' : ''} disabled>
            <div class="checklist-content">
                <div class="checklist-label">${escapeHtml(item.label)}</div>
                <div class="checklist-description">${escapeHtml(item.description)}</div>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;

    // Отображаем общую оценку
    if (callData.script_compliance_score !== null && callData.script_compliance_score !== undefined) {
        const percentage = Math.round(callData.script_compliance_score * 100);
        document.getElementById('compliance-score').innerHTML = `
            <h3>Общая оценка соблюдения скрипта</h3>
            <div class="compliance-value">${percentage}%</div>
            <div class="compliance-label">из 100% возможных</div>
        `;
    }
}

/**
 * Отрисовка результатов анализа
 */
function renderAnalysis() {
    const container = document.getElementById('analysis-result');

    if (!callData.summary_text && !callData.call_result) {
        container.innerHTML = '<div class="error">Анализ недоступен</div>';
        return;
    }

    let html = '';

    // Краткое саммари
    if (callData.summary_text) {
        html += `
            <div class="analysis-section">
                <h3>📋 Краткое резюме</h3>
                <div class="analysis-text">${escapeHtml(callData.summary_text)}</div>
            </div>
        `;
    }

    // Результат звонка
    if (callData.call_result) {
        const badgeClass = callData.is_successful ? 'badge-success' : 'badge-danger';
        html += `
            <div class="analysis-section">
                <h3>🎯 Результат звонка</h3>
                <span class="analysis-result-badge ${badgeClass}">${escapeHtml(callData.call_result)}</span>
            </div>
        `;
    }

    // Причина успешности/неуспешности
    if (callData.success_reason) {
        html += `
            <div class="analysis-section">
                <h3>${callData.is_successful ? '✅' : '❌'} Причина ${callData.is_successful ? 'успешности' : 'неуспешности'}</h3>
                <div class="analysis-text">${escapeHtml(callData.success_reason)}</div>
            </div>
        `;
    }

    // Детали проверки скрипта
    if (callData.script_check_details) {
        html += `
            <div class="analysis-section">
                <h3>📝 Детали проверки скрипта</h3>
                <div class="analysis-text">${escapeHtml(callData.script_check_details)}</div>
            </div>
        `;
    }

    // Полный анализ LLM
    if (callData.llm_analysis) {
        html += `
            <div class="analysis-section">
                <h3>🤖 Полный анализ (LLM)</h3>
                <div class="analysis-text" style="max-height: 400px; overflow-y: auto;">
                    <pre style="white-space: pre-wrap; font-family: inherit;">${escapeHtml(callData.llm_analysis)}</pre>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}

/**
 * Показать ошибку
 */
function showError(message) {
    document.getElementById('call-info').innerHTML = `<div class="error">${escapeHtml(message)}</div>`;
}

/**
 * Форматирование времени (секунды -> mm:ss)
 */
function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
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
    if (!seconds) return '-';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}м ${secs}с`;
}

/**
 * Форматирование направления звонка
 */
function formatDirection(direction) {
    const directions = {
        'INBOUND': '<span class="badge badge-info">Входящий</span>',
        'OUTBOUND': '<span class="badge badge-success">Исходящий</span>',
        'MISSED': '<span class="badge badge-danger">Пропущенный</span>'
    };
    return directions[direction] || `<span class="badge">${escapeHtml(direction || '-')}</span>`;
}

/**
 * Форматирование типа звонка
 */
function formatCallType(type) {
    if (!type) return '-';
    const types = {
        'first_call': 'Первый звонок',
        'other': 'Другое'
    };
    return types[type] || escapeHtml(type);
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
