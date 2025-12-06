<?php
/**
 * TrendRadarConsole - Platforms Management Page
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/configuration.php';
require_once 'includes/auth.php';

if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Require login
Auth::requireLogin();
$userId = Auth::getUserId();

try {
    $config = new Configuration($userId);
    $activeConfig = $config->getActive();
    
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
    
    if (!$activeConfig) {
        setFlash('warning', __('please_create_config'));
        header('Location: index.php');
        exit;
    }
    
    $platforms = $config->getPlatforms($activeConfig['id']);
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Available platforms from TrendRadar
$availablePlatforms = [
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
    ['id' => 'ithome', 'name' => 'IT之家'],
    ['id' => '36kr', 'name' => '36氪'],
    ['id' => 'sspai', 'name' => '少数派'],
    ['id' => 'kr-hotnews', 'name' => '36氪热闻'],
    ['id' => 'hackernews', 'name' => 'Hacker News'],
    ['id' => 'producthunt', 'name' => 'Product Hunt'],
    ['id' => 'github', 'name' => 'GitHub Trending'],
    ['id' => 'v2ex', 'name' => 'V2EX'],
    ['id' => 'coolapk', 'name' => '酷安'],
];

// Get existing platform IDs
$existingIds = array_column($platforms, 'platform_id');

$flash = getFlash();
$currentPage = 'platforms';
$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - <?php _e('platforms'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2><?php _e('platform_management'); ?></h2>
                <p><?php _e('configure_platforms_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <!-- Add Platform -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('add_platform'); ?></h3>
                </div>
                <div class="card-body">
                    <form method="post" action="api/platforms.php" id="add-platform-form">
                        <input type="hidden" name="config_id" value="<?php echo $activeConfig['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('select_platform'); ?></label>
                                    <select name="platform_preset" class="form-control" id="platform-preset">
                                        <option value=""><?php _e('select_a_platform'); ?></option>
                                        <?php foreach ($availablePlatforms as $p): ?>
                                        <?php if (!in_array($p['id'], $existingIds)): ?>
                                        <option value="<?php echo htmlspecialchars($p['id']); ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>">
                                            <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['id']); ?>)
                                        </option>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('or_add_custom'); ?></label>
                                    <div class="d-flex gap-2">
                                        <input type="text" name="platform_id" class="form-control" placeholder="<?php _e('platform_id_placeholder'); ?>" id="platform-id">
                                        <input type="text" name="platform_name" class="form-control" placeholder="<?php _e('display_name'); ?>" id="platform-name">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><?php _e('add_platform'); ?></button>
                    </form>
                </div>
            </div>
            
            <!-- Platforms List -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('configured_platforms'); ?> (<?php echo count($platforms); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($platforms)): ?>
                    <div class="empty-state">
                        <p><?php _e('no_platforms_configured'); ?></p>
                    </div>
                    <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php _e('order'); ?></th>
                                <th><?php _e('platform_id'); ?></th>
                                <th><?php _e('display_name'); ?></th>
                                <th style="width: 100px;"><?php _e('enabled'); ?></th>
                                <th style="width: 150px;"><?php _e('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($platforms as $index => $platform): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><code><?php echo sanitize($platform['platform_id']); ?></code></td>
                                <td><?php echo sanitize($platform['platform_name']); ?></td>
                                <td>
                                    <label class="toggle-switch">
                                        <input type="checkbox" 
                                               <?php echo $platform['is_enabled'] ? 'checked' : ''; ?>
                                               onchange="platformManager.toggle(<?php echo $platform['id']; ?>, this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                                <td class="actions">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="editPlatform(<?php echo $platform['id']; ?>, '<?php echo sanitize($platform['platform_name']); ?>')">
                                        <?php _e('edit'); ?>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="platformManager.remove(<?php echo $platform['id']; ?>)">
                                        <?php _e('delete'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Platform Info -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('available_platforms_ref'); ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <?php
                            echo __('platforms_ref_desc') . ' ';
                            echo '<a href="https://github.com/ourongxing/newsnow/tree/main/server/sources" target="_blank">' . __('newsnow_source_code') . '</a>. ';
                            echo __('find_more_platforms');
                        ?>
                    </p>
                    <div class="keyword-tags">
                        <?php foreach ($availablePlatforms as $p): ?>
                        <span class="keyword-tag <?php echo in_array($p['id'], $existingIds) ? 'required' : ''; ?>">
                            <?php echo sanitize($p['name']); ?> 
                            <small>(<?php echo sanitize($p['id']); ?>)</small>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal-overlay" id="edit-modal">
        <div class="modal">
            <div class="modal-header">
                <h3><?php _e('edit_platform'); ?></h3>
                <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-platform-form">
                    <input type="hidden" name="id" id="edit-platform-id">
                    <div class="form-group">
                        <label class="form-label"><?php _e('display_name'); ?></label>
                        <input type="text" name="platform_name" class="form-control" id="edit-platform-name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')"><?php _e('cancel'); ?></button>
                <button type="button" class="btn btn-primary" onclick="savePlatformEdit()"><?php _e('save_changes'); ?></button>
            </div>
        </div>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        // Platform preset selection
        document.getElementById('platform-preset').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.value) {
                document.getElementById('platform-id').value = option.value;
                document.getElementById('platform-name').value = option.dataset.name;
            }
        });
        
        // Add platform form submission
        document.getElementById('add-platform-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const configId = document.getElementById('config-id').value;
            const platformId = document.getElementById('platform-id').value.trim();
            const platformName = document.getElementById('platform-name').value.trim();
            
            if (!platformId || !platformName) {
                showToast(__('enter_both_id_name'), 'error');
                return;
            }
            
            await platformManager.add(configId, platformId, platformName);
        });
        
        function editPlatform(id, name) {
            document.getElementById('edit-platform-id').value = id;
            document.getElementById('edit-platform-name').value = name;
            openModal('edit-modal');
        }
        
        async function savePlatformEdit() {
            const id = document.getElementById('edit-platform-id').value;
            const name = document.getElementById('edit-platform-name').value.trim();
            
            if (!name) {
                showToast(__('display_name_required'), 'error');
                return;
            }
            
            try {
                await apiRequest('api/platforms.php', 'PUT', {
                    id: id,
                    platform_name: name
                });
                showToast(__('platform_updated'), 'success');
                closeModal('edit-modal');
                location.reload();
            } catch (error) {
                showToast(`${__('failed_to_update')}: ${error.message}`, 'error');
            }
        }
    </script>
</body>
</html>
