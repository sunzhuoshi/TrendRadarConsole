<?php
/**
 * TrendRadarConsole - GitHub Deployment Page
 * 
 * This page manages GitHub deployment settings:
 * - If PAT is not set, shows wizard to fork repo and configure
 * - If PAT is set, shows deployment management options
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

// Get GitHub settings
$auth = new Auth();
$githubSettings = $auth->getGitHubSettings($userId);

// Check if GitHub is fully configured (PAT is set)
$githubConfigured = !empty($githubSettings['github_owner']) && 
                    !empty($githubSettings['github_repo']) && 
                    !empty($githubSettings['github_token']);

// Get current step for wizard (default to step 1)
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 3) {
    $step = 1;
}

// Get configuration for deployment operations
try {
    $config = new Configuration($userId);
    $activeConfig = $config->getActive();
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'github-deployment';
$currentLang = getCurrentLanguage();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>TrendRadarConsole - <?php _e('github_deployment'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .wizard-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        .wizard-step {
            display: flex;
            align-items: center;
            margin: 0 10px;
        }
        .step-number {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 8px;
        }
        .step-number.active {
            background: #667eea;
            color: #fff;
        }
        .step-number.completed {
            background: #28a745;
            color: #fff;
        }
        .step-label {
            font-size: 14px;
            color: #666;
        }
        .step-connector {
            width: 40px;
            height: 2px;
            background: #e0e0e0;
        }
        .step-connector.completed {
            background: #28a745;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }
        .info-box ol {
            margin: 0;
            padding-left: 20px;
            line-height: 2;
        }
        .info-box a {
            color: #667eea;
            text-decoration: none;
        }
        .info-box a:hover {
            text-decoration: underline;
        }
        .btn-group-wizard {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .highlight-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 8px 8px 0;
        }
        .deployment-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #d4edda;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .deployment-status.warning {
            background: #fff3cd;
        }
        .deployment-status .status-icon {
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2>🐙 <?php _e('github_deployment'); ?></h2>
                <p><?php _e('github_deployment_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>
            
            <?php if (!$githubConfigured): ?>
            <!-- GitHub Setup Wizard -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('github_setup_wizard'); ?></h3>
                </div>
                <div class="card-body">
                    <!-- Wizard Steps -->
                    <div class="wizard-steps">
                        <div class="wizard-step">
                            <div class="step-number <?php echo getStepClass($step, 1); ?>">
                                <?php echo $step > 1 ? '✓' : '1'; ?>
                            </div>
                            <span class="step-label"><?php _e('clone_repo'); ?></span>
                        </div>
                        <div class="step-connector <?php echo $step > 1 ? 'completed' : ''; ?>"></div>
                        <div class="wizard-step">
                            <div class="step-number <?php echo getStepClass($step, 2); ?>">
                                <?php echo $step > 2 ? '✓' : '2'; ?>
                            </div>
                            <span class="step-label"><?php _e('repo_settings'); ?></span>
                        </div>
                        <div class="step-connector <?php echo $step > 2 ? 'completed' : ''; ?>"></div>
                        <div class="wizard-step">
                            <div class="step-number <?php echo getStepClass($step, 3); ?>">3</div>
                            <span class="step-label"><?php _e('setup_pat'); ?></span>
                        </div>
                    </div>
                    
                    <!-- Step Content -->
                    <div class="step-content">
                        <?php if ($step === 1): ?>
                        <!-- Step 1: Fork Repository Guide -->
                        <div class="info-box">
                            <h3>📦 <?php _e('step1_title'); ?></h3>
                            <p><?php _e('step1_desc'); ?></p>
                            
                            <div class="highlight-box" style="text-align: center; padding: 25px;">
                                <p style="margin-bottom: 15px;"><?php _e('click_to_fork'); ?></p>
                                <a href="https://github.com/sunzhuoshi/TrendRadar/fork" target="_blank" class="btn btn-primary btn-lg">
                                    🍴 <?php _e('fork_now'); ?>
                                </a>
                            </div>
                            
                            <p style="margin-top: 20px; color: #666;"><?php _e('fork_note'); ?></p>
                        </div>
                        
                        <div class="btn-group-wizard">
                            <div></div>
                            <a href="github-deployment.php?step=2" class="btn btn-primary"><?php _e('next_step'); ?> →</a>
                        </div>
                        
                        <?php elseif ($step === 2): ?>
                        <!-- Step 2: Repository Settings -->
                        <div class="info-box">
                            <h3>⚙️ <?php _e('step2_title'); ?></h3>
                            <p><?php _e('step2_desc'); ?></p>
                        </div>
                        
                        <form id="repo-settings-form">
                            <div class="form-group">
                                <label class="form-label"><?php _e('repo_owner'); ?> <span class="text-danger">*</span></label>
                                <input type="text" name="github_owner" class="form-control" 
                                       value="<?php echo sanitize($githubSettings['github_owner'] ?? ''); ?>"
                                       placeholder="<?php _e('repo_owner_placeholder'); ?>" required>
                                <div class="form-text"><?php _e('repo_owner_desc'); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><?php _e('repo_name'); ?> <span class="text-danger">*</span></label>
                                <input type="text" name="github_repo" class="form-control" 
                                       value="<?php echo sanitize($githubSettings['github_repo'] ?? 'TrendRadar'); ?>"
                                       placeholder="TrendRadar" required>
                                <div class="form-text"><?php _e('repo_name_desc'); ?></div>
                            </div>
                            
                            <div class="btn-group-wizard">
                                <a href="github-deployment.php?step=1" class="btn btn-secondary">← <?php _e('prev_step'); ?></a>
                                <button type="submit" class="btn btn-primary"><?php _e('next_step'); ?> →</button>
                            </div>
                        </form>
                        
                        <?php elseif ($step === 3): ?>
                        <!-- Step 3: GitHub PAT Setup -->
                        <div class="info-box">
                            <h3>🔑 <?php _e('step3_title'); ?></h3>
                            <p><?php _e('step3_desc'); ?></p>
                        </div>
                        
                        <div class="highlight-box">
                            <strong>📋 <?php _e('how_to_get_pat'); ?></strong>
                            <ol style="margin-top: 10px; margin-bottom: 0;">
                                <li><?php _e('pat_step1'); ?> <a href="https://github.com/settings/tokens?type=beta" target="_blank"><?php _e('pat_step1_link'); ?></a></li>
                                <li><?php _e('pat_step2'); ?></li>
                                <li><?php _e('pat_step3'); ?></li>
                                <li><?php _e('pat_step4'); ?></li>
                                <li><?php _e('pat_step5'); ?></li>
                                <li><?php _e('pat_step6'); ?></li>
                            </ol>
                        </div>
                        
                        <form id="pat-settings-form" class="mt-4">
                            <div class="form-group">
                                <label class="form-label"><?php _e('personal_access_token'); ?> <span class="text-danger">*</span></label>
                                <input type="password" name="github_token" class="form-control" 
                                       value="<?php echo sanitize($githubSettings['github_token'] ?? ''); ?>"
                                       placeholder="<?php _e('pat_placeholder'); ?>" required>
                                <div class="form-text"><?php _e('pat_desc'); ?></div>
                            </div>
                            
                            <div class="btn-group-wizard">
                                <a href="github-deployment.php?step=2" class="btn btn-secondary">← <?php _e('prev_step'); ?></a>
                                <button type="submit" class="btn btn-primary"><?php _e('complete_setup'); ?></button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- GitHub Configured - Show Deployment Options -->
            
            <!-- Connection Status -->
            <div class="deployment-status">
                <span class="status-icon">✅</span>
                <div>
                    <strong><?php _e('github_connected'); ?></strong>
                    <div><?php echo sanitize($githubSettings['github_owner'] . '/' . $githubSettings['github_repo']); ?></div>
                </div>
            </div>
            
            <!-- GitHub Connection Settings -->
            <div class="card">
                <div class="card-header">
                    <h3>⚙️ <?php _e('github_connection'); ?></h3>
                </div>
                <div class="card-body">
                    <form id="github-settings-form">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('repo_owner'); ?> <span class="text-danger">*</span></label>
                                    <input type="text" name="github_owner" class="form-control" 
                                           value="<?php echo sanitize($githubSettings['github_owner'] ?? ''); ?>"
                                           placeholder="<?php _e('repo_owner_placeholder'); ?>">
                                    <div class="form-text"><?php _e('repo_owner_desc'); ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label"><?php _e('repo_name'); ?> <span class="text-danger">*</span></label>
                                    <input type="text" name="github_repo" class="form-control" 
                                           value="<?php echo sanitize($githubSettings['github_repo'] ?? ''); ?>"
                                           placeholder="<?php _e('repo_name_placeholder'); ?>">
                                    <div class="form-text"><?php _e('repo_name_desc'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><?php _e('personal_access_token'); ?> <span class="text-danger">*</span></label>
                            <input type="password" name="github_token" class="form-control" 
                                   value="<?php echo sanitize($githubSettings['github_token'] ?? ''); ?>"
                                   placeholder="<?php _e('pat_placeholder'); ?>">
                            <div class="form-text"><?php _e('pat_desc'); ?></div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary"><?php _e('save_settings'); ?></button>
                            <button type="button" class="btn btn-secondary" data-action="test-connection" onclick="testConnection()"><?php _e('test_connection'); ?></button>
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
                    <p class="text-muted mb-3"><?php _e('sync_operations_desc'); ?></p>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="info-box">
                                <h4>⬇️ <?php _e('load_from_github'); ?></h4>
                                <p class="text-muted"><?php _e('load_from_github_desc'); ?></p>
                                <button type="button" class="btn btn-primary" data-action="load-github" onclick="loadFromGitHub()"><?php _e('load_from_github'); ?></button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-box">
                                <h4>⬆️ <?php _e('save_to_github'); ?></h4>
                                <p class="text-muted"><?php _e('save_to_github_desc'); ?></p>
                                <button type="button" class="btn btn-success" data-action="save-github" onclick="saveToGitHub()"><?php _e('save_to_github'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Crawler Workflow -->
            <div class="card">
                <div class="card-header">
                    <h3>🕷️ <?php _e('test_crawling'); ?></h3>
                </div>
                <div class="card-body">
                    <!-- Workflow Status Toggle -->
                    <div class="mb-4">
                        <label class="form-label"><strong><?php _e('workflow_status'); ?>:</strong></label>
                        <div>
                            <button type="button" id="workflow-toggle-btn" class="btn btn-secondary" onclick="toggleWorkflow()">
                                <?php _e('workflow_status_loading'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Test Crawling Button -->
                    <p class="text-muted mb-3"><?php _e('test_crawling_desc'); ?></p>
                    <button type="button" class="btn btn-warning" data-action="test-crawling" onclick="testCrawling()"><?php _e('test_crawling'); ?></button>
                </div>
            </div>
            
            <!-- PAT Instructions -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 <?php _e('how_to_get_pat'); ?></h3>
                </div>
                <div class="card-body">
                    <ol style="line-height: 2;">
                        <li><?php _e('pat_step1'); ?> <a href="https://github.com/settings/tokens?type=beta" target="_blank"><?php _e('pat_step1_link'); ?></a></li>
                        <li><?php _e('pat_step2'); ?></li>
                        <li><?php _e('pat_step3'); ?></li>
                        <li><?php _e('pat_step4'); ?></li>
                        <li><?php _e('pat_step5'); ?></li>
                        <li><?php _e('pat_step6'); ?></li>
                    </ol>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/shared.js"></script>
    <script>
        <?php if (!$githubConfigured && $step === 2): ?>
        document.getElementById('repo-settings-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const owner = document.querySelector('input[name="github_owner"]').value.trim();
            const repo = document.querySelector('input[name="github_repo"]').value.trim();
            
            if (!owner || !repo) {
                showToast(__('fill_all_fields'), 'error');
                return;
            }
            
            setButtonLoading(submitBtn, true);
            try {
                await apiRequest('api/github.php', 'POST', {
                    action: 'save_settings',
                    owner: owner,
                    repo: repo
                });
                // Proceed to next step
                window.location.href = 'github-deployment.php?step=3';
            } catch (error) {
                showToast(__('failed_to_save') + ': ' + error.message, 'error');
                setButtonLoading(submitBtn, false);
            }
        });
        <?php endif; ?>
        
        <?php if (!$githubConfigured && $step === 3): ?>
        document.getElementById('pat-settings-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const token = document.querySelector('input[name="github_token"]').value.trim();
            
            if (!token) {
                showToast(__('fill_all_fields'), 'error');
                return;
            }
            
            setButtonLoading(submitBtn, true);
            try {
                // Save token
                await apiRequest('api/github.php', 'POST', {
                    action: 'save_settings',
                    owner: <?php echo jsonEncodeForJs($githubSettings['github_owner'] ?? ''); ?>,
                    repo: <?php echo jsonEncodeForJs($githubSettings['github_repo'] ?? 'TrendRadar'); ?>,
                    token: token
                });
                
                // Test connection
                const testResult = await apiRequest('api/github.php', 'POST', {
                    action: 'test',
                    owner: <?php echo jsonEncodeForJs($githubSettings['github_owner'] ?? ''); ?>,
                    repo: <?php echo jsonEncodeForJs($githubSettings['github_repo'] ?? 'TrendRadar'); ?>,
                    token: token
                });
                
                showToast(__('connection_successful') + testResult.data.full_name, 'success');
                
                // Load configuration from GitHub repo vars or use default
                try {
                    const loadResult = await apiRequest('api/github.php', 'POST', {
                        action: 'load_or_create_default',
                        owner: <?php echo jsonEncodeForJs($githubSettings['github_owner'] ?? ''); ?>,
                        repo: <?php echo jsonEncodeForJs($githubSettings['github_repo'] ?? 'TrendRadar'); ?>,
                        token: token
                    });
                    
                    if (loadResult.data && loadResult.data.loaded_from_github) {
                        showToast(__('config_loaded_from_github'), 'success');
                    } else {
                        showToast(__('using_default_config'), 'info');
                    }
                } catch (loadError) {
                    // If loading fails, continue with default config
                    showToast(__('using_default_config'), 'info');
                }
                
                // Reload page to show deployment options
                setTimeout(function() {
                    window.location.href = 'github-deployment.php';
                }, 1500);
            } catch (error) {
                showToast(__('connection_failed') + error.message, 'error');
                setButtonLoading(submitBtn, false);
            }
        });
        <?php endif; ?>
        
        <?php if ($githubConfigured): ?>
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
        
        // Load from GitHub
        async function loadFromGitHub() {
            if (!confirm(__('confirm_load_from_github'))) {
                return;
            }
            
            const btn = document.querySelector('button[data-action="load-github"]');
            setButtonLoading(btn, true);
            try {
                const result = await apiRequest('api/github.php', 'POST', {
                    action: 'load_or_create_default',
                    owner: '',  // Will use saved settings
                    repo: '',
                    token: ''
                });
                
                showToast(__('config_loaded_from_github'), 'success');
                // Reload page to show updated configuration
                setTimeout(() => window.location.reload(), 1000);
            } catch (error) {
                showToast(__('failed_to_load') + ': ' + error.message, 'error');
            } finally {
                setButtonLoading(btn, false);
            }
        }
        
        // Save to GitHub
        async function saveToGitHub() {
            if (!confirm(__('confirm_save_to_github'))) {
                return;
            }
            
            const btn = document.querySelector('button[data-action="save-github"]');
            setButtonLoading(btn, true);
            try {
                await apiRequest('api/github.php', 'POST', {
                    action: 'save',
                    owner: '',  // Will use saved settings
                    repo: '',
                    token: '',
                    config_id: <?php echo jsonEncodeForJs(isset($activeConfig['id']) ? $activeConfig['id'] : null); ?>
                });
                
                showToast(__('config_saved_to_github'), 'success');
            } catch (error) {
                showToast(__('failed_to_save') + ': ' + error.message, 'error');
            } finally {
                setButtonLoading(btn, false);
            }
        }
        
        // Test Crawling
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
                
                // Store dispatch time BEFORE calling dispatch
                const dispatchTime = Date.now();
                
                await apiRequest('api/github.php', 'POST', {
                    action: 'dispatch_workflow',
                    workflow_id: 'crawler.yml'
                });
                
                // Start tracking workflow status - store timing data on button
                btn.dataset.dispatchTime = dispatchTime.toString();
                btn.dataset.startTime = dispatchTime.toString();
                btn.dataset.estimatedDuration = estimatedDuration.toString();
                setTimeout(() => trackWorkflowStatus(btn, 0), 3000);
            } catch (error) {
                showToast(__('crawling_trigger_failed') + error.message, 'error');
                setButtonLoadingWithStatus(btn, false);
            }
        }
        
        // Workflow status management
        let currentWorkflowState = null;
        
        async function refreshWorkflowStatus() {
            const toggleBtn = document.getElementById('workflow-toggle-btn');
            
            toggleBtn.textContent = __('workflow_status_loading');
            toggleBtn.className = 'btn btn-secondary';
            toggleBtn.disabled = true;
            
            try {
                const result = await apiRequest('api/github.php', 'POST', {
                    action: 'get_workflow',
                    workflow_id: 'crawler.yml'
                });
                
                const workflow = result.data?.workflow;
                if (workflow) {
                    currentWorkflowState = workflow.state;
                    toggleBtn.disabled = false;
                    
                    if (workflow.state === 'active') {
                        toggleBtn.textContent = '✅ ' + __('workflow_enabled') + ' (' + __('workflow_disable') + ')';
                        toggleBtn.className = 'btn btn-success';
                    } else {
                        toggleBtn.textContent = '⏸️ ' + __('workflow_disabled') + ' (' + __('workflow_enable') + ')';
                        toggleBtn.className = 'btn btn-secondary';
                    }
                }
            } catch (error) {
                toggleBtn.textContent = '⚠️ Error';
                toggleBtn.className = 'btn btn-warning';
                toggleBtn.disabled = false;
                console.error('Failed to get workflow status:', error);
            }
        }
        
        async function toggleWorkflow() {
            const toggleBtn = document.getElementById('workflow-toggle-btn');
            
            // If state is unknown, just refresh
            if (currentWorkflowState === null) {
                refreshWorkflowStatus();
                return;
            }
            
            const isEnabled = currentWorkflowState === 'active';
            
            const confirmMsg = isEnabled ? __('workflow_disable_confirm') : __('workflow_enable_confirm');
            if (!confirm(confirmMsg)) {
                return;
            }
            
            setButtonLoading(toggleBtn, true);
            
            try {
                const action = isEnabled ? 'disable_workflow' : 'enable_workflow';
                await apiRequest('api/github.php', 'POST', {
                    action: action,
                    workflow_id: 'crawler.yml'
                });
                
                const successMsg = isEnabled ? __('workflow_disabled_success') : __('workflow_enabled_success');
                showToast(successMsg, 'success');
                
                // Refresh status after a short delay
                setTimeout(() => refreshWorkflowStatus(), 500);
            } catch (error) {
                const failMsg = isEnabled ? __('workflow_disable_failed') : __('workflow_enable_failed');
                showToast(failMsg + error.message, 'error');
                setButtonLoading(toggleBtn, false);
            }
        }
        
        // Load workflow status on page load
        document.addEventListener('DOMContentLoaded', function() {
            refreshWorkflowStatus();
        });
        
        <?php endif; ?>
    </script>
</body>
</html>
