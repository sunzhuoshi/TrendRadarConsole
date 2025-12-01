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
            
            // Return error if neither variable was found
            if ($configYaml === null && $frequencyWords === null) {
                jsonError('No configuration variables found. Make sure CONFIG_YAML or FREQUENCY_WORDS repository variables exist.', 404);
            }
            
            jsonSuccess([
                'config_yaml' => $configYaml,
                'frequency_words' => $frequencyWords
            ], 'Configuration loaded');
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
            
        default:
            jsonError('Invalid action');
    }
    
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
