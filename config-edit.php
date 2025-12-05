<?php
/**
 * TrendRadarConsole - Configuration Edit Page
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/configuration.php';
require_once 'includes/auth.php';
require_once 'includes/operation_log.php';

if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Require login
Auth::requireLogin();
$userId = Auth::getUserId();

// Check if GitHub is configured
$auth = new Auth();
$githubSettings = $auth->getGitHubSettings($userId);
$githubConfigured = !empty($githubSettings['github_owner']) && 
                    !empty($githubSettings['github_repo']) && 
                    !empty($githubSettings['github_token']);

if (!$githubConfigured) {
    header('Location: github-deployment.php');
    exit;
}

try {
    $config = new Configuration($userId);
    $opLog = new OperationLog($userId);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        
        if (!verifyCsrfToken($csrfToken)) {
            setFlash('error', __('invalid_request_try_again'));
        } else {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                setFlash('error', __('configuration_name_required'));
            } else {
                if ($id) {
                    // Update existing
                    $config->update($id, [
                        'name' => $name,
                        'description' => $description
                    ]);
                    
                    // Log the operation
                    $opLog->log(
                        OperationLog::ACTION_CONFIG_UPDATE,
                        OperationLog::TARGET_CONFIGURATION,
                        $id,
                        ['name' => $name]
                    );
                    
                    setFlash('success', __('config_updated_success'));
                } else {
                    // Create new
                    $newId = $config->create($name, $description);
                    
                    // Copy default platforms
                    $defaultPlatforms = [
                        ['id' => 'toutiao', 'name' => '今日头条'],
                        ['id' => 'baidu', 'name' => '百度热搜'],
                        ['id' => 'wallstreetcn-hot', 'name' => '华尔街见闻'],
                        ['id' => 'thepaper', 'name' => '澎湃新闻'],
                        ['id' => 'bilibili-hot-search', 'name' => 'bilibili 热搜'],
                        ['id' => 'cls-hot', 'name' => '财联社热门'],
                        ['id' => 'ifeng', 'name' => '凤凰网'],
                        ['id' => 'tieba', 'name' => '贴吧'],
                        ['id' => 'weibo', 'name' => '微博'],
                        ['id' => 'douyin', 'name' => '抖音'],
                        ['id' => 'zhihu', 'name' => '知乎'],
                    ];
                    
                    foreach ($defaultPlatforms as $index => $p) {
                        $config->addPlatform($newId, $p['id'], $p['name'], $index + 1);
                    }
                    
                    // Add default settings
                    $defaultSettings = [
                        'report_mode' => 'incremental',
                        'rank_threshold' => '5',
                        'sort_by_position_first' => 'false',
                        'max_news_per_keyword' => '0',
                        'rank_weight' => '0.6',
                        'frequency_weight' => '0.3',
                        'hotness_weight' => '0.1',
                        'enable_crawler' => 'true',
                        'enable_notification' => 'true',
                        'push_window_enabled' => 'false',
                        'push_window_start' => '20:00',
                        'push_window_end' => '22:00',
                        'push_window_once_per_day' => 'true',
                    ];
                    
                    foreach ($defaultSettings as $key => $value) {
                        $config->saveSetting($newId, $key, $value);
                    }
                    
                    // Log the operation
                    $opLog->log(
                        OperationLog::ACTION_CONFIG_CREATE,
                        OperationLog::TARGET_CONFIGURATION,
                        $newId,
                        ['name' => $name]
                    );
                    
                    setFlash('success', __('config_created_success'));
                    header('Location: config-edit.php?id=' . $newId);
                    exit;
                }
            }
        }
    }
    
    // Load configuration if editing
    $configData = null;
    if (isset($_GET['id'])) {
        $configData = $config->getById((int)$_GET['id']);
        if (!$configData) {
            setFlash('error', __('config_not_found'));
            header('Location: index.php');
            exit;
        }
    } else {
        // If not editing, redirect to existing configuration (only one allowed)
        $existingConfigs = $config->getAll();
        if (!empty($existingConfigs)) {
            header('Location: config-edit.php?id=' . $existingConfigs[0]['id']);
            exit;
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'dashboard';
$pageTitle = $configData ? __('edit_configuration') : __('new_config');
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - <?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2><?php echo $pageTitle; ?></h2>
                <p><?php echo $configData ? __('update_config_details') : __('create_config_for_trendradar'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('configuration_details'); ?></h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <?php if ($configData): ?>
                        <input type="hidden" name="id" value="<?php echo $configData['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label"><?php _e('configuration_name'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?php echo sanitize($configData['name'] ?? ''); ?>"
                                   placeholder="<?php _e('config_name_placeholder'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><?php _e('description'); ?></label>
                            <textarea name="description" class="form-control" rows="3"
                                      placeholder="<?php _e('description_placeholder'); ?>"><?php echo sanitize($configData['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $configData ? __('update_configuration') : __('create_configuration'); ?>
                            </button>
                            <a href="index.php" class="btn btn-secondary"><?php _e('cancel'); ?></a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($configData): ?>
            <!-- Quick Links -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('quick_configuration'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-3">
                            <a href="platforms.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
                                <div class="stat-card-icon primary">📡</div>
                                <div class="stat-card-label"><?php _e('manage_platforms'); ?></div>
                            </a>
                        </div>
                        <div class="col-3">
                            <a href="keywords.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
                                <div class="stat-card-icon success">🔑</div>
                                <div class="stat-card-label"><?php _e('configure_keywords'); ?></div>
                            </a>
                        </div>
                        <div class="col-3">
                            <a href="webhooks.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
                                <div class="stat-card-icon warning">🔔</div>
                                <div class="stat-card-label"><?php _e('setup_notifications'); ?></div>
                            </a>
                        </div>
                        <div class="col-3">
                            <a href="settings.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
                                <div class="stat-card-icon info">⚙️</div>
                                <div class="stat-card-label"><?php _e('adjust_settings'); ?></div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="card">
                <div class="card-header">
                    <h3 class="text-danger">⚠️ <?php _e('danger_zone'); ?></h3>
                </div>
                <div class="card-body">
                    <?php if (!$configData['is_active']): ?>
                    <p><?php _e('delete_config_warning'); ?></p>
                    <form method="post" action="api/config-action.php" 
                          onsubmit="return confirm('<?php _e('confirm_delete_config'); ?>');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $configData['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <button type="submit" class="btn btn-danger"><?php _e('delete_configuration'); ?></button>
                    </form>
                    <?php else: ?>
                    <p class="text-muted"><?php _e('active_config_delete_warning'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
</body>
</html>
