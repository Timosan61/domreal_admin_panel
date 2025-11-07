/**
 * JavaScript для страницы оценки звонка (новый дизайн)
 */

// Получаем callid из URL
const urlParams = new URLSearchParams(window.location.search);
const callid = urlParams.get('callid');

// WaveSurfer instance
let wavesurfer = null;

// Checklist questions
const checklistQuestions = [
    {
        id: 'location',
        text: 'Вопросы, которые должен задать менеджер',
        key: 'location'
    },
    {
        id: 'planning',
        text: 'Уточняет город и назначение объекта',
        key: 'goal'
    },
    {
        id: 'stroika',
        text: 'Спрашивает, когда планируется стройка',
        key: 'is_local',
        icon: '👁️'
    },
    {
        id: 'budget',
        text: 'Спрашивает, имеется ли проект',
        key: 'payment'
    },
    {
        id: 'zemlya',
        text: 'Спрашивает, есть ли земельный участок',
        key: 'budget'
    },
    {
        id: 'vozvrat',
        text: 'Спрашивает, почему клиент выбирает лстк для возведения своего объекта',
        key: 'goal',
        icon: '👁️'
    },
    {
        id: 'tech',
        text: 'Спрашивает, с чем еще сравнивают технологию лстк',
        key: 'goal',
        icon: '👁️'
    },
    {
        id: 'criteria',
        text: 'Спрашивает, какие главные критерии при выборе технологии',
        key: 'goal'
    }
];

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    if (!callid) {
        alert('Не указан ID звонка');
        window.location.href = 'index_new.php';
        return;
    }

    initializeAudioPlayer();
    loadCallDetails();
    setupEventListeners();
});

/**
 * Инициализация аудиоплеера с waveform
 */
function initializeAudioPlayer() {
    wavesurfer = WaveSurfer.create({
        container: '#waveform',
        waveColor: '#D1D5DB',
        progressColor: '#007AFF',
        cursorColor: '#007AFF',
        barWidth: 2,
        barGap: 1,
        barRadius: 2,
        height: 64,
        normalize: true,
        backend: 'WebAudio'
    });

    // Обработчики событий
    wavesurfer.on('ready', function() {
        const duration = wavesurfer.getDuration();
        document.getElementById('total-time').textContent = formatTime(duration);
    });

    wavesurfer.on('audioprocess', function() {
        const currentTime = wavesurfer.getCurrentTime();
        document.getElementById('current-time').textContent = formatTime(currentTime);
    });

    wavesurfer.on('finish', function() {
        updatePlayPauseButton(false);
    });
}

/**
 * Настройка обработчиков событий
 */
function setupEventListeners() {
    // Play/Pause
    document.getElementById('play-pause-btn').addEventListener('click', function() {
        if (wavesurfer) {
            wavesurfer.playPause();
            updatePlayPauseButton(wavesurfer.isPlaying());
        }
    });

    // Скорость воспроизведения
    document.getElementById('playback-speed').addEventListener('change', function() {
        if (wavesurfer) {
            wavesurfer.setPlaybackRate(parseFloat(this.value));
        }
    });

    // Сохранение оценки
    document.getElementById('save-evaluation').addEventListener('click', saveEvaluation);
}

/**
 * Загрузка детальной информации о звонке
 */
async function loadCallDetails() {
    try {
        const response = await fetch(`api/call_details.php?callid=${encodeURIComponent(callid)}`);
        const result = await response.json();

        if (result.success) {
            renderCallDetails(result.data);
        } else {
            alert('Ошибка загрузки данных звонка');
        }
    } catch (error) {
        console.error('Ошибка загрузки звонка:', error);
        alert('Ошибка подключения к серверу');
    }
}

/**
 * Отрисовка деталей звонка
 */
function renderCallDetails(call) {
    // Обновляем заголовок
    document.getElementById('call-datetime').textContent = formatDateTime(call.started_at_utc);
    document.getElementById('client-phone').textContent = call.client_phone || 'Не указан';

    // Обновляем поля
    document.getElementById('field-direction').textContent = call.direction === 'INBOUND' ? 'Входящий' : 'Исходящий';

    // Загружаем аудио если есть
    if (call.audio_path || call.call_url) {
        const audioUrl = call.audio_path || call.call_url;
        wavesurfer.load(audioUrl);
    } else {
        // Если нет аудио, показываем заглушку
        document.querySelector('.waveform-container').innerHTML = '<p class="empty-state">Аудиофайл недоступен</p>';
    }

    // Отрисовка чеклиста
    renderChecklist(call.checklist || []);

    // Отрисовка транскрипции
    renderTranscript(call.diarization || []);
}

