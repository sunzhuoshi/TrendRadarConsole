<?php
/**
 * TrendRadarConsole - Sidebar Template
 */
require_once __DIR__ . '/../includes/auth.php';
$currentUsername = Auth::getUsername();
?>
<button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>
<aside class="sidebar">
    <div class="sidebar-header">
        <h1>TrendRadarConsole</h1>
        <small>👤 <?php echo htmlspecialchars($currentUsername ?? 'Guest'); ?></small>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>">
            📊 Dashboard
        </a>
        <a href="platforms.php" class="nav-item <?php echo ($currentPage ?? '') === 'platforms' ? 'active' : ''; ?>">
            📡 Platforms
        </a>
        <a href="keywords.php" class="nav-item <?php echo ($currentPage ?? '') === 'keywords' ? 'active' : ''; ?>">
            🔑 Keywords
        </a>
        <a href="webhooks.php" class="nav-item <?php echo ($currentPage ?? '') === 'webhooks' ? 'active' : ''; ?>">
            🔔 Notifications
        </a>
        <a href="settings.php" class="nav-item <?php echo ($currentPage ?? '') === 'settings' ? 'active' : ''; ?>">
            ⚙️ Settings
        </a>
        <a href="export.php" class="nav-item <?php echo ($currentPage ?? '') === 'export' ? 'active' : ''; ?>">
            📤 Export
        </a>
        <a href="github.php" class="nav-item <?php echo ($currentPage ?? '') === 'github' ? 'active' : ''; ?>">
            🐙 GitHub Sync
        </a>
        <a href="logout.php" class="nav-item" style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1);">
            🚪 Logout
        </a>
    </nav>
</aside>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
