<?php
/**
 * TrendRadarConsole - Keywords API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/Configuration.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $config = new Configuration();
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
                }
            }
            
            jsonSuccess(null, 'Keywords saved successfully');
            break;
            
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
