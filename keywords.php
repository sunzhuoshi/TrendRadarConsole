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
        setFlash('warning', 'Please create and activate a configuration first.');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>TrendRadarConsole - Keywords</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2>Keywords Configuration</h2>
                <p>Define keywords to filter and categorize hot topics</p>
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
                    <h3>Keyword Syntax Guide</h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Syntax</th>
                                <th>Description</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-secondary">Normal</span></td>
                                <td><code>keyword</code></td>
                                <td>Basic matching - includes any news containing this keyword</td>
                                <td><code>华为</code>, <code>AI</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-info">Required</span></td>
                                <td><code>+keyword</code></td>
                                <td>Must include - news must contain this word along with normal keywords</td>
                                <td><code>+发布</code>, <code>+技术</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-danger">Filter</span></td>
                                <td><code>!keyword</code></td>
                                <td>Exclude - news containing this word will be filtered out</td>
                                <td><code>!广告</code>, <code>!预测</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-warning">Limit</span></td>
                                <td><code>@number</code></td>
                                <td>Limit the number of news items for this keyword group</td>
                                <td><code>@10</code>, <code>@5</code></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-muted mt-3">
                        <strong>Tip:</strong> Use empty lines to separate different keyword groups. Each group is processed independently.
                    </p>
                </div>
            </div>
            
            <!-- Keywords Editor -->
            <div class="card">
                <div class="card-header">
                    <h3>Keywords Editor</h3>
                </div>
                <div class="card-body">
                    <form id="keywords-form">
                        <div class="form-group">
                            <label class="form-label">
                                Enter your keywords (one per line, separate groups with empty lines)
                            </label>
                            <textarea name="keywords_text" 
                                      id="keywords-text" 
                                      class="form-control" 
                                      style="min-height: 300px; font-family: monospace;"
                                      placeholder="Example:
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
                            <button type="submit" class="btn btn-primary">Save Keywords</button>
                            <button type="button" class="btn btn-secondary" onclick="previewKeywords()">Preview</button>
                            <button type="button" class="btn btn-outline" onclick="clearKeywords()">Clear All</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Preview Section -->
            <div class="card" id="preview-card" style="display: none;">
                <div class="card-header">
                    <h3>Keywords Preview</h3>
                </div>
                <div class="card-body" id="preview-content">
                </div>
            </div>
            
            <!-- Example Configurations -->
            <div class="card">
                <div class="card-header">
                    <h3>Example Configurations</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <h4>Tech & AI Monitoring</h4>
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
                            <button class="btn btn-outline btn-sm mt-2" onclick="loadExample('tech')">Load This Example</button>
                        </div>
                        <div class="col-4">
                            <h4>Finance & Stock</h4>
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
                            <button class="btn btn-outline btn-sm mt-2" onclick="loadExample('finance')">Load This Example</button>
                        </div>
                        <div class="col-4">
                            <h4>Entertainment</h4>
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
                            <button class="btn btn-outline btn-sm mt-2" onclick="loadExample('entertainment')">Load This Example</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
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
            if (confirm('This will replace your current keywords. Continue?')) {
                document.getElementById('keywords-text').value = examples[type];
                showToast('Example loaded', 'info');
            }
        }
        
        function clearKeywords() {
            if (confirm('Are you sure you want to clear all keywords?')) {
                document.getElementById('keywords-text').value = '';
                showToast('Keywords cleared', 'info');
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
                html = '<div class="empty-state"><p>No keywords to preview</p></div>';
            }
            
            previewContent.innerHTML = html;
            previewCard.style.display = 'block';
        }
        
        function renderGroup(keywords, index) {
            let html = `<div class="mb-4">
                <h4>Group ${index + 1}</h4>
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
                showToast('Keywords saved successfully', 'success');
            } catch (error) {
                showToast('Failed to save: ' + error.message, 'error');
            }
        });
    </script>
</body>
</html>