/**
 * Отрисовка чеклиста
 */
function renderChecklist(savedChecklist) {
    const container = document.getElementById('checklist-container');

    // Если чеклист пуст или отсутствует, показываем заглушку
    if (!savedChecklist || savedChecklist.length === 0) {
        container.innerHTML = '<p class="empty-state">Чеклист недоступен для этого звонка</p>';
        updateChecklistScore(0, 0);
        return;
    }

    let html = '';
    let totalQuestions = savedChecklist.length;  // ✅ Используем данные из API
    let answeredYes = 0;

    savedChecklist.forEach((item, index) => {  // ✅ Используем savedChecklist напрямую
        // Подсчитываем отмеченные пункты
        if (item.checked) answeredYes++;

        html += `
            <div class="checklist-item">
                <div class="checklist-question">
                    <strong>${item.label || item.text}</strong>
                    ${item.description ? `<div class="checklist-description" style="font-size: 0.9em; color: #666; margin-top: 5px;">${item.description}</div>` : ''}
                </div>
                <div class="checklist-status">
                    ${item.checked ? '<span class="badge badge-success">✓ ДА</span>' : '<span class="badge badge-secondary">✗ НЕТ</span>'}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;

    // Обновляем счётчик
    updateChecklistScore(answeredYes, totalQuestions);
}

/**
 * Обновление счётчика чеклиста
 */
function updateChecklistScore(answered, total) {
    document.getElementById('checklist-score').textContent = `${answered}/${total}`;
}

/**
 * Обновление счётчика из полей ввода
 */
function updateChecklistScoreFromInputs() {
    let answeredYes = 0;
    const total = checklistQuestions.length;

    for (let i = 0; i < total; i++) {
        const selected = document.querySelector(`input[name="question_${i}"]:checked`);
        if (selected && selected.value === 'yes') {
            answeredYes++;
        }
    }

    updateChecklistScore(answeredYes, total);
}

/**
 * Отрисовка транскрипции с диаризацией
 */
function renderTranscript(diarization) {
    const container = document.getElementById('transcript-container');

    if (!diarization || diarization.length === 0) {
        container.innerHTML = '<p class="empty-state">Транскрипция недоступна</p>';
        return;
    }

    let html = '<div class="transcript-list">';

    diarization.forEach(segment => {
        const speaker = segment.speaker === 'agent' ? 'Менеджер' : 'Клиент';
        html += `
            <div class="transcript-item">
                <div class="transcript-speaker">${speaker}:</div>
                <div class="transcript-text">${escapeHtml(segment.text)}</div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Сохранение оценки
 */
async function saveEvaluation() {
    // Собираем ответы чеклиста
    const answers = {};

    checklistQuestions.forEach((question, index) => {
        const selected = document.querySelector(`input[name="question_${index}"]:checked`);
        if (selected) {
            answers[question.id] = selected.value;
        }
    });

    try {
        const response = await fetch('api/save_evaluation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                callid: callid,
                checklist: answers
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('Оценка успешно сохранена');
        } else {
            alert('Ошибка сохранения: ' + (result.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Ошибка сохранения:', error);
        alert('Ошибка подключения к серверу');
    }
}

/**
 * Переключение секций (сворачивание/разворачивание)
 */
function toggleSection(header) {
    const section = header.closest('.panel-section');
    section.classList.toggle('collapsed');
}

/**
 * Обновление кнопки Play/Pause
 */
function updatePlayPauseButton(isPlaying) {
    const btn = document.getElementById('play-pause-btn');

    if (isPlaying) {
        btn.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>
            </svg>
        `;
    } else {
        btn.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="5 3 19 12 5 21 5 3"></polygon>
            </svg>
        `;
    }
}

/**
 * Форматирование времени (секунды -> мм:сс)
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
    return text.replace(/[&<>"']/g, m => map[m]);
}
