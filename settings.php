<?php
/**
 * TrendRadarConsole - Settings Page
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
        header('Location: setup-github.php');
        exit;
    }
    
    if (!$activeConfig) {
        setFlash('warning', __('please_create_config'));
        header('Location: index.php');
        exit;
    }
    
    $settings = $config->getSettings($activeConfig['id']);
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'settings';

// Get saved GitHub settings from user profile
$githubOwner = $githubSettings['github_owner'] ?? '';
$githubRepo = $githubSettings['github_repo'] ?? '';
$githubToken = $githubSettings['github_token'] ?? '';

// Default settings
$defaults = [
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

// Merge with defaults
$settings = array_merge($defaults, $settings);
$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - <?php _e('settings'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2><?php _e('configuration_settings'); ?></h2>
                <p><?php _e('settings_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <form id="settings-form">
                <!-- Report Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>📊 <?php _e('report_settings'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('report_mode'); ?></label>
                                    <select name="report_mode" class="form-control">
                                        <option value="daily" <?php echo $settings['report_mode'] === 'daily' ? 'selected' : ''; ?>>
                                            <?php _e('daily_summary'); ?> (当日汇总)
                                        </option>
                                        <option value="current" <?php echo $settings['report_mode'] === 'current' ? 'selected' : ''; ?>>
                                            <?php _e('current_list'); ?> (当前榜单)
                                        </option>
                                        <option value="incremental" <?php echo $settings['report_mode'] === 'incremental' ? 'selected' : ''; ?>>
                                            <?php _e('incremental'); ?> (增量监控)
                                        </option>
                                    </select>
                                    <div class="form-text">
                                        <strong><?php _e('daily_summary'); ?>:</strong> <?php _e('daily_desc'); ?><br>
                                        <strong><?php _e('current_list'); ?>:</strong> <?php _e('current_desc'); ?><br>
                                        <strong><?php _e('incremental'); ?>:</strong> <?php _e('incremental_desc'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('rank_threshold'); ?></label>
                                    <input type="number" name="rank_threshold" class="form-control" 
                                           value="<?php echo sanitize($settings['rank_threshold']); ?>" min="1" max="50">
                                    <div class="form-text"><?php _e('rank_threshold_desc'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('sort_priority'); ?></label>
                                    <select name="sort_by_position_first" class="form-control">
                                        <option value="false" <?php echo $settings['sort_by_position_first'] === 'false' ? 'selected' : ''; ?>>
                                            <?php _e('by_news_count'); ?> (热点条数优先)
                                        </option>
                                        <option value="true" <?php echo $settings['sort_by_position_first'] === 'true' ? 'selected' : ''; ?>>
                                            <?php _e('by_config_position'); ?> (配置顺序优先)
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('max_news_per_keyword'); ?></label>
                                    <input type="number" name="max_news_per_keyword" class="form-control" 
                                           value="<?php echo sanitize($settings['max_news_per_keyword']); ?>" min="0">
                                    <div class="form-text"><?php _e('no_limit'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weight Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>⚖️ <?php _e('weight_settings'); ?></h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            <?php _e('weight_desc'); ?>
                        </p>
                        <div class="row">
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('rank_weight'); ?></label>
                                    <input type="number" name="rank_weight" class="form-control weight-input" 
                                           value="<?php echo sanitize($settings['rank_weight']); ?>" 
                                           min="0" max="1" step="0.1">
                                    <div class="form-text"><?php _e('rank_weight_desc'); ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('frequency_weight'); ?></label>
                                    <input type="number" name="frequency_weight" class="form-control weight-input" 
                                           value="<?php echo sanitize($settings['frequency_weight']); ?>" 
                                           min="0" max="1" step="0.1">
                                    <div class="form-text"><?php _e('frequency_weight_desc'); ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('hotness_weight'); ?></label>
                                    <input type="number" name="hotness_weight" class="form-control weight-input" 
                                           value="<?php echo sanitize($settings['hotness_weight']); ?>" 
                                           min="0" max="1" step="0.1">
                                    <div class="form-text"><?php _e('hotness_weight_desc'); ?></div>
                                </div>
                            </div>
                        </div>
                        <div id="weight-warning" class="alert alert-warning" style="display: none;">
                            <?php _e('weight_sum_warning'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- System Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>🔧 <?php _e('system_settings'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('enable_crawler'); ?></label>
                                    <select name="enable_crawler" class="form-control">
                                        <option value="true" <?php echo $settings['enable_crawler'] === 'true' ? 'selected' : ''; ?>><?php _e('yes'); ?></option>
                                        <option value="false" <?php echo $settings['enable_crawler'] === 'false' ? 'selected' : ''; ?>><?php _e('no'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('enable_notifications'); ?></label>
                                    <select name="enable_notification" class="form-control">
                                        <option value="true" <?php echo $settings['enable_notification'] === 'true' ? 'selected' : ''; ?>><?php _e('yes'); ?></option>
                                        <option value="false" <?php echo $settings['enable_notification'] === 'false' ? 'selected' : ''; ?>><?php _e('no'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Push Window Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>🕐 <?php _e('push_time_window'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label"><?php _e('enable_push_window'); ?></label>
                            <select name="push_window_enabled" class="form-control" id="push-window-toggle">
                                <option value="false" <?php echo $settings['push_window_enabled'] === 'false' ? 'selected' : ''; ?>><?php _e('disabled'); ?></option>
                                <option value="true" <?php echo $settings['push_window_enabled'] === 'true' ? 'selected' : ''; ?>><?php _e('enabled'); ?></option>
                            </select>
                            <div class="form-text"><?php _e('push_window_desc'); ?></div>
                        </div>
                        
                        <div id="push-window-options" style="<?php echo $settings['push_window_enabled'] === 'true' ? '' : 'display:none;'; ?>">
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label"><?php _e('start_time'); ?></label>
                                        <input type="time" name="push_window_start" class="form-control" 
                                               value="<?php echo sanitize($settings['push_window_start']); ?>">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label"><?php _e('end_time'); ?></label>
                                        <input type="time" name="push_window_end" class="form-control" 
                                               value="<?php echo sanitize($settings['push_window_end']); ?>">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label"><?php _e('push_frequency'); ?></label>
                                        <select name="push_window_once_per_day" class="form-control">
                                            <option value="true" <?php echo $settings['push_window_once_per_day'] === 'true' ? 'selected' : ''; ?>>
                                                <?php _e('once_per_day'); ?>
                                            </option>
                                            <option value="false" <?php echo $settings['push_window_once_per_day'] === 'false' ? 'selected' : ''; ?>>
                                                <?php _e('every_execution'); ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="card">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary btn-lg"><?php _e('save_all_settings'); ?></button>
                    </div>
                </div>
            </form>
            
            <!-- GitHub Connection Settings -->
            <div class="card">
                <div class="card-header">
                    <h3>🐙 <?php _e('github_connection'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>📋 <?php _e('how_to_get_pat'); ?></strong>
                        <ol class="mt-2 mb-0">
                            <li><?php _e('pat_step1'); ?> <a href="https://github.com/settings/tokens?type=beta" target="_blank"><?php _e('pat_step1_link'); ?></a></li>
                            <li><?php _e('pat_step2'); ?></li>
                            <li><?php _e('pat_step3'); ?></li>
                            <li><?php _e('pat_step4'); ?></li>
                            <li><?php _e('pat_step5'); ?></li>
                            <li><?php _e('pat_step6'); ?></li>
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
                            <button type="button" class="btn btn-secondary" data-action="test-connection" onclick="testConnection()"><?php _e('test_connection'); ?></button>
                            <button type="button" class="btn btn-success" data-action="save-to-github" onclick="saveToGitHub()"><?php _e('save_to_github'); ?></button>
                            <button type="button" class="btn btn-warning" data-action="test-crawling" onclick="testCrawling()"><?php _e('test_crawling'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/shared.js"></script>
    <script>
        // Push window toggle
        document.getElementById('push-window-toggle').addEventListener('change', function() {
            const options = document.getElementById('push-window-options');
            options.style.display = this.value === 'true' ? 'block' : 'none';
        });
        
        // Weight validation
        document.querySelectorAll('.weight-input').forEach(input => {
            input.addEventListener('change', validateWeights);
        });
        
        function validateWeights() {
            const weights = document.querySelectorAll('.weight-input');
            let sum = 0;
            weights.forEach(w => sum += parseFloat(w.value) || 0);
            
            const warning = document.getElementById('weight-warning');
            if (Math.abs(sum - 1.0) > 0.01) {
                warning.style.display = 'block';
                warning.textContent = __('weight_sum_message').replace(':sum', sum.toFixed(2)).replace(':expected', '1.0');
            } else {
                warning.style.display = 'none';
            }
        }
        
        // Form submission
        document.getElementById('settings-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const configId = document.getElementById('config-id').value;
            const formData = new FormData(this);
            const settings = {};
            
            for (const [key, value] of formData.entries()) {
                settings[key] = value;
            }
            
            try {
                await apiRequest('api/settings.php', 'POST', {
                    config_id: configId,
                    settings: settings
                });
                showToast(__('settings_saved'), 'success');
            } catch (error) {
                showToast(__('failed_to_save') + ': ' + error.message, 'error');
            }
        });
        
        // GitHub settings form submission
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
        
        // Test GitHub connection
        async function testConnection() {
            const testBtn = document.querySelector('button[data-action="test-connection"]');
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
        
        // Save to GitHub
        async function saveToGitHub() {
            const saveBtn = document.querySelector('button[data-action="save-to-github"]');
            const configId = document.getElementById('config-id').value;
            
            if (!confirm(__('confirm_save_to_github'))) {
                return;
            }
            
            setButtonLoading(saveBtn, true);
            try {
                await apiRequest('api/github.php', 'POST', {
                    action: 'save',
                    owner: '',  // Will use saved settings
                    repo: '',
                    token: '',
                    config_id: configId
                });
                
                showToast(__('config_saved_to_github'), 'success');
            } catch (error) {
                if (error.message && error.message.includes('Owner, repo, and token are required')) {
                    showToast(__('configure_github_first'), 'error');
                } else {
                    showToast(__('failed_to_save') + ': ' + error.message, 'error');
                }
            } finally {
                setButtonLoading(saveBtn, false);
            }
        }
        
        // Test Crawling - helper functions are loaded from assets/js/shared.js
        async function testCrawling() {
            const btn = document.querySelector('button[data-action="test-crawling"]');
            if (!confirm(__('confirm_test_crawling'))) {
                return;
            }
            
            setButtonLoadingWithStatus(btn, true);
            setButtonStatusText(btn, __('crawling_triggered'));
            
            try {
                // Get last successful run duration for progress estimation
                const runsResult = await apiRequest('api/github.php', 'POST', {
                    action: 'get_workflow_runs',
                    workflow_id: 'crawler.yml'
                });
                
                let estimatedDuration = DEFAULT_ESTIMATED_DURATION_MS;
                const runs = runsResult.data?.runs || [];
                for (const run of runs) {
                    if (run.conclusion === 'success' && run.run_started_at && run.updated_at) {
                        const startTime = new Date(run.run_started_at).getTime();
                        const endTime = new Date(run.updated_at).getTime();
                        estimatedDuration = endTime - startTime;
                        break;
                    }
                }
                
                await apiRequest('api/github.php', 'POST', {
                    action: 'dispatch_workflow',
                    workflow_id: 'crawler.yml'
                });
                
                // Start tracking workflow status - store timing data on button
                btn.dataset.startTime = Date.now().toString();
                btn.dataset.estimatedDuration = estimatedDuration.toString();
                setTimeout(() => trackWorkflowStatus(btn, 0), 3000);
            } catch (error) {
                if (error.message && error.message.includes('Owner, repo, and token are required')) {
                    showToast(__('configure_github_first'), 'error');
                } else {
                    showToast(__('crawling_trigger_failed') + error.message, 'error');
                }
                setButtonLoadingWithStatus(btn, false);
            }
        }
    </script>
</body>
</html>
