/**
 * Theme Switcher
 * Управление темной/светлой темой с поддержкой:
 * - Автоматического определения системной темы
 * - Сохранения выбора в localStorage
 * - Плавных переходов между темами
 */

class ThemeSwitcher {
    constructor() {
        this.STORAGE_KEY = 'domreal-theme';
        this.currentTheme = this.getInitialTheme();
        this.init();
    }

    /**
     * Определяет начальную тему
     * Приоритет: localStorage -> системная тема -> светлая тема
     */
    getInitialTheme() {
        // 1. Проверяем localStorage
        const savedTheme = localStorage.getItem(this.STORAGE_KEY);
        if (savedTheme && (savedTheme === 'light' || savedTheme === 'dark')) {
            return savedTheme;
        }

        // 2. Проверяем системную тему
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        // 3. По умолчанию светлая тема
        return 'light';
    }

    /**
     * Инициализация темы
     */
    init() {
        // Применяем тему ДО загрузки DOM для предотвращения мигания
        this.applyTheme(this.currentTheme);

        // Слушаем изменение системной темы
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                // Меняем тему только если пользователь не выбирал вручную
                const savedTheme = localStorage.getItem(this.STORAGE_KEY);
                if (!savedTheme) {
                    const newTheme = e.matches ? 'dark' : 'light';
                    this.setTheme(newTheme);
                }
            });
        }
    }

    /**
     * Применяет тему к документу
     * @param {string} theme - 'light' или 'dark'
     */
    applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    }

    /**
     * Устанавливает новую тему
     * @param {string} theme - 'light' или 'dark'
     */
    setTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') {
            console.error('Invalid theme:', theme);
            return;
        }

        this.currentTheme = theme;
        this.applyTheme(theme);
        localStorage.setItem(this.STORAGE_KEY, theme);

        // Генерируем событие для обновления UI кнопки
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
    }

    /**
     * Переключает тему на противоположную
     */
    toggleTheme() {
        const newTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }

    /**
     * Возвращает текущую тему
     * @returns {string} 'light' или 'dark'
     */
    getCurrentTheme() {
        return this.currentTheme;
    }

    /**
     * Проверяет, включена ли темная тема
     * @returns {boolean}
     */
    isDarkTheme() {
        return this.currentTheme === 'dark';
    }
}

// Создаем глобальный экземпляр СРАЗУ (до загрузки DOM)
window.themeSwitcher = new ThemeSwitcher();

/**
 * Компонент кнопки переключения темы
 */
class ThemeSwitcherButton {
    constructor(buttonElement) {
        this.button = buttonElement;
        this.init();
    }

    init() {
        // Обновляем начальное состояние кнопки
        this.updateButton();

        // Слушаем клики
        this.button.addEventListener('click', () => {
            window.themeSwitcher.toggleTheme();
        });

        // Слушаем изменения темы для обновления UI
        window.addEventListener('themeChanged', () => {
            this.updateButton();
        });
    }

    /**
     * Обновляет иконку и aria-label кнопки
     */
    updateButton() {
        const isDark = window.themeSwitcher.isDarkTheme();

        // Обновляем SVG иконку
        this.button.innerHTML = isDark
            ? this.getSunIcon()  // Показываем солнце в темной теме (переключение на светлую)
            : this.getMoonIcon(); // Показываем луну в светлой теме (переключение на темную)

        // Обновляем aria-label для доступности
        this.button.setAttribute('aria-label', isDark ? 'Переключить на светлую тему' : 'Переключить на темную тему');
        this.button.setAttribute('title', isDark ? 'Светлая тема' : 'Темная тема');
    }

    /**
     * Возвращает SVG иконку луны
     */
    getMoonIcon() {
        return `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
        `;
    }

    /**
     * Возвращает SVG иконку солнца
     */
    getSunIcon() {
        return `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
        `;
    }
}

// Инициализация кнопки после загрузки DOM
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('theme-switcher-btn');
    if (button) {
        new ThemeSwitcherButton(button);
    }
});
