<?php
/**
 * TrendRadarConsole - Docker Deployment Page
 * Local Docker deployment as alternative to GitHub Actions
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
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'docker';

// Docker settings are calculated based on user ID (not user-configurable)
$containerName = 'trend-radar-' . $userId;
$configPath = './workspace/' . $userId . '/config';
$outputPath = './workspace/' . $userId . '/output';
$dockerImage = 'wantcat/trendradar:latest';

$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - <?php _e('docker_deployment'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2>🐳 <?php _e('docker_deployment'); ?></h2>
                <p><?php _e('docker_deployment_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <!-- Docker Settings (Auto-calculated) -->
            <div class="card">
                <div class="card-header">
                    <h3>⚙️ <?php _e('docker_settings'); ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?php _e('docker_settings_auto_desc'); ?></p>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><?php _e('container_name'); ?></label>
                                <input type="text" class="form-control" value="<?php echo sanitize($containerName); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><?php _e('docker_image'); ?></label>
                                <input type="text" class="form-control" value="<?php echo sanitize($dockerImage); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><?php _e('config_path'); ?></label>
                                <input type="text" class="form-control" value="<?php echo sanitize($configPath); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><?php _e('output_path'); ?></label>
                                <input type="text" class="form-control" value="<?php echo sanitize($outputPath); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Container Status -->
            <div class="card">
                <div class="card-header">
                    <h3>📊 <?php _e('container_status'); ?></h3>
                    <button type="button" class="btn btn-outline btn-sm" onclick="inspectContainer()">
                        🔄 <?php _e('refresh'); ?>
                    </button>
                </div>
                <div class="card-body">
                    <div id="container-status">
                        <div class="empty-state">
                            <p><?php _e('click_refresh_to_check'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Container Logs -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 <?php _e('container_logs'); ?></h3>
                    <div class="btn-group">
                        <select id="log-tail-lines" class="form-control" style="width: auto; display: inline-block;">
                            <option value="50">50 <?php _e('lines'); ?></option>
                            <option value="100" selected>100 <?php _e('lines'); ?></option>
                            <option value="200">200 <?php _e('lines'); ?></option>
                            <option value="500">500 <?php _e('lines'); ?></option>
                        </select>
                        <button type="button" class="btn btn-outline btn-sm" onclick="fetchLogs()">
                            🔄 <?php _e('fetch_logs'); ?>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="container-logs">
                        <div class="empty-state">
                            <p><?php _e('click_fetch_logs'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Docker Command Generator -->
            <div class="card">
                <div class="card-header">
                    <h3>🛠️ <?php _e('docker_command_generator'); ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?php _e('docker_command_generator_desc'); ?></p>
                    
                    <div class="form-group">
                        <label class="form-label"><?php _e('environment_variables'); ?> <small>(<?php _e('optional'); ?>)</small></label>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><small>FEISHU_WEBHOOK_URL</small></label>
                                <input type="text" id="env-feishu" class="form-control env-var" placeholder="<?php _e('feishu_webhook_placeholder'); ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><small>DINGTALK_WEBHOOK_URL</small></label>
                                <input type="text" id="env-dingtalk" class="form-control env-var" placeholder="<?php _e('dingtalk_webhook_placeholder'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><small>WEWORK_WEBHOOK_URL</small></label>
                                <input type="text" id="env-wework" class="form-control env-var" placeholder="<?php _e('wework_webhook_placeholder'); ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><small>TELEGRAM_BOT_TOKEN</small></label>
                                <input type="text" id="env-telegram-token" class="form-control env-var" placeholder="<?php _e('telegram_token_placeholder'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><small>TELEGRAM_CHAT_ID</small></label>
                                <input type="text" id="env-telegram-chat" class="form-control env-var" placeholder="<?php _e('telegram_chat_id_placeholder'); ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><small>EMAIL_FROM</small></label>
                                <input type="text" id="env-email-from" class="form-control env-var" placeholder="<?php _e('email_from_placeholder'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><small>EMAIL_PASSWORD</small></label>
                                <input type="password" id="env-email-password" class="form-control env-var" placeholder="<?php _e('email_password_placeholder'); ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label"><small>EMAIL_TO</small></label>
                                <input type="text" id="env-email-to" class="form-control env-var" placeholder="<?php _e('email_to_placeholder'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label"><small>CRON_SCHEDULE</small></label>
                                <input type="text" id="env-cron" class="form-control env-var" value="*/30 * * * *" placeholder="*/30 * * * *">
                                <div class="form-text"><?php _e('cron_schedule_desc'); ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label"><small>RUN_MODE</small></label>
                                <select id="env-run-mode" class="form-control env-var">
                                    <option value="cron"><?php _e('cron_mode'); ?></option>
                                    <option value="once"><?php _e('once_mode'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label"><small>IMMEDIATE_RUN</small></label>
                                <select id="env-immediate-run" class="form-control env-var">
                                    <option value="true"><?php _e('yes'); ?></option>
                                    <option value="false"><?php _e('no'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="btn-group mt-3">
                        <button type="button" class="btn btn-primary" onclick="generateCommand()">
                            🔧 <?php _e('generate_command'); ?>
                        </button>
                    </div>
                    
                    <div id="generated-command" style="display: none; margin-top: 20px;">
                        <label class="form-label"><?php _e('generated_docker_command'); ?></label>
                        <pre id="command-output" style="position: relative;"></pre>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyCommand()">
                            📋 <?php _e('copy_command'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Quick Reference -->
            <div class="card">
                <div class="card-header">
                    <h3>📖 <?php _e('quick_reference'); ?></h3>
                </div>
                <div class="card-body">
                    <h4><?php _e('useful_docker_commands'); ?></h4>
                    <div class="example-config-block mt-2 mb-3">
                        <code># <?php _e('inspect_container'); ?></code><br>
                        <code>docker inspect <?php echo sanitize($containerName ?: 'trend-radar'); ?></code><br><br>
                        <code># <?php _e('view_container_logs'); ?></code><br>
                        <code>docker logs --tail 100 <?php echo sanitize($containerName ?: 'trend-radar'); ?></code><br><br>
                        <code># <?php _e('stop_container'); ?></code><br>
                        <code>docker stop <?php echo sanitize($containerName ?: 'trend-radar'); ?></code><br><br>
                        <code># <?php _e('start_container'); ?></code><br>
                        <code>docker start <?php echo sanitize($containerName ?: 'trend-radar'); ?></code><br><br>
                        <code># <?php _e('remove_container'); ?></code><br>
                        <code>docker rm <?php echo sanitize($containerName ?: 'trend-radar'); ?></code><br><br>
                        <code># <?php _e('view_running_containers'); ?></code><br>
                        <code>docker ps</code>
                    </div>
                    
                    <h4><?php _e('directory_structure'); ?></h4>
                    <p class="text-muted"><?php _e('directory_structure_desc'); ?></p>
                    <div class="example-config-block mt-2">
                        <code>./config/</code><br>
                        <code>├── config.yaml          # <?php _e('main_config_file'); ?></code><br>
                        <code>└── frequency_words.txt  # <?php _e('keywords_file'); ?></code><br><br>
                        <code>./output/</code><br>
                        <code>└── ...                  # <?php _e('output_files'); ?></code>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        // Fixed Docker settings (calculated by server based on user ID)
        const dockerSettings = {
            containerName: '<?php echo $containerName; ?>',
            configPath: '<?php echo $configPath; ?>',
            outputPath: '<?php echo $outputPath; ?>',
            dockerImage: '<?php echo $dockerImage; ?>'
        };
        
        // Inspect container
        async function inspectContainer() {
            const statusDiv = document.getElementById('container-status');
            
            statusDiv.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            
            try {
                const result = await apiRequest('api/docker.php', 'POST', {
                    action: 'inspect'
                });
                
                const data = result.data;
                
                if (data.status === 'not_found') {
                    statusDiv.innerHTML = `
                        <div class="alert alert-warning">
                            <strong><?php _e('container_not_found'); ?></strong><br>
                            ${escapeHtml(data.message)}
                        </div>
                    `;
                } else if (data.status === 'found') {
                    const state = data.state;
                    const statusBadge = state.running 
                        ? '<span class="badge badge-success"><?php _e('running'); ?></span>'
                        : '<span class="badge badge-secondary"><?php _e('stopped'); ?></span>';
                    
                    statusDiv.innerHTML = `
                        <div class="row">
                            <div class="col-3">
                                <div class="stat-card">
                                    <div class="stat-card-label"><?php _e('status'); ?></div>
                                    <div class="stat-card-value">${statusBadge}</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="stat-card">
                                    <div class="stat-card-label"><?php _e('image'); ?></div>
                                    <div class="stat-card-value" style="font-size: 14px;">${escapeHtml(data.image)}</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="stat-card">
                                    <div class="stat-card-label"><?php _e('created'); ?></div>
                                    <div class="stat-card-value" style="font-size: 14px;">${formatDateTime(data.created)}</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="stat-card">
                                    <div class="stat-card-label"><?php _e('started_at'); ?></div>
                                    <div class="stat-card-value" style="font-size: 14px;">${state.started_at ? formatDateTime(state.started_at) : '-'}</div>
                                </div>
                            </div>
                        </div>
                        ${data.mounts.length > 0 ? `
                        <div class="mt-3">
                            <h4><?php _e('volume_mounts'); ?></h4>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?php _e('source'); ?></th>
                                        <th><?php _e('destination'); ?></th>
                                        <th><?php _e('type'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.mounts.map(m => `
                                        <tr>
                                            <td><code>${escapeHtml(m.source)}</code></td>
                                            <td><code>${escapeHtml(m.destination)}</code></td>
                                            <td>${escapeHtml(m.type)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        ` : ''}
                    `;
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-danger">
                            ${escapeHtml(data.message || 'Unknown error')}
                        </div>
                    `;
                }
            } catch (error) {
                statusDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <?php _e('failed_to_inspect'); ?>: ${escapeHtml(error.message)}
                    </div>
                `;
            }
        }
        
        // Fetch container logs
        async function fetchLogs() {
            const tailLines = document.getElementById('log-tail-lines').value;
            const logsDiv = document.getElementById('container-logs');
            
            logsDiv.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            
            try {
                const result = await apiRequest('api/docker.php', 'POST', {
                    action: 'logs',
                    tail: parseInt(tailLines)
                });
                
                const data = result.data;
                
                if (data.logs) {
                    logsDiv.innerHTML = `<pre style="max-height: 400px; overflow-y: auto;">${escapeHtml(data.logs)}</pre>`;
                } else {
                    logsDiv.innerHTML = `
                        <div class="empty-state">
                            <p><?php _e('no_logs_available'); ?></p>
                        </div>
                    `;
                }
            } catch (error) {
                logsDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <?php _e('failed_to_fetch_logs'); ?>: ${escapeHtml(error.message)}
                    </div>
                `;
            }
        }
        
        // Generate docker run command
        async function generateCommand() {
            try {
                const result = await apiRequest('api/docker.php', 'POST', {
                    action: 'generate_command',
                    feishu_webhook_url: document.getElementById('env-feishu').value,
                    dingtalk_webhook_url: document.getElementById('env-dingtalk').value,
                    wework_webhook_url: document.getElementById('env-wework').value,
                    telegram_bot_token: document.getElementById('env-telegram-token').value,
                    telegram_chat_id: document.getElementById('env-telegram-chat').value,
                    email_from: document.getElementById('env-email-from').value,
                    email_password: document.getElementById('env-email-password').value,
                    email_to: document.getElementById('env-email-to').value,
                    cron_schedule: document.getElementById('env-cron').value,
                    run_mode: document.getElementById('env-run-mode').value,
                    immediate_run: document.getElementById('env-immediate-run').value
                });
                
                document.getElementById('command-output').textContent = result.data.command;
                document.getElementById('generated-command').style.display = 'block';
            } catch (error) {
                showToast(__('failed_to_generate') + ': ' + error.message, 'error');
            }
        }
        
        // Copy command to clipboard
        function copyCommand() {
            const command = document.getElementById('command-output').textContent;
            copyToClipboard(command);
        }
        
        // Helper functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDateTime(dateStr) {
            if (!dateStr) return '-';
            try {
                const date = new Date(dateStr);
                return date.toLocaleString();
            } catch (e) {
                return dateStr;
            }
        }
    </script>
</body>
</html>
