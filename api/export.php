<?php
/**
 * TrendRadarConsole - Export API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/configuration.php';

try {
    $configId = isset($_GET['config_id']) ? (int)$_GET['config_id'] : null;
    $format = isset($_GET['format']) ? $_GET['format'] : 'yaml';
    
    if (!$configId) {
        http_response_code(400);
        echo 'Configuration ID is required';
        exit;
    }
    
    $config = new Configuration();
    
    switch ($format) {
        case 'yaml':
            $data = $config->exportAsYaml($configId);
            if (!$data) {
                http_response_code(404);
                echo 'Configuration not found';
                exit;
            }
            
            header('Content-Type: text/yaml; charset=utf-8');
            header('Content-Disposition: attachment; filename="config.yaml"');
            echo arrayToYaml($data);
            break;
            
        case 'keywords':
            $keywords = $config->exportKeywords($configId);
            
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="frequency_words.txt"');
            echo $keywords;
            break;
            
        case 'json':
            $data = $config->exportAsYaml($configId);
            if (!$data) {
                http_response_code(404);
                echo json_encode(['error' => 'Configuration not found']);
                exit;
            }
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            http_response_code(400);
            echo 'Invalid format. Use: yaml, keywords, or json';
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo $e->getMessage();
}
