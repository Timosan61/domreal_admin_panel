/**
 * JavaScript для страницы оценки звонка
 */

// Глобальные переменные
let callData = null;
let evalWaveSurfer = null;

// Переменные для синхронизации транскрипции с аудио
let lastHighlightedSegmentIndex = -1;  // Индекс последнего подсвеченного сегмента
let autoScrollEnabled = true;           // Флаг умной автопрокрутки
let throttleTimeout = null;             // Таймер для throttling обновлений

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
    setupAudioPlayer();  // Сначала создаем WaveSurfer
    setupAudioSource();  // Потом загружаем аудио в него
    setupSmartAutoScroll();  // Настройка умной автопрокрутки
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
            renderCallInfo(); // Теперь включает CRM данные
            renderTranscript();
            renderChecklist();
            renderAnalysis();
            renderEmotionAnalysis(); // Гибридный анализ эмоций
            // setupAudioSource() вызывается позже в initializePage()
        } else {
            showError(result.error || 'Ошибка загрузки данных');
        }
    } catch (error) {
        console.error('Ошибка загрузки звонка:', error);
        showError('Ошибка подключения к серверу');
    }
}

/**
 * Отрисовка основной информации о звонке (включая CRM данные)
 */
function renderCallInfo() {
    const container = document.getElementById('call-info');

    let html = `
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
    `;

    // Добавляем CRM данные в ту же сетку, если они есть
    if (callData.crm_funnel_name && callData.crm_step_name) {
        // Цветовая кодировка по воронкам
        const funnelColors = {
            'Покупатели': 'success',
            'Продавец': 'info',
            'Риелторы': 'warning'
        };
        const badgeColor = funnelColors[callData.crm_funnel_name] || 'secondary';

        html += `
            <div class="info-item">
                <div class="info-label">🎯 Воронка CRM</div>
                <div class="info-value">
                    <span class="badge badge-${badgeColor}">${escapeHtml(callData.crm_funnel_name)}</span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">📍 Этап</div>
                <div class="info-value">${escapeHtml(callData.crm_step_name)}</div>
            </div>
            <div class="info-item">
                <div class="info-label">🔖 ID Заявки</div>
                <div class="info-value">
                    ${callData.crm_requisition_id ?
                        `<a href="https://api.joywork.ru/requisitions/${escapeHtml(callData.crm_requisition_id)}" target="_blank" style="color: #007bff; text-decoration: none;">
                            ${escapeHtml(callData.crm_requisition_id)}
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 4px;">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>` :
                        '<span style="color: #9ca3af;">N/A</span>'
                    }
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">🔄 Синхронизация</div>
                <div class="info-value">
                    <small style="color: #6b7280;">${callData.crm_last_sync ? formatDateTime(callData.crm_last_sync) : 'Не синхронизировано'}</small>
                </div>
            </div>
        `;
    }

    html += `</div>`;

    // Добавляем агрегированное резюме клиента (если есть) под основной информацией
    if (callData.aggregate_summary && callData.aggregate_summary.trim() !== '') {
        html += `
            <div class="client-aggregate-summary">
                <h6 class="client-aggregate-title">
                    📊 Агрегированное резюме клиента
                    ${callData.total_calls_count > 1 ? `<span class="badge badge-info" style="font-size: 0.75em; margin-left: 8px;">${callData.total_calls_count} звонков</span>` : ''}
                </h6>
                <div class="client-aggregate-content">
                    ${escapeHtml(callData.aggregate_summary)}
                </div>
                ${callData.last_call_date ? `<small class="client-aggregate-date">Последний звонок: ${formatDateTime(callData.last_call_date)}</small>` : ''}
            </div>
        `;
    }

    container.innerHTML = html;
}

/**
 * Настройка источника аудио для WaveSurfer
 */
function setupAudioSource() {
    if (!evalWaveSurfer) {
        console.error('❌ evalWaveSurfer не инициализирован');
        return;
    }

    const playerContainer = document.querySelector('.audio-panel');
    const audioUrl = `api/audio_stream.php?callid=${encodeURIComponent(callData.callid)}`;

    // Обновление player-info с данными звонка
    document.getElementById('eval-player-callid').textContent = callData.callid;
    document.getElementById('eval-player-employee').textContent = callData.employee_name || '-';
    document.getElementById('eval-player-client').textContent = callData.client_phone || '-';

    // ✅ Загружаем аудио в WaveSurfer
    console.log('🎵 Загрузка аудио:', audioUrl);
    evalWaveSurfer.load(audioUrl);

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
    evalWaveSurfer.on('error', function(error) {
        console.error('❌ WaveSurfer error:', error);
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
 * Настройка аудиоплеера с WaveSurfer.js
 * Обеспечивает синхронизацию с транскрипцией через highlightCurrentSegment()
 */
function setupAudioPlayer() {
    console.log('🎵 setupAudioPlayer() вызвана');

    // Проверка наличия WaveSurfer
    if (typeof WaveSurfer === 'undefined') {
        console.error('❌ WaveSurfer.js не загружен');
        return;
    }

    // Создание экземпляра WaveSurfer
    evalWaveSurfer = WaveSurfer.create({
        container: '#eval-waveform',
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

    console.log('✅ WaveSurfer создан');

    // Обработчик готовности аудио (загружены метаданные)
    evalWaveSurfer.on('ready', function() {
        const duration = evalWaveSurfer.getDuration();
        document.getElementById('eval-total-time').textContent = formatTime(duration);
        console.log('✅ Аудио готово, длительность:', formatTime(duration));
    });

    // Обработчик процесса воспроизведения (аналог timeupdate)
    let audioprocessCount = 0;
    evalWaveSurfer.on('audioprocess', function() {
        audioprocessCount++;
        const currentTime = evalWaveSurfer.getCurrentTime();

        // Обновление текущего времени
        document.getElementById('eval-current-time').textContent = formatTime(currentTime);

        // Логирование каждого 10-го события
        if (audioprocessCount % 10 === 0) {
            console.log(`⏰ Audioprocess #${audioprocessCount}:`, currentTime.toFixed(2) + 's');
        }

        // Синхронизация с транскрипцией (с throttling для производительности)
        if (!throttleTimeout) {
            throttleTimeout = setTimeout(() => {
                console.log('🎯 Вызов highlightCurrentSegment:', currentTime.toFixed(2) + 's');
                highlightCurrentSegment(currentTime);
                throttleTimeout = null;
            }, 100); // Обновление каждые 100мс
        }
    });

    // Обработчик начала воспроизведения
    evalWaveSurfer.on('play', function() {
        console.log('▶️ WaveSurfer play event');
        updateEvalPlayPauseButton(true);
    });

    // Обработчик паузы
    evalWaveSurfer.on('pause', function() {
        console.log('⏸️ WaveSurfer pause event');
        updateEvalPlayPauseButton(false);
    });

    // Обработчик завершения воспроизведения
    evalWaveSurfer.on('finish', function() {
        console.log('⏹️ WaveSurfer finish event');
        updateEvalPlayPauseButton(false);
    });

    // Обработчик кнопки Play/Pause
    document.getElementById('eval-play-btn').addEventListener('click', function() {
        if (evalWaveSurfer) {
            evalWaveSurfer.playPause();
        }
    });

    // Обработчик регулятора громкости
    document.getElementById('eval-volume-slider').addEventListener('input', function() {
        if (evalWaveSurfer) {
            evalWaveSurfer.setVolume(this.value / 100);
        }
    });

    // Обработчик скорости воспроизведения
    document.getElementById('eval-speed').addEventListener('change', function() {
        if (evalWaveSurfer) {
            evalWaveSurfer.setPlaybackRate(parseFloat(this.value));
        }
    });

    console.log('✅ Все обработчики WaveSurfer установлены');
}

