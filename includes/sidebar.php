<?php
/**
 * Sidebar navigation component
 */
?>
<aside class="sidebar">
    <!-- Header -->
    <div class="sidebar-toggle">
        <button class="sidebar-toggle-btn" id="sidebar-toggle-btn" title="Свернуть/развернуть меню">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-menu">
        <a href="index_new.php" class="sidebar-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index_new.php' ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg>
            <span class="sidebar-menu-text">Звонки</span>
        </a>

        <a href="analytics.php" class="sidebar-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="20" x2="12" y2="10"></line>
                <line x1="18" y1="20" x2="18" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="16"></line>
            </svg>
            <span class="sidebar-menu-text">Аналитика</span>
        </a>

        <a href="#" class="sidebar-menu-item">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                <line x1="7" y1="7" x2="7.01" y2="7"></line>
            </svg>
            <span class="sidebar-menu-text">Теги</span>
        </a>

        <a href="#" class="sidebar-menu-item">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="sidebar-menu-text">Менеджеры</span>
        </a>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="admin_users.php" class="sidebar-menu-item" style="color: #dc3545;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 15v5m-3 0h6M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
            </svg>
            <span class="sidebar-menu-text">ADMIN</span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- User Info -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></div>
            <a href="auth/logout.php" style="font-size: 12px; color: #6c757d; text-decoration: none;">Выйти</a>
        </div>
    </div>
</aside>
