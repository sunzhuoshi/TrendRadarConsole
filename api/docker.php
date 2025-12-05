<?php
/**
 * TrendRadarConsole - Docker API Endpoint
 * Provides Docker container management functionality for local deployment
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

// Validate user ID is numeric (database IDs are always integers)
if (!is_numeric($userId) || $userId <= 0) {
    jsonError('Invalid user ID', 400);
}
$userId = (int)$userId;

// Docker settings are calculated based on user ID (not user-configurable)
// User ID is validated to be numeric, so it's safe for shell commands
$containerName = 'trend-radar-' . $userId;
$configPath = './workspace/' . $userId . '/config';
$outputPath = './workspace/' . $userId . '/output';
$dockerImage = 'wantcat/trendradar:latest';

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
        case 'get_settings':
            jsonSuccess([
                'container_name' => $containerName,
                'config_path' => $configPath,
                'output_path' => $outputPath,
                'docker_image' => $dockerImage
            ], 'Docker settings retrieved');
            break;
            
        case 'inspect':
            // Execute docker inspect command
            // Security: Container name is calculated from user ID (no user input)
            $escapedName = escapeshellarg($containerName);
            $output = [];
            $returnVar = 0;
            exec("docker inspect {$escapedName} 2>&1", $output, $returnVar);
            
            $outputStr = implode("\n", $output);
            
            if ($returnVar !== 0) {
                // Container not found or Docker not available
                jsonSuccess([
                    'status' => 'not_found',
                    'container_name' => $containerName,
                    'message' => $outputStr
                ], 'Container not found or Docker not available');
            } else {
                $inspectData = json_decode($outputStr, true);
                if ($inspectData && isset($inspectData[0])) {
                    $container = $inspectData[0];
                    $state = $container['State'] ?? [];
                    jsonSuccess([
                        'status' => 'found',
                        'container_name' => $containerName,
                        'state' => [
                            'status' => $state['Status'] ?? 'unknown',
                            'running' => $state['Running'] ?? false,
                            'started_at' => $state['StartedAt'] ?? null,
                            'finished_at' => $state['FinishedAt'] ?? null,
                            'exit_code' => $state['ExitCode'] ?? null,
                            'error' => $state['Error'] ?? ''
                        ],
                        'image' => $container['Config']['Image'] ?? '',
                        'created' => $container['Created'] ?? '',
                        'mounts' => array_map(function($m) {
                            return [
                                'source' => $m['Source'] ?? '',
                                'destination' => $m['Destination'] ?? '',
                                'type' => $m['Type'] ?? ''
                            ];
                        }, $container['Mounts'] ?? []),
                        'env' => $container['Config']['Env'] ?? []
                    ], 'Container inspected');
                } else {
                    jsonSuccess([
                        'status' => 'error',
                        'container_name' => $containerName,
                        'message' => 'Failed to parse inspect output'
                    ], 'Failed to parse inspect output');
                }
            }
            break;
            
        case 'logs':
            // Get container logs
            $tail = isset($input['tail']) ? (int)$input['tail'] : 100;
            
            // Limit tail lines
            if ($tail < 1) $tail = 100;
            if ($tail > 1000) $tail = 1000;
            
            // Execute docker logs command
            // Security: Container name is calculated from user ID (no user input)
            $escapedName = escapeshellarg($containerName);
            $escapedTail = escapeshellarg((string)$tail);
            $output = [];
            $returnVar = 0;
            exec("docker logs --tail {$escapedTail} {$escapedName} 2>&1", $output, $returnVar);
            
            $outputStr = implode("\n", $output);
            
            jsonSuccess([
                'container_name' => $containerName,
                'tail' => $tail,
                'logs' => $outputStr,
                'success' => $returnVar === 0
            ], $returnVar === 0 ? 'Logs retrieved' : 'Failed to retrieve logs');
            break;
            
        case 'generate_command':
            // Environment variables from input
            $envVars = [];
            $envKeys = [
                'FEISHU_WEBHOOK_URL', 'DINGTALK_WEBHOOK_URL', 'WEWORK_WEBHOOK_URL',
                'TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHAT_ID',
                'EMAIL_FROM', 'EMAIL_PASSWORD', 'EMAIL_TO',
                'CRON_SCHEDULE', 'RUN_MODE', 'IMMEDIATE_RUN'
            ];
            
            foreach ($envKeys as $key) {
                $inputKey = strtolower($key);
                if (isset($input[$inputKey]) && $input[$inputKey] !== '') {
                    $value = $input[$inputKey];
                    
                    // Validate specific environment variables
                    if ($key === 'RUN_MODE' && !in_array($value, ['cron', 'once'])) {
                        $value = 'cron';
                    }
                    if ($key === 'IMMEDIATE_RUN' && !in_array($value, ['true', 'false'])) {
                        $value = 'true';
                    }
                    // Validate cron schedule format (basic validation)
                    if ($key === 'CRON_SCHEDULE' && !preg_match('/^[\d\*\/\-,\s]+$/', $value)) {
                        continue; // Skip invalid cron schedules
                    }
                    
                    $envVars[$key] = $value;
                }
            }
            
            // Build docker run command
            // Note: Container name, paths, and image are calculated (not from user input)
            $cmd = "docker run -d --name " . escapeshellarg($containerName) . " \\\n";
            $cmd .= "  -v " . escapeshellarg($configPath) . ":/app/config:ro \\\n";
            $cmd .= "  -v " . escapeshellarg($outputPath) . ":/app/output \\\n";
            
            foreach ($envVars as $key => $value) {
                $cmd .= "  -e " . escapeshellarg($key . '=' . $value) . " \\\n";
            }
            
            $cmd .= "  " . escapeshellarg($dockerImage);
            
            jsonSuccess([
                'command' => $cmd,
                'container_name' => $containerName,
                'config_path' => $configPath,
                'output_path' => $outputPath,
                'docker_image' => $dockerImage
            ], 'Command generated');
            break;
            
        default:
            jsonError('Invalid action');
    }
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
