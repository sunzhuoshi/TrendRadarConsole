<?php
/**
 * TrendRadarConsole - Webhooks/Notifications Management Page
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
    
    $webhooks = $config->getWebhooks($activeConfig['id']);
    
    // Organize webhooks by type
    $webhooksByType = [];
    foreach ($webhooks as $w) {
        $webhooksByType[$w['webhook_type']] = $w;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'webhooks';
$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();

// Webhook types configuration organized by groups
$webhookGroups = [
    'recommended' => [
        'label' => __('webhook_group_recommended'),
        'webhooks' => [
            'bark' => [
                'name' => __('bark'),
                'icon' => '🍎',
                'description' => __('bark_desc'),
                'homepage' => 'https://bark.day.app',
                'fields' => [
                    ['name' => 'webhook_url', 'label' => __('bark_url'), 'type' => 'url', 'required' => true, 'placeholder' => 'https://api.day.app/your_device_key'],
                ]
            ],
            'ntfy' => [
                'name' => __('ntfy'),
                'icon' => '🔔',
                'description' => __('ntfy_desc'),
                'homepage' => 'ntfy.sh',
                'fields' => [
                    ['name' => 'webhook_url', 'label' => __('topic_name'), 'type' => 'text', 'required' => true, 'placeholder' => 'TrendRadar'],
                    ['name' => 'server_url', 'label' => __('server_url'), 'type' => 'url', 'placeholder' => 'ntfy.sh (default)'],
                    ['name' => 'token', 'label' => __('access_token'), 'type' => 'password'],
                ]
            ],
        ]
    ],
    'domestic' => [
        'label' => __('webhook_group_domestic'),
        'webhooks' => [
            'wework' => [
                'name' => __('wework') . ' (企业微信)',
                'icon' => '💬',
                'description' => __('wework_desc'),
                'fields' => [
                    ['name' => 'webhook_url', 'label' => __('webhook_url'), 'type' => 'url', 'required' => true],
                    ['name' => 'msg_type', 'label' => __('message_type'), 'type' => 'select', 'options' => ['markdown' => __('markdown_group_robot'), 'text' => __('text_personal_wechat')]],
                ]
            ],
            'feishu' => [
                'name' => __('feishu') . ' (飞书)',
                'icon' => '🐦',
                'description' => __('feishu_desc'),
                'fields' => [
                    ['name' => 'webhook_url', 'label' => __('webhook_url'), 'type' => 'url', 'required' => true],
                ]
            ],
            'dingtalk' => [
                'name' => __('dingtalk') . ' (钉钉)',
                'icon' => '🔔',
                'description' => __('dingtalk_desc'),
                'fields' => [
                    ['name' => 'webhook_url', 'label' => __('webhook_url'), 'type' => 'url', 'required' => true],
                ]
            ],
        ]
    ],
    'international' => [
        'label' => __('webhook_group_international'),
        'webhooks' => [
            'telegram' => [
                'name' => __('telegram'),
                'icon' => '📱',
                'description' => __('telegram_desc'),
                'fields' => [
                    ['name' => 'webhook_url', 'label' => __('bot_token'), 'type' => 'password', 'required' => true, 'placeholder' => '123456789:AAHfiqksKZ8WmR2zSjiQ7...'],
                    ['name' => 'chat_id', 'label' => __('chat_id'), 'type' => 'text', 'required' => true, 'placeholder' => '123456789'],
                ]
            ],
            'email' => [
                'name' => __('email'),
                'icon' => '📧',
                'description' => __('email_desc'),
                'fields' => [
                    ['name' => 'from', 'label' => __('from_email'), 'type' => 'email', 'required' => true],
                    ['name' => 'password', 'label' => __('password_app_password'), 'type' => 'password', 'required' => true],
                    ['name' => 'to', 'label' => __('to_emails'), 'type' => 'text', 'required' => true, 'placeholder' => __('multiple_emails_hint')],
                    ['name' => 'smtp_server', 'label' => __('smtp_server'), 'type' => 'text', 'placeholder' => __('auto_detected')],
                    ['name' => 'smtp_port', 'label' => __('smtp_port'), 'type' => 'number', 'placeholder' => __('auto_detected')],
                ]
            ],
            'slack' => [
                'name' => __('slack'),
                'icon' => '💼',
                'description' => __('slack_desc'),
                'fields' => [
                    ['name' => 'webhook_url', 'label' => __('webhook_url'), 'type' => 'url', 'required' => true, 'placeholder' => 'https://hooks.slack.com/services/...'],
                ]
            ],
        ]
    ],
];
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - <?php _e('notifications'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2><?php _e('notification_webhooks'); ?></h2>
                <p><?php _e('webhooks_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <div class="alert alert-warning">
                <strong>⚠️ <?php _e('security_warning'); ?></strong> 
                <?php _e('webhook_security_msg'); ?>
            </div>
            
            <!-- Webhook Cards by Group -->
            <?php foreach ($webhookGroups as $groupKey => $group): ?>
            <h3 class="webhook-group-header"><?php echo htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="row">
                <?php foreach ($group['webhooks'] as $type => $webhookInfo): ?>
                <?php 
                $existing = $webhooksByType[$type] ?? null;
                $additionalConfig = $existing && $existing['additional_config'] ? json_decode($existing['additional_config'], true) : [];
                ?>
                <div class="col-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><?php echo $webhookInfo['icon']; ?> <?php echo $webhookInfo['name']; ?></h3>
                            <?php if ($existing): ?>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       <?php echo $existing['is_enabled'] ? 'checked' : ''; ?>
                                       onchange="webhookManager.toggle(<?php echo $existing['id']; ?>, this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <?php echo $webhookInfo['description']; ?>
                                <?php if (!empty($webhookInfo['homepage'])): ?>
                                <br><a href="<?php echo htmlspecialchars($webhookInfo['homepage'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">🏠 <?php _e('home_page'); ?></a>
                                <?php endif; ?>
                            </p>
                            
                            <form class="webhook-form" data-type="<?php echo $type; ?>">
                                <?php foreach ($webhookInfo['fields'] as $field): ?>
                                <div class="form-group">
                                    <label class="form-label">
                                        <?php echo $field['label']; ?>
                                        <?php if (!empty($field['required'])): ?>
                                        <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <?php if ($field['type'] === 'select'): ?>
                                    <select name="<?php echo $field['name']; ?>" class="form-control">
                                        <?php foreach ($field['options'] as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" 
                                                <?php echo ($additionalConfig[$field['name']] ?? '') === $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php else: ?>
                                    <input type="<?php echo $field['type']; ?>" 
                                           name="<?php echo $field['name']; ?>" 
                                           class="form-control"
                                           placeholder="<?php echo $field['placeholder'] ?? ''; ?>"
                                           <?php if (!empty($field['required'])): ?>required<?php endif; ?>
                                           value="<?php 
                                               if ($field['name'] === 'webhook_url') {
                                                   echo sanitize($existing['webhook_url'] ?? '');
                                               } else {
                                                   echo sanitize($additionalConfig[$field['name']] ?? '');
                                               }
                                           ?>">
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="btn-group">
                                    <button type="submit" class="btn btn-primary btn-sm"><?php _e('save'); ?></button>
                                    <?php if ($existing): ?>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="webhookManager.remove(<?php echo $existing['id']; ?>)">
                                        <?php _e('remove'); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        // Webhook form submissions
        document.querySelectorAll('.webhook-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const type = this.dataset.type;
                const configId = document.getElementById('config-id').value;
                const formData = new FormData(this);
                
                const data = {
                    config_id: configId,
                    type: type
                };
                
                // Collect form fields
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                try {
                    await apiRequest('api/webhooks.php', 'POST', data);
                    showToast(__('webhook_saved'), 'success');
                    setTimeout(() => location.reload(), 1000);
                } catch (error) {
                    showToast(__('failed_to_save') + ': ' + error.message, 'error');
                }
            });
        });
    </script>
</body>
</html>
