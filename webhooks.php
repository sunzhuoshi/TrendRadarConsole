<?php
/**
 * TrendRadarConsole - Webhooks/Notifications Management Page
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/configuration.php';

if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

try {
    $config = new Configuration();
    $activeConfig = $config->getActive();
    
    if (!$activeConfig) {
        setFlash('warning', 'Please create and activate a configuration first.');
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

// Webhook types configuration
$webhookTypes = [
    'wework' => [
        'name' => 'WeChat Work (企业微信)',
        'icon' => '💬',
        'description' => 'Send notifications via WeChat Work robot',
        'fields' => [
            ['name' => 'webhook_url', 'label' => 'Webhook URL', 'type' => 'url', 'required' => true],
            ['name' => 'msg_type', 'label' => 'Message Type', 'type' => 'select', 'options' => ['markdown' => 'Markdown (群机器人)', 'text' => 'Text (个人微信)']],
        ]
    ],
    'feishu' => [
        'name' => 'Feishu (飞书)',
        'icon' => '🐦',
        'description' => 'Send notifications via Feishu robot',
        'fields' => [
            ['name' => 'webhook_url', 'label' => 'Webhook URL', 'type' => 'url', 'required' => true],
        ]
    ],
    'dingtalk' => [
        'name' => 'DingTalk (钉钉)',
        'icon' => '🔔',
        'description' => 'Send notifications via DingTalk robot',
        'fields' => [
            ['name' => 'webhook_url', 'label' => 'Webhook URL', 'type' => 'url', 'required' => true],
        ]
    ],
    'telegram' => [
        'name' => 'Telegram',
        'icon' => '📱',
        'description' => 'Send notifications via Telegram bot',
        'fields' => [
            ['name' => 'webhook_url', 'label' => 'Bot Token', 'type' => 'password', 'required' => true, 'placeholder' => '123456789:AAHfiqksKZ8WmR2zSjiQ7...'],
            ['name' => 'chat_id', 'label' => 'Chat ID', 'type' => 'text', 'required' => true, 'placeholder' => '123456789'],
        ]
    ],
    'email' => [
        'name' => 'Email',
        'icon' => '📧',
        'description' => 'Send notifications via email',
        'fields' => [
            ['name' => 'from', 'label' => 'From Email', 'type' => 'email', 'required' => true],
            ['name' => 'password', 'label' => 'Password/App Password', 'type' => 'password', 'required' => true],
            ['name' => 'to', 'label' => 'To Email(s)', 'type' => 'text', 'required' => true, 'placeholder' => 'Multiple emails separated by comma'],
            ['name' => 'smtp_server', 'label' => 'SMTP Server (optional)', 'type' => 'text', 'placeholder' => 'Auto-detected if empty'],
            ['name' => 'smtp_port', 'label' => 'SMTP Port (optional)', 'type' => 'number', 'placeholder' => 'Auto-detected if empty'],
        ]
    ],
    'ntfy' => [
        'name' => 'ntfy',
        'icon' => '🔔',
        'description' => 'Send notifications via ntfy.sh',
        'fields' => [
            ['name' => 'webhook_url', 'label' => 'Topic Name', 'type' => 'text', 'required' => true, 'placeholder' => 'trendradar-your-topic'],
            ['name' => 'server_url', 'label' => 'Server URL', 'type' => 'url', 'placeholder' => 'https://ntfy.sh (default)'],
            ['name' => 'token', 'label' => 'Access Token (optional)', 'type' => 'password'],
        ]
    ],
    'bark' => [
        'name' => 'Bark (iOS)',
        'icon' => '🍎',
        'description' => 'Send notifications via Bark (iOS only)',
        'fields' => [
            ['name' => 'webhook_url', 'label' => 'Bark URL', 'type' => 'url', 'required' => true, 'placeholder' => 'https://api.day.app/your_device_key'],
        ]
    ],
    'slack' => [
        'name' => 'Slack',
        'icon' => '💼',
        'description' => 'Send notifications via Slack Incoming Webhook',
        'fields' => [
            ['name' => 'webhook_url', 'label' => 'Webhook URL', 'type' => 'url', 'required' => true, 'placeholder' => 'https://hooks.slack.com/services/...'],
        ]
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - Notifications</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2>Notification Webhooks</h2>
                <p>Configure notification channels for hot topic alerts</p>
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
                <strong>⚠️ Security Warning:</strong> Never expose your webhook URLs publicly. 
                Keep them secure to prevent spam and abuse.
            </div>
            
            <!-- Webhook Cards -->
            <div class="row">
                <?php foreach ($webhookTypes as $type => $webhookInfo): ?>
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
                            <p class="text-muted mb-3"><?php echo $webhookInfo['description']; ?></p>
                            
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
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                    <?php if ($existing): ?>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="webhookManager.remove(<?php echo $existing['id']; ?>)">
                                        Remove
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
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
                    showToast('Webhook saved successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } catch (error) {
                    showToast('Failed to save: ' + error.message, 'error');
                }
            });
        });
    </script>
</body>
</html>
