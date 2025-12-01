<?php
/**
 * TrendRadarConsole - Export Page
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
    $configurations = $config->getAll();
    
    // If specific config ID is provided
    $selectedId = isset($_GET['id']) ? (int)$_GET['id'] : ($activeConfig ? $activeConfig['id'] : null);
    
    if ($selectedId) {
        $yamlData = $config->exportAsYaml($selectedId);
        $keywordsData = $config->exportKeywords($selectedId);
        $yamlString = arrayToYaml($yamlData);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'export';
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - <?php _e('export'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2><?php _e('export_configuration'); ?></h2>
                <p><?php _e('export_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php elseif (!$selectedId): ?>
            <div class="alert alert-warning"><?php _e('no_config_selected'); ?></div>
            <?php else: ?>
            
            <!-- Configuration Selection -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('select_configuration'); ?></h3>
                </div>
                <div class="card-body">
                    <form method="get" action="">
                        <div class="d-flex gap-2 align-center">
                            <select name="id" class="form-control" style="max-width: 300px;">
                                <?php foreach ($configurations as $cfg): ?>
                                <option value="<?php echo $cfg['id']; ?>" <?php echo $cfg['id'] == $selectedId ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cfg['name']); ?>
                                    <?php echo $cfg['is_active'] ? '(' . __('active') . ')' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary"><?php _e('load'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- config.yaml Export -->
            <div class="card">
                <div class="card-header">
                    <h3>config.yaml</h3>
                    <div class="btn-group">
                        <button class="btn btn-primary btn-sm" onclick="downloadYaml()"><?php _e('download'); ?></button>
                        <button class="btn btn-secondary btn-sm" onclick="copyContent('yaml-content')"><?php _e('copy'); ?></button>
                    </div>
                </div>
                <div class="card-body">
                    <pre id="yaml-content"><?php echo htmlspecialchars($yamlString); ?></pre>
                </div>
            </div>
            
            <!-- frequency_words.txt Export -->
            <div class="card">
                <div class="card-header">
                    <h3>frequency_words.txt</h3>
                    <div class="btn-group">
                        <button class="btn btn-primary btn-sm" onclick="downloadKeywords()"><?php _e('download'); ?></button>
                        <button class="btn btn-secondary btn-sm" onclick="copyContent('keywords-content')"><?php _e('copy'); ?></button>
                    </div>
                </div>
                <div class="card-body">
                    <pre id="keywords-content"><?php echo htmlspecialchars($keywordsData ?: __('no_keywords_configured')); ?></pre>
                </div>
            </div>
            
            <!-- Usage Instructions -->
            <div class="card">
                <div class="card-header">
                    <h3>📖 <?php _e('usage_instructions'); ?></h3>
                </div>
                <div class="card-body">
                    <ol style="line-height: 2;">
                        <li><?php echo __('usage_step1'); ?></li>
                        <li><?php echo __('usage_step2'); ?></li>
                        <li><?php echo __('usage_step3'); ?></li>
                        <li><?php echo __('usage_step4'); ?></li>
                    </ol>
                    
                    <div class="alert alert-success mt-3">
                        <strong>🚀 <?php _e('github_actions_deployment'); ?></strong><br>
                        <?php _e('github_actions_desc'); ?>
                        <ul class="mt-2 mb-0">
                            <li><code>vars.CONFIG_YAML</code> - <?php _e('config_yaml_var'); ?></li>
                            <li><code>vars.FREQUENCY_WORDS</code> - <?php _e('frequency_words_var'); ?></li>
                        </ul>
                        <?php echo __('repo_settings_path'); ?>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <strong>💡 <?php _e('security_tip'); ?></strong> 
                        <?php _e('security_tip_msg'); ?>
                    </div>
                    
                    <h4 class="mt-4"><?php _e('github_secrets_mapping'); ?></h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php _e('platform'); ?></th>
                                <th><?php _e('secret_names'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php _e('wework'); ?></td>
                                <td><code>WEWORK_WEBHOOK_URL</code>, <code>WEWORK_MSG_TYPE</code></td>
                            </tr>
                            <tr>
                                <td><?php _e('feishu'); ?></td>
                                <td><code>FEISHU_WEBHOOK_URL</code></td>
                            </tr>
                            <tr>
                                <td><?php _e('dingtalk'); ?></td>
                                <td><code>DINGTALK_WEBHOOK_URL</code></td>
                            </tr>
                            <tr>
                                <td><?php _e('telegram'); ?></td>
                                <td><code>TELEGRAM_BOT_TOKEN</code>, <code>TELEGRAM_CHAT_ID</code></td>
                            </tr>
                            <tr>
                                <td><?php _e('email'); ?></td>
                                <td><code>EMAIL_FROM</code>, <code>EMAIL_PASSWORD</code>, <code>EMAIL_TO</code></td>
                            </tr>
                            <tr>
                                <td><?php _e('ntfy'); ?></td>
                                <td><code>NTFY_TOPIC</code>, <code>NTFY_SERVER_URL</code>, <code>NTFY_TOKEN</code></td>
                            </tr>
                            <tr>
                                <td><?php _e('bark'); ?></td>
                                <td><code>BARK_URL</code></td>
                            </tr>
                            <tr>
                                <td><?php _e('slack'); ?></td>
                                <td><code>SLACK_WEBHOOK_URL</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        function downloadYaml() {
            const content = document.getElementById('yaml-content').textContent;
            downloadFile(content, 'config.yaml', 'text/yaml');
        }
        
        function downloadKeywords() {
            const content = document.getElementById('keywords-content').textContent;
            downloadFile(content, 'frequency_words.txt', 'text/plain');
        }
        
        function downloadFile(content, filename, type) {
            const blob = new Blob([content], { type: type });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast(__('file_downloaded'), 'success');
        }
        
        function copyContent(elementId) {
            const content = document.getElementById(elementId).textContent;
            copyToClipboard(content);
        }
    </script>
</body>
</html>
