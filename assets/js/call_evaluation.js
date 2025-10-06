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

        console.log('API Response:', result); // DEBUG

        if (result.success) {
            callData = result.data;
            console.log('Call Data:', callData); // DEBUG
            console.log('Audio Status:', callData.audio_status); // DEBUG
            console.log('Audio Error:', callData.audio_error); // DEBUG
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
        let errorMessage = 'Аудиозапись недоступна';

        if (callData.audio_status === 'ERROR') {
            errorMessage = `<div style="color: #721c24;">
                <strong>❌ Аудиозапись недоступна (статус: ERROR)</strong><br>`;

            if (callData.audio_error && callData.audio_error !== 'null') {
                errorMessage += `<div style="margin-top: 8px;">📋 Причина: <em>${escapeHtml(callData.audio_error)}</em></div>`;
            } else {
                errorMessage += `<div style="margin-top: 8px;">⚠️ Произошла ошибка при обработке аудио</div>`;
            }

            errorMessage += `<div style="margin-top: 12px; font-size: 13px; color: #856404;">
                💡 <strong>Решение:</strong> Обратитесь к администратору для повторной обработки звонка
            </div></div>`;
        } else if (callData.audio_status === 'QUEUED') {
            errorMessage = '⏳ Аудиозапись в очереди на обработку';
        } else if (callData.audio_status === 'DOWNLOADING') {
            errorMessage = '⬇️ Аудиозапись загружается...';
        } else if (callData.audio_status === 'TRANSCRIBING') {
            errorMessage = '🎙️ Идёт транскрибация...';
        } else if (!callData.audio_status) {
            errorMessage = '❓ Задача на обработку аудио не создана';
        }

        document.getElementById('audio-player-container').innerHTML = `
            <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404;">${errorMessage}</div>
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
        let message = '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404;">';
        message += '<strong>📝 Транскрипция недоступна</strong><br>';

        if (callData.audio_status === 'ERROR') {
            message += '<div style="margin-top: 8px;">⚠️ Транскрипция не была создана из-за ошибки обработки аудио</div>';
        } else if (callData.audio_status === 'QUEUED' || callData.audio_status === 'DOWNLOADING') {
            message += '<div style="margin-top: 8px;">⏳ Ожидание обработки аудио...</div>';
        } else if (callData.audio_status === 'TRANSCRIBING') {
            message += '<div style="margin-top: 8px;">🎙️ Транскрибация в процессе...</div>';
        } else {
            message += '<div style="margin-top: 8px;">❓ Данные транскрипции не найдены</div>';
        }

        message += '</div>';
        container.innerHTML = message;
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
        let message = '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404;">';
        message += '<strong>🤖 Анализ недоступен</strong><br>';

        if (!callData.transcript_text && !callData.diarization) {
            message += '<div style="margin-top: 8px;">⚠️ Анализ не был создан, так как отсутствует транскрипция</div>';

            if (callData.audio_status === 'ERROR') {
                message += '<div style="margin-top: 8px;">📋 Причина: ошибка при обработке аудио</div>';
            }
        } else {
            message += '<div style="margin-top: 8px;">❓ Результаты анализа не найдены</div>';
        }

        message += '</div>';
        container.innerHTML = message;
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
        // Логика совпадает с общей таблицей (calls_list.js)
        let badgeClass = 'badge-info'; // По умолчанию синий
        const resultLower = callData.call_result.toLowerCase();

        // Проверяем ключевые слова в тексте результата (регистронезависимо)
        if (resultLower.includes('показ')) {
            badgeClass = 'badge-success'; // Зеленый
        } else if (resultLower.includes('перезвон')) {
            badgeClass = 'badge-warning'; // Желтый
        } else if (resultLower.includes('отказ')) {
            badgeClass = 'badge-danger'; // Красный
        }
        // Если нет ключевых слов, но есть флаг успешности
        else if (callData.is_successful !== null && callData.is_successful !== undefined) {
            badgeClass = callData.is_successful ? 'badge-success' : 'badge-danger';
        }

        html += `
            <div class="analysis-section">
                <h3>🎯 Результат звонка</h3>
                <span class="analysis-result-badge ${badgeClass}">${escapeHtml(callData.call_result)}</span>
            </div>
        `;
    } else if (callData.is_successful !== null && callData.is_successful !== undefined) {
        // Если нет call_result, но есть флаг успешности
        const badgeClass = callData.is_successful ? 'badge-success' : 'badge-danger';
        const text = callData.is_successful ? 'Успешный' : 'Неуспешный';
        html += `
            <div class="analysis-section">
                <h3>🎯 Результат звонка</h3>
                <span class="analysis-result-badge ${badgeClass}">${text}</span>
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