/**
 * Обновление иконки кнопки Play/Pause
 */
function updateEvalPlayPauseButton(isPlaying) {
    const playBtn = document.getElementById('eval-play-btn');

    if (isPlaying) {
        // Иконка Pause (две вертикальные полоски)
        playBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>
            </svg>
        `;
        playBtn.title = 'Pause';
    } else {
        // Иконка Play (треугольник)
        playBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="5 3 19 12 5 21 5 3"></polygon>
            </svg>
        `;
        playBtn.title = 'Play';
    }
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

    const html = segments.map((segment, index) => {
        // Определяем класс спикера для визуального разделения
        let speakerClass = '';

        if (segment.speaker_role === 'Менеджер') {
            // Если роль определена - используем её
            speakerClass = 'speaker-manager';
        } else if (segment.speaker_role === 'Клиент') {
            speakerClass = 'speaker-client';
        } else if (segment.speaker === 'SPEAKER_00') {
            // Если роль не определена - просто визуальное разделение по цветам
            // SPEAKER_00 = синий (не обязательно менеджер!)
            speakerClass = 'speaker-00';
        } else if (segment.speaker === 'SPEAKER_01') {
            // SPEAKER_01 = красный (не обязательно клиент!)
            speakerClass = 'speaker-01';
        } else {
            // По умолчанию нейтральный
            speakerClass = 'speaker-unknown';
        }

        return `
            <div class="transcript-segment ${speakerClass}"
                 data-segment-index="${index}"
                 data-start="${segment.start}"
                 data-end="${segment.end}"
                 data-speaker="${segment.speaker}">
                <div class="segment-header">
                    <span class="speaker-label">${escapeHtml(segment.speaker_role || segment.speaker)}</span>
                    <span class="segment-time">${formatTime(segment.start)} - ${formatTime(segment.end)}</span>
                </div>
                <div class="segment-text">${escapeHtml(segment.text)}</div>
                <div class="segment-progress-bar" style="width: 0%;"></div>
            </div>
        `;
    }).join('');

    container.innerHTML = html || '<div class="error">Нет сегментов транскрипции</div>';

    // Добавляем обработчики кликов для синхронизации Транскрипция → Аудио
    const segmentElements = container.querySelectorAll('.transcript-segment');
    segmentElements.forEach(segmentElement => {
        segmentElement.addEventListener('click', function() {
            const startTime = parseFloat(this.dataset.start);

            if (!isNaN(startTime) && evalWaveSurfer) {
                const duration = evalWaveSurfer.getDuration();

                // Перемотка на начало сегмента (seekTo принимает процент от 0 до 1)
                const progress = startTime / duration;
                evalWaveSurfer.seekTo(progress);

                // Автоплей (всегда, даже если была пауза)
                evalWaveSurfer.play();

                console.log('🎯 Клик по сегменту:', startTime.toFixed(2) + 's', `(${(progress * 100).toFixed(1)}%)`);
            }
        });
    });
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

    const html = callData.checklist.map(item => {
        // Предварительно подсчитываем количество релевантных сегментов для каждого критерия
        const relevantCount = findRelevantSegments(item.id).length;
        const segmentBadge = relevantCount > 0
            ? `<span class="segment-count-badge" title="${relevantCount} релевантных сегментов">${relevantCount}</span>`
            : '';

        return `
            <div class="checklist-item" data-checklist-id="${escapeHtml(item.id)}">
                <input type="checkbox" class="checklist-checkbox" ${item.checked ? 'checked' : ''} disabled>
                <div class="checklist-content">
                    <div class="checklist-label">
                        ${escapeHtml(item.label)}
                        ${segmentBadge}
                    </div>
                    <div class="checklist-description">${escapeHtml(item.description)}</div>
                </div>
            </div>
        `;
    }).join('');

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

    // Делаем чеклист интерактивным после рендеринга
    makeChecklistInteractive();
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
            badgeClass = 'badge-danger';
            icon = '🚫 ';
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

// ========================================
// Интерактивная связь между чеклистом и транскрипцией
// ========================================

/**
 * Создает маппинг критериев оценки на ключевые слова
 * @returns {Object} Объект с ID критериев и массивами ключевых слов
 */
function createChecklistKeywordMapping() {
    return {
        // Критерии для первого звонка (v4, 6 пунктов)
        'v4_interest': [
            'интерес', 'заинтересован', 'заинтересована', 'интересует',
            'подыск', 'ищу', 'ищем', 'подбира', 'подобрать', 'найти',
            'хочу купить', 'хотим купить', 'нужна квартира', 'нужен дом'
        ],
        'v4_location': [
            'сочи', 'находитесь', 'находится', 'приедете', 'приедет',
            'местный', 'местная', 'локация', 'где вы', 'откуда',
            'в городе', 'иногороднй', 'иногородняя', 'живете', 'живёте'
        ],
        'v4_payment': [
            'оплата', 'ипотека', 'ипотек', 'наличные', 'наличных',
            'рассрочка', 'рассроч', 'бюджет', 'сколько', 'цена',
            'стоимость', 'деньги', 'финансы', 'платить', 'заплатить'
        ],
        'v4_goal': [
            'цель', 'инвестиция', 'инвестиц', 'жить', 'проживан',
            'сдавать', 'сдавал', 'для себя', 'для семьи', 'переехать',
            'вложить', 'вложен', 'доход', 'заработ'
        ],
        'v4_history': [
            'смотрели', 'смотрел', 'видели', 'видел', 'показ',
            'просмотр', 'варианты', 'вариант', 'предлагали',
            'другие объекты', 'уже показывали', 'уже смотрели'
        ],
        'v4_action': [
            'встреча', 'встречаемся', 'встретимся', 'показ', 'покажу',
            'отправлю', 'отправл', 'пришлю', 'прислать', 'свяжусь',
            'перезвоню', 'позвоню', 'свяжемся', 'договорим', 'назначим'
        ],

        // Критерии для повторного звонка (v4, 5 пунктов: 4.1-4.5)
        'repeat_greeting': [
            'добрый день', 'здравствуйте', 'меня зовут', 'это',
            'компания', 'напомню', 'звонил', 'звонила', 'говорили',
            'общались', 'беседовали', 'обсуждали', 'рассказывал', 'рассказывала'
        ],
        'repeat_actions': [
            'предлагаю', 'предложу', 'могу отправить', 'могу показать',
            'давайте встретимся', 'давайте посмотрим', 'назначим',
            'организуем', 'подготовлю', 'подберу', 'отправил', 'отправила',
            'звонить', 'позвон', 'созвон', 'встреча', 'показ'
        ],
        'repeat_next_step': [
            'следующий', 'дальше', 'договорились', 'договоримся',
            'жду', 'ждём', 'созвонимся', 'созвон', 'встреча',
            'связь', 'свяжемся', 'уточним', 'обсудим', 'удобно',
            'время', 'когда', 'во сколько', 'в 17', 'в 18'
        ],
        'repeat_objections': [
            'понимаю', 'понятно', 'согласен', 'но', 'однако',
            'дорого', 'дороговато', 'не подходит', 'не устраивает',
            'другое', 'другой', 'проблема', 'сложность', 'вопрос',
            'сомнение', 'не уверен', 'подумаю'
        ],
        'repeat_informal': [
            'как дела', 'как у вас', 'отлично', 'хорошо', 'супер',
            'здорово', 'прекрасно', 'замечательно', 'согласен',
            'понял', 'ясно', 'конечно', 'разумеется', 'ага', 'угу',
            'верно', 'точно', 'да-да', 'ок', 'окей'
        ]
    };
}

/**
 * Ищет релевантные сегменты транскрипции для критерия оценки
 * @param {string} checklistItemId - ID критерия из чеклиста
 * @returns {Array<number>} Массив индексов релевантных сегментов
 */
function findRelevantSegments(checklistItemId) {
    if (!callData || !callData.diarization || !callData.diarization.segments) {
        console.warn('Нет данных транскрипции');
        return [];
    }

    const keywordMapping = createChecklistKeywordMapping();
    const keywords = keywordMapping[checklistItemId];

    if (!keywords || keywords.length === 0) {
        console.warn(`⚠️ Не найдены ключевые слова для критерия: "${checklistItemId}"`);
        console.log('Доступные критерии в маппинге:', Object.keys(keywordMapping));
        return [];
    }

    const segments = callData.diarization.segments;
    const relevantIndices = [];

    console.log(`🔍 Поиск для критерия "${checklistItemId}"`);
    console.log(`📝 Ключевые слова (${keywords.length}):`, keywords);

    segments.forEach((segment, index) => {
        const text = segment.text.toLowerCase();

        // Проверяем, содержит ли сегмент хотя бы одно ключевое слово
        const matchedKeywords = keywords.filter(keyword => text.includes(keyword.toLowerCase()));

        if (matchedKeywords.length > 0) {
            relevantIndices.push(index);
            console.log(`✅ Сегмент #${index} содержит ключевые слова:`, matchedKeywords);
        }
    });

    console.log(`📊 Итого найдено ${relevantIndices.length} релевантных сегментов для "${checklistItemId}"`);
    return relevantIndices;
}

/**
 * Подсвечивает и прокручивает к сегменту транскрипции
 * @param {number} segmentIndex - Индекс сегмента для подсветки
 */
function highlightAndScrollToSegment(segmentIndex) {
    // Убираем предыдущую подсветку
    const prevHighlighted = document.querySelectorAll('.transcript-segment.segment-highlighted');
    prevHighlighted.forEach(el => el.classList.remove('segment-highlighted'));

    // Находим целевой сегмент
    const targetSegment = document.querySelector(`.transcript-segment[data-segment-index="${segmentIndex}"]`);

    if (!targetSegment) {
        console.warn(`Сегмент с индексом ${segmentIndex} не найден`);
        return;
    }

    // Добавляем подсветку
    targetSegment.classList.add('segment-highlighted');

    // Прокручиваем к сегменту с плавной анимацией
    targetSegment.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
        inline: 'nearest'
    });

    // Убираем подсветку через 3 секунды
    setTimeout(() => {
        targetSegment.classList.remove('segment-highlighted');
    }, 3000);
}

