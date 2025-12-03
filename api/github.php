<?php
/**
 * TrendRadarConsole - GitHub API Endpoint
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/configuration.php';
require_once '../includes/github.php';
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
    $owner = $input['owner'] ?? '';
    $repo = $input['repo'] ?? '';
    $token = $input['token'] ?? '';
    
    // Handle save_settings action first (doesn't need full GitHub credentials)
    if ($action === 'save_settings') {
        $auth = new Auth();
        $auth->updateGitHubSettings($userId, $owner, $repo, $token ?: null);
        jsonSuccess(null, 'GitHub settings saved');
    }
    
    // If owner/repo/token are empty, try to get from saved settings
    if (!$owner || !$repo || !$token) {
        $auth = new Auth();
        $savedSettings = $auth->getGitHubSettings($userId);
        if (!$owner) $owner = $savedSettings['github_owner'] ?? '';
        if (!$repo) $repo = $savedSettings['github_repo'] ?? '';
        if (!$token) $token = $savedSettings['github_token'] ?? '';
    }
    
    if (!$owner || !$repo || !$token) {
        jsonError('Owner, repo, and token are required');
    }
    
    $github = new GitHub($token, $owner, $repo);
    
    switch ($action) {
        case 'test':
            // Save settings on successful test
            $auth = new Auth();
            $auth->updateGitHubSettings($userId, $owner, $repo, $token);
            
            $repoInfo = $github->testConnection();
            jsonSuccess($repoInfo, 'Connection successful');
            break;
            
        case 'load':
            $configYaml = $github->getConfigYaml();
            $frequencyWords = $github->getFrequencyWords();
            
            jsonSuccess([
                'config_yaml' => $configYaml,
                'frequency_words' => $frequencyWords
            ], 'Configuration loaded');
            break;
            
        case 'load_or_create_default':
            // Try to load CONFIG_YAML and FREQUENCY_WORDS from GitHub repo vars
            $configYaml = $github->getConfigYaml();
            $frequencyWords = $github->getFrequencyWords();
            
            $config = new Configuration($userId);
            $activeConfig = $config->getActive();
            
            // If no active config exists, this shouldn't happen but handle it
            if (!$activeConfig) {
                jsonError('No active configuration found');
            }
            
            $configId = $activeConfig['id'];
            $loadedFromGitHub = false;
            
            // If we found CONFIG_YAML in repo vars, parse and apply it
            if ($configYaml) {
                $loadedFromGitHub = true;
                
                // Parse YAML and update configuration
                $parsedConfig = parseSimpleYaml($configYaml);
                
                // Update platforms from config
                if (isset($parsedConfig['platforms']) && is_array($parsedConfig['platforms'])) {
                    // Delete existing platforms
                    $existingPlatforms = $config->getPlatforms($configId);
                    foreach ($existingPlatforms as $p) {
                        $config->deletePlatform($p['id']);
                    }
                    
                    // Add platforms from GitHub config
                    foreach ($parsedConfig['platforms'] as $index => $platform) {
                        if (isset($platform['id']) && isset($platform['name'])) {
                            $config->addPlatform($configId, $platform['id'], $platform['name'], $index + 1);
                        }
                    }
                }
                
                // Update settings from config
                if (isset($parsedConfig['report'])) {
                    if (isset($parsedConfig['report']['mode'])) {
                        $config->saveSetting($configId, 'report_mode', $parsedConfig['report']['mode']);
                    }
                    if (isset($parsedConfig['report']['rank_threshold'])) {
                        $config->saveSetting($configId, 'rank_threshold', (string)$parsedConfig['report']['rank_threshold']);
                    }
                    if (isset($parsedConfig['report']['sort_by_position_first'])) {
                        $config->saveSetting($configId, 'sort_by_position_first', $parsedConfig['report']['sort_by_position_first'] ? 'true' : 'false');
                    }
                    if (isset($parsedConfig['report']['max_news_per_keyword'])) {
                        $config->saveSetting($configId, 'max_news_per_keyword', (string)$parsedConfig['report']['max_news_per_keyword']);
                    }
                }
                
                if (isset($parsedConfig['crawler']['enable_crawler'])) {
                    $config->saveSetting($configId, 'enable_crawler', $parsedConfig['crawler']['enable_crawler'] ? 'true' : 'false');
                }
                
                if (isset($parsedConfig['notification'])) {
                    if (isset($parsedConfig['notification']['enable_notification'])) {
                        $config->saveSetting($configId, 'enable_notification', $parsedConfig['notification']['enable_notification'] ? 'true' : 'false');
                    }
                    if (isset($parsedConfig['notification']['push_window'])) {
                        $pw = $parsedConfig['notification']['push_window'];
                        if (isset($pw['enabled'])) {
                            $config->saveSetting($configId, 'push_window_enabled', $pw['enabled'] ? 'true' : 'false');
                        }
                        if (isset($pw['time_range']['start'])) {
                            $config->saveSetting($configId, 'push_window_start', $pw['time_range']['start']);
                        }
                        if (isset($pw['time_range']['end'])) {
                            $config->saveSetting($configId, 'push_window_end', $pw['time_range']['end']);
                        }
                        if (isset($pw['once_per_day'])) {
                            $config->saveSetting($configId, 'push_window_once_per_day', $pw['once_per_day'] ? 'true' : 'false');
                        }
                    }
                }
                
                if (isset($parsedConfig['weight'])) {
                    if (isset($parsedConfig['weight']['rank_weight'])) {
                        $config->saveSetting($configId, 'rank_weight', (string)$parsedConfig['weight']['rank_weight']);
                    }
                    if (isset($parsedConfig['weight']['frequency_weight'])) {
                        $config->saveSetting($configId, 'frequency_weight', (string)$parsedConfig['weight']['frequency_weight']);
                    }
                    if (isset($parsedConfig['weight']['hotness_weight'])) {
                        $config->saveSetting($configId, 'hotness_weight', (string)$parsedConfig['weight']['hotness_weight']);
                    }
                }
            }
            
            // If we found FREQUENCY_WORDS in repo vars, parse and apply it
            if ($frequencyWords) {
                $loadedFromGitHub = true;
                
                // Delete existing keywords
                $config->deleteAllKeywords($configId);
                
                // Parse keywords and add them
                $lines = explode("\n", $frequencyWords);
                $group = 0;
                $sortOrder = 0;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Empty line means new group
                    if ($line === '') {
                        $group++;
                        $sortOrder = 0;
                        continue;
                    }
                    
                    // Determine keyword type
                    $type = 'normal';
                    $keyword = $line;
                    $limitValue = null;
                    
                    if (strpos($line, '+') === 0) {
                        $type = 'required';
                        $keyword = substr($line, 1);
                    } elseif (strpos($line, '!') === 0) {
                        $type = 'filter';
                        $keyword = substr($line, 1);
                    } elseif (strpos($line, '@') === 0) {
                        $type = 'limit';
                        $limitValue = (int)substr($line, 1);
                        $keyword = '@' . $limitValue;
                    }
                    
                    if ($keyword !== '') {
                        $config->addKeyword($configId, $keyword, $type, $group, $sortOrder, $limitValue);
                        $sortOrder++;
                    }
                }
            }
            
            // Update configuration description with GitHub repo info
            if ($loadedFromGitHub) {
                $description = "Loaded from GitHub: {$owner}/{$repo}";
                $config->update($configId, ['description' => $description]);
            }
            
            jsonSuccess([
                'loaded_from_github' => $loadedFromGitHub,
                'config_id' => $configId
            ], $loadedFromGitHub ? 'Configuration loaded from GitHub' : 'Using default configuration');
            break;
            
        case 'save':
            $configId = isset($input['config_id']) ? (int)$input['config_id'] : null;
            if (!$configId) {
                jsonError('Configuration ID is required');
            }
            
            $config = new Configuration($userId);
            
            // Export configuration as YAML
            $yamlData = $config->exportAsYaml($configId);
            if (!$yamlData) {
                jsonError('Configuration not found');
            }
            $yamlString = arrayToYaml($yamlData);
            
            // Export keywords
            $keywordsString = $config->exportKeywords($configId);
            
            // Save to GitHub
            $github->setConfigYaml($yamlString);
            $github->setFrequencyWords($keywordsString);
            
            jsonSuccess(null, 'Configuration saved to GitHub');
            break;
            
        case 'dispatch_workflow':
            $workflowId = isset($input['workflow_id']) ? $input['workflow_id'] : 'crawler.yml';
            $ref = isset($input['ref']) ? $input['ref'] : 'master';
            
            $success = $github->dispatchWorkflow($workflowId, $ref);
            if (!$success) {
                jsonError('Failed to dispatch workflow');
            }
            jsonSuccess(null, 'Workflow dispatched successfully');
            break;
            
        case 'get_workflow_runs':
            $workflowId = isset($input['workflow_id']) ? $input['workflow_id'] : 'crawler.yml';
            $runs = $github->getWorkflowRuns($workflowId, 5);
            jsonSuccess(['runs' => $runs], 'Workflow runs retrieved');
            break;
            
        case 'get_workflow_run':
            $runId = isset($input['run_id']) ? (int)$input['run_id'] : null;
            if (!$runId) {
                jsonError('Run ID is required');
            }
            $run = $github->getWorkflowRun($runId);
            if (!$run) {
                jsonError('Workflow run not found');
            }
            jsonSuccess(['run' => $run], 'Workflow run retrieved');
            break;
            
        default:
            jsonError('Invalid action');
    }
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

/**
 * Parse simple YAML string into array
 * This is a basic parser specifically for the TrendRadar config.yaml structure
 * It handles the specific format used by TrendRadar and is not a general-purpose YAML parser
 */
