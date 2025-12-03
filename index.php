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
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
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
                        <button type="button" class="btn btn-primary" onclick="loadFromGitHub()"><?php _e('load_from_github'); ?></button>
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
                                    <button type="button" class="btn btn-outline btn-sm" data-action="load-github-<?php echo $cfg['id']; ?>" onclick="loadFromGitHub(<?php echo $cfg['id']; ?>, this)" title="<?php _e('load_from_github'); ?>">📥 GitHub</button>
                                    <button type="button" class="btn btn-outline btn-sm" data-action="save-github-<?php echo $cfg['id']; ?>" onclick="saveToGitHub(<?php echo $cfg['id']; ?>, this)" title="<?php _e('save_to_github'); ?>">📤 GitHub</button>
                                    <button type="button" class="btn btn-outline btn-sm" data-action="test-crawling-<?php echo $cfg['id']; ?>" onclick="testCrawling(this)" title="<?php _e('test_crawling'); ?>">🕷️ <?php _e('test_crawling'); ?></button>
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
                        <li><?php _e('step6_sync_github'); ?></li>
                    </ol>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        // Load from GitHub
        async function loadFromGitHub(configId, btn) {
            if (!confirm(__('confirm_load_from_github'))) {
                return;
            }
            
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
                if (error.message && error.message.includes('Owner, repo, and token are required')) {
                    showToast(__('configure_github_first'), 'error');
                    setTimeout(() => window.location.href = 'settings.php', 1500);
                } else {
                    showToast(__('failed_to_load') + ': ' + error.message, 'error');
                }
            } finally {
                setButtonLoading(btn, false);
            }
        }
        
        // Save to GitHub
        async function saveToGitHub(configId, btn) {
            if (!confirm(__('confirm_save_to_github'))) {
                return;
            }
            
            setButtonLoading(btn, true);
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
                    setTimeout(() => window.location.href = 'settings.php', 1500);
                } else {
                    showToast(__('failed_to_save') + ': ' + error.message, 'error');
                }
            } finally {
                setButtonLoading(btn, false);
            }
        }
        
        // Test Crawling
        async function testCrawling(btn) {
            if (!confirm(__('confirm_test_crawling'))) {
                return;
            }
            
            setButtonLoadingWithStatus(btn, true);
            setButtonStatusText(btn, __('crawling_triggered'));
            startDotAnimation(btn, __('crawling_triggered').replace('...', ''));
            
            try {
                await apiRequest('api/github.php', 'POST', {
                    action: 'dispatch_workflow',
                    workflow_id: 'crawler.yml',
                    owner: '',  // Will use saved settings
                    repo: '',
                    token: ''
                });
                
                // Start tracking workflow status
                setTimeout(() => trackWorkflowStatus(btn, 0), 3000);
            } catch (error) {
                stopDotAnimation();
                if (error.message && error.message.includes('Owner, repo, and token are required')) {
                    showToast(__('configure_github_first'), 'error');
                    setTimeout(() => window.location.href = 'settings.php', 1500);
                } else {
                    showToast(__('crawling_trigger_failed') + error.message, 'error');
                }
                setButtonLoadingWithStatus(btn, false);
            }
        }
        
        // Button loading with visible status text
        function setButtonLoadingWithStatus(btn, isLoading) {
            if (!btn) return;
            
            if (isLoading) {
                if (!btn.querySelector('.btn-text')) {
                    const span = document.createElement('span');
                    span.className = 'btn-text';
                    while (btn.firstChild) {
                        span.appendChild(btn.firstChild);
                    }
                    btn.appendChild(span);
                }
                btn.classList.add('loading-with-status');
                btn.disabled = true;
            } else {
                btn.classList.remove('loading-with-status');
                btn.disabled = false;
                const span = btn.querySelector('.btn-text');
                if (span) {
                    while (span.firstChild) {
                        btn.insertBefore(span.firstChild, span);
                    }
                    span.remove();
                }
            }
        }
        
        // Set button status text
        function setButtonStatusText(btn, text) {
            if (!btn) return;
            const textSpan = btn.querySelector('.btn-text');
            if (textSpan) {
                textSpan.textContent = text;
            }
        }
        
        // Dot animation variables
        let dotAnimationInterval = null;
        let currentDotCount = 0;
        
        // Start dot animation (fast - every 300ms)
        function startDotAnimation(btn, baseText) {
            stopDotAnimation();
            currentDotCount = 0;
            dotAnimationInterval = setInterval(() => {
                currentDotCount = (currentDotCount + 1) % 4;
                const dots = '.'.repeat(currentDotCount);
                setButtonStatusText(btn, baseText + dots);
            }, 300);
        }
        
        // Stop dot animation
        function stopDotAnimation() {
            if (dotAnimationInterval) {
                clearInterval(dotAnimationInterval);
                dotAnimationInterval = null;
            }
        }
        
        // Track workflow status
        let currentStatusBaseText = '';
        async function trackWorkflowStatus(btn, attempts = 0) {
            const maxAttempts = 60; // Max 5 minutes (60 * 5 seconds)
            
            if (attempts >= maxAttempts) {
                stopDotAnimation();
                setButtonStatusText(btn, __('workflow_status_unknown'));
                setTimeout(() => setButtonLoadingWithStatus(btn, false), 1500);
                return;
            }
            
            try {
                const result = await apiRequest('api/github.php', 'POST', {
                    action: 'get_workflow_runs',
                    workflow_id: 'crawler.yml',
                    owner: '',
                    repo: '',
                    token: ''
                });
                
                const runs = result.data?.runs || [];
                if (runs.length > 0) {
                    const latestRun = runs[0];
                    const status = latestRun.status;
                    const conclusion = latestRun.conclusion;
                    
                    if (status === 'completed') {
                        stopDotAnimation();
                        if (conclusion === 'success') {
                            setButtonStatusText(btn, __('workflow_status_success'));
                        } else if (conclusion === 'failure') {
                            setButtonStatusText(btn, __('workflow_status_failure'));
                        } else if (conclusion === 'cancelled') {
                            setButtonStatusText(btn, __('workflow_status_cancelled'));
                        } else {
                            setButtonStatusText(btn, __('workflow_status_completed'));
                        }
                        setTimeout(() => setButtonLoadingWithStatus(btn, false), 2000);
                        return;
                    } else if (status === 'queued') {
                        const newBaseText = __('workflow_status_queued').replace('...', '');
                        if (currentStatusBaseText !== newBaseText) {
                            currentStatusBaseText = newBaseText;
                            startDotAnimation(btn, newBaseText);
                        }
                    } else if (status === 'in_progress') {
                        const newBaseText = __('workflow_status_in_progress').replace('...', '');
                        if (currentStatusBaseText !== newBaseText) {
                            currentStatusBaseText = newBaseText;
                            startDotAnimation(btn, newBaseText);
                        }
                    } else {
                        const newBaseText = __('workflow_checking_status').replace('...', '');
                        if (currentStatusBaseText !== newBaseText) {
                            currentStatusBaseText = newBaseText;
                            startDotAnimation(btn, newBaseText);
                        }
                    }
                } else {
                    const newBaseText = __('workflow_checking_status').replace('...', '');
                    if (currentStatusBaseText !== newBaseText) {
                        currentStatusBaseText = newBaseText;
                        startDotAnimation(btn, newBaseText);
                    }
                }
                
                // Continue polling
                setTimeout(() => trackWorkflowStatus(btn, attempts + 1), 5000);
            } catch (error) {
                console.error('Error tracking workflow:', error);
                stopDotAnimation();
                setButtonStatusText(btn, __('workflow_status_unknown'));
                setTimeout(() => setButtonLoadingWithStatus(btn, false), 1500);
            }
        }
    </script>
</body>
</html>