/**
 * Делает чеклист интерактивным - добавляет обработчики кликов
 */
function makeChecklistInteractive() {
    const checklistItems = document.querySelectorAll('.checklist-item');

    // Отладка: выводим все ID критериев в консоль
    console.log('=== ОТЛАДКА: ID критериев в чеклисте ===');
    checklistItems.forEach(item => {
        const checklistId = item.getAttribute('data-checklist-id');
        console.log(`- ${checklistId}`);
    });
    console.log('========================================');

    checklistItems.forEach(item => {
        const checklistId = item.getAttribute('data-checklist-id');

        if (!checklistId) {
            return;
        }

        // Добавляем обработчик клика
        item.addEventListener('click', function(event) {
            // Убираем класс 'active' с всех элементов
            checklistItems.forEach(el => el.classList.remove('active'));

            // Добавляем класс 'active' к текущему элементу
            this.classList.add('active');

            // Находим релевантные сегменты
            const relevantSegments = findRelevantSegments(checklistId);

            if (relevantSegments.length === 0) {
                console.warn(`Не найдено релевантных сегментов для критерия: ${checklistId}`);
                // Можно показать уведомление пользователю
                showNotification('Не найдено релевантных фрагментов транскрипции для этого критерия', 'info');
                return;
            }

            // Прокручиваем и подсвечиваем первый релевантный сегмент
            highlightAndScrollToSegment(relevantSegments[0]);

            console.log(`Критерий "${checklistId}": найдено ${relevantSegments.length} сегментов, показан первый (#${relevantSegments[0]})`);
        });

        // Добавляем визуальный эффект при наведении
        item.addEventListener('mouseenter', function() {
            const relevantSegments = findRelevantSegments(checklistId);
            if (relevantSegments.length > 0) {
                // Показываем счетчик найденных сегментов
                this.style.cursor = 'pointer';
            } else {
                this.style.cursor = 'not-allowed';
            }
        });
    });

    console.log(`Сделано интерактивными ${checklistItems.length} критериев чеклиста`);
}

