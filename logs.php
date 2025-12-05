<?php
/**
 * TrendRadarConsole - Operation Logs Page
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/operation_log.php';
require_once 'includes/auth.php';

if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Require login
Auth::requireLogin();
$userId = Auth::getUserId();

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

try {
    $operationLog = new OperationLog($userId);
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 20;
    
    $logs = $operationLog->getAll($page, $perPage);
    $totalLogs = $operationLog->getCount();
    $totalPages = ceil($totalLogs / $perPage);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'logs';
$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>TrendRadarConsole - <?php _e('operation_logs'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .log-entry {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
        .log-entry:hover {
            background-color: #f8f9fa;
        }
        .log-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .log-icon.github { background-color: #f0f0f0; }
        .log-icon.config { background-color: #e3f2fd; }
        .log-icon.platform { background-color: #fff3e0; }
        .log-icon.keyword { background-color: #e8f5e9; }
        .log-icon.webhook { background-color: #fce4ec; }
        .log-icon.setting { background-color: #f3e5f5; }
        .log-content {
            flex: 1;
            min-width: 0;
        }
        .log-action {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 2px;
        }
        .log-details {
            font-size: 12px;
            color: var(--text-muted);
            word-break: break-word;
        }
        .log-time {
            font-size: 12px;
            color: var(--text-muted);
            flex-shrink: 0;
            text-align: right;
            min-width: 130px;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 4px;
            text-decoration: none;
        }
        .pagination a {
            background-color: var(--light-color);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }
        .pagination a:hover {
            background-color: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }
        .pagination span.current {
            background-color: var(--primary-color);
            color: #fff;
        }
        .pagination span.disabled {
            color: var(--text-muted);
            cursor: not-allowed;
        }
        .log-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .log-stat {
            background: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .log-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        .log-stat-label {
            font-size: 12px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2><?php _e('operation_logs'); ?></h2>
                <p><?php _e('operation_logs_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php else: ?>
            
            <!-- Stats -->
            <div class="log-stats">
                <div class="log-stat">
                    <div class="log-stat-value"><?php echo $totalLogs; ?></div>
                    <div class="log-stat-label"><?php _e('total_logs'); ?></div>
                </div>
            </div>
            
            <!-- Logs List -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 <?php _e('recent_operations'); ?></h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <p><?php _e('no_logs_yet'); ?></p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        $actionKey = OperationLog::getActionTextKey($log['action']);
                        $targetTypeKey = OperationLog::getTargetTypeTextKey($log['target_type']);
                        $details = $log['details'] ? json_decode($log['details'], true) : null;
                        
                        // Determine icon based on action/target
                        $iconClass = 'config';
                        $icon = '📝';
                        if (strpos($log['action'], 'github') !== false) {
                            $iconClass = 'github';
                            $icon = '🐙';
                        } elseif ($log['target_type'] === 'platform') {
                            $iconClass = 'platform';
                            $icon = '📡';
                        } elseif ($log['target_type'] === 'keyword') {
                            $iconClass = 'keyword';
                            $icon = '🔑';
                        } elseif ($log['target_type'] === 'webhook') {
                            $iconClass = 'webhook';
                            $icon = '🔔';
                        } elseif ($log['target_type'] === 'setting') {
                            $iconClass = 'setting';
                            $icon = '⚙️';
                        }
                    ?>
                    <div class="log-entry">
                        <div class="log-icon <?php echo htmlspecialchars($iconClass); ?>"><?php echo $icon; ?></div>
                        <div class="log-content">
                            <div class="log-action"><?php _e($actionKey); ?></div>
                            <div class="log-details">
                                <?php if ($targetTypeKey): ?>
                                    <span><?php _e($targetTypeKey); ?></span>
                                    <?php if ($log['target_id']): ?>
                                        <span>#<?php echo htmlspecialchars((string)(int)$log['target_id']); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($details): ?>
                                    <?php if (isset($details['name'])): ?>
                                        - <?php echo sanitize($details['name']); ?>
                                    <?php endif; ?>
                                    <?php if (isset($details['key'])): ?>
                                        - <?php echo sanitize($details['key']); ?>
                                    <?php endif; ?>
                                    <?php if (isset($details['type'])): ?>
                                        (<?php echo sanitize($details['type']); ?>)
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="log-time">
                            <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1">&laquo;</a>
                    <a href="?page=<?php echo $page - 1; ?>">&lsaquo;</a>
                <?php else: ?>
                    <span class="disabled">&laquo;</span>
                    <span class="disabled">&lsaquo;</span>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                    if ($i === $page):
                ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">&rsaquo;</a>
                    <a href="?page=<?php echo $totalPages; ?>">&raquo;</a>
                <?php else: ?>
                    <span class="disabled">&rsaquo;</span>
                    <span class="disabled">&raquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
</body>
</html>
