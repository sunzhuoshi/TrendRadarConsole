<?php
/**
 * TrendRadarConsole - Installation Page
 */

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
        $error = 'Database name and username are required.';
    } else {
        // Test database connection
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Check if database exists, create if not
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
                $error = 'Failed to write config file. Please check directory permissions.';
            }
            
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    }
}

// Check if already installed
if (file_exists('config/config.php')) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - Installation</title>
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
    </style>
</head>
<body>
    <div class="install-container">
        <?php if ($success): ?>
        <div class="success-message">
            <div class="icon">✅</div>
            <h2>Installation Complete!</h2>
            <p class="mt-3">TrendRadarConsole has been installed successfully.</p>
            <a href="index.php" class="btn btn-primary btn-lg mt-4">Go to Dashboard</a>
        </div>
        <?php else: ?>
        <div class="install-header">
            <h1>🚀 TrendRadarConsole</h1>
            <p>Configure your database connection</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label class="form-label">Database Host</label>
                <input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Database Port</label>
                <input type="number" name="db_port" class="form-control" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Database Name</label>
                <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'trendradar_console'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Database Username</label>
                <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Database Password</label>
                <input type="password" name="db_pass" class="form-control" value="">
            </div>
            
            <div class="form-group">
                <label class="form-label">Timezone</label>
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
                Install TrendRadarConsole
            </button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
