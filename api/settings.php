<?php
/**
 * TrendRadarConsole - Settings API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/configuration.php';
require_once '../includes/auth.php';
require_once '../includes/operation_log.php';

header('Content-Type: application/json; charset=utf-8');

// Require login for API
if (!Auth::isLoggedIn()) {
    jsonError('Unauthorized', 401);
}
$userId = Auth::getUserId();

try {
    $config = new Configuration($userId);
    $opLog = new OperationLog($userId);
    $method = getMethod();
    $input = getInput();
    
    // CSRF protection for state-changing operations
    if ($method === 'POST') {
        $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            jsonError('Invalid CSRF token', 403);
        }
    }
    
    switch ($method) {
        case 'GET':
            $configId = isset($_GET['config_id']) ? (int)$_GET['config_id'] : null;
            if (!$configId) {
                jsonError('Configuration ID is required');
            }
            $settings = $config->getSettings($configId);
            jsonSuccess($settings);
            break;
            
        case 'POST':
            $configId = isset($input['config_id']) ? (int)$input['config_id'] : null;
            
            if (!$configId) {
                jsonError('Configuration ID is required');
            }
            
            // Handle single setting update
            if (isset($input['key']) && isset($input['value'])) {
                $config->saveSetting($configId, $input['key'], $input['value']);
                
                // Log the operation
                $opLog->log(
                    OperationLog::ACTION_SETTING_UPDATE,
                    OperationLog::TARGET_SETTING,
                    $configId,
                    ['key' => $input['key']]
                );
                
                jsonSuccess(null, 'Setting updated successfully');
            }
            
            // Handle multiple settings update
            if (isset($input['settings']) && is_array($input['settings'])) {
                foreach ($input['settings'] as $key => $value) {
                    $config->saveSetting($configId, $key, $value);
                }
                
                // Log the operation
                $opLog->log(
                    OperationLog::ACTION_SETTINGS_SAVE,
                    OperationLog::TARGET_SETTING,
                    $configId,
                    ['count' => count($input['settings'])]
                );
                
                jsonSuccess(null, 'Settings saved successfully');
            }
            
            jsonError('No settings provided');
            break;
            
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
