<?php
/**
 * TrendRadarConsole - Operation Logs API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/operation_log.php';
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
    
    // CSRF protection for POST/DELETE requests
    if ($method === 'POST' || $method === 'DELETE') {
        $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            jsonError('Invalid CSRF token', 403);
        }
    }
    
    $operationLog = new OperationLog($userId);
    
    switch ($method) {
        case 'GET':
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
            
            $logs = $operationLog->getAll($page, $perPage);
            $total = $operationLog->getCount();
            
            jsonSuccess([
                'logs' => $logs,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);
            break;
            
        case 'DELETE':
            // Clear old logs (keep last 30 days by default)
            $days = isset($input['days']) ? max(1, (int)$input['days']) : 30;
            $deleted = $operationLog->clearOldLogs($days);
            jsonSuccess(['deleted' => $deleted], 'Old logs cleared');
            break;
            
        default:
            jsonError('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
