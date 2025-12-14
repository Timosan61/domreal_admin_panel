<?php
require_once 'auth/session.php';
checkAuth();

$user_full_name = $_SESSION['full_name'] ?? 'Пользователь';
$user_role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Маппинг навыков - AILOCA Admin</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="skill-mapping-page">
        <div class="skill-mapping-content">
            <!-- Header -->
            <div class="mapping-header">
                <h1>Маппинг навыков на контент Moodle</h1>
                <p>Связь вопросов из чек-листов с обучающими материалами для автоматических рекомендаций</p>
            </div>

            <!-- Body -->
            <div class="mapping-body">
                <!-- Stats -->
                <div class="stats-panel">
                    <div class="stat-card">
                        <h4>Всего навыков</h4>
                        <div class="value" id="stat-total">-</div>
                        <div class="subtext">вопросов в шаблонах</div>
                    </div>
                    <div class="stat-card">
                        <h4>Настроено</h4>
                        <div class="value" id="stat-mapped">-</div>
                        <div class="subtext">связано с контентом</div>
                    </div>
                    <div class="stat-card">
                        <h4>Не настроено</h4>
                        <div class="value" id="stat-unmapped">-</div>
                        <div class="subtext">требует настройки</div>
                    </div>
                    <div class="stat-card">
                        <h4>Активных шаблонов</h4>
                        <div class="value" id="stat-templates">-</div>
                        <div class="subtext">используются в анализе</div>
                    </div>
                </div>

                <!-- Templates List -->
                <div id="templates-container">
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Загрузка навыков...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Mapping -->
    <div id="modal-mapping" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Настройка навыка</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-mapping">
                    <input type="hidden" id="mapping-question-id">
                    <input type="hidden" id="mapping-id">

                    <div class="form-group">
                        <label>Вопрос из чек-листа</label>
                        <div id="mapping-question-text" class="skill-mapping-question-display"></div>
                    </div>

                    <div class="form-group">
                        <label>Название навыка</label>
                        <input type="text" id="mapping-skill-name" required placeholder="Например: Выявление потребностей">
                        <div class="form-hint">Краткое название для отображения в отчетах</div>
                    </div>

                    <div class="form-group">
                        <label>Категория навыка</label>
                        <select id="mapping-category">
                            <option value="">Без категории</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Описание</label>
                        <textarea id="mapping-description" placeholder="Подробное описание навыка и критерии оценки"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Порог предупреждения (%)</label>
                            <input type="number" id="mapping-threshold-warning" value="70" min="0" max="100">
                            <div class="form-hint">Ниже = желтая зона</div>
                        </div>
                        <div class="form-group">
                            <label>Критический порог (%)</label>
                            <input type="number" id="mapping-threshold-critical" value="50" min="0" max="100">
                            <div class="form-hint">Ниже = рекомендация</div>
                        </div>
                    </div>

                    <div class="threshold-visual">
                        <div class="zone critical" style="flex: 0.5;"></div>
                        <div class="zone warning" style="flex: 0.2;"></div>
                        <div class="zone ok" style="flex: 0.3;"></div>
                    </div>

                    <div class="form-group">
                        <label>Триггер: N провалов подряд</label>
                        <input type="number" id="mapping-consecutive-fails" value="3" min="1" max="10">
                        <div class="form-hint">Отправить рекомендацию после N неудачных ответов подряд</div>
                    </div>

                    <hr class="skill-mapping-separator">

                    <h4 class="skill-mapping-section-title">Связь с Moodle</h4>

                    <div class="form-group">
                        <label>Anchor ID контент-блока</label>
                        <input type="text" id="mapping-moodle-anchor" placeholder="Например: skill_discovery_needs">
                        <div class="form-hint">ID блока в Moodle для webhook рекомендации</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>ID страницы Moodle</label>
                            <input type="number" id="mapping-moodle-page">
                        </div>
                        <div class="form-group">
                            <label>ID курса Moodle</label>
                            <input type="number" id="mapping-moodle-course">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Время на обучение (мин)</label>
                        <input type="number" id="mapping-training-duration" value="15" min="1">
                    </div>

                    <div class="form-group">
                        <label>Приоритет</label>
                        <input type="number" id="mapping-priority" value="100" min="1" max="1000">
                        <div class="form-hint">Выше = важнее (влияет на порядок рекомендаций)</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline close-modal">Отмена</button>
                <button type="button" class="btn btn-danger d-none" id="btn-delete-mapping">Удалить</button>
                <button type="button" class="btn btn-primary" id="btn-save-mapping">Сохранить</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast">
        <span id="toast-message"></span>
    </div>

    <script>
        const API_BASE = 'api/skill_mapping.php';

        let skillsData = [];
        let categories = [];
        let moodleBlocks = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadCategories();
            loadSkills();
            setupEventListeners();
        });

        // Event Listeners
        function setupEventListeners() {
            // Close modal
            document.querySelectorAll('.close-modal').forEach(btn => {
                btn.addEventListener('click', closeModal);
            });

            // Save mapping
            document.getElementById('btn-save-mapping').addEventListener('click', saveMapping);

            // Delete mapping
            document.getElementById('btn-delete-mapping').addEventListener('click', deleteMapping);

            // Update threshold visual
            document.getElementById('mapping-threshold-warning').addEventListener('input', updateThresholdVisual);
            document.getElementById('mapping-threshold-critical').addEventListener('input', updateThresholdVisual);

            // Close modal on backdrop click
            document.getElementById('modal-mapping').addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    closeModal();
                }
            });
        }

        // Load Categories
        async function loadCategories() {
            try {
                const response = await fetch(`${API_BASE}?action=categories`);
                const result = await response.json();

                if (result.success) {
                    categories = result.data;
                    const select = document.getElementById('mapping-category');

                    categories.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.category_code;
                        option.textContent = cat.category_name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        }

        // Load Skills
        async function loadSkills() {
            try {
                const response = await fetch(`${API_BASE}?action=list`);
                const result = await response.json();

                if (result.success) {
                    skillsData = result.data;
                    renderTemplates();
                    updateStats();
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                document.getElementById('templates-container').innerHTML =
                    `<div class="loading text-danger">Ошибка загрузки: ${error.message}</div>`;
            }
        }

        // Render Templates
        function renderTemplates() {
            const container = document.getElementById('templates-container');

            if (skillsData.length === 0) {
                container.innerHTML = '<div class="loading">Нет шаблонов с вопросами</div>';
                return;
            }

            let html = '';
            skillsData.forEach(template => {
                const mappedCount = template.questions.filter(q => q.has_mapping).length;
                const totalCount = template.questions.length;

                html += `
                    <div class="template-card" data-template-id="${template.template_id}">
                        <div class="template-card-header" onclick="toggleTemplate('${template.template_id}')">
                            <h3>
                                ${template.template_name}
                                <span class="badge ${template.template_active ? 'badge-active' : 'badge-inactive'}">
                                    ${template.template_active ? 'Активен' : 'Неактивен'}
                                </span>
                                <span class="badge badge-mapped">
                                    ${mappedCount}/${totalCount} настроено
                                </span>
                            </h3>
                            <span class="expand-icon">▼</span>
                        </div>
                        <div class="template-card-body">
                            ${template.questions.map(q => renderQuestionRow(q)).join('')}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Render Question Row
        function renderQuestionRow(question) {
            const hasMapping = question.has_mapping;
            const mapping = question.mapping;

            return `
                <div class="question-row">
                    <div class="mapping-status">
                        <div class="mapping-indicator ${hasMapping ? 'mapped' : 'unmapped'}"></div>
                    </div>
                    <div class="question-info">
                        <div class="question-text">${question.question_text}</div>
                        <div class="question-code">code: ${question.question_code}</div>
                    </div>
                    ${hasMapping ? `
                        <div class="mapping-summary">
                            <strong>${mapping.skill_name}</strong><br>
                            Порог: ${mapping.threshold_critical}% / ${mapping.threshold_warning}%
                            ${mapping.moodle_anchor_id ? `<br>Moodle: ${mapping.moodle_anchor_id}` : ''}
                        </div>
                        <button class="btn btn-outline" onclick="openMapping('${question.question_id}', ${JSON.stringify(mapping).replace(/"/g, '&quot;')})">
                            Редактировать
                        </button>
                    ` : `
                        <button class="btn btn-primary" onclick="openMapping('${question.question_id}', null, '${question.question_text.replace(/'/g, "\\'")}')">
                            Настроить
                        </button>
                    `}
                </div>
            `;
        }

        // Toggle Template
        function toggleTemplate(templateId) {
            const card = document.querySelector(`.template-card[data-template-id="${templateId}"]`);
            card.classList.toggle('expanded');
        }

        // Open Mapping Modal
        function openMapping(questionId, existingMapping = null, questionText = '') {
            const modal = document.getElementById('modal-mapping');
            const title = document.getElementById('modal-title');
            const deleteBtn = document.getElementById('btn-delete-mapping');

            // Reset form
            document.getElementById('form-mapping').reset();

            document.getElementById('mapping-question-id').value = questionId;

            if (existingMapping) {
                // Edit mode
                title.textContent = 'Редактировать навык';
                deleteBtn.classList.remove('d-none');

                document.getElementById('mapping-id').value = existingMapping.mapping_id;
                document.getElementById('mapping-question-text').textContent = existingMapping.skill_name || 'Вопрос';
                document.getElementById('mapping-skill-name').value = existingMapping.skill_name || '';
                document.getElementById('mapping-category').value = existingMapping.skill_category || '';
                document.getElementById('mapping-description').value = existingMapping.skill_description || '';
                document.getElementById('mapping-threshold-warning').value = existingMapping.threshold_warning || 70;
                document.getElementById('mapping-threshold-critical').value = existingMapping.threshold_critical || 50;
                document.getElementById('mapping-consecutive-fails').value = existingMapping.consecutive_fails_trigger || 3;
                document.getElementById('mapping-moodle-anchor').value = existingMapping.moodle_anchor_id || '';
                document.getElementById('mapping-moodle-page').value = existingMapping.moodle_page_id || '';
                document.getElementById('mapping-moodle-course').value = existingMapping.moodle_course_id || '';
                document.getElementById('mapping-training-duration').value = existingMapping.training_duration_minutes || 15;
                document.getElementById('mapping-priority').value = existingMapping.priority || 100;
            } else {
                // Create mode
                title.textContent = 'Настроить навык';
                deleteBtn.classList.add('d-none');

                document.getElementById('mapping-id').value = '';
                document.getElementById('mapping-question-text').textContent = questionText;
                document.getElementById('mapping-skill-name').value = questionText;
            }

            updateThresholdVisual();
            modal.classList.add('active');
        }

        // Close Modal
        function closeModal() {
            document.getElementById('modal-mapping').classList.remove('active');
        }

        // Update Threshold Visual
        function updateThresholdVisual() {
            const warning = parseInt(document.getElementById('mapping-threshold-warning').value) || 70;
            const critical = parseInt(document.getElementById('mapping-threshold-critical').value) || 50;

            const visual = document.querySelector('.threshold-visual');
            visual.innerHTML = `
                <div class="zone critical" style="flex: ${critical / 100};"></div>
                <div class="zone warning" style="flex: ${(warning - critical) / 100};"></div>
                <div class="zone ok" style="flex: ${(100 - warning) / 100};"></div>
            `;
        }

        // Save Mapping
        async function saveMapping() {
            const questionId = document.getElementById('mapping-question-id').value;
            const mappingId = document.getElementById('mapping-id').value;

            const data = {
                question_id: questionId,
                skill_name: document.getElementById('mapping-skill-name').value,
                skill_description: document.getElementById('mapping-description').value,
                skill_category: document.getElementById('mapping-category').value || null,
                threshold_warning: parseFloat(document.getElementById('mapping-threshold-warning').value),
                threshold_critical: parseFloat(document.getElementById('mapping-threshold-critical').value),
                consecutive_fails_trigger: parseInt(document.getElementById('mapping-consecutive-fails').value),
                moodle_anchor_id: document.getElementById('mapping-moodle-anchor').value || null,
                moodle_page_id: document.getElementById('mapping-moodle-page').value ? parseInt(document.getElementById('mapping-moodle-page').value) : null,
                moodle_course_id: document.getElementById('mapping-moodle-course').value ? parseInt(document.getElementById('mapping-moodle-course').value) : null,
                training_duration_minutes: parseInt(document.getElementById('mapping-training-duration').value),
                priority: parseInt(document.getElementById('mapping-priority').value)
            };

            try {
                let url = API_BASE;
                let method = 'POST';

                if (mappingId) {
                    url += `?action=update&id=${mappingId}`;
                    method = 'PATCH';
                } else {
                    url += '?action=create';
                }

                const response = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Настройки сохранены', 'success');
                    closeModal();
                    loadSkills();
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                showToast('Ошибка: ' + error.message, 'error');
            }
        }

        // Delete Mapping
        async function deleteMapping() {
            const mappingId = document.getElementById('mapping-id').value;

            if (!mappingId) return;

            if (!confirm('Удалить настройку навыка?')) return;

            try {
                const response = await fetch(`${API_BASE}?action=delete&id=${mappingId}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Настройка удалена', 'success');
                    closeModal();
                    loadSkills();
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                showToast('Ошибка: ' + error.message, 'error');
            }
        }

        // Update Stats
        function updateStats() {
            let totalQuestions = 0;
            let mappedQuestions = 0;
            let activeTemplates = 0;

            skillsData.forEach(template => {
                if (template.template_active) activeTemplates++;
                template.questions.forEach(q => {
                    totalQuestions++;
                    if (q.has_mapping) mappedQuestions++;
                });
            });

            document.getElementById('stat-total').textContent = totalQuestions;
            document.getElementById('stat-mapped').textContent = mappedQuestions;
            document.getElementById('stat-unmapped').textContent = totalQuestions - mappedQuestions;
            document.getElementById('stat-templates').textContent = activeTemplates;
        }

        // Show Toast
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');

            toast.className = `toast active ${type}`;
            toastMessage.textContent = message;

            setTimeout(() => {
                toast.classList.remove('active');
            }, 3000);
        }
    </script>
</body>
</html>
