<?php
/**
 * TrendRadarConsole - Settings Page
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
$currentPage = 'settings';

// Default settings
$defaults = [
    'report_mode' => 'incremental',
    'rank_threshold' => '5',
    'sort_by_position_first' => 'false',
    'max_news_per_keyword' => '0',
    'rank_weight' => '0.6',
    'frequency_weight' => '0.3',
    'hotness_weight' => '0.1',
    'enable_crawler' => 'true',
    'enable_notification' => 'true',
    'push_window_enabled' => 'false',
    'push_window_start' => '20:00',
    'push_window_end' => '22:00',
    'push_window_once_per_day' => 'true',
];

// Merge with defaults
$settings = array_merge($defaults, $settings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - Settings</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <input type="hidden" id="config-id" value="<?php echo $activeConfig['id']; ?>">
            
            <div class="page-header">
                <h2>Configuration Settings</h2>
                <p>Adjust report mode, weights, and notification settings</p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <form id="settings-form">
                <!-- Report Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>📊 Report Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Report Mode</label>
                                    <select name="report_mode" class="form-control">
                                        <option value="daily" <?php echo $settings['report_mode'] === 'daily' ? 'selected' : ''; ?>>
                                            Daily Summary (当日汇总)
                                        </option>
                                        <option value="current" <?php echo $settings['report_mode'] === 'current' ? 'selected' : ''; ?>>
                                            Current List (当前榜单)
                                        </option>
                                        <option value="incremental" <?php echo $settings['report_mode'] === 'incremental' ? 'selected' : ''; ?>>
                                            Incremental (增量监控)
                                        </option>
                                    </select>
                                    <div class="form-text">
                                        <strong>Daily:</strong> All matching news of the day<br>
                                        <strong>Current:</strong> Current hot list<br>
                                        <strong>Incremental:</strong> Only new items (no duplicates)
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Rank Threshold</label>
                                    <input type="number" name="rank_threshold" class="form-control" 
                                           value="<?php echo sanitize($settings['rank_threshold']); ?>" min="1" max="50">
                                    <div class="form-text">Items with rank ≤ this value will be highlighted</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Sort Priority</label>
                                    <select name="sort_by_position_first" class="form-control">
                                        <option value="false" <?php echo $settings['sort_by_position_first'] === 'false' ? 'selected' : ''; ?>>
                                            By News Count (热点条数优先)
                                        </option>
                                        <option value="true" <?php echo $settings['sort_by_position_first'] === 'true' ? 'selected' : ''; ?>>
                                            By Config Position (配置顺序优先)
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Max News Per Keyword</label>
                                    <input type="number" name="max_news_per_keyword" class="form-control" 
                                           value="<?php echo sanitize($settings['max_news_per_keyword']); ?>" min="0">
                                    <div class="form-text">0 = no limit</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weight Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>⚖️ Hot Topic Weight Settings</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            These weights determine how news items are ranked. The sum must equal 1.0.
                        </p>
                        <div class="row">
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Rank Weight (排名权重)</label>
                                    <input type="number" name="rank_weight" class="form-control weight-input" 
                                           value="<?php echo sanitize($settings['rank_weight']); ?>" 
                                           min="0" max="1" step="0.1">
                                    <div class="form-text">Higher = prioritize top-ranked news</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Frequency Weight (频次权重)</label>
                                    <input type="number" name="frequency_weight" class="form-control weight-input" 
                                           value="<?php echo sanitize($settings['frequency_weight']); ?>" 
                                           min="0" max="1" step="0.1">
                                    <div class="form-text">Higher = prioritize frequently appearing news</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Hotness Weight (热度权重)</label>
                                    <input type="number" name="hotness_weight" class="form-control weight-input" 
                                           value="<?php echo sanitize($settings['hotness_weight']); ?>" 
                                           min="0" max="1" step="0.1">
                                    <div class="form-text">Higher = prioritize trending topics</div>
                                </div>
                            </div>
                        </div>
                        <div id="weight-warning" class="alert alert-warning" style="display: none;">
                            The sum of weights must equal 1.0
                        </div>
                    </div>
                </div>
                
                <!-- System Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>🔧 System Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Enable Crawler</label>
                                    <select name="enable_crawler" class="form-control">
                                        <option value="true" <?php echo $settings['enable_crawler'] === 'true' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="false" <?php echo $settings['enable_crawler'] === 'false' ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Enable Notifications</label>
                                    <select name="enable_notification" class="form-control">
                                        <option value="true" <?php echo $settings['enable_notification'] === 'true' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="false" <?php echo $settings['enable_notification'] === 'false' ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Push Window Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>🕐 Push Time Window</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Enable Push Time Window</label>
                            <select name="push_window_enabled" class="form-control" id="push-window-toggle">
                                <option value="false" <?php echo $settings['push_window_enabled'] === 'false' ? 'selected' : ''; ?>>Disabled</option>
                                <option value="true" <?php echo $settings['push_window_enabled'] === 'true' ? 'selected' : ''; ?>>Enabled</option>
                            </select>
                            <div class="form-text">Limit notifications to a specific time window</div>
                        </div>
                        
                        <div id="push-window-options" style="<?php echo $settings['push_window_enabled'] === 'true' ? '' : 'display:none;'; ?>">
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label">Start Time</label>
                                        <input type="time" name="push_window_start" class="form-control" 
                                               value="<?php echo sanitize($settings['push_window_start']); ?>">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label">End Time</label>
                                        <input type="time" name="push_window_end" class="form-control" 
                                               value="<?php echo sanitize($settings['push_window_end']); ?>">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label">Push Frequency</label>
                                        <select name="push_window_once_per_day" class="form-control">
                                            <option value="true" <?php echo $settings['push_window_once_per_day'] === 'true' ? 'selected' : ''; ?>>
                                                Once per day
                                            </option>
                                            <option value="false" <?php echo $settings['push_window_once_per_day'] === 'false' ? 'selected' : ''; ?>>
                                                Every execution
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="card">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary btn-lg">Save All Settings</button>
                    </div>
                </div>
            </form>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Push window toggle
        document.getElementById('push-window-toggle').addEventListener('change', function() {
            const options = document.getElementById('push-window-options');
            options.style.display = this.value === 'true' ? 'block' : 'none';
        });
        
        // Weight validation
        document.querySelectorAll('.weight-input').forEach(input => {
            input.addEventListener('change', validateWeights);
        });
        
        function validateWeights() {
            const weights = document.querySelectorAll('.weight-input');
            let sum = 0;
            weights.forEach(w => sum += parseFloat(w.value) || 0);
            
            const warning = document.getElementById('weight-warning');
            if (Math.abs(sum - 1.0) > 0.01) {
                warning.style.display = 'block';
                warning.textContent = `Weight sum is ${sum.toFixed(2)} - should be 1.0`;
            } else {
                warning.style.display = 'none';
            }
        }
        
        // Form submission
        document.getElementById('settings-form').addEventListener('submit', async function(e) {
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
                showToast('Settings saved successfully', 'success');
            } catch (error) {
                showToast('Failed to save: ' + error.message, 'error');
            }
        });
    </script>
</body>
</html>
