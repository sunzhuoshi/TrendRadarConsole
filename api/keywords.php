<?php
/**
 * TrendRadarConsole - Keywords API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/configuration.php';
require_once '../includes/auth.php';
require_once '../includes/operation_log.php';

header('Content-Type: application/json; charset=utf-8');

// Require login for API
if (!Auth::isLoggedIn()) {
    jsonError('Unauthorized', 401);
}
$userId = Auth::getUserId();

try {
    $config = new Configuration($userId);
    $opLog = new OperationLog($userId);
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
            $configId = isset($_GET['config_id']) ? (int)$_GET['config_id'] : null;
            if (!$configId) {
                jsonError('Configuration ID is required');
            }
            
            $format = isset($_GET['format']) ? $_GET['format'] : 'json';
            
            if ($format === 'text') {
                header('Content-Type: text/plain; charset=utf-8');
                echo $config->exportKeywords($configId);
                exit;
            }
            
            $keywords = $config->getKeywords($configId);
            jsonSuccess($keywords);
            break;
            
        case 'POST':
            $configId = isset($input['config_id']) ? (int)$input['config_id'] : null;
            $keywordsText = isset($input['keywords_text']) ? $input['keywords_text'] : '';
            
            if (!$configId) {
                jsonError('Configuration ID is required');
            }
            
            // Delete existing keywords
            $config->deleteAllKeywords($configId);
            
            // Parse and save new keywords
            $lines = explode("\n", $keywordsText);
            $currentGroup = 0;
            $sortOrder = 0;
            $previousWasEmpty = false;
            $keywordCount = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if ($line === '') {
                    if (!$previousWasEmpty && $sortOrder > 0) {
                        $currentGroup++;
                        $sortOrder = 0;
                    }
                    $previousWasEmpty = true;
                    continue;
                }
                
                $previousWasEmpty = false;
                
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
                    $keyword = '';
                    $limitValue = (int)substr($line, 1);
                }
                
                if ($keyword !== '' || $type === 'limit') {
                    $config->addKeyword($configId, $keyword, $type, $currentGroup, $sortOrder, $limitValue);
                    $sortOrder++;
                    $keywordCount++;
                }
            }
            
            // Log the operation
            $action = $keywordCount > 0 ? OperationLog::ACTION_KEYWORD_SAVE : OperationLog::ACTION_KEYWORD_CLEAR;
            $opLog->log(
                $action,
                OperationLog::TARGET_KEYWORD,
                $configId,
                ['count' => $keywordCount, 'groups' => $currentGroup + 1]
            );
            
            jsonSuccess(null, 'Keywords saved successfully');
            break;
            
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