function parseSimpleYaml($yaml) {
    $result = [];
    $lines = explode("\n", $yaml);
    $stack = [&$result];
    $indentStack = [-1];
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $trimmedLine = trim($line);
        if ($trimmedLine === '' || strpos($trimmedLine, '#') === 0) {
            continue;
        }
        
        // Calculate indentation (number of spaces)
        $indent = strlen($line) - strlen(ltrim($line));
        
        // Pop stack if indentation decreased (strictly less than current level)
        while (count($indentStack) > 1 && $indent < end($indentStack)) {
            array_pop($stack);
            array_pop($indentStack);
        }
        
        // Handle list item (e.g., "- id: value, name: value" or "- value")
        if (strpos($trimmedLine, '- ') === 0) {
            $value = substr($trimmedLine, 2);
            $current = &$stack[count($stack) - 1];
            
            // Check if value is key-value inline (e.g., "id: value, name: value")
            if (strpos($value, ':') !== false) {
                $item = [];
                // Parse inline key-value pairs more safely
                // Split by ", " but preserve values that might contain spaces
                $parts = preg_split('/,\s+(?=[a-zA-Z_]+:)/', $value);
                foreach ($parts as $part) {
                    $colonPos = strpos($part, ':');
                    if ($colonPos !== false) {
                        $k = trim(substr($part, 0, $colonPos));
                        $v = trim(substr($part, $colonPos + 1));
                        $item[$k] = parseYamlValue($v);
                    }
                }
                $current[] = $item;
            } else {
                $current[] = parseYamlValue($value);
            }
            continue;
        }
        
        // Handle key-value pair (e.g., "key: value" or "key:")
        $colonPos = strpos($trimmedLine, ':');
        if ($colonPos !== false) {
            $key = trim(substr($trimmedLine, 0, $colonPos));
            $value = trim(substr($trimmedLine, $colonPos + 1));
            
            $current = &$stack[count($stack) - 1];
            
            if ($value === '') {
                // This is a parent key, create nested array
                $current[$key] = [];
                $stack[] = &$current[$key];
                $indentStack[] = $indent;
            } else {
                // This is a key-value pair
                $current[$key] = parseYamlValue($value);
            }
        }
    }
    
    return $result;
}

/**
 * Parse a YAML value (handle booleans, numbers, strings)
 */
function parseYamlValue($value) {
    if ($value === '') {
        return '';
    }
    
    // Remove quotes if they wrap the entire value
    $len = strlen($value);
    if ($len >= 2) {
        $firstChar = $value[0];
        $lastChar = $value[$len - 1];
        if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
            return substr($value, 1, -1);
        }
    }
    
    // Handle booleans
    if ($value === 'true') return true;
    if ($value === 'false') return false;
    
    // Handle numbers
    if (is_numeric($value)) {
        return strpos($value, '.') !== false ? (float)$value : (int)$value;
    }
    
    return $value;
}
