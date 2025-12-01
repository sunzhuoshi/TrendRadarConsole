<?php
/**
 * TrendRadarConsole - GitHub Settings Page
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
    
    if (!$activeConfig) {
        setFlash('warning', __('please_create_config'));
        header('Location: index.php');
        exit;
    }
    
    // Get GitHub settings from user profile
    $auth = new Auth();
    $githubSettings = $auth->getGitHubSettings($userId);
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'github';
$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();

// Get saved GitHub settings from user profile
$githubOwner = $githubSettings['github_owner'] ?? '';
$githubRepo = $githubSettings['github_repo'] ?? '';
$githubToken = $githubSettings['github_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>TrendRadarConsole - <?php _e('github_sync'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2><?php _e('github_sync_title'); ?></h2>
                <p><?php _e('github_sync_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <!-- GitHub Connection Settings -->
            <div class="card">
                <div class="card-header">
                    <h3>🔑 <?php _e('github_connection'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>📋 <?php _e('how_to_get_pat'); ?></strong>
                        <ol class="mt-2 mb-0">
                            <li><?php _e('pat_step1'); ?> <a href="https://github.com/settings/tokens?type=beta" target="_blank"><?php _e('pat_step1_link'); ?></a></li>
                            <li><?php _e('pat_step2'); ?></li>
                            <li><?php _e('pat_step3'); ?></li>
                            <li><?php echo __('pat_step4'); ?></li>
                            <li><?php _e('pat_step5'); ?></li>
                        </ol>
                    </div>
                    
                    <form id="github-settings-form" class="mt-4">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('repo_owner'); ?> <span class="text-danger">*</span></label>
                                    <input type="text" name="github_owner" class="form-control" 
                                           value="<?php echo sanitize($githubOwner); ?>"
                                           placeholder="<?php _e('repo_owner_placeholder'); ?>">
                                    <div class="form-text"><?php _e('repo_owner_desc'); ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('repo_name'); ?> <span class="text-danger">*</span></label>
                                    <input type="text" name="github_repo" class="form-control" 
                                           value="<?php echo sanitize($githubRepo); ?>"
                                           placeholder="<?php _e('repo_name_placeholder'); ?>">
                                    <div class="form-text"><?php _e('repo_name_desc'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><?php _e('personal_access_token'); ?> <span class="text-danger">*</span></label>
                            <input type="password" name="github_token" class="form-control" 
                                   value="<?php echo sanitize($githubToken); ?>"
                                   placeholder="<?php _e('pat_placeholder'); ?>">
                            <div class="form-text"><?php _e('pat_desc'); ?></div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary"><?php _e('save_settings'); ?></button>
                            <button type="button" class="btn btn-secondary" onclick="testConnection()"><?php _e('test_connection'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sync Operations -->
            <div class="card">
                <div class="card-header">
                    <h3>🔄 <?php _e('sync_operations'); ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        <?php _e('sync_operations_desc'); ?>
                    </p>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-card">
                                <h4>📥 <?php _e('load_from_github'); ?></h4>
                                <p class="text-muted">
                                    <?php _e('load_from_github_desc'); ?>
                                </p>
                                <button class="btn btn-primary mt-2" onclick="loadFromGitHub()">
                                    <?php _e('load_from_github'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <h4>📤 <?php _e('save_to_github'); ?></h4>
                                <p class="text-muted">
                                    <?php _e('save_to_github_desc'); ?>
                                </p>
                                <button class="btn btn-success mt-2" onclick="saveToGitHub()">
                                    <?php _e('save_to_github'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview Section -->
            <div class="card" id="preview-card" style="display: none;">
                <div class="card-header">
                    <h3>📋 <?php _e('loaded_config_preview'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="tabs" data-tab-group="preview">
                        <ul class="tab-list">
                            <li class="tab-item active" data-tab="config-yaml" data-tab-group="preview">config.yaml</li>
                            <li class="tab-item" data-tab="frequency-words" data-tab-group="preview">frequency_words.txt</li>
                        </ul>
                    </div>
                    <div class="tab-content active" data-tab="config-yaml" data-tab-group="preview">
                        <pre id="config-yaml-preview" style="max-height: 400px;"></pre>
                    </div>
                    <div class="tab-content" data-tab="frequency-words" data-tab-group="preview">
                        <pre id="frequency-words-preview" style="max-height: 400px;"></pre>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        // Save GitHub settings to user profile
        document.getElementById('github-settings-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const owner = document.querySelector('input[name="github_owner"]').value;
            const repo = document.querySelector('input[name="github_repo"]').value;
            const token = document.querySelector('input[name="github_token"]').value;
            
            setButtonLoading(submitBtn, true);
            try {
                await apiRequest('api/github.php', 'POST', {
                    action: 'save_settings',
                    owner: owner,
                    repo: repo,
                    token: token
                });
                showToast(__('github_settings_saved'), 'success');
            } catch (error) {
                showToast(__('failed_to_save') + ': ' + error.message, 'error');
            } finally {
                setButtonLoading(submitBtn, false);
            }
        });
        
        // Test connection
        async function testConnection() {
            const testBtn = document.querySelector('button[onclick="testConnection()"]');
            const owner = document.querySelector('input[name="github_owner"]').value;
            const repo = document.querySelector('input[name="github_repo"]').value;
            const token = document.querySelector('input[name="github_token"]').value;
            
            if (!owner || !repo || !token) {
                showToast(__('fill_all_fields'), 'error');
                return;
            }
            
            setButtonLoading(testBtn, true);
            try {
                const result = await apiRequest('api/github.php', 'POST', {
                    action: 'test',
                    owner: owner,
                    repo: repo,
                    token: token
                });
                showToast(__('connection_successful') + result.data.full_name, 'success');
            } catch (error) {
                showToast(__('connection_failed') + error.message, 'error');
            } finally {
                setButtonLoading(testBtn, false);
            }
        }
        
        // Load from GitHub
        async function loadFromGitHub() {
            const loadBtn = document.querySelector('button[onclick="loadFromGitHub()"]');
            const owner = document.querySelector('input[name="github_owner"]').value;
            const repo = document.querySelector('input[name="github_repo"]').value;
            const token = document.querySelector('input[name="github_token"]').value;
            
            if (!owner || !repo || !token) {
                showToast(__('configure_github_first'), 'error');
                return;
            }
            
            setButtonLoading(loadBtn, true);
            try {
                const result = await apiRequest('api/github.php', 'POST', {
                    action: 'load',
                    owner: owner,
                    repo: repo,
                    token: token
                });
                
                // Show preview
                document.getElementById('config-yaml-preview').textContent = result.data.config_yaml || __('not_found');
                document.getElementById('frequency-words-preview').textContent = result.data.frequency_words || __('not_found');
                document.getElementById('preview-card').style.display = 'block';
                
                showToast(__('config_loaded_from_github'), 'success');
            } catch (error) {
                showToast(__('failed_to_load') + ': ' + error.message, 'error');
            } finally {
                setButtonLoading(loadBtn, false);
            }
        }
        
        // Save to GitHub
        async function saveToGitHub() {
            const saveBtn = document.querySelector('button[onclick="saveToGitHub()"]');
            const owner = document.querySelector('input[name="github_owner"]').value;
            const repo = document.querySelector('input[name="github_repo"]').value;
            const token = document.querySelector('input[name="github_token"]').value;
            const configId = document.getElementById('config-id').value;
            
            if (!owner || !repo || !token) {
                showToast(__('configure_github_first'), 'error');
                return;
            }
            
            if (!confirm(__('confirm_save_to_github'))) {
                return;
            }
            
            setButtonLoading(saveBtn, true);
            try {
                await apiRequest('api/github.php', 'POST', {
                    action: 'save',
                    owner: owner,
                    repo: repo,
                    token: token,
                    config_id: configId
                });
                
                showToast(__('config_saved_to_github'), 'success');
            } catch (error) {
                showToast(__('failed_to_save') + ': ' + error.message, 'error');
            } finally {
                setButtonLoading(saveBtn, false);
            }
        }
    </script>
</body>
</html>
