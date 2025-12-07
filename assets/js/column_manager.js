/**
 * Column Manager - управление видимостью колонок таблицы звонков
 */

// Определение всех колонок таблицы
const TABLE_COLUMNS = [
    { id: 'checkbox', label: 'Чекбокс', index: 0, required: true },
    { id: 'tag', label: 'Тег', index: 1, required: false },
    { id: 'manager', label: 'Менеджер', index: 2, required: true },
    { id: 'result', label: 'Результат', index: 3, required: false },
    { id: 'compliance', label: 'Оценки шаблонов', index: 4, required: false, isDynamic: true },
    { id: 'summary', label: 'Резюме', index: 5, required: false },
    { id: 'risk', label: '🚨 Риск', index: 6, required: false },
    { id: 'solvency', label: 'Платежеспособность', index: 7, required: false },
    { id: 'datetime', label: 'Дата и время', index: 8, required: true },
    { id: 'duration', label: 'Длина', index: 9, required: false },
    { id: 'phone', label: 'Номер', index: 10, required: false },
    { id: 'crm', label: 'CRM', index: 11, required: false },
    { id: 'actions', label: 'Действия', index: 12, required: true },
    { id: 'call_type', label: 'Тип звонка', index: 13, required: false },
    { id: 'department', label: 'Отдел', index: 14, required: false },
    { id: 'direction', label: 'Направление', index: 15, required: false }
];

// Дефолтные настройки (все колонки видимы кроме платежеспособности)
const DEFAULT_COLUMNS = TABLE_COLUMNS.reduce((acc, col) => {
    acc[col.id] = col.id !== 'solvency'; // Скрываем только solvency по умолчанию
    return acc;
}, {});

class ColumnManager {
    constructor() {
        this.settings = this.loadSettings();
        this.modal = document.getElementById('columns-modal');
        this.columnsList = document.getElementById('columns-list');

        this.init();
    }

    init() {
        // Применяем сохраненные настройки при загрузке
        this.applyColumnSettings();

        // Рендерим список колонок в модальном окне
        this.renderColumnsList();

        // Обработчики событий
        document.getElementById('columns-settings-btn').addEventListener('click', () => this.openModal());
        document.getElementById('columns-modal-close').addEventListener('click', () => this.closeModal());
        document.getElementById('columns-apply-btn').addEventListener('click', () => this.applySettings());
        document.getElementById('columns-reset-btn').addEventListener('click', () => this.resetToDefaults());

        // Закрытие по клику вне модального окна
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });
    }

    loadSettings() {
        const saved = localStorage.getItem('callsTableColumns');
        return saved ? JSON.parse(saved) : DEFAULT_COLUMNS;
    }

    saveSettings() {
        localStorage.setItem('callsTableColumns', JSON.stringify(this.settings));
    }

    openModal() {
        this.modal.classList.add('active');
        // Обновляем состояние чекбоксов на основе текущих настроек
        TABLE_COLUMNS.forEach(col => {
            const checkbox = document.getElementById(`col-checkbox-${col.id}`);
            if (checkbox) {
                checkbox.checked = this.settings[col.id] !== false;
            }
        });
    }

    closeModal() {
        this.modal.classList.remove('active');
    }

    renderColumnsList() {
        this.columnsList.innerHTML = TABLE_COLUMNS.map(col => {
            const isChecked = this.settings[col.id] !== false;
            const isDisabled = col.required;

            return `
                <div class="column-item ${isDisabled ? 'disabled' : ''}"
                     onclick="${isDisabled ? '' : `document.getElementById('col-checkbox-${col.id}').click()`}">
                    <input type="checkbox"
                           id="col-checkbox-${col.id}"
                           ${isChecked ? 'checked' : ''}
                           ${isDisabled ? 'disabled' : ''}
                           onclick="event.stopPropagation()">
                    <label for="col-checkbox-${col.id}" style="cursor: ${isDisabled ? 'not-allowed' : 'pointer'};">
                        ${col.label}
                        ${col.required ? '<small style="color: #999;"> (обязательная)</small>' : ''}
                    </label>
                </div>
            `;
        }).join('');
    }

    applySettings() {
        // Собираем новые настройки из чекбоксов
        TABLE_COLUMNS.forEach(col => {
            const checkbox = document.getElementById(`col-checkbox-${col.id}`);
            if (checkbox && !col.required) {
                this.settings[col.id] = checkbox.checked;
            }
        });

        // Сохраняем в localStorage
        this.saveSettings();

        // Применяем к таблице
        this.applyColumnSettings();

        // Закрываем модальное окно
        this.closeModal();
    }

    applyColumnSettings() {
        const table = document.getElementById('calls-table');
        if (!table) return;

        TABLE_COLUMNS.forEach(col => {
            const isVisible = this.settings[col.id] !== false;

            if (col.isDynamic) {
                // Для динамических колонок (шаблонов) применяем к заголовкам с классом
                const headers = table.querySelectorAll('.compliance-column-header');
                headers.forEach(header => {
                    header.style.display = isVisible ? '' : 'none';
                });

                // И к ячейкам
                const cells = table.querySelectorAll('.compliance-column');
                cells.forEach(cell => {
                    cell.style.display = isVisible ? '' : 'none';
                });
            } else {
                // Для статических колонок используем data-column-id
                // Скрываем заголовок
                const th = table.querySelector(`thead th[data-column-id="${col.id}"]`);
                if (th) {
                    th.style.display = isVisible ? '' : 'none';
                }

                // Скрываем все ячейки в этой колонке
                const tds = table.querySelectorAll(`tbody td[data-column-id="${col.id}"]`);
                tds.forEach(td => {
                    td.style.display = isVisible ? '' : 'none';
                });
            }
        });

        console.log('✅ Настройки колонок применены:', this.settings);
    }

    resetToDefaults() {
        if (confirm('Вы уверены, что хотите сбросить настройки колонок по умолчанию?')) {
            this.settings = { ...DEFAULT_COLUMNS };
            this.saveSettings();
            this.renderColumnsList();
            this.applyColumnSettings();
            this.closeModal();
        }
    }
}

// Инициализация после загрузки DOM
document.addEventListener('DOMContentLoaded', () => {
    window.columnManager = new ColumnManager();
});
