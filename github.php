<?php
/**
 * TrendRadarConsole - GitHub Settings Page
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
    
    $settings = $config->getSettings($activeConfig['id']);
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'github';
$csrfToken = generateCsrfToken();

// Get saved GitHub settings
$githubOwner = $settings['github_owner'] ?? '';
$githubRepo = $settings['github_repo'] ?? '';
$githubToken = $settings['github_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>TrendRadarConsole - GitHub Sync</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2>GitHub Sync</h2>
                <p>Load and save configurations directly to your TrendRadar GitHub repository</p>
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
                    <h3>🔑 GitHub Connection</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>📋 How to get a Personal Access Token (PAT):</strong>
                        <ol class="mt-2 mb-0">
                            <li>Go to <a href="https://github.com/settings/tokens?type=beta" target="_blank">GitHub Settings → Developer settings → Personal access tokens → Fine-grained tokens</a></li>
                            <li>Click "Generate new token"</li>
                            <li>Select your TrendRadar repository</li>
                            <li>Under "Repository permissions", set <strong>Variables</strong> to <strong>Read and write</strong></li>
                            <li>Click "Generate token" and copy it</li>
                        </ol>
                    </div>
                    
                    <form id="github-settings-form" class="mt-4">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Repository Owner <span class="text-danger">*</span></label>
                                    <input type="text" name="github_owner" class="form-control" 
                                           value="<?php echo sanitize($githubOwner); ?>"
                                           placeholder="e.g., your-username">
                                    <div class="form-text">Your GitHub username or organization name</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Repository Name <span class="text-danger">*</span></label>
                                    <input type="text" name="github_repo" class="form-control" 
                                           value="<?php echo sanitize($githubRepo); ?>"
                                           placeholder="e.g., TrendRadar">
                                    <div class="form-text">Your forked TrendRadar repository name</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Personal Access Token (PAT) <span class="text-danger">*</span></label>
                            <input type="password" name="github_token" class="form-control" 
                                   value="<?php echo sanitize($githubToken); ?>"
                                   placeholder="github_pat_...">
                            <div class="form-text">Your GitHub Fine-grained Personal Access Token with Variables read/write permission</div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                            <button type="button" class="btn btn-secondary" onclick="testConnection()">Test Connection</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sync Operations -->
            <div class="card">
                <div class="card-header">
                    <h3>🔄 Sync Operations</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Load configurations from your GitHub repository or push your current configuration to GitHub.
                    </p>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-card">
                                <h4>📥 Load from GitHub</h4>
                                <p class="text-muted">
                                    Import CONFIG_YAML and FREQUENCY_WORDS from your GitHub repository variables.
                                </p>
                                <button class="btn btn-primary mt-2" onclick="loadFromGitHub()">
                                    Load from GitHub
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <h4>📤 Save to GitHub</h4>
                                <p class="text-muted">
                                    Push your current configuration to GitHub as repository variables.
                                </p>
                                <button class="btn btn-success mt-2" onclick="saveToGitHub()">
                                    Save to GitHub
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview Section -->
            <div class="card" id="preview-card" style="display: none;">
                <div class="card-header">
                    <h3>📋 Loaded Configuration Preview</h3>
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
    
    <script src="assets/js/app.js"></script>
    <script>
        // Save GitHub settings
        document.getElementById('github-settings-form').addEventListener('submit', async function(e) {
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
                showToast('GitHub settings saved', 'success');
            } catch (error) {
                showToast('Failed to save: ' + error.message, 'error');
            }
        });
        
        // Test connection
        async function testConnection() {
            const owner = document.querySelector('input[name="github_owner"]').value;
            const repo = document.querySelector('input[name="github_repo"]').value;
            const token = document.querySelector('input[name="github_token"]').value;
            
            if (!owner || !repo || !token) {
                showToast('Please fill in all fields first', 'error');
                return;
            }
            
            try {
                const result = await apiRequest('api/github.php', 'POST', {
                    action: 'test',
                    owner: owner,
                    repo: repo,
                    token: token
                });
                showToast('Connection successful! Repository: ' + result.data.full_name, 'success');
            } catch (error) {
                showToast('Connection failed: ' + error.message, 'error');
            }
        }
        
        // Load from GitHub
        async function loadFromGitHub() {
            const owner = document.querySelector('input[name="github_owner"]').value;
            const repo = document.querySelector('input[name="github_repo"]').value;
            const token = document.querySelector('input[name="github_token"]').value;
            
            if (!owner || !repo || !token) {
                showToast('Please configure GitHub settings first', 'error');
                return;
            }
            
            try {
                const result = await apiRequest('api/github.php', 'POST', {
                    action: 'load',
                    owner: owner,
                    repo: repo,
                    token: token
                });
                
                // Show preview
                document.getElementById('config-yaml-preview').textContent = result.data.config_yaml || '# Not found';
                document.getElementById('frequency-words-preview').textContent = result.data.frequency_words || '# Not found';
                document.getElementById('preview-card').style.display = 'block';
                
                showToast('Configuration loaded from GitHub', 'success');
            } catch (error) {
                showToast('Failed to load: ' + error.message, 'error');
            }
        }
        
        // Save to GitHub
        async function saveToGitHub() {
            const owner = document.querySelector('input[name="github_owner"]').value;
            const repo = document.querySelector('input[name="github_repo"]').value;
            const token = document.querySelector('input[name="github_token"]').value;
            const configId = document.getElementById('config-id').value;
            
            if (!owner || !repo || !token) {
                showToast('Please configure GitHub settings first', 'error');
                return;
            }
            
            if (!confirm('This will overwrite CONFIG_YAML and FREQUENCY_WORDS variables in your GitHub repository. Continue?')) {
                return;
            }
            
            try {
                await apiRequest('api/github.php', 'POST', {
                    action: 'save',
                    owner: owner,
                    repo: repo,
                    token: token,
                    config_id: configId
                });
                
                showToast('Configuration saved to GitHub successfully!', 'success');
            } catch (error) {
                showToast('Failed to save: ' + error.message, 'error');
            }
        }
    </script>
</body>
</html>
