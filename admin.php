<?php
/**
 * TrendRadarConsole - Admin Panel
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
require_once 'includes/operation_log.php';

if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Require login
Auth::requireLogin();
$userId = Auth::getUserId();

// Check if user is admin
$auth = new Auth();
if (!$auth->isAdmin($userId)) {
    setFlash('error', __('only_admins_can_access'));
    header('Location: index.php');
    exit;
}

// Get all users
try {
    $users = $auth->getAllUsers();
    $featureToggles = $auth->getAllFeatureToggles();
} catch (Exception $e) {
    $error = $e->getMessage();
}

$flash = getFlash();
$currentPage = 'admin';
$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title><?php _e('app_name'); ?> - <?php _e('admin_panel'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-section {
            margin-bottom: 40px;
        }
        .admin-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .users-table, .features-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .users-table th, .users-table td,
        .features-table th, .features-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .users-table th, .features-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .users-table tr:hover, .features-table tr:hover {
            background-color: #f8f9fa;
        }
        .admin-badge-table {
            background: #f44336;
            color: #fff;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        .btn-grant {
            background: #28a745;
            color: white;
        }
        .btn-grant:hover {
            background: #218838;
        }
        .btn-revoke {
            background: #dc3545;
            color: white;
        }
        .btn-revoke:hover {
            background: #c82333;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #28a745;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box-icon {
            display: inline-block;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2>🔑 <?php _e('admin_panel'); ?></h2>
                <p><?php _e('admin_panel_desc'); ?></p>
            </div>

            <?php if (isset($flash) && $flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="flash-message flash-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- User Management Section -->
            <div class="admin-section">
                <div class="card">
                    <div class="admin-section-header">
                        <h3>👥 <?php _e('user_management'); ?></h3>
                    </div>
                    <p><?php _e('user_management_desc'); ?></p>
                    
                    <div class="info-box">
                        <span class="info-box-icon">💡</span>
                        <?php _e('first_user_is_admin'); ?>
                    </div>

                    <table class="users-table">
                        <thead>
                            <tr>
                                <th><?php _e('user_id'); ?></th>
                                <th><?php _e('username'); ?></th>
                                <th><?php _e('email'); ?></th>
                                <th><?php _e('is_admin'); ?></th>
                                <th><?php _e('last_login'); ?></th>
                                <th><?php _e('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($user['is_admin']): ?>
                                                <span class="admin-badge-table"><?php _e('admin_badge'); ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($user['last_login']) {
                                                echo htmlspecialchars(date('Y-m-d H:i', strtotime($user['last_login'])));
                                            } else {
                                                _e('never');
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!$user['is_admin']): ?>
                                                <button class="btn btn-grant btn-small" onclick="grantAdmin(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">
                                                    <?php _e('grant_admin'); ?>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-revoke btn-small" onclick="revokeAdmin(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" <?php echo ((int)$user['id'] === (int)$userId || (int)$user['id'] === 1) ? 'disabled' : ''; ?>>
                                                    <?php _e('revoke_admin'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">
                                        <?php _e('no_users_found'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feature Toggles Section -->
            <div class="admin-section">
                <div class="card">
                    <div class="admin-section-header">
                        <h3>⚙️ <?php _e('feature_toggles'); ?></h3>
                    </div>
                    <p><?php _e('feature_toggles_desc'); ?></p>

                    <table class="features-table">
                        <thead>
                            <tr>
                                <th><?php _e('feature_key'); ?></th>
                                <th><?php _e('description'); ?></th>
                                <th><?php _e('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($featureToggles)): ?>
                                <?php foreach ($featureToggles as $feature): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($feature['feature_key']); ?></code></td>
                                        <td><?php echo htmlspecialchars($feature['description'] ?? '-'); ?></td>
                                        <td>
                                            <label class="toggle-switch">
                                                <input type="checkbox" 
                                                       <?php echo $feature['is_enabled'] ? 'checked' : ''; ?>
                                                       onchange="toggleFeature('<?php echo htmlspecialchars($feature['feature_key'], ENT_QUOTES); ?>', this.checked)">
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 20px;">
                                        <?php _e('no_features_found'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Helper function to handle API responses with proper error logging
        function handleApiResponse(response) {
            return response.json()
                .then(data => ({
                    ok: response.ok,
                    status: response.status,
                    data: data
                }))
                .catch(parseError => {
                    // If JSON parsing fails, return error info
                    console.error('Failed to parse JSON response:', parseError);
                    return {
                        ok: false,
                        status: response.status,
                        data: { success: false, message: 'Invalid response format' }
                    };
                });
        }

        function grantAdmin(userId, username) {
            if (!confirm('<?php _e('confirm_grant_admin'); ?>')) {
                return;
            }

            fetch('api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify({
                    action: 'grant_admin',
                    user_id: userId
                })
            })
            .then(handleApiResponse)
            .then(({ok, status, data}) => {
                if (ok && data && data.success) {
                    showFlash('success', '<?php _e('admin_granted'); ?>');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    console.error('Grant admin failed:', status, data);
                    showFlash('error', (data && data.message) || '<?php _e('admin_grant_failed'); ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFlash('error', '<?php _e('admin_grant_failed'); ?>');
            });
        }

        function revokeAdmin(userId, username) {
            if (!confirm('<?php _e('confirm_revoke_admin'); ?>')) {
                return;
            }

            fetch('api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify({
                    action: 'revoke_admin',
                    user_id: userId
                })
            })
            .then(handleApiResponse)
            .then(({ok, status, data}) => {
                if (ok && data && data.success) {
                    showFlash('success', '<?php _e('admin_revoked'); ?>');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    console.error('Revoke admin failed:', status, data);
                    showFlash('error', (data && data.message) || '<?php _e('admin_revoke_failed'); ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFlash('error', '<?php _e('admin_revoke_failed'); ?>');
            });
        }

        function toggleFeature(featureKey, enabled) {
            fetch('api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify({
                    action: 'toggle_feature',
                    feature_key: featureKey,
                    enabled: enabled
                })
            })
            .then(handleApiResponse)
            .then(({ok, status, data}) => {
                if (ok && data && data.success) {
                    showFlash('success', '<?php _e('feature_toggled'); ?>');
                } else {
                    console.error('Toggle failed:', status, data);
                    showFlash('error', (data && data.message) || '<?php _e('feature_toggle_failed'); ?>');
                    // Reload to reset toggle state
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFlash('error', '<?php _e('feature_toggle_failed'); ?>');
                // Reload to reset toggle state
                setTimeout(() => location.reload(), 1000);
            });
        }

        function showFlash(type, message) {
            const existingFlash = document.querySelector('.flash-message');
            if (existingFlash) {
                existingFlash.remove();
            }

            const flash = document.createElement('div');
            flash.className = `flash-message flash-${type}`;
            flash.textContent = message;
            
            const mainContent = document.querySelector('.main-content');
            mainContent.insertBefore(flash, mainContent.firstChild);

            setTimeout(() => flash.remove(), 5000);
        }
    </script>

    <script src="assets/js/app.js"></script>
</body>
</html>
