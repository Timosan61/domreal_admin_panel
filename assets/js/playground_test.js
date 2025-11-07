/**
 * Playground JavaScript - A/B Testing LLM Models
 */

let currentCalls = [];
let analysisTaskId = null;
let progressInterval = null;

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initEventListeners();
});

function initEventListeners() {
    document.getElementById('btn-load-calls').addEventListener('click', loadCalls);
    document.getElementById('btn-start-analysis').addEventListener('click', startAnalysis);
}

/**
 * Загрузка звонков за выбранную дату
 */
async function loadCalls() {
    const date = document.getElementById('filter-date').value;
    const limit = document.getElementById('filter-limit').value;

    if (!date) {
        alert('Выберите дату');
        return;
    }

    updateStatus('Загрузка звонков...', 'warning');

    try {
        const response = await fetch(`api/playground_calls_test.php?date=${date}&limit=${limit}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Ошибка загрузки');
        }

        currentCalls = data.calls;
        displayCalls(data.calls);

        // Активируем кнопку анализа
        document.getElementById('btn-start-analysis').disabled = false;
        updateStatus(`Загружено ${data.count} звонков за ${date}`, 'success');

    } catch (error) {
        console.error('Error loading calls:', error);
        updateStatus('Ошибка: ' + error.message, 'danger');
    }
}

/**
 * Отображение звонков в таблице
 */
function displayCalls(calls) {
    const tbody = document.getElementById('results-tbody');
    tbody.innerHTML = '';

    document.getElementById('results-table').style.display = 'table';
    document.getElementById('results-empty').style.display = 'none';

    calls.forEach((call, index) => {
        const row = document.createElement('tr');
        row.id = `call-row-${call.callid}`;

        const startTime = new Date(call.started_at_utc + 'Z').toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        const duration = Math.floor(call.duration_sec / 60) + 'м ' + (call.duration_sec % 60) + 'с';

        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${startTime}</td>
            <td>${call.client_phone}</td>
            <td>${duration}</td>

            <!-- Production -->
            <td class="production-summary">${truncate(call.production.summary || '-', 50)}</td>
            <td class="production-result">${call.production.result || '-'}</td>

            <!-- GigaChat -->
            <td id="gigachat-summary-${call.callid}" class="model-summary">-</td>
            <td id="gigachat-result-${call.callid}" class="model-result">-</td>
            <td id="gigachat-success-${call.callid}" class="model-success">-</td>
            <td id="gigachat-script-${call.callid}" class="model-script">-</td>

            <!-- OpenAI -->
            <td id="openai-summary-${call.callid}" class="model-summary">-</td>
            <td id="openai-result-${call.callid}" class="model-result">-</td>
            <td id="openai-success-${call.callid}" class="model-success">-</td>
            <td id="openai-script-${call.callid}" class="model-script">-</td>
        `;

        tbody.appendChild(row);
    });
}

/**
 * Запуск анализа звонков
 */
async function startAnalysis() {
    const date = document.getElementById('filter-date').value;
    const limit = document.getElementById('filter-limit').value;

    const models = [];
    if (document.getElementById('model-gigachat').checked) models.push('gigachat');
    if (document.getElementById('model-openai').checked) models.push('openai');

    if (models.length === 0) {
        showError('Выберите хотя бы одну модель для анализа');
        return;
    }

    updateStatus('Запуск анализа...', 'primary');
    document.getElementById('btn-start-analysis').disabled = true;

    try {
        const response = await fetch('api/playground_analyze_test.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date, models, limit: parseInt(limit) })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Ошибка сервера`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Ошибка запуска анализа');
        }

        analysisTaskId = data.task_id;
        updateStatus('Анализ запущен...', 'primary');

        // Показываем прогресс бар
        document.getElementById('progress-container').style.display = 'block';

        // Запускаем отслеживание прогресса
        startProgressTracking(date, parseInt(limit));

    } catch (error) {
        console.error('Error starting analysis:', error);
        showError('Ошибка запуска анализа: ' + error.message);
        document.getElementById('btn-start-analysis').disabled = false;
        document.getElementById('progress-container').style.display = 'none';
    }
}

