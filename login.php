<?php
/**
 * TrendRadarConsole - Login Page
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = __('please_enter_username_password');
    } else {
        try {
            $auth = new Auth();
            $user = $auth->login($username, $password);
            
            if ($user) {
                $auth->startSession($user);
                header('Location: index.php');
                exit;
            } else {
                $error = __('invalid_username_password');
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('app_name'); ?> - <?php _e('login'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            margin: 20px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .login-header p {
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
    </style>
</head>
<body>
    <div class="language-toggle">
        <select onchange="switchLanguage(this.value)">
            <option value="zh" <?php echo $currentLang === 'zh' ? 'selected' : ''; ?>><?php _e('chinese'); ?></option>
            <option value="en" <?php echo $currentLang === 'en' ? 'selected' : ''; ?>><?php _e('english'); ?></option>
        </select>
    </div>
    
    <div class="login-container">
        <div class="login-header">
            <h1>🚀 <?php _e('app_name'); ?></h1>
            <p><?php _e('sign_in_to_manage'); ?></p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label class="form-label"><?php _e('username'); ?></label>
                <input type="text" name="username" class="form-control" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       required autofocus>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('password'); ?></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                <?php _e('sign_in'); ?>
            </button>
        </form>
        
        <div class="text-center mt-4">
            <p><?php _e('no_account'); ?> <a href="register.php"><?php _e('register'); ?></a></p>
        </div>
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
