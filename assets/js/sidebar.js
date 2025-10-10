/**
 * Sidebar Toggle - Управление сворачиванием боковой панели и мобильным меню
 */

(function() {
    'use strict';

    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle-btn');

    if (!sidebar || !toggleBtn) {
        console.warn('Sidebar elements not found');
        return;
    }

    // Функция определения мобильного устройства
    function isMobile() {
        return window.innerWidth <= 768;
    }

    // Создание overlay для мобильных
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    // Создание кнопки мобильного меню
    let mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    if (!mobileMenuBtn) {
        mobileMenuBtn = document.createElement('button');
        mobileMenuBtn.className = 'mobile-menu-btn';
        mobileMenuBtn.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        `;
        document.body.appendChild(mobileMenuBtn);
    }

    // Загрузка сохраненного состояния из localStorage (только для desktop)
    if (!isMobile()) {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
    }

    // Обработчик клика на кнопку toggle (desktop)
    toggleBtn.addEventListener('click', function() {
        if (isMobile()) {
            // На мобильных закрываем sidebar
            closeMobileSidebar();
        } else {
            // На desktop сворачиваем/разворачиваем
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
            const collapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', collapsed);
        }
    });

    // Обработчик клика на мобильную кнопку меню
    mobileMenuBtn.addEventListener('click', function() {
        openMobileSidebar();
    });

    // Обработчик клика на overlay
    overlay.addEventListener('click', function() {
        closeMobileSidebar();
    });

    // Функция открытия мобильного меню
    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // Блокируем прокрутку body
    }

    // Функция закрытия мобильного меню
    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        document.body.style.overflow = ''; // Разблокируем прокрутку body
    }

    // Закрытие меню при клике на пункт меню (только на мобильных)
    const menuItems = document.querySelectorAll('.sidebar-menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            if (isMobile()) {
                closeMobileSidebar();
            }
        });
    });

    // Обработка изменения размера окна
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (isMobile()) {
                // Переключаемся в мобильный режим
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
                closeMobileSidebar();
            } else {
                // Переключаемся в desktop режим
                closeMobileSidebar();

                // Восстанавливаем сохраненное состояние collapsed
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    document.body.classList.add('sidebar-collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                    document.body.classList.remove('sidebar-collapsed');
                }
            }
        }, 250);
    });
})();
