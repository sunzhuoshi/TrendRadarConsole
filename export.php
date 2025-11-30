<?php
/**
 * TrendRadarConsole - Export Page
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/Configuration.php';

if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

try {
    $config = new Configuration();
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - Export</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2>Export Configuration</h2>
                <p>Export your configuration as YAML and keywords files for TrendRadar</p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php elseif (!$selectedId): ?>
            <div class="alert alert-warning">No configuration selected. Please create or activate a configuration first.</div>
            <?php else: ?>
            
            <!-- Configuration Selection -->
            <div class="card">
                <div class="card-header">
                    <h3>Select Configuration</h3>
                </div>
                <div class="card-body">
                    <form method="get" action="">
                        <div class="d-flex gap-2 align-center">
                            <select name="id" class="form-control" style="max-width: 300px;">
                                <?php foreach ($configurations as $cfg): ?>
                                <option value="<?php echo $cfg['id']; ?>" <?php echo $cfg['id'] == $selectedId ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cfg['name']); ?>
                                    <?php echo $cfg['is_active'] ? '(Active)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Load</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- config.yaml Export -->
            <div class="card">
                <div class="card-header">
                    <h3>config.yaml</h3>
                    <div class="btn-group">
                        <button class="btn btn-primary btn-sm" onclick="downloadYaml()">Download</button>
                        <button class="btn btn-secondary btn-sm" onclick="copyContent('yaml-content')">Copy</button>
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
                        <button class="btn btn-primary btn-sm" onclick="downloadKeywords()">Download</button>
                        <button class="btn btn-secondary btn-sm" onclick="copyContent('keywords-content')">Copy</button>
                    </div>
                </div>
                <div class="card-body">
                    <pre id="keywords-content"><?php echo htmlspecialchars($keywordsData ?: '# No keywords configured'); ?></pre>
                </div>
            </div>
            
            <!-- Usage Instructions -->
            <div class="card">
                <div class="card-header">
                    <h3>📖 Usage Instructions</h3>
                </div>
                <div class="card-body">
                    <ol style="line-height: 2;">
                        <li>Download both <code>config.yaml</code> and <code>frequency_words.txt</code> files</li>
                        <li>Place them in your TrendRadar project's <code>config/</code> directory</li>
                        <li>For GitHub Actions deployment, add webhook URLs to GitHub Secrets instead of the config file</li>
                        <li>For Docker deployment, use environment variables for sensitive data</li>
                    </ol>
                    
                    <div class="alert alert-info mt-3">
                        <strong>💡 Tip:</strong> For security reasons, webhook URLs in the exported config.yaml are included.
                        If deploying via GitHub Fork, remove them and use GitHub Secrets instead.
                    </div>
                    
                    <h4 class="mt-4">GitHub Secrets Mapping</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>Secret Name(s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>WeChat Work</td>
                                <td><code>WEWORK_WEBHOOK_URL</code>, <code>WEWORK_MSG_TYPE</code></td>
                            </tr>
                            <tr>
                                <td>Feishu</td>
                                <td><code>FEISHU_WEBHOOK_URL</code></td>
                            </tr>
                            <tr>
                                <td>DingTalk</td>
                                <td><code>DINGTALK_WEBHOOK_URL</code></td>
                            </tr>
                            <tr>
                                <td>Telegram</td>
                                <td><code>TELEGRAM_BOT_TOKEN</code>, <code>TELEGRAM_CHAT_ID</code></td>
                            </tr>
                            <tr>
                                <td>Email</td>
                                <td><code>EMAIL_FROM</code>, <code>EMAIL_PASSWORD</code>, <code>EMAIL_TO</code></td>
                            </tr>
                            <tr>
                                <td>ntfy</td>
                                <td><code>NTFY_TOPIC</code>, <code>NTFY_SERVER_URL</code>, <code>NTFY_TOKEN</code></td>
                            </tr>
                            <tr>
                                <td>Bark</td>
                                <td><code>BARK_URL</code></td>
                            </tr>
                            <tr>
                                <td>Slack</td>
                                <td><code>SLACK_WEBHOOK_URL</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
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
            showToast('File downloaded', 'success');
        }
        
        function copyContent(elementId) {
            const content = document.getElementById(elementId).textContent;
            copyToClipboard(content);
        }
    </script>
</body>
</html>
