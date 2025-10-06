/**
 * Multiselect компонент с чекбоксами и поиском
 */

class MultiSelect {
    constructor(element) {
        this.element = element;
        this.trigger = element.querySelector('.multiselect-trigger');
        this.dropdown = element.querySelector('.multiselect-dropdown');
        this.valueDisplay = element.querySelector('.multiselect-value');
        this.searchInput = element.querySelector('.multiselect-search');
        this.selectAllBtn = element.querySelector('.multiselect-select-all');
        this.optionsContainer = element.querySelector('.multiselect-options');
        this.isOpen = false;

        this.init();
    }

    init() {
        // Открытие/закрытие dropdown
        this.trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });

        // Поиск
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                this.filterOptions(e.target.value);
            });

            this.searchInput.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }

        // Выбрать все
        if (this.selectAllBtn) {
            this.selectAllBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleSelectAll();
            });
        }

        // Сбросить (очистить выбор)
        const clearBtn = this.element.querySelector('.multiselect-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.clear();
            });
        }

        // Обработка выбора чекбоксов
        this.optionsContainer.addEventListener('change', (e) => {
            if (e.target.type === 'checkbox') {
                this.updateValue();
            }
        });

        // Клик по опции (по всей строке)
        this.optionsContainer.addEventListener('click', (e) => {
            const option = e.target.closest('.multiselect-option');
            if (option) {
                const checkbox = option.querySelector('input[type="checkbox"]');
                if (e.target !== checkbox) {
                    checkbox.checked = !checkbox.checked;
                    this.updateValue();
                }
            }
        });

        // Закрытие при клике вне элемента
        document.addEventListener('click', (e) => {
            if (!this.element.contains(e.target)) {
                this.close();
            }
        });

        // Инициализация отображения
        this.updateValue();
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.isOpen = true;
        this.element.classList.add('active');
        this.dropdown.style.display = 'flex';
        if (this.searchInput) {
            this.searchInput.value = '';
            this.filterOptions('');
            setTimeout(() => this.searchInput.focus(), 100);
        }
    }

    close() {
        this.isOpen = false;
        this.element.classList.remove('active');
        this.dropdown.style.display = 'none';
    }

    updateValue() {
        const checkboxes = this.optionsContainer.querySelectorAll('input[type="checkbox"]:checked');
        const count = checkboxes.length;

        if (count === 0) {
            this.valueDisplay.innerHTML = '—';
            this.valueDisplay.classList.add('placeholder');
        } else {
            const names = Array.from(checkboxes).map(cb => {
                const label = cb.closest('.multiselect-option').querySelector('span').textContent;
                return label;
            });

            // Показываем счетчик + первый элемент, если выбрано несколько
            if (count === 1) {
                this.valueDisplay.innerHTML = names[0];
            } else {
                this.valueDisplay.innerHTML = `<span class="multiselect-count">${count}</span> ${names[0]}`;
            }
            this.valueDisplay.classList.remove('placeholder');
        }
    }

    filterOptions(searchTerm) {
        const options = this.optionsContainer.querySelectorAll('.multiselect-option');
        const term = searchTerm.toLowerCase();

        options.forEach(option => {
            const text = option.querySelector('span').textContent.toLowerCase();
            const matches = text.includes(term);
            option.style.display = matches ? 'flex' : 'none';

            // Подсветка совпадений
            if (matches && term) {
                option.classList.add('highlight');
            } else {
                option.classList.remove('highlight');
            }
        });
    }

    toggleSelectAll() {
        const visibleCheckboxes = Array.from(
            this.optionsContainer.querySelectorAll('.multiselect-option:not([style*="display: none"]) input[type="checkbox"]')
        );

        const allChecked = visibleCheckboxes.every(cb => cb.checked);

        visibleCheckboxes.forEach(cb => {
            cb.checked = !allChecked;
        });

        this.updateValue();
    }

    // Программно установить значения
    setValues(values) {
        const checkboxes = this.optionsContainer.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.checked = values.includes(cb.value);
        });
        this.updateValue();
    }

    // Получить выбранные значения
    getValues() {
        const checkboxes = this.optionsContainer.querySelectorAll('input[type="checkbox"]:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    // Добавить опции (для динамической загрузки)
    setOptions(options) {
        this.optionsContainer.innerHTML = options.map(opt => `
            <label class="multiselect-option">
                <input type="checkbox" name="${opt.name}" value="${opt.value}">
                <span>${opt.label}</span>
            </label>
        `).join('');
        this.updateValue();
    }

    // Очистить выбор
    clear() {
        const checkboxes = this.optionsContainer.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = false);
        this.updateValue();
    }
}

// Инициализация всех multiselect на странице
function initMultiselects() {
    const multiselects = document.querySelectorAll('.multiselect');
    const instances = new Map();

    multiselects.forEach(element => {
        const instance = new MultiSelect(element);
        instances.set(element.id, instance);
    });

    return instances;
}
