<?php
/**
 * TrendRadarConsole - Main Index Page
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/Configuration.php';

// Check if config file exists
if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

try {
    $config = new Configuration();
    $configurations = $config->getAll();
    $activeConfig = $config->getActive();
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2>Dashboard</h2>
                <p>Welcome to TrendRadarConsole - Manage your TrendRadar configurations</p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo sanitize($error); ?>
            </div>
            <?php else: ?>
            
            <!-- Stats Cards -->
            <div class="row">
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-card-icon primary">📋</div>
                        <div class="stat-card-value"><?php echo count($configurations); ?></div>
                        <div class="stat-card-label">Configurations</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-card-icon success">✓</div>
                        <div class="stat-card-value"><?php echo $activeConfig ? '1' : '0'; ?></div>
                        <div class="stat-card-label">Active Config</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-card-icon warning">📡</div>
                        <div class="stat-card-value">
                            <?php 
                            if ($activeConfig) {
                                $platforms = $config->getPlatforms($activeConfig['id']);
                                echo count(array_filter($platforms, function($p) { return $p['is_enabled']; }));
                            } else {
                                echo '0';
                            }
                            ?>
                        </div>
                        <div class="stat-card-label">Active Platforms</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="stat-card">
                        <div class="stat-card-icon info">🔔</div>
                        <div class="stat-card-value">
                            <?php 
                            if ($activeConfig) {
                                $webhooks = $config->getWebhooks($activeConfig['id']);
                                echo count(array_filter($webhooks, function($w) { return $w['is_enabled']; }));
                            } else {
                                echo '0';
                            }
                            ?>
                        </div>
                        <div class="stat-card-label">Active Webhooks</div>
                    </div>
                </div>
            </div>
            
            <!-- Configurations List -->
            <div class="card">
                <div class="card-header">
                    <h3>Configurations</h3>
                    <a href="config-edit.php" class="btn btn-primary btn-sm">+ New Configuration</a>
                </div>
                <div class="card-body">
                    <?php if (empty($configurations)): ?>
                    <div class="empty-state">
                        <p>No configurations yet.</p>
                        <a href="config-edit.php" class="btn btn-primary">Create Your First Configuration</a>
                    </div>
                    <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configurations as $cfg): ?>
                            <tr>
                                <td>
                                    <a href="config-edit.php?id=<?php echo $cfg['id']; ?>">
                                        <strong><?php echo sanitize($cfg['name']); ?></strong>
                                    </a>
                                </td>
                                <td><?php echo sanitize($cfg['description'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($cfg['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($cfg['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="config-edit.php?id=<?php echo $cfg['id']; ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <?php if (!$cfg['is_active']): ?>
                                    <form method="post" action="api/config-action.php" style="display:inline;">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="id" value="<?php echo $cfg['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Activate</button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="export.php?id=<?php echo $cfg['id']; ?>" class="btn btn-secondary btn-sm">Export</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Start Guide -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Start Guide</h3>
                </div>
                <div class="card-body">
                    <ol style="line-height: 2;">
                        <li>Create or select a configuration</li>
                        <li>Configure your monitoring platforms (e.g., Weibo, Zhihu, Toutiao)</li>
                        <li>Set up keywords to filter news by your interests</li>
                        <li>Configure webhook notifications (WeChat Work, Feishu, Telegram, etc.)</li>
                        <li>Adjust report settings (mode, weights, etc.)</li>
                        <li>Export the configuration files for use with TrendRadar</li>
                    </ol>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
