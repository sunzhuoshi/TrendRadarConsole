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
    
    // Get Docker settings from user
    $auth = new Auth();
    $dockerSettings = $auth->getDockerSettings($userId);
    
    switch ($action) {
        case 'save_settings':
            // Save Docker settings
            $containerName = isset($input['container_name']) ? trim($input['container_name']) : '';
            $configPath = isset($input['config_path']) ? trim($input['config_path']) : '';
            $outputPath = isset($input['output_path']) ? trim($input['output_path']) : '';
            $dockerImage = isset($input['docker_image']) ? trim($input['docker_image']) : 'wantcat/trendradar:latest';
            
            // Validate container name (alphanumeric, underscore, hyphen)
            if ($containerName && !preg_match('/^[a-zA-Z0-9_-]+$/', $containerName)) {
                jsonError('Invalid container name. Use only letters, numbers, underscore and hyphen.');
            }
            
            $auth->updateDockerSettings($userId, $containerName, $configPath, $outputPath, $dockerImage);
            jsonSuccess(null, 'Docker settings saved');
            break;
            
        case 'get_settings':
            jsonSuccess($dockerSettings, 'Docker settings retrieved');
            break;
            
        case 'inspect':
            // Get container name from settings or input
            $containerName = $input['container_name'] ?? $dockerSettings['docker_container_name'] ?? '';
            if (!$containerName) {
                jsonError('Container name is required');
            }
            
            // Validate container name
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $containerName)) {
                jsonError('Invalid container name');
            }
            
            // Execute docker inspect command
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
            $containerName = $input['container_name'] ?? $dockerSettings['docker_container_name'] ?? '';
            $tail = isset($input['tail']) ? (int)$input['tail'] : 100;
            
            if (!$containerName) {
                jsonError('Container name is required');
            }
            
            // Validate container name
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $containerName)) {
                jsonError('Invalid container name');
            }
            
            // Limit tail lines
            if ($tail < 1) $tail = 100;
            if ($tail > 1000) $tail = 1000;
            
            // Execute docker logs command
            $escapedName = escapeshellarg($containerName);
            $output = [];
            $returnVar = 0;
            exec("docker logs --tail {$tail} {$escapedName} 2>&1", $output, $returnVar);
            
            $outputStr = implode("\n", $output);
            
            jsonSuccess([
                'container_name' => $containerName,
                'tail' => $tail,
                'logs' => $outputStr,
                'success' => $returnVar === 0
            ], $returnVar === 0 ? 'Logs retrieved' : 'Failed to retrieve logs');
            break;
            
        case 'generate_command':
            // Generate docker run command based on settings
            $containerName = $input['container_name'] ?? $dockerSettings['docker_container_name'] ?? 'trend-radar';
            $configPath = $input['config_path'] ?? $dockerSettings['docker_config_path'] ?? './config';
            $outputPath = $input['output_path'] ?? $dockerSettings['docker_output_path'] ?? './output';
            $dockerImage = $input['docker_image'] ?? $dockerSettings['docker_image'] ?? 'wantcat/trendradar:latest';
            
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
                    $envVars[$key] = $input[$inputKey];
                }
            }
            
            // Build docker run command
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
