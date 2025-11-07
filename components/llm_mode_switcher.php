<!-- LLM Mode Switcher Component -->
<style>
.llm-mode-switcher {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 16px;
    z-index: 1000;
    min-width: 320px;
}

.llm-mode-switcher.collapsed {
    min-width: auto;
    padding: 12px;
}

.llm-mode-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.llm-mode-header h6 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.llm-mode-toggle-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    color: #666;
    padding: 0;
}

.llm-mode-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.llm-mode-option {
    display: flex;
    align-items: center;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.llm-mode-option:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.llm-mode-option.active {
    border-color: #007bff;
    background: #e7f3ff;
}

.llm-mode-option input[type="radio"] {
    margin-right: 10px;
}

.llm-mode-label {
    flex: 1;
}

.llm-mode-label strong {
    display: block;
    font-size: 13px;
    color: #333;
    margin-bottom: 2px;
}

.llm-mode-label small {
    display: block;
    font-size: 11px;
    color: #666;
}

.llm-mode-status {
    margin-top: 12px;
    padding: 8px;
    border-radius: 6px;
    font-size: 12px;
    text-align: center;
}

.llm-mode-status.success {
    background: #d4edda;
    color: #155724;
}

.llm-mode-status.warning {
    background: #fff3cd;
    color: #856404;
}

.llm-mode-status.info {
    background: #d1ecf1;
    color: #0c5460;
}

.llm-worker-status {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    font-size: 11px;
    color: #666;
}

.worker-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #6c757d;
}

.worker-indicator.active {
    background: #28a745;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.collapsed .llm-mode-options,
.collapsed .llm-mode-status,
.collapsed .llm-worker-status {
    display: none;
}

.llm-mode-fab {
    display: none;
}

.collapsed .llm-mode-fab {
    display: block;
    font-size: 24px;
}
</style>

<div class="llm-mode-switcher" id="llmModeSwitcher">
    <div class="llm-mode-header">
        <h6>🤖 LLM Режим</h6>
        <button class="llm-mode-toggle-btn" onclick="toggleLLMSwitcher()">
            <span class="llm-mode-expand">−</span>
            <span class="llm-mode-fab" style="display:none;">⚙️</span>
        </button>
    </div>

    <div class="llm-mode-options">
        <label class="llm-mode-option" data-mode="cloud">
            <input type="radio" name="llm_mode" value="cloud" onchange="changeLLMMode('cloud')">
            <div class="llm-mode-label">
                <strong>🌐 Cloud-Only</strong>
                <small>Все на GigaChat/Yandex (~500₽/день)</small>
            </div>
        </label>

        <label class="llm-mode-option" data-mode="hybrid">
            <input type="radio" name="llm_mode" value="hybrid" onchange="changeLLMMode('hybrid')">
            <div class="llm-mode-label">
                <strong>⚡ Hybrid</strong>
                <small>Простые → local, сложные → cloud (~300₽/день)</small>
            </div>
        </label>

        <label class="llm-mode-option" data-mode="local">
            <input type="radio" name="llm_mode" value="local" onchange="changeLLMMode('local')">
            <div class="llm-mode-label">
                <strong>🏠 Local-Only</strong>
                <small>Все на локальной LLM (0₽ API)</small>
            </div>
        </label>
    </div>

    <div class="llm-mode-status" id="llmStatus" style="display:none;"></div>

    <div class="llm-worker-status">
        <span class="worker-indicator" id="workerIndicator"></span>
        <span id="workerStatus">Проверка worker...</span>
    </div>
</div>

<script>
// Загрузка текущей конфигурации
async function loadLLMConfig() {
    try {
        const response = await fetch('api/llm_config.php');
        const data = await response.json();

        if (data.success) {
            const currentMode = data.config.llm_mode || 'hybrid';

            // Установить текущий режим
            const radio = document.querySelector(`input[value="${currentMode}"]`);
            if (radio) {
                radio.checked = true;
                document.querySelector(`[data-mode="${currentMode}"]`).classList.add('active');
            }

            // Обновить статус worker
            updateWorkerStatus(data.worker);
        }
    } catch (error) {
        console.error('Failed to load LLM config:', error);
        showStatus('Ошибка загрузки конфигурации', 'warning');
    }
}

// Изменить LLM режим
async function changeLLMMode(newMode) {
    showStatus('Сохранение...', 'info');

    try {
        const response = await fetch('api/llm_config.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ mode: newMode })
        });

        const data = await response.json();

        if (data.success) {
            // Обновить активный режим
            document.querySelectorAll('.llm-mode-option').forEach(opt => {
                opt.classList.remove('active');
            });
            document.querySelector(`[data-mode="${newMode}"]`).classList.add('active');

            showStatus(`✅ Режим изменен: ${newMode}. Перезапустите worker!`, 'warning');
        } else {
            showStatus('❌ ' + (data.error || 'Ошибка сохранения'), 'warning');
        }
    } catch (error) {
        console.error('Failed to change LLM mode:', error);
        showStatus('❌ Ошибка соединения', 'warning');
    }
}

// Показать статус
function showStatus(message, type) {
    const statusEl = document.getElementById('llmStatus');
    statusEl.textContent = message;
    statusEl.className = `llm-mode-status ${type}`;
    statusEl.style.display = 'block';

    setTimeout(() => {
        statusEl.style.display = 'none';
    }, 5000);
}

// Обновить статус worker
function updateWorkerStatus(worker) {
    const indicator = document.getElementById('workerIndicator');
    const status = document.getElementById('workerStatus');

    if (worker && worker.is_running) {
        indicator.classList.add('active');
        status.textContent = 'Worker активен';
    } else {
        indicator.classList.remove('active');
        status.textContent = 'Worker неактивен';
    }
}

// Свернуть/развернуть виджет
function toggleLLMSwitcher() {
    const switcher = document.getElementById('llmModeSwitcher');
    switcher.classList.toggle('collapsed');
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    loadLLMConfig();

    // Обновление статуса каждые 30 секунд
    setInterval(loadLLMConfig, 30000);
});
</script>