/**
 * Отслеживание прогресса анализа
 */
function startProgressTracking(date, total) {
    let checkCount = 0;
    const maxChecks = 600; // 10 минут максимум

    progressInterval = setInterval(async () => {
        checkCount++;

        if (checkCount > maxChecks) {
            stopProgressTracking();
            updateStatus('Timeout: анализ занял слишком много времени', 'danger');
            return;
        }

        try {
            const response = await fetch(`api/playground_progress_test.php?task_id=${analysisTaskId}&date=${date}&total=${total}`);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                stopProgressTracking();
                showError('Ошибка проверки прогресса: ' + (data.error || 'Неизвестная ошибка'));
                document.getElementById('btn-start-analysis').disabled = false;
                return;
            }

            // Обновляем прогресс бар
            document.getElementById('progress-bar').style.width = data.progress + '%';
            document.getElementById('progress-percent').textContent = data.progress.toFixed(1) + '%';
            document.getElementById('progress-text').textContent = `Проанализировано ${data.analyzed_count} из ${data.total}`;

            // Если завершено
            if (data.is_complete) {
                stopProgressTracking();

                // Проверяем, есть ли результаты
                if (data.analyzed_count === 0) {
                    showError('Анализ не дал результатов. Возможно, нет связи с LLM или закончились средства на балансе.');
                    document.getElementById('btn-start-analysis').disabled = false;
                } else {
                    loadResults();
                }
            }

        } catch (error) {
            console.error('Progress check error:', error);
            // Не останавливаем прогресс сразу, чтобы дать возможность восстановиться
        }
    }, 3000); // Проверка каждые 3 секунды
}

/**
 * Остановка отслеживания прогресса
 */
function stopProgressTracking() {
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
}

/**
 * Загрузка результатов анализа
 */
