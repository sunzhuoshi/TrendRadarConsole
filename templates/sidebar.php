<?php
/**
 * TrendRadarConsole - Sidebar Template
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$currentUsername = Auth::getUsername();
$currentLang = getCurrentLanguage();
$lastUpdated = getLastUpdatedTime();
$isAdvancedMode = Auth::checkAdvancedMode();
$isAdmin = Auth::checkIsAdmin();

// Get feature toggle status
$auth = new Auth();
$dockerEnabled = $auth->isFeatureEnabled('docker_deployment');
$githubEnabled = $auth->isFeatureEnabled('github_deployment');
$advancedModeEnabled = $auth->isFeatureEnabled('advanced_mode');
?>
<button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>
<aside class="sidebar">
    <div class="sidebar-header">
        <h1><?php _e('app_name'); ?></h1>
        <small>👤 <?php echo htmlspecialchars($currentUsername ?? __('guest')); ?></small>
        <?php if ($isAdmin): ?>
        <div>
            <span class="admin-badge">🔑 <?php _e('admin_badge'); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($isAdvancedMode): ?>
        <div>
            <span class="advanced-mode-badge">🛠️ ADV</span>
        </div>
        <?php endif; ?>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>">
            📊 <?php _e('dashboard'); ?>
        </a>
        <a href="platforms.php" class="nav-item <?php echo ($currentPage ?? '') === 'platforms' ? 'active' : ''; ?>">
            📡 <?php _e('platforms'); ?>
        </a>
        <a href="keywords.php" class="nav-item <?php echo ($currentPage ?? '') === 'keywords' ? 'active' : ''; ?>">
            🔑 <?php _e('keywords'); ?>
        </a>
        <a href="webhooks.php" class="nav-item <?php echo ($currentPage ?? '') === 'webhooks' ? 'active' : ''; ?>">
            🔔 <?php _e('notifications'); ?>
        </a>
        <a href="settings.php" class="nav-item <?php echo ($currentPage ?? '') === 'settings' ? 'active' : ''; ?>">
            ⚙️ <?php _e('settings'); ?>
        </a>
        <?php if ($dockerEnabled): ?>
        <a href="docker.php" class="nav-item <?php echo ($currentPage ?? '') === 'docker' ? 'active' : ''; ?>">
            🐳 <?php _e('docker_deployment'); ?>
        </a>
        <?php endif; ?>
        <?php if ($isAdvancedMode && $advancedModeEnabled): ?>
        <a href="docker-workers.php" class="nav-item <?php echo ($currentPage ?? '') === 'docker-workers' ? 'active' : ''; ?>">
            🖥️ <?php _e('docker_workers'); ?>
        </a>
        <?php endif; ?>
        <?php if ($githubEnabled): ?>
        <a href="github-deployment.php" class="nav-item <?php echo ($currentPage ?? '') === 'github-deployment' ? 'active' : ''; ?>">
            🐙 <?php _e('github_deployment'); ?>
        </a>
        <?php endif; ?>
        <a href="logs.php" class="nav-item <?php echo ($currentPage ?? '') === 'logs' ? 'active' : ''; ?>">
            📋 <?php _e('operation_logs'); ?>
        </a>
        <?php if ($isAdmin): ?>
        <a href="admin.php" class="nav-item <?php echo ($currentPage ?? '') === 'admin' ? 'active' : ''; ?>">
            🔑 <?php _e('admin_panel'); ?>
        </a>
        <?php endif; ?>
        <div class="nav-item language-switcher" style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px; margin-top: 10px;">
            🌐 <?php _e('language'); ?>:
            <select id="language-select" onchange="switchLanguage(this.value)" style="margin-left: 8px; padding: 4px 8px; border-radius: 4px; border: none; background: rgba(255,255,255,0.1); color: inherit; cursor: pointer;">
                <option value="zh" <?php echo $currentLang === 'zh' ? 'selected' : ''; ?>><?php _e('chinese'); ?></option>
                <option value="en" <?php echo $currentLang === 'en' ? 'selected' : ''; ?>><?php _e('english'); ?></option>
            </select>
        </div>
        <a href="logout.php" class="nav-item" style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1);">
            🚪 <?php _e('logout'); ?>
        </a>
        <?php 
        if ($lastUpdated): 
            $timestamp = strtotime($lastUpdated);
            if ($timestamp !== false):
        ?>
        <div class="nav-item version-info" style="font-size: 0.75rem; opacity: 0.7; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
            🕐 <?php _e('last_updated'); ?>: <?php echo htmlspecialchars(date('Y-m-d H:i', $timestamp)); ?>
        </div>
        <?php 
            endif;
        endif; 
        ?>
    </nav>
</aside>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