/**
 * Подсвечивает текущий сегмент транскрипции на основе времени воспроизведения аудио
 * @param {number} currentTime - Текущее время воспроизведения в секундах
 */
function highlightCurrentSegment(currentTime) {
    const segments = document.querySelectorAll('.transcript-segment');
    if (segments.length === 0) return;

    let activeSegmentIndex = -1;
    let activeSegment = null;

    // Находим активный сегмент
    segments.forEach((segment, index) => {
        const start = parseFloat(segment.dataset.start);
        const end = parseFloat(segment.dataset.end);

        if (currentTime >= start && currentTime < end) {
            activeSegmentIndex = index;
            activeSegment = segment;
        }
    });

    // Если активный сегмент изменился
    if (activeSegmentIndex !== lastHighlightedSegmentIndex) {
        // Убираем подсветку у предыдущего
        if (lastHighlightedSegmentIndex >= 0 && lastHighlightedSegmentIndex < segments.length) {
            segments[lastHighlightedSegmentIndex].classList.remove('segment-active');

            // Сбрасываем прогресс-бар предыдущего сегмента
            const prevProgressBar = segments[lastHighlightedSegmentIndex].querySelector('.segment-progress-bar');
            if (prevProgressBar) {
                prevProgressBar.style.width = '0%';
            }
        }

        // Подсвечиваем текущий
        if (activeSegment) {
            activeSegment.classList.add('segment-active');

            // Прокрутка к активному сегменту (если включена)
            if (autoScrollEnabled) {
                activeSegment.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

            lastHighlightedSegmentIndex = activeSegmentIndex;
        } else {
            lastHighlightedSegmentIndex = -1;
        }
    }

    // Обновляем прогресс-бар активного сегмента
    if (activeSegment) {
        const start = parseFloat(activeSegment.dataset.start);
        const end = parseFloat(activeSegment.dataset.end);
        updateSegmentProgress(activeSegment, currentTime, start, end);
    }
}

/**
 * Обновляет прогресс-бар воспроизведения сегмента
 * @param {HTMLElement} segmentElement - DOM элемент сегмента
 * @param {number} currentTime - Текущее время воспроизведения
 * @param {number} startTime - Время начала сегмента
 * @param {number} endTime - Время окончания сегмента
 */
function updateSegmentProgress(segmentElement, currentTime, startTime, endTime) {
    const progressBar = segmentElement.querySelector('.segment-progress-bar');
    if (!progressBar) return;

    const duration = endTime - startTime;
    if (duration <= 0) return;

    const progress = ((currentTime - startTime) / duration) * 100;
    const clampedProgress = Math.max(0, Math.min(100, progress));

    progressBar.style.width = `${clampedProgress}%`;
}

/**
 * Настраивает умную автопрокрутку транскрипции
 * Автопрокрутка отключается при ручном скролле и включается через 3 секунды после остановки
 */
function setupSmartAutoScroll() {
    const transcriptContainer = document.getElementById('transcript');
    if (!transcriptContainer) return;

    let scrollTimeout = null;

    transcriptContainer.addEventListener('scroll', function() {
        // Пользователь начал скроллить → отключить автопрокрутку
        autoScrollEnabled = false;

        // Очищаем предыдущий таймер
        if (scrollTimeout) {
            clearTimeout(scrollTimeout);
        }

        // Включаем автопрокрутку через 3 секунды после остановки скролла
        scrollTimeout = setTimeout(() => {
            autoScrollEnabled = true;
        }, 3000);
    });

    console.log('Умная автопрокрутка настроена');
}

/**
 * Показывает временное уведомление пользователю
 * @param {string} message - Текст уведомления
 * @param {string} type - Тип уведомления (info, warning, success, error)
 */
function showNotification(message, type = 'info') {
    // Создаем элемент уведомления
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'info' ? '#3b82f6' : type === 'warning' ? '#f59e0b' : type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        font-size: 14px;
        max-width: 300px;
        animation: slideInRight 0.3s ease-out;
    `;
    notification.textContent = message;

    // Добавляем в документ
    document.body.appendChild(notification);

    // Удаляем через 3 секунды
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

/**
 * Рендеринг гибридного анализа эмоций
 */
function renderEmotionAnalysis() {
    // Получаем callid из URL
    const urlParams = new URLSearchParams(window.location.search);
    const callid = urlParams.get('callid');

    if (!callid) {
        console.error('No callid for emotion analysis');
        return;
    }

    // Инициализируем emotion display компонент
    const emotionDisplay = new EmotionDisplay('#emotion-analysis-container');
    emotionDisplay.loadAndDisplay(callid);
}

// Добавляем CSS анимации для уведомлений
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
