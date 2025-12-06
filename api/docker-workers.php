<?php
/**
 * TrendRadarConsole - Docker Workers API Endpoint
 * Provides Docker worker management functionality
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/auth.php';
require_once '../includes/ssh.php';

header('Content-Type: application/json; charset=utf-8');

// Require login for API
if (!Auth::isLoggedIn()) {
    jsonError('Unauthorized', 401);
}
$userId = Auth::getUserId();

// Validate user ID is numeric
if (!is_numeric($userId) || $userId <= 0) {
    jsonError('Invalid user ID', 400);
}
$userId = (int)$userId;

$auth = new Auth();

// Check if advanced mode is enabled - required for worker management
$isAdvancedMode = $auth->isAdvancedModeEnabled($userId);

try {
    $method = getMethod();
    $input = getInput();
    
    // CSRF protection
    if ($method === 'POST') {
        $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            jsonError('Invalid CSRF token', 403);
        }
    }
    
    if ($method !== 'POST') {
        jsonError('Method not allowed', 405);
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'list':
            // List all available workers (own + public)
            $workers = $auth->getAvailableDockerWorkers($userId);
            jsonSuccess([
                'workers' => array_map(function($w) use ($userId) {
                    return [
                        'id' => (int)$w['id'],
                        'name' => $w['name'],
                        'ssh_host' => $w['ssh_host'],
                        'ssh_port' => (int)$w['ssh_port'],
                        'is_active' => (bool)$w['is_active'],
                        'is_public' => (bool)$w['is_public'],
                        'is_own' => $w['user_id'] == $userId,
                        'owner' => $w['owner_username'] ?? null
                    ];
                }, $workers)
            ], 'Workers listed');
            break;
            
        case 'get':
            // Get a specific worker
            $workerId = (int)($input['worker_id'] ?? 0);
            if (!$workerId) {
                jsonError('Worker ID is required');
            }
            
            $worker = $auth->getDockerWorkerById($workerId);
            if (!$worker) {
                jsonError('Worker not found', 404);
            }
            
            if (!$auth->canAccessDockerWorker($userId, $workerId)) {
                jsonError('Access denied', 403);
            }
            
            jsonSuccess([
                'worker' => [
                    'id' => (int)$worker['id'],
                    'name' => $worker['name'],
                    'ssh_host' => $worker['ssh_host'],
                    'ssh_port' => (int)$worker['ssh_port'],
                    'ssh_username' => $worker['ssh_username'],
                    'workspace_path' => $worker['workspace_path'],
                    'is_active' => (bool)$worker['is_active'],
                    'is_public' => (bool)$worker['is_public'],
                    'is_own' => $worker['user_id'] == $userId
                ]
            ], 'Worker retrieved');
            break;
            
        case 'create':
            // Create a new worker (requires advanced mode)
            if (!$isAdvancedMode) {
                jsonError('Advanced mode required', 403);
            }
            
            $name = trim($input['name'] ?? '');
            $host = trim($input['ssh_host'] ?? '');
            $port = (int)($input['ssh_port'] ?? 22);
            $username = trim($input['ssh_username'] ?? 'trendradarsrv');
            $password = $input['ssh_password'] ?? '';
            $workspacePath = trim($input['workspace_path'] ?? '/srv/trendradar');
            $isPublic = (bool)($input['is_public'] ?? false);
            
            if (empty($name)) {
                jsonError('Worker name is required');
            }
            if (empty($host)) {
                jsonError('SSH host is required');
            }
            if (empty($password)) {
                jsonError('SSH password is required');
            }
            if ($port < 1 || $port > 65535) {
                $port = 22;
            }
            
            // Validate workspace path
            if (!preg_match('/^\/[a-zA-Z0-9_\-\/]+$/', $workspacePath)) {
                jsonError('Invalid workspace path. Must be an absolute path.');
            }
            
            $workerId = $auth->createDockerWorker($userId, $name, $host, $port, $username, $password, $workspacePath, $isPublic);
            
            jsonSuccess([
                'worker_id' => $workerId,
                'message' => 'Worker created successfully'
            ], 'Worker created');
            break;
            
        case 'update':
            // Update an existing worker (requires advanced mode and ownership)
            if (!$isAdvancedMode) {
                jsonError('Advanced mode required', 403);
            }
            
            $workerId = (int)($input['worker_id'] ?? 0);
            if (!$workerId) {
                jsonError('Worker ID is required');
            }
            
            $worker = $auth->getDockerWorkerById($workerId);
            if (!$worker) {
                jsonError('Worker not found', 404);
            }
            
            // Only owner can update
            if ($worker['user_id'] != $userId) {
                jsonError('Only the owner can update this worker', 403);
            }
            
            $name = trim($input['name'] ?? '');
            $host = trim($input['ssh_host'] ?? '');
            $port = (int)($input['ssh_port'] ?? 22);
            $password = isset($input['ssh_password']) && $input['ssh_password'] !== '' ? $input['ssh_password'] : null;
            $isPublic = isset($input['is_public']) ? (bool)$input['is_public'] : null;
            
            if (empty($name)) {
                jsonError('Worker name is required');
            }
            if (empty($host)) {
                jsonError('SSH host is required');
            }
            if ($port < 1 || $port > 65535) {
                $port = 22;
            }
            
            $auth->updateDockerWorkerById($workerId, $name, $host, $port, $worker['ssh_username'], $password, null, $isPublic);
            
            jsonSuccess([
                'worker_id' => $workerId,
                'message' => 'Worker updated successfully'
            ], 'Worker updated');
            break;
            
        case 'delete':
            // Delete a worker (requires advanced mode and ownership)
            if (!$isAdvancedMode) {
                jsonError('Advanced mode required', 403);
            }
            
            $workerId = (int)($input['worker_id'] ?? 0);
            if (!$workerId) {
                jsonError('Worker ID is required');
            }
            
            $worker = $auth->getDockerWorkerById($workerId);
            if (!$worker) {
                jsonError('Worker not found', 404);
            }
            
            // Only owner can delete
            if ($worker['user_id'] != $userId) {
                jsonError('Only the owner can delete this worker', 403);
            }
            
            $auth->deleteDockerWorker($workerId, $userId);
            
            jsonSuccess([
                'message' => 'Worker deleted successfully'
            ], 'Worker deleted');
            break;
            
        case 'test':
            // Test connection to a worker
            $workerId = (int)($input['worker_id'] ?? 0);
            if (!$workerId) {
                jsonError('Worker ID is required');
            }
            
            $worker = $auth->getDockerWorkerById($workerId);
            if (!$worker) {
                jsonError('Worker not found', 404);
            }
            
            if (!$auth->canAccessDockerWorker($userId, $workerId)) {
                jsonError('Access denied', 403);
            }
            
            $ssh = new SSHHelper(
                $worker['ssh_host'],
                $worker['ssh_username'],
                $worker['ssh_password'],
                $worker['ssh_port']
            );
            
            $testResult = $ssh->testConnection();
            
            if ($testResult['success']) {
                // Also check Docker availability
                $dockerCheck = $ssh->checkDocker();
                if ($dockerCheck['success']) {
                    jsonSuccess([
                        'ssh_connected' => true,
                        'docker_available' => true,
                        'docker_version' => $dockerCheck['version']
                    ], 'Connection successful. Docker is available.');
                } else {
                    jsonSuccess([
                        'ssh_connected' => true,
                        'docker_available' => false,
                        'message' => $dockerCheck['message']
                    ], 'SSH connected but Docker is not available.');
                }
            } else {
                jsonError($testResult['message']);
            }
            break;
            
        case 'select':
            // Select a worker for the current session
            $workerId = (int)($input['worker_id'] ?? 0);
            if (!$workerId) {
                jsonError('Worker ID is required');
            }
            
            if (!$auth->canAccessDockerWorker($userId, $workerId)) {
                jsonError('Access denied', 403);
            }
            
            $auth->setSelectedDockerWorker($userId, $workerId);
            
            $worker = $auth->getDockerWorkerById($workerId);
            
            jsonSuccess([
                'worker_id' => $workerId,
                'worker_name' => $worker['name'],
                'message' => 'Worker selected'
            ], 'Worker selected');
            break;
            
        default:
            jsonError('Invalid action');
    }
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
