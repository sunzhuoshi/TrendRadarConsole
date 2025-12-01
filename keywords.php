<?php
/**
 * TrendRadarConsole - Keywords Management Page
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
    
    if (!$activeConfig) {
        setFlash('warning', __('please_create_config'));
        header('Location: index.php');
        exit;
    }
    
    $keywords = $config->getKeywords($activeConfig['id']);
    $keywordsText = $config->exportKeywords($activeConfig['id']);
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'keywords';
$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>TrendRadarConsole - <?php _e('keywords'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2><?php _e('keywords_configuration'); ?></h2>
                <p><?php _e('keywords_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <!-- Syntax Guide -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('keyword_syntax_guide'); ?></h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php _e('type'); ?></th>
                                <th><?php _e('syntax'); ?></th>
                                <th><?php _e('description'); ?></th>
                                <th><?php _e('example'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-secondary"><?php _e('normal'); ?></span></td>
                                <td><code>keyword</code></td>
                                <td><?php _e('normal_desc'); ?></td>
                                <td><code>华为</code>, <code>AI</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-info"><?php _e('required'); ?></span></td>
                                <td><code>+keyword</code></td>
                                <td><?php _e('required_desc'); ?></td>
                                <td><code>+发布</code>, <code>+技术</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-danger"><?php _e('filter'); ?></span></td>
                                <td><code>!keyword</code></td>
                                <td><?php _e('filter_desc'); ?></td>
                                <td><code>!广告</code>, <code>!预测</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-warning"><?php _e('limit'); ?></span></td>
                                <td><code>@number</code></td>
                                <td><?php _e('limit_desc'); ?></td>
                                <td><code>@10</code>, <code>@5</code></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-muted mt-3">
                        <strong>Tip:</strong> <?php _e('keywords_tip'); ?>
                    </p>
                </div>
            </div>
            
            <!-- Keywords Editor -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('keywords_editor'); ?></h3>
                </div>
                <div class="card-body">
                    <form id="keywords-form">
                        <div class="form-group">
                            <label class="form-label">
                                <?php _e('enter_keywords_instruction'); ?>
                            </label>
                            <textarea name="keywords_text" 
                                      id="keywords-text" 
                                      class="form-control" 
                                      style="min-height: 300px; font-family: monospace;"
                                      placeholder="<?php _e('keywords_placeholder'); ?>
华为
苹果
+发布
!广告

AI
ChatGPT
+技术
@10"><?php echo sanitize($keywordsText); ?></textarea>
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary"><?php _e('save_keywords'); ?></button>
                            <button type="button" class="btn btn-secondary" onclick="previewKeywords()"><?php _e('preview'); ?></button>
                            <button type="button" class="btn btn-outline" onclick="clearKeywords()"><?php _e('clear_all'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Preview Section -->
            <div class="card" id="preview-card" style="display: none;">
                <div class="card-header">
                    <h3><?php _e('keywords_preview'); ?></h3>
                </div>
                <div class="card-body" id="preview-content">
                </div>
            </div>
            
            <!-- Example Configurations -->
            <div class="card">
                <div class="card-header">
                    <h3><?php _e('example_configurations'); ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <h4><?php _e('tech_ai_monitoring'); ?></h4>
                            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px;">AI
人工智能
ChatGPT
+技术
!培训
!广告
@15

苹果
华为
小米
+发布
+新品</pre>
                            <button class="btn btn-outline btn-sm mt-2" onclick="loadExample('tech')"><?php _e('load_this_example'); ?></button>
                        </div>
                        <div class="col-4">
                            <h4><?php _e('finance_stock'); ?></h4>
                            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px;">A股
上证
深证
+涨跌
!预测

比特币
以太坊
加密货币
@10

特斯拉
比亚迪
新能源</pre>
                            <button class="btn btn-outline btn-sm mt-2" onclick="loadExample('finance')"><?php _e('load_this_example'); ?></button>
                        </div>
                        <div class="col-4">
                            <h4><?php _e('entertainment'); ?></h4>
                            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px;">电影
电视剧
综艺
+热播
!预告

游戏
王者荣耀
原神
@8

明星
娱乐
!八卦</pre>
                            <button class="btn btn-outline btn-sm mt-2" onclick="loadExample('entertainment')"><?php _e('load_this_example'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        const examples = {
            tech: `AI
人工智能
ChatGPT
+技术
!培训
!广告
@15

苹果
华为
小米
+发布
+新品`,
            finance: `A股
上证
深证
+涨跌
!预测

比特币
以太坊
加密货币
@10

特斯拉
比亚迪
新能源`,
            entertainment: `电影
电视剧
综艺
+热播
!预告

游戏
王者荣耀
原神
@8

明星
娱乐
!八卦`
        };
        
        function loadExample(type) {
            if (confirm(__('replace_keywords_confirm'))) {
                document.getElementById('keywords-text').value = examples[type];
                showToast(__('example_loaded'), 'info');
            }
        }
        
        function clearKeywords() {
            if (confirm(__('clear_keywords_confirm'))) {
                document.getElementById('keywords-text').value = '';
                showToast(__('keywords_cleared'), 'info');
            }
        }
        
        function previewKeywords() {
            const text = document.getElementById('keywords-text').value;
            const lines = text.split('\n');
            const previewCard = document.getElementById('preview-card');
            const previewContent = document.getElementById('preview-content');
            
            let html = '';
            let currentGroup = [];
            let groupIndex = 0;
            
            lines.forEach((line, index) => {
                const trimmed = line.trim();
                
                if (trimmed === '') {
                    if (currentGroup.length > 0) {
                        html += renderGroup(currentGroup, groupIndex);
                        currentGroup = [];
                        groupIndex++;
                    }
                } else {
                    currentGroup.push(trimmed);
                }
            });
            
            // Render last group
            if (currentGroup.length > 0) {
                html += renderGroup(currentGroup, groupIndex);
            }
            
            if (html === '') {
                html = '<div class="empty-state"><p>' + __('no_keywords_preview') + '</p></div>';
            }
            
            previewContent.innerHTML = html;
            previewCard.style.display = 'block';
        }
        
        function renderGroup(keywords, index) {
            let html = `<div class="mb-4">
                <h4>${__('group')} ${index + 1}</h4>
                <div class="keyword-tags">`;
            
            keywords.forEach(kw => {
                let type = '';
                let display = kw;
                
                if (kw.startsWith('+')) {
                    type = 'required';
                    display = kw.substring(1);
                } else if (kw.startsWith('!')) {
                    type = 'filter';
                    display = kw.substring(1);
                } else if (kw.startsWith('@')) {
                    type = 'limit';
                    display = 'Limit: ' + kw.substring(1);
                }
                
                html += `<span class="keyword-tag ${type}">${escapeHtml(display)}</span>`;
            });
            
            html += '</div></div>';
            return html;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Form submission
        document.getElementById('keywords-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const configId = document.getElementById('config-id').value;
            const keywordsText = document.getElementById('keywords-text').value;
            
            try {
                await apiRequest('api/keywords.php', 'POST', {
                    config_id: configId,
                    keywords_text: keywordsText
                });
                showToast(__('keywords_saved'), 'success');
            } catch (error) {
                showToast(__('failed_to_save') + ': ' + error.message, 'error');
            }
        });
    </script>
</body>
</html>
