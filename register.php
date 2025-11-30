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
        $error = 'Username and password are required';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores';
    } else {
        try {
            $auth = new Auth();
            $userId = $auth->register($username, $password, $email);
            
            // Auto-login after registration
            $user = $auth->login($username, $password);
            if ($user) {
                $auth->startSession($user);
                header('Location: index.php');
                exit;
            }
            
            $success = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendRadarConsole - Register</title>
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
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🚀 TrendRadarConsole</h1>
            <p>Create your account</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            Registration successful! <a href="login.php">Click here to login</a>
        </div>
        <?php else: ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       pattern="[a-zA-Z0-9_]+" minlength="3"
                       required autofocus>
                <div class="form-text">Letters, numbers, and underscores only</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email <small>(optional)</small></label>
                <input type="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" minlength="6" required>
                <div class="form-text">At least 6 characters</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                Create Account
            </button>
        </form>
        
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <p>Already have an account? <a href="login.php">Sign In</a></p>
        </div>
    </div>
</body>
</html>
