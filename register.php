<?php
/**
 * TrendRadarConsole - Registration Page
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
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '') ?: null;
    
    if (empty($username) || empty($password)) {
        $error = __('username_password_required');
    } elseif (strlen($username) < 3) {
        $error = __('username_min_chars');
    } elseif (strlen($password) < 6) {
        $error = __('password_min_chars');
    } elseif ($password !== $confirmPassword) {
        $error = __('passwords_not_match');
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = __('username_invalid_chars');
    } else {
        try {
            $auth = new Auth();
            $userId = $auth->register($username, $password, $email);
            
            // Auto-login after registration
            $user = $auth->login($username, $password);
            if ($user) {
                $auth->startSession($user);
                // Redirect to dashboard after registration
                header('Location: index.php');
                exit;
            }
            
            $success = true;
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
    <title><?php _e('app_name'); ?> - <?php _e('register'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .register-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            margin: 20px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .register-header p {
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
    
    <div class="register-container">
        <div class="register-header">
            <h1>🚀 <?php _e('app_name'); ?></h1>
            <p><?php _e('create_account'); ?></p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php _e('registration_success'); ?> <a href="login.php"><?php _e('click_to_login'); ?></a>
        </div>
        <?php else: ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label class="form-label"><?php _e('username'); ?> <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       pattern="[a-zA-Z0-9_]+" minlength="3"
                       required autofocus>
                <div class="form-text"><?php _e('letters_numbers_underscores_only'); ?></div>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('email_optional'); ?></label>
                <input type="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('password'); ?> <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" minlength="6" required>
                <div class="form-text"><?php _e('at_least_6_characters'); ?></div>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php _e('confirm_password'); ?> <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                <?php _e('create_account'); ?>
            </button>
        </form>
        
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <p><?php _e('already_have_account'); ?> <a href="login.php"><?php _e('sign_in'); ?></a></p>
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
