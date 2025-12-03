<?php
/**
 * TrendRadarConsole - Configuration Actions API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/configuration.php';
require_once '../includes/auth.php';
require_once '../includes/operation_log.php';

// Require login
if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}
$userId = Auth::getUserId();

try {
    $config = new Configuration($userId);
    $opLog = new OperationLog($userId);
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Invalid request. Please try again.');
        header('Location: ../index.php');
        exit;
    }
    
    if (!$id) {
        setFlash('error', 'Configuration ID is required');
        header('Location: ../index.php');
        exit;
    }
    
    switch ($action) {
        case 'activate':
            // Get config details for logging
            $cfg = $config->getById($id);
            $config->setActive($id);
            
            // Log the operation
            $opLog->log(
                OperationLog::ACTION_CONFIG_ACTIVATE,
                OperationLog::TARGET_CONFIGURATION,
                $id,
                ['name' => $cfg ? $cfg['name'] : '']
            );
            
            setFlash('success', 'Configuration activated successfully');
            break;
            
        case 'delete':
            $cfg = $config->getById($id);
            if ($cfg && $cfg['is_active']) {
                setFlash('error', 'Cannot delete active configuration');
            } else {
                $configName = $cfg ? $cfg['name'] : '';
                $config->delete($id);
                
                // Log the operation
                $opLog->log(
                    OperationLog::ACTION_CONFIG_DELETE,
                    OperationLog::TARGET_CONFIGURATION,
                    $id,
                    ['name' => $configName]
                );
                
                setFlash('success', 'Configuration deleted successfully');
            }
            break;
            
        default:
            setFlash('error', 'Invalid action');
    }
    
    header('Location: ../index.php');
    exit;
    
} catch (Exception $e) {
    setFlash('error', $e->getMessage());
    header('Location: ../index.php');
    exit;
}
