<?php
/**
 * TrendRadarConsole - Platforms API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/configuration.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Require login for API
if (!Auth::isLoggedIn()) {
    jsonError('Unauthorized', 401);
}
$userId = Auth::getUserId();

try {
    $config = new Configuration($userId);
    $method = getMethod();
    $input = getInput();
    
    // CSRF protection for state-changing operations
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
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
            $platforms = $config->getPlatforms($configId);
            jsonSuccess($platforms);
            break;
            
        case 'POST':
            $configId = isset($input['config_id']) ? (int)$input['config_id'] : null;
            $platformId = isset($input['platform_id']) ? trim($input['platform_id']) : '';
            $platformName = isset($input['platform_name']) ? trim($input['platform_name']) : '';
            
            if (!$configId || !$platformId || !$platformName) {
                jsonError('Configuration ID, platform ID, and platform name are required');
            }
            
            // Check if platform already exists
            $existing = $config->getPlatforms($configId);
            foreach ($existing as $p) {
                if ($p['platform_id'] === $platformId) {
                    jsonError('Platform already exists');
                }
            }
            
            $sortOrder = count($existing) + 1;
            $id = $config->addPlatform($configId, $platformId, $platformName, $sortOrder);
            jsonSuccess(['id' => $id], 'Platform added successfully');
            break;
            
        case 'PUT':
            $id = isset($input['id']) ? (int)$input['id'] : null;
            if (!$id) {
                jsonError('Platform ID is required');
            }
            
            $data = [];
            if (isset($input['platform_name'])) {
                $data['platform_name'] = trim($input['platform_name']);
            }
            if (isset($input['is_enabled'])) {
                $data['is_enabled'] = $input['is_enabled'] ? 1 : 0;
            }
            if (isset($input['sort_order'])) {
                $data['sort_order'] = (int)$input['sort_order'];
            }
            
            if (empty($data)) {
                jsonError('No data to update');
            }
            
            $config->updatePlatform($id, $data);
            jsonSuccess(null, 'Platform updated successfully');
            break;
            
        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) {
                jsonError('Platform ID is required');
            }
            
            $config->deletePlatform($id);
            jsonSuccess(null, 'Platform deleted successfully');
            break;
            
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
