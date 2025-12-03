<?php
/**
 * TrendRadarConsole - Main Index Page
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/configuration.php';
require_once 'includes/auth.php';

// Check if config file exists
if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Require login
Auth::requireLogin();
$userId = Auth::getUserId();

try {
    $config = new Configuration($userId);
    $configurations = $config->getAll();
    $activeConfig = $config->getActive();
    
    // Get GitHub settings to check if configured
    $auth = new Auth();
    $githubSettings = $auth->getGitHubSettings($userId);
    $githubConfigured = !empty($githubSettings['github_owner']) && 
                        !empty($githubSettings['github_repo']) && 
                        !empty($githubSettings['github_token']);
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'dashboard';
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - <?php _e('dashboard'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2><?php _e('dashboard'); ?></h2>
                <p><?php _e('welcome_message'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo sanitize($error); ?>
            </div>
            <?php elseif (!$githubConfigured): ?>
            
            <!-- GitHub Setup Required -->
            <div class="card" style="border-left: 4px solid #667eea;">
                <div class="card-body" style="text-align: center; padding: 40px 20px;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🐙</div>
                    <h3><?php _e('github_setup_required'); ?></h3>
                    <p style="color: #666; margin: 15px 0 25px;"><?php _e('github_setup_required_desc'); ?></p>
                    <a href="setup-github.php" class="btn btn-primary btn-lg"><?php _e('setup_github_now'); ?></a>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- Stats Cards -->
            <div class="row">
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-card-icon primary">📋</div>
                        <div class="stat-card-value"><?php echo count($configurations); ?></div>
                        <div class="stat-card-label"><?php _e('configurations'); ?></div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-card-icon success">✓</div>
                        <div class="stat-card-value"><?php echo $activeConfig ? '1' : '0'; ?></div>
                        <div class="stat-card-label"><?php _e('active_config'); ?></div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-card-icon warning">📡</div>
                        <div class="stat-card-value">
                            <?php 
                            if ($activeConfig) {
                                $platforms = $config->getPlatforms($activeConfig['id']);
                                echo count(array_filter($platforms, function($p) { return $p['is_enabled']; }));
                            } else {
                                echo '0';
                            }
                            ?>
                        </div>
                        <div class="stat-card-label"><?php _e('active_platforms'); ?></div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-card-icon info">🔔</div>
                        <div class="stat-card-value">
                            <?php 
                            if ($activeConfig) {
                                $webhooks = $config->getWebhooks($activeConfig['id']);
                                echo count(array_filter($webhooks, function($w) { return $w['is_enabled']; }));
                            } else {
                                echo '0';
                            }
                            ?>
                        </div>
                        <div class="stat-card-label"><?php _e('active_webhooks'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Configurations List -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('configurations'); ?></h3>
                    <!-- New configuration button hidden - only one configuration allowed -->
                </div>
                <div class="card-body">
                    <?php if (empty($configurations)): ?>
                    <div class="empty-state">
                        <p><?php _e('no_configurations'); ?></p>
                        <a href="github.php" class="btn btn-primary"><?php _e('load_from_github'); ?></a>
                    </div>
                    <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php _e('name'); ?></th>
                                <th><?php _e('description'); ?></th>
                                <th><?php _e('status'); ?></th>
                                <th><?php _e('created'); ?></th>
                                <th><?php _e('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configurations as $cfg): ?>
                            <tr>
                                <td>
                                    <a href="config-edit.php?id=<?php echo $cfg['id']; ?>">
                                        <strong><?php echo sanitize($cfg['name']); ?></strong>
                                    </a>
                                </td>
                                <td><?php echo sanitize($cfg['description'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($cfg['is_active']): ?>
                                    <span class="badge badge-success"><?php _e('active'); ?></span>
                                    <?php else: ?>
                                    <span class="badge badge-secondary"><?php _e('inactive'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($cfg['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="config-edit.php?id=<?php echo $cfg['id']; ?>" class="btn btn-outline btn-sm"><?php _e('edit'); ?></a>
                                    <?php if (!$cfg['is_active']): ?>
                                    <form method="post" action="api/config-action.php" style="display:inline;">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="id" value="<?php echo $cfg['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <button type="submit" class="btn btn-success btn-sm"><?php _e('activate'); ?></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Start Guide -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('quick_start_guide'); ?></h3>
                </div>
                <div class="card-body">
                    <ol style="line-height: 2;">
                        <li><?php _e('step1_create_config'); ?></li>
                        <li><?php _e('step2_platforms'); ?></li>
                        <li><?php _e('step3_keywords'); ?></li>
                        <li><?php _e('step4_webhooks'); ?></li>
                        <li><?php _e('step5_settings'); ?></li>
                        <li><?php _e('step6_github_sync'); ?></li>
                    </ol>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
</body>
</html>
