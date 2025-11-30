<?php
/**
 * TrendRadarConsole - Installation Page
 */

require_once 'includes/helpers.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $port = intval($_POST['db_port'] ?? 3306);
    $database = trim($_POST['db_name'] ?? '');
    $username = trim($_POST['db_user'] ?? '');
    $password = $_POST['db_pass'] ?? '';
    $timezone = trim($_POST['timezone'] ?? 'Asia/Shanghai');
    
    // Validate inputs
    if (empty($database) || empty($username)) {
        $error = __('db_name_username_required');
    } elseif (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $database)) {
        // Validate database name to prevent SQL injection
        $error = __('invalid_database_name');
    } else {
        // Test database connection
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Database name is validated above, safe to use with backticks
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$database}`");
            
            // Import schema
            $schema = file_get_contents('sql/schema.sql');
            $pdo->exec($schema);
            
            // Create config file
            $configContent = "<?php\n/**\n * TrendRadarConsole - Database Configuration\n * Generated on " . date('Y-m-d H:i:s') . "\n */\n\nreturn [\n    'db' => [\n        'host' => " . var_export($host, true) . ",\n        'port' => {$port},\n        'database' => " . var_export($database, true) . ",\n        'username' => " . var_export($username, true) . ",\n        'password' => " . var_export($password, true) . ",\n        'charset' => 'utf8mb4'\n    ],\n    'app' => [\n        'debug' => false,\n        'timezone' => " . var_export($timezone, true) . ",\n        'base_url' => ''\n    ]\n];\n";
            
            if (file_put_contents('config/config.php', $configContent)) {
                $success = true;
            } else {
                $error = __('failed_write_config');
            }
            
        } catch (PDOException $e) {
            $error = __('database_connection_failed') . $e->getMessage();
        }
    }
}

// Check if already installed
if (file_exists('config/config.php')) {
    header('Location: index.php');
    exit;
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - <?php _e('installation'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .install-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
            padding: 40px;
        }
        .install-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .install-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .install-header p {
            color: var(--text-muted);
        }
        .success-message {
            text-align: center;
            padding: 40px 20px;
        }
        .success-message .icon {
            font-size: 64px;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="language-toggle">
        <select onchange="switchLanguage(this.value)">
            <option value="zh" <?php echo $currentLang === 'zh' ? 'selected' : ''; ?>><?php _e('chinese'); ?></option>
            <option value="en" <?php echo $currentLang === 'en' ? 'selected' : ''; ?>><?php _e('english'); ?></option>
        </select>
    </div>
    
    <div class="install-container">
        <?php if ($success): ?>
        <div class="success-message">
            <div class="icon">✅</div>
            <h2><?php _e('installation_complete'); ?></h2>
            <p class="mt-3"><?php _e('installation_success_msg'); ?></p>
            <a href="index.php" class="btn btn-primary btn-lg mt-4"><?php _e('go_to_dashboard'); ?></a>
        </div>
        <?php else: ?>
        <div class="install-header">
            <h1>🚀 TrendRadarConsole</h1>
            <p><?php _e('configure_database'); ?></p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label class="form-label"><?php _e('database_host'); ?></label>
                <input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('database_port'); ?></label>
                <input type="number" name="db_port" class="form-control" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('database_name'); ?></label>
                <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'trendradar_console'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('database_username'); ?></label>
                <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('database_password'); ?></label>
                <input type="password" name="db_pass" class="form-control" value="">
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('timezone'); ?></label>
                <select name="timezone" class="form-control">
                    <option value="Asia/Shanghai" <?php echo ($_POST['timezone'] ?? 'Asia/Shanghai') === 'Asia/Shanghai' ? 'selected' : ''; ?>>Asia/Shanghai (UTC+8)</option>
                    <option value="Asia/Tokyo" <?php echo ($_POST['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Asia/Tokyo (UTC+9)</option>
                    <option value="Asia/Hong_Kong" <?php echo ($_POST['timezone'] ?? '') === 'Asia/Hong_Kong' ? 'selected' : ''; ?>>Asia/Hong_Kong (UTC+8)</option>
                    <option value="UTC" <?php echo ($_POST['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC (UTC+0)</option>
                    <option value="America/New_York" <?php echo ($_POST['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (UTC-5)</option>
                    <option value="Europe/London" <?php echo ($_POST['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (UTC+0)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                <?php _e('install'); ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <script>
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