async function loadResults() {
    updateStatus('Загрузка результатов...', 'primary');

    const callIds = currentCalls.map(c => c.callid).join(',');
    const models = [];
    if (document.getElementById('model-gigachat').checked) models.push('gigachat');
    if (document.getElementById('model-openai').checked) models.push('openai');

    try {
        const response = await fetch(`api/playground_results_test.php?call_ids=${callIds}&models=${models.join(',')}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error);
        }

        displayResults(data.results);
        displayAggregations(data.aggregations);

        updateStatus('✅ Анализ завершен!', 'success');
        document.getElementById('progress-container').style.display = 'none';
        document.getElementById('btn-start-analysis').disabled = false;

    } catch (error) {
        console.error('Error loading results:', error);
        showError('Ошибка загрузки результатов: ' + error.message);
        document.getElementById('btn-start-analysis').disabled = false;
    }
}

/**
 * Показать ошибку в интерфейсе
 */
function showError(message) {
    updateStatus('❌ Ошибка', 'danger');

    // Создаем или обновляем блок с ошибкой
    let errorBlock = document.getElementById('error-message');
    if (!errorBlock) {
        errorBlock = document.createElement('div');
        errorBlock.id = 'error-message';
        errorBlock.style.cssText = `
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.5;
        `;

        const container = document.querySelector('.filters-container');
        container.parentNode.insertBefore(errorBlock, container.nextSibling);
    }

    errorBlock.innerHTML = `
        <strong>Ошибка:</strong> ${message}
        <button onclick="document.getElementById('error-message').remove()"
                style="float: right; background: none; border: none; color: #721c24; cursor: pointer; font-size: 18px; line-height: 1;">
            ×
        </button>
    `;

    errorBlock.style.display = 'block';
}

/**
 * Отображение результатов в таблице
 */
function displayResults(results) {
    for (const [callid, models] of Object.entries(results)) {
        // GigaChat
        if (models.gigachat) {
            updateCell(`gigachat-summary-${callid}`, truncate(models.gigachat.summary, 50));
            updateCell(`gigachat-result-${callid}`, models.gigachat.result);
            updateCell(`gigachat-success-${callid}`, models.gigachat.is_successful ? '✅' : '❌');
            updateCell(`gigachat-script-${callid}`, models.gigachat.script_score + '%');
        }

        // OpenAI
        if (models.openai) {
            updateCell(`openai-summary-${callid}`, truncate(models.openai.summary, 50));
            updateCell(`openai-result-${callid}`, models.openai.result);
            updateCell(`openai-success-${callid}`, models.openai.is_successful ? '✅' : '❌');
            updateCell(`openai-script-${callid}`, models.openai.script_score + '%');

            // Подсветка различий
            highlightDifferences(callid, models);
        }
    }
}

/**
 * Подсветка различий между моделями
 */
function highlightDifferences(callid, models) {
    if (!models.gigachat || !models.openai) return;

    const gc = models.gigachat;
    const ai = models.openai;

    // Если разный результат - подсвечиваем
    if (gc.result !== ai.result) {
        document.getElementById(`gigachat-result-${callid}`).classList.add('diff-highlight-yellow');
        document.getElementById(`openai-result-${callid}`).classList.add('diff-highlight-yellow');
    }

    // Если разная успешность - красный
    if (gc.is_successful !== ai.is_successful) {
        document.getElementById(`gigachat-success-${callid}`).classList.add('diff-highlight-red');
        document.getElementById(`openai-success-${callid}`).classList.add('diff-highlight-red');
    }

    // Если скрипт отличается >20% - оранжевый
    if (Math.abs(gc.script_score - ai.script_score) > 20) {
        document.getElementById(`gigachat-script-${callid}`).classList.add('diff-highlight-orange');
        document.getElementById(`openai-script-${callid}`).classList.add('diff-highlight-orange');
    }
}

/**
 * Отображение агрегаций
 */
function displayAggregations(aggregations) {
    if (Object.keys(aggregations).length === 0) return;

    const container = document.getElementById('aggregations-container');
    const list = document.getElementById('aggregations-list');
    list.innerHTML = '';

    for (const [phone, models] of Object.entries(aggregations)) {
        const card = document.createElement('div');
        card.className = 'aggregation-card';
        card.style.cssText = 'border: 1px solid #dee2e6; padding: 15px; margin-bottom: 15px; border-radius: 8px;';

        let html = `<h5>📞 ${phone}</h5>`;

        if (models.gigachat) {
            html += `
                <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                    <strong>GigaChat:</strong>
                    <div style="margin-top: 5px;">${models.gigachat.summary}</div>
                    <div style="margin-top: 5px;"><strong>Статус:</strong> ${models.gigachat.status}</div>
                    <div style="font-size: 12px; color: #6c757d;">Звонков: ${models.gigachat.calls_count}</div>
                </div>
            `;
        }

        if (models.openai) {
            html += `
                <div style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-radius: 4px;">
                    <strong>OpenAI:</strong>
                    <div style="margin-top: 5px;">${models.openai.summary}</div>
                    <div style="margin-top: 5px;"><strong>Статус:</strong> ${models.openai.status}</div>
                    <div style="font-size: 12px; color: #6c757d;">Звонков: ${models.openai.calls_count}</div>
                </div>
            `;
        }

        card.innerHTML = html;
        list.appendChild(card);
    }

    container.style.display = 'block';
}

/**
 * Утилиты
 */
function updateCell(id, text) {
    const cell = document.getElementById(id);
    if (cell) cell.textContent = text;
}

function truncate(text, maxLength) {
    if (!text) return '-';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

function updateStatus(message, type = 'secondary') {
    const badge = document.getElementById('analysis-status');
    badge.textContent = message;
    badge.className = `badge badge-${type}`;
}
