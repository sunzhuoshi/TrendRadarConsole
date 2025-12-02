<?php
/**
 * TrendRadarConsole - GitHub Setup Wizard
 * 
 * This wizard guides new users through setting up their GitHub connection
 * Step 1: Clone TrendRadar repo guide
 * Step 2: Set GitHub username and repo name
 * Step 3: Create and set GitHub PAT
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

// Get current step (default to step 1)
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 3) {
    $step = 1;
}

// Get GitHub settings
$auth = new Auth();
$githubSettings = $auth->getGitHubSettings($userId);

$flash = getFlash();
$currentLang = getCurrentLanguage();
$csrfToken = generateCsrfToken();

// Check if GitHub is fully configured
$githubConfigured = !empty($githubSettings['github_owner']) && 
                    !empty($githubSettings['github_repo']) && 
                    !empty($githubSettings['github_token']);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>TrendRadarConsole - <?php _e('github_setup_wizard'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .setup-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 700px;
            padding: 40px;
            margin: 20px;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .setup-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .setup-header p {
            color: var(--text-muted);
        }
        .language-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .language-toggle select {
            padding: 8px 12px;
            border-radius: 6px;
            border: none;
            background: rgba(255,255,255,0.9);
            cursor: pointer;
            font-size: 14px;
        }
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
        .step-content {
            min-height: 300px;
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
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Consolas', 'Monaco', monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
        .btn-group-wizard {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .skip-link {
            text-align: center;
            margin-top: 20px;
        }
        .skip-link a {
            color: #666;
            text-decoration: none;
        }
        .skip-link a:hover {
            color: #333;
        }
        .highlight-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
    <div class="language-toggle">
        <select onchange="switchLanguage(this.value)">
            <option value="zh" <?php echo $currentLang === 'zh' ? 'selected' : ''; ?>><?php _e('chinese'); ?></option>
            <option value="en" <?php echo $currentLang === 'en' ? 'selected' : ''; ?>><?php _e('english'); ?></option>
        </select>
    </div>
    
    <div class="setup-container">
        <div class="setup-header">
            <h1>🐙 <?php _e('github_setup_wizard'); ?></h1>
            <p><?php _e('github_setup_desc'); ?></p>
        </div>
        
        <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
            <?php echo sanitize($flash['message']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Wizard Steps -->
        <div class="wizard-steps">
            <div class="wizard-step">
                <div class="step-number <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">
                    <?php echo $step > 1 ? '✓' : '1'; ?>
                </div>
                <span class="step-label"><?php _e('clone_repo'); ?></span>
            </div>
            <div class="step-connector <?php echo $step > 1 ? 'completed' : ''; ?>"></div>
            <div class="wizard-step">
                <div class="step-number <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">
                    <?php echo $step > 2 ? '✓' : '2'; ?>
                </div>
                <span class="step-label"><?php _e('repo_settings'); ?></span>
            </div>
            <div class="step-connector <?php echo $step > 2 ? 'completed' : ''; ?>"></div>
            <div class="wizard-step">
                <div class="step-number <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                <span class="step-label"><?php _e('setup_pat'); ?></span>
            </div>
        </div>
        
        <!-- Step Content -->
        <div class="step-content">
            <?php if ($step === 1): ?>
            <!-- Step 1: Clone Repository Guide -->
            <div class="info-box">
                <h3>📦 <?php _e('step1_title'); ?></h3>
                <p><?php _e('step1_desc'); ?></p>
                
                <div class="highlight-box">
                    <strong><?php _e('fork_instructions'); ?></strong>
                </div>
                
                <ol>
                    <li><?php _e('step1_instruction1'); ?> <a href="https://github.com/sunzhuoshi/TrendRadar" target="_blank">github.com/sunzhuoshi/TrendRadar</a></li>
                    <li><?php _e('step1_instruction2'); ?></li>
                    <li><?php _e('step1_instruction3'); ?></li>
                    <li><?php _e('step1_instruction4'); ?></li>
                </ol>
                
                <div class="highlight-box" style="margin-top: 20px;">
                    <strong>🔗 <?php _e('trendradar_repo_url'); ?>:</strong><br>
                    <a href="https://github.com/sunzhuoshi/TrendRadar" target="_blank">https://github.com/sunzhuoshi/TrendRadar</a>
                </div>
            </div>
            
            <div class="btn-group-wizard">
                <div></div>
                <a href="setup-github.php?step=2" class="btn btn-primary"><?php _e('next_step'); ?> →</a>
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
                    <a href="setup-github.php?step=1" class="btn btn-secondary">← <?php _e('prev_step'); ?></a>
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
                    <a href="setup-github.php?step=2" class="btn btn-secondary">← <?php _e('prev_step'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php _e('complete_setup'); ?></button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        
        <div class="skip-link">
            <a href="index.php"><?php _e('skip_for_now'); ?></a>
        </div>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        <?php if ($step === 2): ?>
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
                window.location.href = 'setup-github.php?step=3';
            } catch (error) {
                showToast(__('failed_to_save') + ': ' + error.message, 'error');
                setButtonLoading(submitBtn, false);
            }
        });
        <?php endif; ?>
        
        <?php if ($step === 3): ?>
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
                    owner: '<?php echo sanitize($githubSettings['github_owner'] ?? ''); ?>',
                    repo: '<?php echo sanitize($githubSettings['github_repo'] ?? 'TrendRadar'); ?>',
                    token: token
                });
                
                // Test connection
                const testResult = await apiRequest('api/github.php', 'POST', {
                    action: 'test',
                    owner: '<?php echo sanitize($githubSettings['github_owner'] ?? ''); ?>',
                    repo: '<?php echo sanitize($githubSettings['github_repo'] ?? 'TrendRadar'); ?>',
                    token: token
                });
                
                showToast(__('connection_successful') + testResult.data.full_name, 'success');
                
                // Redirect to load configuration from GitHub
                setTimeout(function() {
                    window.location.href = 'index.php?load_from_github=1';
                }, 1500);
            } catch (error) {
                showToast(__('connection_failed') + error.message, 'error');
                setButtonLoading(submitBtn, false);
            }
        });
        <?php endif; ?>
        
        function switchLanguage(lang) {
            fetch('api/language.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lang: lang })
            }).then(() => location.reload());
        }
    </script>
</body>
</html>
