/**
 * Bulk Actions для системы тегов
 * Управление массовым выбором звонков и применением тегов
 */

class BulkActions {
    constructor() {
        this.selectedCallIds = new Set();
        this.currentTagType = null;

        this.bulkActionsBar = document.getElementById('bulk-actions-bar');
        this.selectedCountEl = document.getElementById('selected-count');
        this.selectAllCheckbox = document.getElementById('select-all-calls');

        this.tagModal = document.getElementById('tag-modal');
        this.tagModalTitle = document.getElementById('tag-modal-title');
        this.tagModalCount = document.getElementById('tag-modal-count');
        this.tagNoteTextarea = document.getElementById('tag-note');

        this.init();
    }

    init() {
        console.log('🎬 BulkActions инициализация...');
        console.log('Найдено элементов:', {
            bulkActionsBar: !!this.bulkActionsBar,
            selectAllCheckbox: !!this.selectAllCheckbox,
            tagModal: !!this.tagModal
        });

        // Обработчики чекбоксов
        this.initCheckboxHandlers();

        // Обработчики кнопок тегов
        this.initTagButtons();

        // Обработчики модального окна
        this.initModalHandlers();

        // Обработчик закрытия bulk actions
        document.getElementById('bulk-actions-close')?.addEventListener('click', () => {
            this.clearSelection();
        });

        console.log('✅ BulkActions успешно инициализирован');
    }

    /**
     * Инициализация обработчиков чекбоксов
     */
    initCheckboxHandlers() {
        // Select All checkbox
        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.addEventListener('change', (e) => {
                this.handleSelectAll(e.target.checked);
            });
        }

