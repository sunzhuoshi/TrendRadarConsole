<?php
/**
 * TrendRadarConsole - Docker API Endpoint
 * Provides Docker container management functionality via SSH to remote Docker worker
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/auth.php';
require_once '../includes/ssh.php';
require_once '../includes/configuration.php';

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

// Get Docker SSH settings using selected worker
$auth = new Auth();
$selectedWorker = $auth->getSelectedDockerWorker($userId);

$sshSettings = [
    'docker_ssh_host' => $selectedWorker['ssh_host'] ?? '',
    'docker_ssh_port' => $selectedWorker['ssh_port'] ?? 22,
    'docker_ssh_username' => $selectedWorker['ssh_username'] ?? '',
    'docker_ssh_password' => $selectedWorker['ssh_password'] ?? '',
    'docker_workspace_path' => $selectedWorker['workspace_path'] ?? '/srv/trendradar',
];

$sshConfigured = !empty($sshSettings['docker_ssh_host']) && !empty($sshSettings['docker_ssh_username']);

// Docker settings are calculated based on user ID (not user-configurable)
// Environment suffix applied to container name and paths
$workspacePath = $sshSettings['docker_workspace_path'] ?: '/srv/trendradar';
// Add 'user-' prefix and '-dev' suffix based on deployment environment
$deploymentEnv = getDeploymentEnvironment();
$envSuffix = $deploymentEnv === 'development' ? '-dev' : '';
$containerName = 'trendradar-' . $userId . $envSuffix;
$userFolder = 'user-' . $userId . $envSuffix;
$configPath = $workspacePath . '/' . $userFolder . '/config';
$outputPath = $workspacePath . '/' . $userFolder . '/output';
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
    
    // Actions that don't require SSH connection
    switch ($action) {
        case 'get_settings':
            jsonSuccess([
                'container_name' => $containerName,
                'config_path' => $configPath,
                'output_path' => $outputPath,
                'docker_image' => $dockerImage,
                'ssh_configured' => $auth->isDockerSSHConfigured($userId),
                'ssh_host' => $sshSettings['docker_ssh_host'] ?: '',
                'ssh_port' => $sshSettings['docker_ssh_port'] ?: 22,
                'ssh_username' => $sshSettings['docker_ssh_username'] ?: '',
                'workspace_path' => $workspacePath
            ], 'Docker settings retrieved');
            break;
            
        case 'save_ssh_settings':
            // Save SSH connection settings
            $host = trim($input['ssh_host'] ?? '');
            $port = (int)($input['ssh_port'] ?? 22);
            $username = trim($input['ssh_username'] ?? '');
            $password = $input['ssh_password'] ?? null;
            $workspace = trim($input['workspace_path'] ?? '/srv/trendradar');
            
            if (empty($host)) {
                jsonError('SSH host is required');
            }
            if (empty($username)) {
                jsonError('SSH username is required');
            }
            if ($port < 1 || $port > 65535) {
                $port = 22;
            }
            
            // Validate workspace path
            if (!preg_match('/^\/[a-zA-Z0-9_\-\/]+$/', $workspace)) {
                jsonError('Invalid workspace path. Must be an absolute path.');
            }
            
            $auth->updateDockerSSHSettings($userId, $host, $port, $username, $password, $workspace);
            
            jsonSuccess([
                'ssh_host' => $host,
                'ssh_port' => $port,
                'ssh_username' => $username,
                'workspace_path' => $workspace
            ], 'SSH settings saved successfully');
            break;
            
        case 'test_connection':
            // Test SSH connection
            if (!$auth->isDockerSSHConfigured($userId)) {
                jsonError('SSH connection not configured. Please configure SSH settings first.');
            }
            
            $ssh = new SSHHelper(
                $sshSettings['docker_ssh_host'],
                $sshSettings['docker_ssh_username'],
                $sshSettings['docker_ssh_password'],
                $sshSettings['docker_ssh_port']
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
    }
    
    // Actions that require SSH connection
    if (!$sshConfigured) {
        jsonError('SSH connection not configured. Please configure SSH settings first.');
    }
    
    $ssh = new SSHHelper(
        $sshSettings['docker_ssh_host'],
        $sshSettings['docker_ssh_username'],
        $sshSettings['docker_ssh_password'],
        $sshSettings['docker_ssh_port']
    );
    
    if (!$ssh->connect()) {
        jsonError('Failed to connect to Docker worker: ' . $ssh->getLastError());
    }
    
    switch ($action) {
        case 'inspect':
            // Execute docker inspect command via SSH
            $escapedName = escapeshellarg($containerName);
            $result = $ssh->exec("docker inspect {$escapedName} 2>&1");
            
            if (!$result['success'] && strpos($result['output'], 'No such object') !== false) {
                // Container not found
                jsonSuccess([
                    'status' => 'not_found',
                    'container_name' => $containerName,
                    'message' => 'Container not found'
                ], 'Container not found');
            } else if ($result['success'] || !empty($result['output'])) {
                $inspectData = json_decode($result['output'], true);
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
                        'status' => 'not_found',
                        'container_name' => $containerName,
                        'message' => 'Container not found or failed to parse output'
                    ], 'Container not found');
                }
            } else {
                jsonError('Failed to inspect container: ' . $result['error']);
            }
            break;
            
        case 'logs':
            // Get container logs via SSH
            $tail = isset($input['tail']) ? (int)$input['tail'] : 100;
            
            // Limit tail lines
            if ($tail < 1) $tail = 100;
            if ($tail > 1000) $tail = 1000;
            
            $escapedName = escapeshellarg($containerName);
            $escapedTail = escapeshellarg((string)$tail);
            $result = $ssh->exec("docker logs --tail {$escapedTail} {$escapedName} 2>&1");
            
            jsonSuccess([
                'container_name' => $containerName,
                'tail' => $tail,
                'logs' => $result['output'],
                'success' => $result['success']
            ], $result['success'] ? 'Logs retrieved' : 'Failed to retrieve logs');
            break;
            
        case 'run':
            // Create and start a new container via SSH
            // First, ensure workspace directories exist on remote server
            $escapedConfigPath = escapeshellarg($configPath);
            $escapedOutputPath = escapeshellarg($outputPath);
            $ssh->exec("mkdir -p {$escapedConfigPath} {$escapedOutputPath}");
            
            // Generate config files from current configuration
            generateConfigFiles($userId, $ssh, $configPath);
            
            // Build environment variables
            $envArgs = [];
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
                    
                    $envArgs[] = '-e ' . escapeshellarg($key . '=' . $value);
                }
            }
            
            $envStr = implode(' ', $envArgs);
            
            // Build and execute docker run command
            $escapedName = escapeshellarg($containerName);
            $escapedImage = escapeshellarg($dockerImage);
            
            $cmd = "docker run -d --name {$escapedName} " .
                   "-v {$escapedConfigPath}:/app/config:ro " .
                   "-v {$escapedOutputPath}:/app/output " .
                   "{$envStr} {$escapedImage} 2>&1";
            
            $result = $ssh->exec($cmd);
            
            if ($result['success'] && !empty($result['output'])) {
                jsonSuccess([
                    'container_name' => $containerName,
                    'container_id' => trim($result['output']),
                    'message' => 'Container started successfully'
                ], 'Container started');
            } else {
                jsonError('Failed to start container: ' . ($result['error'] ?: $result['output']));
            }
            break;
            
        case 'start':
            // Start an existing stopped container via SSH
            $escapedName = escapeshellarg($containerName);
            $result = $ssh->exec("docker start {$escapedName} 2>&1");
            
            if ($result['success']) {
                jsonSuccess([
                    'container_name' => $containerName,
                    'message' => 'Container started successfully'
                ], 'Container started');
            } else {
                jsonError('Failed to start container: ' . ($result['error'] ?: $result['output']));
            }
            break;
            
        case 'stop':
            // Stop a running container via SSH
            $escapedName = escapeshellarg($containerName);
            $result = $ssh->exec("docker stop {$escapedName} 2>&1");
            
            if ($result['success']) {
                jsonSuccess([
                    'container_name' => $containerName,
                    'message' => 'Container stopped successfully'
                ], 'Container stopped');
            } else {
                jsonError('Failed to stop container: ' . ($result['error'] ?: $result['output']));
            }
            break;
            
        case 'remove':
            // Remove a container via SSH (must be stopped first)
            $escapedName = escapeshellarg($containerName);
            
            // First stop the container if it's running
            $ssh->exec("docker stop {$escapedName} 2>&1");
            
            // Then remove it
            $result = $ssh->exec("docker rm {$escapedName} 2>&1");
            
            if ($result['success']) {
                jsonSuccess([
                    'container_name' => $containerName,
                    'message' => 'Container removed successfully'
                ], 'Container removed');
            } else {
                jsonError('Failed to remove container: ' . ($result['error'] ?: $result['output']));
            }
            break;
            
        case 'restart':
            // Restart a container via SSH
            // Generate config files from current configuration before restart
            $escapedConfigPath = escapeshellarg($configPath);
            $escapedOutputPath = escapeshellarg($outputPath);
            $ssh->exec("mkdir -p {$escapedConfigPath} {$escapedOutputPath}");
            generateConfigFiles($userId, $ssh, $configPath);
            
            $escapedName = escapeshellarg($containerName);
            $result = $ssh->exec("docker restart {$escapedName} 2>&1");
            
            if ($result['success']) {
                jsonSuccess([
                    'container_name' => $containerName,
                    'message' => 'Container restarted successfully'
                ], 'Container restarted');
            } else {
                jsonError('Failed to restart container: ' . ($result['error'] ?: $result['output']));
            }
            break;
            
        default:
            jsonError('Invalid action');
    }
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

/**
 * Generate config.yaml and frequency_words.txt files on remote server
 * 
 * @param int $userId User ID
 * @param SSHHelper $ssh SSH helper instance
 * @param string $configPath Path to config directory on remote server
 */
