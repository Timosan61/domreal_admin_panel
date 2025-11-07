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

    // Загружаем CRM данные
    if (callData && callData.client_phone) {
        renderCrmData();
    }
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
            renderCrmData();  // Добавлено: отрисовка CRM данных
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
                <div class="info-value">${formatCallType(callData.call_type, callData.is_first_call)}</div>
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
    const playerContainer = document.getElementById('audio-player-container');

    // ✅ ВСЕГДА пытаемся загрузить аудио из API (backend поддерживает скачивание из Beeline)
    audioSource.src = `api/audio_stream.php?callid=${encodeURIComponent(callData.callid)}`;
    audioPlayer.load();

    // Показываем предупреждение для не-DONE статусов, НО плеер оставляем
    let statusWarning = '';

    if (callData.audio_status === 'ERROR') {
        statusWarning = `<div style="margin-bottom: 12px; padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404;">
            <strong>⚠️ Транскрибация не удалась</strong>`;

        if (callData.audio_error && callData.audio_error !== 'null') {
            statusWarning += `<div style="margin-top: 6px; font-size: 13px;">Причина: <em>${escapeHtml(callData.audio_error)}</em></div>`;
        }

        statusWarning += `<div style="margin-top: 8px; font-size: 13px;">
            💡 Аудиозапись доступна для прослушивания (загружается из Beeline API)<br>
            📋 Для повторной обработки обратитесь к администратору
        </div></div>`;
    } else if (callData.audio_status === 'QUEUED') {
        statusWarning = `<div style="margin-bottom: 12px; padding: 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; color: #0c5460;">
            ⏳ Транскрибация в очереди. Аудио доступно для прослушивания.
        </div>`;
    } else if (callData.audio_status === 'DOWNLOADING') {
        statusWarning = `<div style="margin-bottom: 12px; padding: 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; color: #0c5460;">
            ⬇️ Аудио загружается. Попробуйте позже.
        </div>`;
    } else if (callData.audio_status === 'TRANSCRIBING') {
        statusWarning = `<div style="margin-bottom: 12px; padding: 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; color: #0c5460;">
            🎙️ Идёт транскрибация. Аудио доступно для прослушивания.
        </div>`;
    } else if (!callData.audio_status) {
        statusWarning = `<div style="margin-bottom: 12px; padding: 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404;">
            ❓ Задача на обработку не создана. Аудио может быть доступно через Beeline API.
        </div>`;
    }

    // Вставляем предупреждение ПЕРЕД плеером (если есть)
    if (statusWarning) {
        playerContainer.insertAdjacentHTML('afterbegin', statusWarning);
    }

    // Обработка ошибок загрузки аудио
    audioPlayer.addEventListener('error', function() {
        const errorDiv = document.createElement('div');
        errorDiv.style.cssText = 'padding: 20px; background: #f8d7da; border: 1px solid #dc3545; border-radius: 8px; color: #721c24; margin-top: 12px;';
        errorDiv.innerHTML = `
            <strong>❌ Не удалось загрузить аудиофайл</strong><br>
            <div style="margin-top: 8px; font-size: 13px;">
                Возможные причины:<br>
                • Файл отсутствует в хранилище<br>
                • Beeline API недоступен<br>
                • Запись не найдена в системе телефонии
            </div>
        `;
        playerContainer.appendChild(errorDiv);
    });
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

    if (!audioPlayer || !playPauseBtn || !seekBar || !volumeBar) return;

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

    // Результат звонка - стандартизированные категории (2025-10-29)
    if (callData.call_result) {
        // Очищаем префикс "Результат:" если есть
        let cleanResult = callData.call_result.replace(/^Результат:\s*/i, '').trim();

        // Убираем лишние слова для компактности
        cleanResult = cleanResult.replace(/\s+звонок$/i, '');
        cleanResult = cleanResult.replace(/\s+выполнена$/i, '');

        // Логика совпадает с общей таблицей (calls_list.js)
        let badgeClass = 'badge-info'; // По умолчанию синий
        let icon = '';
        const resultLower = cleanResult.toLowerCase();

        // ✅ Стандартизированные результаты (работают для обоих типов звонков)

        // 🟢 Позитивные результаты (зеленые)
        if (resultLower.includes('назначен показ')) {
            badgeClass = 'badge-success';
            icon = '📅 ';
        } else if (resultLower.includes('подтвержден показ') || resultLower.includes('подтверждён показ')) {
            badgeClass = 'badge-success';
            icon = '✅ ';
        } else if (resultLower.includes('показ проведен') || resultLower.includes('показ провед')) {
            badgeClass = 'badge-success';
            icon = '🏠 ';
        } else if (resultLower.includes('отправлены новые варианты') || (resultLower.includes('отправлен') && resultLower.includes('вариант'))) {
            badgeClass = 'badge-success';
            icon = '📤 ';
        } else if (resultLower.includes('клиент подтвердил интерес')) {
            badgeClass = 'badge-success';
            icon = '👍 ';
        } else if (resultLower.includes('бронь') || resultLower.includes('задаток')) {
            badgeClass = 'badge-success';
            icon = '💰 ';
        } else if (resultLower.includes('сделка закрыта') || resultLower.includes('сделка заверш')) {
            badgeClass = 'badge-success';
            icon = '🎉 ';
        } else if (resultLower.includes('назначена консультация')) {
            badgeClass = 'badge-success';
            icon = '🗓️ ';
        }

        // 🟡 Нейтральные/Ожидание (желтые/синие)
        else if (resultLower.includes('отложенное решение') || resultLower.includes('отложен')) {
            badgeClass = 'badge-info';
            icon = '⏳ ';
        } else if (resultLower.includes('ожидается ответ клиента') || (resultLower.includes('ожидается') && resultLower.includes('ответ'))) {
            badgeClass = 'badge-info';
            icon = '⏰ ';
        }

        // 🔴 Негативные (красные/серые)
        else if (resultLower.includes('недозвон') || resultLower.includes('не дозвон') || resultLower.includes('не отвечает')) {
            badgeClass = 'badge-secondary';
            icon = '📵 ';
        } else if (resultLower.includes('отказ') || resultLower.includes('неактуально')) {
            badgeClass = 'badge-danger';
            icon = '❌ ';
        } else if (resultLower.includes('не целевой') || resultLower.includes('нецелевой')) {
            badgeClass = 'badge-warning';
            icon = '⛔ ';
        }

        // 🔵 Fallback для старых результатов
        else if (resultLower.includes('квалифик')) {
            badgeClass = 'badge-success';
            icon = '📋 ';
        } else if (resultLower.includes('показ') || resultLower.includes('презентац')) {
            badgeClass = 'badge-success';
            icon = '🏠 ';
        } else if (resultLower.includes('материал')) {
            badgeClass = 'badge-success';
            icon = '📤 ';
        } else if (resultLower.includes('перезвон')) {
            badgeClass = 'badge-warning';
            icon = '📞 ';
        } else if (resultLower.includes('думает')) {
            badgeClass = 'badge-info';
            icon = '💭 ';
        }

        // Общие категории (для любого типа звонка)
        if (resultLower.includes('личн') || resultLower.includes('нерабоч')) {
            badgeClass = 'badge-secondary';
            icon = '👤 ';
        }

        // Если нет спецкатегорий, используем флаг успешности как fallback
        if (!icon && (callData.is_successful !== null && callData.is_successful !== undefined)) {
            badgeClass = callData.is_successful ? 'badge-success' : 'badge-danger';
        }

        html += `
            <div class="analysis-section">
                <h3>🎯 Результат звонка</h3>
                <span class="analysis-result-badge ${badgeClass}">${icon}${escapeHtml(cleanResult)}</span>
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
function formatCallType(type, isFirstCall) {
    // ✨ НОВАЯ ЛОГИКА (2025-10-26): Тип звонка определяется по полю is_first_call
    // Если поле is_first_call доступно, используем его
    if (isFirstCall !== undefined && isFirstCall !== null) {
        if (isFirstCall === 1 || isFirstCall === true) {
            return '1️⃣ Первый звонок';
        } else {
            return '🔁 Повторный звонок';
        }
    }

    // Fallback на старую логику (если is_first_call недоступен)
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

/**
 * Отрисовка CRM данных из callData
 */
function renderCrmData() {
    const crmBlock = document.getElementById('crm-data-block');

    // Проверяем наличие CRM полей в callData
    if (callData.crm_funnel_name && callData.crm_step_name) {
        // Цветовая кодировка по воронкам
        const funnelColors = {
            'Покупатели': 'success',
            'Продавец': 'info',
            'Риелторы': 'warning'
        };
        const badgeColor = funnelColors[callData.crm_funnel_name] || 'secondary';

        crmBlock.innerHTML = `
            <table class="table table-sm" style="margin-bottom: 0;">
                <tr>
                    <th width="30%" style="border-top: none;">Воронка:</th>
                    <td style="border-top: none;">
                        <span class="badge badge-${badgeColor}" style="font-size: 14px; padding: 6px 12px;">${escapeHtml(callData.crm_funnel_name)}</span>
                    </td>
                </tr>
                <tr>
                    <th>Этап:</th>
                    <td>${escapeHtml(callData.crm_step_name)}</td>
                </tr>
                <tr>
                    <th>ID Заявки:</th>
                    <td>
                        ${callData.crm_requisition_id ?
                            `<a href="https://api.joywork.ru/requisitions/${escapeHtml(callData.crm_requisition_id)}" target="_blank" style="color: #007bff;">
                                ${escapeHtml(callData.crm_requisition_id)}
                                <i class="fas fa-external-link-alt" style="font-size: 12px; margin-left: 4px;"></i>
                            </a>` :
                            '<span class="text-muted">N/A</span>'
                        }
                    </td>
                </tr>
                <tr>
                    <th>Обновлено:</th>
                    <td>
                        <small class="text-muted">${callData.crm_last_sync ? formatDateTime(callData.crm_last_sync) : 'Не синхронизировано'}</small>
                    </td>
                </tr>
            </table>
        `;

        // Агрегированное резюме клиента (если есть)
        if (callData.aggregate_summary && callData.aggregate_summary.trim() !== '') {
            crmBlock.innerHTML += `
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                    <h6 style="color: #138496; margin-bottom: 10px;">
                        📊 Агрегированное резюме клиента
                        ${callData.total_calls_count > 1 ? `<span class="badge badge-info" style="font-size: 0.75em; margin-left: 8px;">${callData.total_calls_count} звонков</span>` : ''}
                    </h6>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 14px; line-height: 1.6;">
                        ${escapeHtml(callData.aggregate_summary)}
                    </div>
                    ${callData.last_call_date ? `<small class="text-muted" style="display: block; margin-top: 8px;">Последний звонок: ${formatDateTime(callData.last_call_date)}</small>` : ''}
                </div>
            `;
        }
    } else {
        crmBlock.innerHTML = `
            <div class="alert alert-warning" role="alert" style="margin-bottom: 0;">
                <i class="fas fa-exclamation-triangle"></i>
                CRM данные не найдены для этого звонка
            </div>
        `;
    }
}
