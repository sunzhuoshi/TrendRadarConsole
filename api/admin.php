<?php
/**
 * TrendRadarConsole - Admin API
 * 
 * Handles admin operations:
 * - Grant/revoke admin role
 * - Toggle features
 */

header('Content-Type: application/json');

session_start();
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/operation_log.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = Auth::getUserId();
$auth = new Auth();

// Check if user is admin
if (!$auth->isAdmin($userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => __('only_admins_can_access')]);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    $opLog = new OperationLog($userId);
    
    switch ($action) {
        case 'grant_admin':
            $targetUserId = (int)($input['user_id'] ?? 0);
            if (!$targetUserId) {
                throw new Exception('Invalid user ID');
            }
            
            $auth->grantAdmin($targetUserId, $userId);
            
            // Log the action
            $opLog->log(
                OperationLog::ACTION_ADMIN_GRANT,
                OperationLog::TARGET_USER,
                $targetUserId,
                ['granted_by' => $userId]
            );
            
            echo json_encode(['success' => true, 'message' => __('admin_granted')]);
            break;
            
        case 'revoke_admin':
            $targetUserId = (int)($input['user_id'] ?? 0);
            if (!$targetUserId) {
                throw new Exception('Invalid user ID');
            }
            
            $auth->revokeAdmin($targetUserId, $userId);
            
            // Log the action
            $opLog->log(
                OperationLog::ACTION_ADMIN_REVOKE,
                OperationLog::TARGET_USER,
                $targetUserId,
                ['revoked_by' => $userId]
            );
            
            echo json_encode(['success' => true, 'message' => __('admin_revoked')]);
            break;
            
        case 'toggle_feature':
            $featureKey = $input['feature_key'] ?? '';
            $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : false;
            
            if (!$featureKey) {
                throw new Exception('Invalid feature key');
            }
            
            $auth->toggleFeature($featureKey, $enabled, $userId);
            
            // Log the action
            $opLog->log(
                OperationLog::ACTION_FEATURE_TOGGLE,
                OperationLog::TARGET_FEATURE,
                null,
                [
                    'feature_key' => $featureKey,
                    'enabled' => $enabled,
                    'toggled_by' => $userId
                ]
            );
            
            echo json_encode(['success' => true, 'message' => __('feature_toggled')]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
