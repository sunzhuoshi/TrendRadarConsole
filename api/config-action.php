<?php
/**
 * TrendRadarConsole - Configuration Actions API
 */

session_start();
require_once '../includes/helpers.php';
require_once '../includes/Configuration.php';

try {
    $config = new Configuration();
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
            $config->setActive($id);
            setFlash('success', 'Configuration activated successfully');
            break;
            
        case 'delete':
            $cfg = $config->getById($id);
            if ($cfg && $cfg['is_active']) {
                setFlash('error', 'Cannot delete active configuration');
            } else {
                $config->delete($id);
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
