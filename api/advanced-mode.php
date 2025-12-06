<?php
/**
 * TrendRadarConsole - Advanced Mode API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Require login for API
if (!Auth::isLoggedIn()) {
    jsonError('Unauthorized', 401);
}
$userId = Auth::getUserId();

try {
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
            $auth = new Auth();
            $advancedModeEnabled = $auth->isAdvancedModeEnabled($userId);
            jsonSuccess(['advanced_mode' => $advancedModeEnabled]);
            break;
            
        case 'POST':
            if (!isset($input['advanced_mode'])) {
                jsonError('advanced_mode parameter is required');
            }
            
            $advancedMode = filter_var($input['advanced_mode'], FILTER_VALIDATE_BOOLEAN);
            
            $auth = new Auth();
            $auth->setAdvancedMode($userId, $advancedMode);
            
            jsonSuccess(['advanced_mode' => $advancedMode], 'Advanced mode ' . ($advancedMode ? 'enabled' : 'disabled'));
            break;
            
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