        // Делегирование событий для чекбоксов строк
        const tbody = document.getElementById('calls-tbody');
        if (tbody) {
            tbody.addEventListener('change', (e) => {
                if (e.target.type === 'checkbox' && e.target.classList.contains('call-checkbox')) {
                    this.handleRowCheckbox(e.target);
                }
            });
        }
    }

    /**
     * Обработчик Select All
     */
    handleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.call-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            const row = checkbox.closest('tr');
            const callId = checkbox.dataset.callid;

            if (checked) {
                this.selectedCallIds.add(callId);
                row?.classList.add('selected');
            } else {
                this.selectedCallIds.delete(callId);
                row?.classList.remove('selected');
            }
        });

        this.updateUI();
    }

    /**
     * Обработчик чекбокса строки
     */
    handleRowCheckbox(checkbox) {
        const callId = checkbox.dataset.callid;
        const row = checkbox.closest('tr');

        console.log('✅ Чекбокс клик:', callId, checkbox.checked);

        if (checkbox.checked) {
            this.selectedCallIds.add(callId);
            row?.classList.add('selected');
        } else {
            this.selectedCallIds.delete(callId);
            row?.classList.remove('selected');

            // Снять галочку с Select All
            if (this.selectAllCheckbox) {
                this.selectAllCheckbox.checked = false;
            }
        }

        console.log('📊 Выбрано звонков:', this.selectedCallIds.size);
        this.updateUI();
    }

    /**
     * Обновление UI (показ/скрытие панели, счетчик)
     */
    updateUI() {
        const count = this.selectedCallIds.size;
        console.log('🔄 updateUI вызван, выбрано:', count);

        if (count > 0) {
            console.log('👉 Показываем панель bulk actions');
            this.bulkActionsBar.style.display = 'block';
            this.selectedCountEl.textContent = count;
        } else {
            console.log('👉 Скрываем панель bulk actions');
            this.bulkActionsBar.style.display = 'none';
        }

        // Обновить состояние Select All
        if (this.selectAllCheckbox) {
            const totalCheckboxes = document.querySelectorAll('.call-checkbox').length;
            this.selectAllCheckbox.checked = count > 0 && count === totalCheckboxes;
        }
    }

    /**
     * Инициализация кнопок тегов
     */
    initTagButtons() {
        // Кнопка "Хорошо"
        document.getElementById('bulk-tag-good')?.addEventListener('click', () => {
            this.openTagModal('good', '✅ Хорошо');
        });

        // Кнопка "Плохо"
        document.getElementById('bulk-tag-bad')?.addEventListener('click', () => {
            this.openTagModal('bad', '❌ Плохо');
        });

        // Кнопка "Вопрос"
        document.getElementById('bulk-tag-question')?.addEventListener('click', () => {
            this.openTagModal('question', '❓ Вопрос');
        });

        // Кнопка "Проблемный"
        document.getElementById('bulk-tag-problem')?.addEventListener('click', () => {
            this.openTagModal('problem', '⚠️ Проблемный');
        });

        // Кнопка "Снять теги"
        document.getElementById('bulk-remove-tags')?.addEventListener('click', () => {
            this.removeTags();
        });
    }

    /**
     * Открыть модальное окно для ввода тега
     */
    openTagModal(tagType, title) {
        this.currentTagType = tagType;
        this.tagModalTitle.textContent = `Применить тег: ${title}`;
        this.tagModalCount.textContent = this.selectedCallIds.size;
        this.tagNoteTextarea.value = '';

        // Для тега 'problem' описание обязательно
        if (tagType === 'problem') {
            this.tagNoteTextarea.placeholder = 'Обязательно опишите проблему: что не так в этом звонке?';
            this.tagNoteTextarea.setAttribute('required', 'true');
        } else {
            this.tagNoteTextarea.placeholder = 'Введите дополнительную заметку к тегу...';
            this.tagNoteTextarea.removeAttribute('required');
        }

        this.tagModal.style.display = 'flex';
    }

    /**
     * Инициализация обработчиков модального окна
     */
    initModalHandlers() {
        // Закрытие модального окна
        const closeModal = () => {
            this.tagModal.style.display = 'none';
            this.currentTagType = null;
        };

        document.getElementById('tag-modal-close')?.addEventListener('click', closeModal);
        document.getElementById('tag-modal-cancel')?.addEventListener('click', closeModal);
        document.getElementById('tag-modal-overlay')?.addEventListener('click', closeModal);

        // Применить тег
        document.getElementById('tag-modal-submit')?.addEventListener('click', () => {
            this.applyTag();
        });

        // ESC для закрытия
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.tagModal.style.display === 'flex') {
                closeModal();
            }
        });
    }

    /**
     * Применить тег к выбранным звонкам
     */
    async applyTag() {
        if (!this.currentTagType || this.selectedCallIds.size === 0) {
            return;
        }

        const note = this.tagNoteTextarea.value.trim();

        // Для тега 'problem' описание обязательно
        if (this.currentTagType === 'problem' && !note) {
            alert('⚠️ Для проблемных звонков необходимо указать описание проблемы!');
            this.tagNoteTextarea.focus();
            return;
        }

        const callids = Array.from(this.selectedCallIds);

        const submitBtn = document.getElementById('tag-modal-submit');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Применение...';

        try {
            const response = await fetch('/api/tags.php', {
                method: 'POST',
                credentials: 'same-origin',  // Отправляем cookies
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    callids: callids,
                    tag_type: this.currentTagType,
                    note: note || null
                })
            });

            const result = await response.json();

            if (result.success) {
                // Закрыть модальное окно
                this.tagModal.style.display = 'none';

                // Показать уведомление
                this.showNotification(
                    `✅ Тег применен к ${result.count} звонку(ам)`,
                    'success'
                );

                // Обновить отображение тегов в таблице
                this.updateTagsInTable(callids, this.currentTagType);

                // Очистить выбор
                this.clearSelection();
            } else {
                throw new Error(result.error || 'Ошибка применения тега');
            }
        } catch (error) {
            console.error('Ошибка применения тега:', error);
            this.showNotification(
                `❌ Ошибка: ${error.message}`,
                'error'
            );
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    /**
     * Снять теги с выбранных звонков
     */
    async removeTags() {
        if (this.selectedCallIds.size === 0) {
            return;
        }

        if (!confirm(`Снять теги с ${this.selectedCallIds.size} звонка(ов)?`)) {
            return;
        }

        const callids = Array.from(this.selectedCallIds);

        try {
            const response = await fetch('/api/tags.php', {
                method: 'DELETE',
                credentials: 'same-origin',  // Отправляем cookies
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    callids: callids
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(
                    `✅ Теги сняты с ${result.deleted} звонка(ов)`,
                    'success'
                );

                // Обновить отображение в таблице
                this.updateTagsInTable(callids, null);

                // Очистить выбор
                this.clearSelection();
            } else {
                throw new Error(result.error || 'Ошибка снятия тегов');
            }
        } catch (error) {
            console.error('Ошибка снятия тегов:', error);
            this.showNotification(
                `❌ Ошибка: ${error.message}`,
                'error'
            );
        }
    }

    /**
     * Обновить отображение тегов в таблице
     */
    updateTagsInTable(callids, tagType) {
        const tagEmojis = {
            'good': '✅',
            'bad': '❌',
            'question': '❓',
            'problem': '⚠️'
        };

        callids.forEach(callid => {
            const checkbox = document.querySelector(`.call-checkbox[data-callid="${callid}"]`);
            if (checkbox) {
                const row = checkbox.closest('tr');
                const tagCell = row.querySelector('.tag-cell');

                if (tagCell) {
                    if (tagType) {
                        tagCell.textContent = tagEmojis[tagType];
                        tagCell.classList.remove('no-tag');
                        tagCell.title = `Тег: ${tagType}`;
                    } else {
                        tagCell.textContent = '—';
                        tagCell.classList.add('no-tag');
                        tagCell.title = 'Без тега';
                    }
                }
            }
        });
    }

    /**
     * Очистить выбор
     */
    clearSelection() {
        this.selectedCallIds.clear();

        // Снять галочки
        document.querySelectorAll('.call-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });

        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.checked = false;
        }

        // Убрать класс selected у строк
        document.querySelectorAll('.calls-table tbody tr.selected').forEach(row => {
            row.classList.remove('selected');
        });

        this.updateUI();
    }

    /**
     * Показать уведомление
     */
    showNotification(message, type = 'info') {
        // Создать элемент уведомления
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: ${type === 'success' ? '#34C759' : '#FF3B30'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10001;
            animation: slideInRight 0.3s ease-out;
            font-size: 14px;
            font-weight: 500;
        `;

        document.body.appendChild(notification);

        // Автоматически удалить через 3 секунды
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Добавить чекбоксы к существующим строкам (для динамически загруженных)
     */
    addCheckboxesToRows() {
        const rows = document.querySelectorAll('.calls-table tbody tr[data-callid]');
        rows.forEach(row => {
            const callId = row.dataset.callid;

            // Проверить, есть ли уже чекбокс
            if (!row.querySelector('.call-checkbox')) {
                const firstCell = row.cells[0];
                if (firstCell) {
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.classList.add('call-checkbox');
                    checkbox.dataset.callid = callId;

                    firstCell.innerHTML = '';
                    firstCell.appendChild(checkbox);
                }
            }
        });
    }
}

// Добавить стили для анимации уведомлений
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

// Инициализация при загрузке страницы
let bulkActionsInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    bulkActionsInstance = new BulkActions();
});

// Экспорт для использования в других скриптах
window.BulkActions = BulkActions;
window.getBulkActionsInstance = () => bulkActionsInstance;