function generateConfigFiles($userId, $ssh, $configPath) {
    try {
        $config = new Configuration($userId);
        $activeConfig = $config->getActive();
        
        if (!$activeConfig) {
            // No active configuration, skip generating files
            return;
        }
        
        $configId = $activeConfig['id'];
        
        // Generate config.yaml content
        $yamlData = $config->exportAsYaml($configId);
        if ($yamlData) {
            $yamlContent = convertToYaml($yamlData);
            $escapedYaml = escapeshellarg($yamlContent);
            $escapedPath = escapeshellarg($configPath . '/config.yaml');
            $ssh->exec("echo {$escapedYaml} > {$escapedPath}");
        }
        
        // Generate frequency_words.txt content
        $keywordsContent = $config->exportKeywords($configId);
        if ($keywordsContent) {
            $escapedKeywords = escapeshellarg($keywordsContent);
            $escapedPath = escapeshellarg($configPath . '/frequency_words.txt');
            $ssh->exec("echo {$escapedKeywords} > {$escapedPath}");
        }
    } catch (Exception $e) {
        // Log error but don't fail the operation
        error_log('Failed to generate config files: ' . $e->getMessage());
    }
}

/**
 * Convert array to YAML format string
 * 
 * @param array $data Data to convert
 * @param int $indent Current indentation level
 * @return string YAML formatted string
 */
function convertToYaml($data, $indent = 0) {
    $yaml = '';
    $prefix = str_repeat('  ', $indent);
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (empty($value)) {
                $yaml .= $prefix . $key . ": []\n";
            } elseif (isset($value[0])) {
                // Indexed array
                $yaml .= $prefix . $key . ":\n";
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $yaml .= $prefix . "  -\n";
                        foreach ($item as $k => $v) {
                            $yaml .= $prefix . "    " . $k . ": " . formatYamlValue($v) . "\n";
                        }
                    } else {
                        $yaml .= $prefix . "  - " . formatYamlValue($item) . "\n";
                    }
                }
            } else {
                // Associative array
                $yaml .= $prefix . $key . ":\n";
                $yaml .= convertToYaml($value, $indent + 1);
            }
        } else {
            $yaml .= $prefix . $key . ": " . formatYamlValue($value) . "\n";
        }
    }
    
    return $yaml;
}
