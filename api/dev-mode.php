<?php
/**
 * TrendRadarConsole - Development Mode API
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
            $devModeEnabled = $auth->isDevModeEnabled($userId);
            jsonSuccess(['dev_mode' => $devModeEnabled]);
            break;
            
        case 'POST':
            if (!isset($input['dev_mode'])) {
                jsonError('dev_mode parameter is required');
            }
            
            $devMode = filter_var($input['dev_mode'], FILTER_VALIDATE_BOOLEAN);
            
            $auth = new Auth();
            $auth->setDevMode($userId, $devMode);
            
            jsonSuccess(['dev_mode' => $devMode], 'Development mode ' . ($devMode ? 'enabled' : 'disabled'));
            break;
            
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
