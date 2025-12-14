/**
 * Moodle Students Manager - JavaScript класс для управления студентами Moodle
 *
 * @author Claude Code
 * @date 2025-12-11
 */

class MoodleStudentsManager {
    constructor() {
        this.courseid = 13; // База знаний AILOCA
        this.currentTab = 'students';
        this.students = [];
        this.webhookHistory = [];
        this.webhookStats = null;
        this.contentBlocks = [];
        this.selectedStudentId = null;
        this.selectedAnchorId = null;
        // HR Bot data
        this.hrbotCandidates = [];
        this.hrbotStats = null;
    }

    /**
     * Инициализация
     */
    async init() {
        console.log('Initializing Moodle Students Manager...');

        this.setupTabs();
        this.setupModals();
        this.setupEventListeners();

        // Загружаем данные для активного таба
        await this.loadStudents();
        await this.loadContentBlocks();
    }

    /**
     * Настройка переключения табов
     */
    setupTabs() {
        const tabButtons = document.querySelectorAll('.tab-btn');

        tabButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                // Убираем active со всех кнопок и контента
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Активируем текущую кнопку и контент
                btn.classList.add('active');
                const tabName = btn.getAttribute('data-tab');
                document.getElementById(`tab-${tabName}`).classList.add('active');

                this.currentTab = tabName;

                // Загружаем данные для таба
                await this.loadTabData(tabName);
            });
        });
    }

    /**
     * Загрузка данных для таба
     */
    async loadTabData(tabName) {
        switch (tabName) {
            case 'students':
                if (this.students.length === 0) {
                    await this.loadStudents();
                }
                break;
            case 'hrbot':
                await this.loadHRBotCandidates();
                await this.loadHRBotStats();
                break;
            case 'webhooks':
                await this.loadWebhookHistory();
                await this.loadWebhookStats();
                break;
            case 'blocks':
                if (this.contentBlocks.length === 0) {
                    await this.loadContentBlocks();
                }
                break;
        }
    }

    /**
     * Настройка модальных окон
     */
    setupModals() {
        // Закрытие модальных окон по клику на close или overlay
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                this.closeAllModals();
            });
        });

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeAllModals();
                }
            });
        });
    }

    /**
     * Настройка event listeners
     */
    setupEventListeners() {
        // Кнопка "Отправить рекомендацию"
        document.getElementById('btn-send-webhook').addEventListener('click', () => {
            this.openSendWebhookModal();
        });

        // Кнопка "Обновить данные"
        document.getElementById('btn-refresh').addEventListener('click', async () => {
            await this.refreshCurrentTab();
        });

        // Форма отправки webhook
        document.getElementById('form-send-webhook').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.sendWebhook();
        });

        // Поиск content blocks
        document.getElementById('block-search').addEventListener('input', (e) => {
            this.filterContentBlocks(e.target.value);
        });

        // Autocomplete для поиска anchor_id
        const anchorSearchInput = document.getElementById('webhook-anchor-search');
        if (anchorSearchInput) {
            let searchTimeout;
            anchorSearchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchContentBlocks(e.target.value);
                }, 300);
            });
        }
    }

    // ===== STUDENTS TAB =====

    /**
     * Загрузка списка студентов
     */
    async loadStudents() {
        try {
            const response = await fetch(`/api/moodle/students.php?action=list&courseid=${this.courseid}`);
            const result = await response.json();

            if (result.success) {
                this.students = result.data;
                this.renderStudentsTable();
            } else {
                this.showError('Ошибка загрузки студентов: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading students:', error);
            this.showError('Ошибка загрузки студентов');
        }
    }

    /**
     * Рендеринг таблицы студентов
     */
    renderStudentsTable() {
        const container = document.getElementById('students-table-container');

        if (this.students.length === 0) {
            container.innerHTML = '<div class="loading"><p>Нет студентов на курсе</p></div>';
            return;
        }

        let html = '<div class="table-container"><table>';
        html += '<thead><tr>';
        html += '<th>Имя студента</th>';
        html += '<th>Email</th>';
        html += '<th>Резюме</th>';
        html += '<th>Дата регистрации</th>';
        html += '<th>Прогресс</th>';
        html += '<th>Модулей завершено</th>';
        html += '<th>Действия</th>';
        html += '</tr></thead><tbody>';

        this.students.forEach(student => {
            html += '<tr>';
            html += `<td>${student.firstname} ${student.lastname}</td>`;
            html += `<td>${student.email}</td>`;

            // Колонка резюме
            if (student.resume && student.resume.filename) {
                html += `<td>
                    <a href="${student.resume.download_url}" target="_blank" class="btn btn-sm btn-success" title="Скачать ${student.resume.filename}">
                        📄 Скачать
                    </a>
                </td>`;
            } else {
                html += `<td><span style="color: #999; font-size: 12px;">Не загружено</span></td>`;
            }

            html += `<td>${student.enrolled_at}</td>`;
            html += `<td>
                <div>${student.overall_progress}%</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${student.overall_progress}%"></div>
                </div>
            </td>`;
            html += `<td>${student.modules_completed} / ${student.modules_total}</td>`;
            html += `<td>
                <button class="btn btn-sm btn-info" onclick="moodleManager.showStudentDetails(${student.user_id})">Детали</button>
                <button class="btn btn-sm btn-primary" onclick="moodleManager.openSendWebhookModal(${student.user_id})">Рекомендация</button>
            </td>`;
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    /**
     * Показать детали студента
     */
    async showStudentDetails(userId) {
        try {
            const response = await fetch(`/api/moodle/student_details.php?action=get&userid=${userId}&courseid=${this.courseid}`);
            const result = await response.json();

            if (result.success) {
                this.renderStudentDetailsModal(result.data);
            } else {
                this.showError('Ошибка загрузки деталей: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading student details:', error);
            this.showError('Ошибка загрузки деталей студента');
        }
    }

    /**
     * Рендеринг модального окна с деталями студента
     */
    renderStudentDetailsModal(student) {
        let html = '<div style="margin-bottom: 20px;">';
        html += `<p><strong>Студент:</strong> ${student.firstname} ${student.lastname}</p>`;
        html += `<p><strong>Email:</strong> ${student.email}</p>`;
        html += `<p><strong>Дата регистрации:</strong> ${student.enrolled_at}</p>`;
        html += `<p><strong>Общий прогресс:</strong> ${student.overall_progress}%</p>`;
        html += '</div>';

        html += '<h4 style="margin-bottom: 10px;">Модули курса:</h4>';
        html += '<div class="table-container"><table>';
        html += '<thead><tr>';
        html += '<th>Модуль</th>';
        html += '<th>Тип</th>';
        html += '<th>Статус</th>';
        html += '<th>Детали</th>';
        html += '</tr></thead><tbody>';

        student.modules.forEach(module => {
            const completionBadge = module.completion_state > 0
                ? '<span class="badge badge-viewed">Завершено</span>'
                : '<span class="badge badge-sent">Не завершено</span>';

            let details = '';
            if (module.module_type === 'page' && module.scroll_percentage) {
                details = `Прокрутка: ${module.scroll_percentage}%`;
            } else if (module.module_type === 'quiz' && module.quiz_grade !== null) {
                details = `Балл: ${module.quiz_grade}, Попыток: ${module.quiz_attempts}`;
            }

            html += '<tr>';
            html += `<td>${module.module_name}</td>`;
            html += `<td>${module.module_type}</td>`;
            html += `<td>${completionBadge}</td>`;
            html += `<td>${details}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table></div>';

        document.getElementById('student-details-body').innerHTML = html;
        document.getElementById('modal-student-details').classList.add('active');
    }

    // ===== WEBHOOKS TAB =====

    /**
     * Загрузка истории webhook
     */
    async loadWebhookHistory() {
        try {
            const response = await fetch(`/api/moodle/webhook_history.php?action=list&courseid=${this.courseid}&limit=50&offset=0`);
            const result = await response.json();

            if (result.success) {
                this.webhookHistory = result.data;
                this.renderWebhookHistoryTable();
            } else {
                this.showError('Ошибка загрузки истории: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading webhook history:', error);
            this.showError('Ошибка загрузки истории webhook');
        }
    }

    /**
     * Загрузка статистики webhook
     */
    async loadWebhookStats() {
        try {
            const response = await fetch(`/api/moodle/webhook_stats.php?action=summary&courseid=${this.courseid}&days=30`);
            const result = await response.json();

            if (result.success) {
                this.webhookStats = result.data;
                this.renderWebhookStats();
            } else {
                this.showError('Ошибка загрузки статистики: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading webhook stats:', error);
            this.showError('Ошибка загрузки статистики webhook');
        }
    }

    /**
     * Рендеринг статистики webhook
     */
    renderWebhookStats() {
        if (!this.webhookStats) return;

        const stats = this.webhookStats;
        let html = '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">';

        html += `<div class="table-container" style="padding: 15px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #2196F3;">${stats.total_sent}</div>
            <div style="font-size: 13px; color: #666;">Всего отправлено</div>
        </div>`;

        html += `<div class="table-container" style="padding: 15px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #4CAF50;">${stats.total_viewed}</div>
            <div style="font-size: 13px; color: #666;">Просмотрено</div>
        </div>`;

        html += `<div class="table-container" style="padding: 15px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #FFC107;">${stats.view_rate}%</div>
            <div style="font-size: 13px; color: #666;">Процент просмотра</div>
        </div>`;

        html += `<div class="table-container" style="padding: 15px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #f44336;">${stats.total_failed}</div>
            <div style="font-size: 13px; color: #666;">Ошибок</div>
        </div>`;

        html += '</div>';

        document.getElementById('webhook-stats').innerHTML = html;
    }

    /**
     * Рендеринг таблицы истории webhook
     */
    renderWebhookHistoryTable() {
        const container = document.getElementById('webhook-history-table-container');

        if (this.webhookHistory.length === 0) {
            container.innerHTML = '<div class="loading"><p>Нет отправленных webhook</p></div>';
            return;
        }

        let html = '<div class="table-container"><table>';
        html += '<thead><tr>';
        html += '<th>Дата отправки</th>';
        html += '<th>Студент</th>';
        html += '<th>Content Block</th>';
        html += '<th>Приоритет</th>';
        html += '<th>Причина</th>';
        html += '<th>Статус</th>';
        html += '<th>Дата просмотра</th>';
        html += '</tr></thead><tbody>';

        this.webhookHistory.forEach(webhook => {
            const priorityBadge = `<span class="badge badge-${webhook.priority}">${this.getPriorityLabel(webhook.priority)}</span>`;
            const statusBadge = `<span class="badge badge-${webhook.status}">${this.getStatusLabel(webhook.status)}</span>`;

            html += '<tr>';
            html += `<td>${webhook.sent_at}</td>`;
            html += `<td>${webhook.user_name}</td>`;
            html += `<td>${webhook.block_title}</td>`;
            html += `<td>${priorityBadge}</td>`;
            html += `<td>${webhook.reason || '-'}</td>`;
            html += `<td>${statusBadge}</td>`;
            html += `<td>${webhook.viewed_at || '-'}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    /**
     * Открыть модальное окно отправки webhook
     */
    async openSendWebhookModal(userId = null) {
        this.selectedStudentId = userId;

        // Загружаем список студентов для select
        const select = document.getElementById('webhook-user-id');
        select.innerHTML = '<option value="">Выберите студента...</option>';

        this.students.forEach(student => {
            const option = document.createElement('option');
            option.value = student.user_id;
            option.textContent = `${student.firstname} ${student.lastname} (${student.email})`;
            if (userId && student.user_id === userId) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        document.getElementById('modal-send-webhook').classList.add('active');
    }

    /**
     * Поиск content blocks для autocomplete
     */
    async searchContentBlocks(query) {
        if (query.length < 2) {
            document.getElementById('anchor-suggestions').style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`/api/moodle/content_block_search.php?action=search&query=${encodeURIComponent(query)}&courseid=${this.courseid}`);
            const result = await response.json();

            if (result.success) {
                this.renderAnchorSuggestions(result.data);
            }
        } catch (error) {
            console.error('Error searching blocks:', error);
        }
    }

    /**
     * Рендеринг подсказок anchor_id
     */
    renderAnchorSuggestions(blocks) {
        const container = document.getElementById('anchor-suggestions');

        if (blocks.length === 0) {
            container.style.display = 'none';
            return;
        }

        let html = '';
        blocks.forEach(block => {
            html += `<div style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0;"
                     onclick="moodleManager.selectAnchor('${block.anchor_id}', '${block.title.replace(/'/g, "\\'")}')">
                <div style="font-size: 13px; font-weight: 600;">${block.title}</div>
                <div style="font-size: 11px; color: #999;">${block.page_name} • ${block.anchor_id}</div>
            </div>`;
        });

        container.innerHTML = html;
        container.style.display = 'block';
    }

    /**
     * Выбор anchor_id из подсказок
     */
    selectAnchor(anchorId, title) {
        this.selectedAnchorId = anchorId;
        document.getElementById('webhook-anchor-search').value = title;
        document.getElementById('webhook-anchor-id').value = anchorId;
        document.getElementById('anchor-suggestions').style.display = 'none';
    }

    /**
     * Отправка webhook
     */
    async sendWebhook() {
        const userId = parseInt(document.getElementById('webhook-user-id').value);
        const anchorId = document.getElementById('webhook-anchor-id').value;
        const priority = document.getElementById('webhook-priority').value;
        const reason = document.getElementById('webhook-reason').value;

        if (!userId || !anchorId) {
            this.showError('Заполните все обязательные поля');
            return;
        }

        try {
            const response = await fetch('/api/moodle/webhook_send.php?action=send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    anchor_id: anchorId,
                    priority: priority,
                    reason: reason,
                    courseid: this.courseid
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Рекомендация отправлена успешно!');
                this.closeAllModals();

                // Обновляем историю webhook
                if (this.currentTab === 'webhooks') {
                    await this.loadWebhookHistory();
                    await this.loadWebhookStats();
                }
            } else {
                this.showError('Ошибка отправки: ' + result.error);
            }
        } catch (error) {
            console.error('Error sending webhook:', error);
            this.showError('Ошибка отправки webhook');
        }
    }

    // ===== CONTENT BLOCKS TAB =====

    /**
     * Загрузка content blocks
     */
    async loadContentBlocks() {
        try {
            const response = await fetch(`/api/moodle/content_blocks.php?action=list&courseid=${this.courseid}`);
            const result = await response.json();

            if (result.success) {
                this.contentBlocks = result.data;
                this.renderContentBlocksTree();
            } else {
                this.showError('Ошибка загрузки блоков: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading content blocks:', error);
            this.showError('Ошибка загрузки content blocks');
        }
    }

    /**
     * Рендеринг дерева content blocks
     */
    renderContentBlocksTree() {
        const container = document.getElementById('content-blocks-tree');

        if (this.contentBlocks.length === 0) {
            container.innerHTML = '<div class="loading"><p>Нет content blocks</p></div>';
            return;
        }

        // Группируем блоки по page_cm_id
        const groupedBlocks = {};
        this.contentBlocks.forEach(block => {
            if (!groupedBlocks[block.page_cm_id]) {
                groupedBlocks[block.page_cm_id] = {
                    page_name: block.page_name,
                    blocks: []
                };
            }
            groupedBlocks[block.page_cm_id].blocks.push(block);
        });

        let html = '<div class="blocks-tree">';

        Object.keys(groupedBlocks).forEach(pageCmId => {
            const page = groupedBlocks[pageCmId];

            html += `<div class="block-page">`;
            html += `<div class="block-page-title" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'">
                <span>📄</span>
                <span>${page.page_name} (${page.blocks.length} блоков)</span>
            </div>`;
            html += `<div class="block-list" style="display: block;">`;

            page.blocks.forEach(block => {
                const levelIndent = block.level * 10;
                html += `<div class="block-item" style="padding-left: ${levelIndent}px;">
                    <div>
                        <div class="block-title">H${block.level}: ${block.title}</div>
                        <div class="block-anchor">${block.anchor_id}</div>
                    </div>
                    <div class="block-actions">
                        <button class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('${block.anchor_id}'); moodleManager.showSuccess('Скопировано!')">
                            📋 Копировать ID
                        </button>
                    </div>
                </div>`;
            });

            html += `</div></div>`;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    /**
     * Фильтрация content blocks
     */
    filterContentBlocks(query) {
        if (query.length < 2) {
            this.renderContentBlocksTree();
            return;
        }

        const filtered = this.contentBlocks.filter(block =>
            block.title.toLowerCase().includes(query.toLowerCase()) ||
            block.anchor_id.toLowerCase().includes(query.toLowerCase())
        );

        // Рендерим фильтрованные блоки
        const container = document.getElementById('content-blocks-tree');
        let html = '<div class="blocks-tree">';

        filtered.forEach(block => {
            html += `<div class="block-item">
                <div>
                    <div class="block-title">H${block.level}: ${block.title}</div>
                    <div class="block-anchor">${block.anchor_id} • ${block.page_name}</div>
                </div>
                <div class="block-actions">
                    <button class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('${block.anchor_id}'); moodleManager.showSuccess('Скопировано!')">
                        📋 Копировать ID
                    </button>
                </div>
            </div>`;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    // ===== HR BOT TAB =====

    /**
     * Загрузка списка кандидатов HR Bot
     */
    async loadHRBotCandidates() {
        try {
            const response = await fetch('/api/hr_bot/candidates.php?action=list');
            const result = await response.json();

            if (result.success) {
                this.hrbotCandidates = result.data;
                this.renderHRBotTable();
            } else {
                this.showError('Ошибка загрузки кандидатов: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading HR Bot candidates:', error);
            this.showError('Ошибка загрузки кандидатов HR Bot');
        }
    }

    /**
     * Загрузка статистики HR Bot
     */
    async loadHRBotStats() {
        try {
            const response = await fetch('/api/hr_bot/candidates.php?action=stats');
            const result = await response.json();

            if (result.success) {
                this.hrbotStats = result.data;
                this.renderHRBotStats();
            } else {
                this.showError('Ошибка загрузки статистики: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading HR Bot stats:', error);
            this.showError('Ошибка загрузки статистики HR Bot');
        }
    }

    /**
     * Рендеринг статистики HR Bot
     */
    renderHRBotStats() {
        if (!this.hrbotStats || !this.hrbotStats.total) return;

        const stats = this.hrbotStats.total;
        let html = '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">';

        html += `<div class="table-container" style="padding: 15px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #2196F3;">${stats.total || 0}</div>
            <div style="font-size: 13px; color: #666;">Всего кандидатов</div>
        </div>`;

        html += `<div class="table-container" style="padding: 15px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #4CAF50;">${stats.hired || 0}</div>
            <div style="font-size: 13px; color: #666;">Нанято</div>
        </div>`;

        html += `<div class="table-container" style="padding: 15px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #FFC107;">${stats.interviews || 0}</div>
            <div style="font-size: 13px; color: #666;">На собеседовании</div>
        </div>`;

        html += `<div class="table-container" style="padding: 15px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #f44336;">${stats.rejected || 0}</div>
            <div style="font-size: 13px; color: #666;">Отклонено</div>
        </div>`;

        html += '</div>';

        document.getElementById('hrbot-stats').innerHTML = html;
    }

    /**
     * Рендеринг таблицы кандидатов HR Bot
     */
    renderHRBotTable() {
        const container = document.getElementById('hrbot-table-container');

        if (this.hrbotCandidates.length === 0) {
            container.innerHTML = '<div class="loading"><p>Нет кандидатов в HR Bot</p></div>';
            return;
        }

        let html = '<div class="table-container"><table>';
        html += '<thead><tr>';
        html += '<th>ID</th>';
        html += '<th>Имя</th>';
        html += '<th>Телефон</th>';
        html += '<th>Email</th>';
        html += '<th>Статус</th>';
        html += '<th>Тест 1</th>';
        html += '<th>Тест 2</th>';
        html += '<th>Голос</th>';
        html += '<th>Прогресс</th>';
        html += '<th>Резюме</th>';
        html += '<th>Дата</th>';
        html += '<th>Действия</th>';
        html += '</tr></thead><tbody>';

        this.hrbotCandidates.forEach(candidate => {
            const statusClass = this.getStatusClass(candidate.status);

            html += '<tr>';
            html += `<td>#${candidate.id}</td>`;
            html += `<td>${candidate.full_name || '—'}</td>`;
            html += `<td>${candidate.phone || '—'}</td>`;
            html += `<td>${candidate.email || '—'}</td>`;
            html += `<td><span class="badge ${statusClass}">${candidate.status_label}</span></td>`;
            html += `<td>${candidate.module1_score ? candidate.module1_score + '%' : '—'}</td>`;
            html += `<td>${candidate.module2_score ? candidate.module2_score + '%' : '—'}</td>`;
            html += `<td>${candidate.voice_score ? candidate.voice_score + '/10' : '—'}</td>`;
            html += `<td>
                <div>${candidate.overall_progress}%</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${candidate.overall_progress}%"></div>
                </div>
            </td>`;
            html += `<td>${candidate.has_resume ? '✅' : '—'}</td>`;
            html += `<td>${candidate.created_at ? candidate.created_at.split(' ')[0] : '—'}</td>`;
            html += `<td>
                <button class="btn btn-sm btn-info" onclick="moodleManager.showHRBotCandidateDetails(${candidate.id})">Детали</button>
            </td>`;
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    /**
     * Получить CSS класс для статуса
     */
    getStatusClass(status) {
        if (status.includes('completed') || status === 'hired') return 'badge-viewed';
        if (status.includes('failed') || status.includes('rejected')) return 'badge-failed';
        if (status.includes('in_progress') || status === 'registered') return 'badge-sent';
        if (status.includes('interview')) return 'badge-medium';
        return 'badge-sent';
    }

    /**
     * Показать детали кандидата HR Bot
     */
    async showHRBotCandidateDetails(candidateId) {
        try {
            const response = await fetch(`/api/hr_bot/candidates.php?action=get&id=${candidateId}`);
            const result = await response.json();

            if (result.success) {
                this.renderHRBotDetailsModal(result.data);
            } else {
                this.showError('Ошибка загрузки деталей: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading candidate details:', error);
            this.showError('Ошибка загрузки деталей кандидата');
        }
    }

    /**
     * Рендеринг модального окна с деталями кандидата HR Bot
     */
    renderHRBotDetailsModal(candidate) {
        let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';

        // Левая колонка - основная информация
        html += '<div>';
        html += '<h4 style="margin-bottom: 15px; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">📋 Основная информация</h4>';
        html += `<p><strong>ID:</strong> #${candidate.id}</p>`;
        html += `<p><strong>Имя:</strong> ${candidate.full_name || '—'}</p>`;
        html += `<p><strong>Телефон:</strong> ${candidate.phone || '—'}</p>`;
        html += `<p><strong>Email:</strong> ${candidate.email || '—'}</p>`;
        html += `<p><strong>Telegram:</strong> @${candidate.telegram_username || '—'} (ID: ${candidate.telegram_id})</p>`;
        html += `<p><strong>Статус:</strong> <span class="badge ${this.getStatusClass(candidate.status)}">${candidate.status_label}</span></p>`;
        html += `<p><strong>Резюме:</strong> ${candidate.resume_path ? '✅ Загружено' : '❌ Нет'}</p>`;
        if (candidate.interview_datetime) {
            html += `<p><strong>Собеседование:</strong> ${candidate.interview_datetime}</p>`;
        }
        html += `<p><strong>Дата регистрации:</strong> ${candidate.created_at}</p>`;
        html += '</div>';

        // Правая колонка - результаты
        html += '<div>';
        html += '<h4 style="margin-bottom: 15px; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">📊 Результаты</h4>';

        // Результаты тестов
        if (candidate.quiz_results && candidate.quiz_results.length > 0) {
            html += '<div style="margin-bottom: 15px;">';
            html += '<strong>Тесты:</strong>';
            html += '<table style="width: 100%; margin-top: 5px; font-size: 12px;">';
            html += '<tr><th>Модуль</th><th>Балл</th><th>Попытка</th><th>Дата</th></tr>';
            candidate.quiz_results.forEach(quiz => {
                const passedBadge = quiz.passed ? '<span style="color: green;">✅</span>' : '<span style="color: red;">❌</span>';
                html += `<tr>
                    <td>Модуль ${quiz.module_number}</td>
                    <td>${quiz.score_percent}% ${passedBadge}</td>
                    <td>#${quiz.attempt_number}</td>
                    <td>${quiz.completed_at ? quiz.completed_at.split(' ')[0] : '—'}</td>
                </tr>`;
            });
            html += '</table></div>';
        } else {
            html += '<p><strong>Тесты:</strong> нет результатов</p>';
        }

        // Голосовые задания
        if (candidate.voice_tasks && candidate.voice_tasks.length > 0) {
            html += '<div style="margin-bottom: 15px;">';
            html += '<strong>Голосовые задания:</strong>';
            html += '<table style="width: 100%; margin-top: 5px; font-size: 12px;">';
            html += '<tr><th>Попытка</th><th>Оценка</th><th>Рекомендация</th><th>Дата</th><th>Аудио</th></tr>';
            candidate.voice_tasks.forEach(voice => {
                const passedBadge = voice.passed ? '<span style="color: green;">✅</span>' : '<span style="color: red;">❌</span>';
                const audioPlayer = voice.voice_file_path
                    ? `<audio controls style="height: 30px; width: 150px;">
                         <source src="/api/hr_bot/voice_download.php?id=${voice.id}" type="audio/ogg">
                         Браузер не поддерживает аудио
                       </audio>`
                    : '—';
                html += `<tr>
                    <td>#${voice.attempt_number}</td>
                    <td>${voice.score}/10 ${passedBadge}</td>
                    <td>${voice.recommendation || '—'}</td>
                    <td>${voice.submitted_at ? voice.submitted_at.split(' ')[0] : '—'}</td>
                    <td>${audioPlayer}</td>
                </tr>`;
            });
            html += '</table></div>';
        } else {
            html += '<p><strong>Голосовые:</strong> нет результатов</p>';
        }

        html += '</div></div>';

        // События
        if (candidate.events && candidate.events.length > 0) {
            html += '<div style="margin-top: 20px;">';
            html += '<h4 style="margin-bottom: 10px; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">📅 История событий</h4>';
            html += '<div style="max-height: 200px; overflow-y: auto;">';
            candidate.events.forEach(event => {
                html += `<div style="padding: 8px; margin: 5px 0; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                    <strong>${event.event_type}</strong> - ${event.created_at}
                </div>`;
            });
            html += '</div></div>';
        }

        document.getElementById('hrbot-details-body').innerHTML = html;
        document.getElementById('modal-hrbot-details').classList.add('active');
    }

    // ===== UTILITY FUNCTIONS =====

    /**
     * Обновить данные текущего таба
     */
    async refreshCurrentTab() {
        switch (this.currentTab) {
            case 'students':
                await this.loadStudents();
                break;
            case 'hrbot':
                await this.loadHRBotCandidates();
                await this.loadHRBotStats();
                break;
            case 'webhooks':
                await this.loadWebhookHistory();
                await this.loadWebhookStats();
                break;
            case 'blocks':
                await this.loadContentBlocks();
                break;
        }
        this.showSuccess('Данные обновлены');
    }

    /**
     * Закрыть все модальные окна
     */
    closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('active');
        });
    }

    /**
     * Показать toast уведомление
     */
    showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');

        toastMessage.textContent = message;
        toast.className = `toast ${type} active`;

        setTimeout(() => {
            toast.classList.remove('active');
        }, 3000);
    }

    /**
     * Показать успешное уведомление
     */
    showSuccess(message) {
        this.showToast(message, 'success');
    }

    /**
     * Показать ошибку
     */
    showError(message) {
        this.showToast(message, 'error');
    }

    /**
     * Получить label для приоритета
     */
    getPriorityLabel(priority) {
        const labels = {
            'high': 'Высокий',
            'medium': 'Средний',
            'low': 'Низкий'
        };
        return labels[priority] || priority;
    }

    /**
     * Получить label для статуса
     */
    getStatusLabel(status) {
        const labels = {
            'sent': 'Отправлено',
            'viewed': 'Просмотрено',
            'failed': 'Ошибка'
        };
        return labels[status] || status;
    }
}

// Инициализация при загрузке страницы
let moodleManager;
document.addEventListener('DOMContentLoaded', async () => {
    moodleManager = new MoodleStudentsManager();
    await moodleManager.init();
});
