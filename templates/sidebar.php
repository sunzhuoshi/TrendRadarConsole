<?php
/**
 * TrendRadarConsole - Sidebar Template
 */
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h1>TrendRadarConsole</h1>
        <small>Configuration Manager</small>
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
    </nav>
</aside>
