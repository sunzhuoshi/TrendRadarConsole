<?php
/**
 * TrendRadarConsole - Webhooks API
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
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
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
            $webhooks = $config->getWebhooks($configId);
            jsonSuccess($webhooks);
            break;
            
        case 'POST':
            $configId = isset($input['config_id']) ? (int)$input['config_id'] : null;
            $type = isset($input['type']) ? trim($input['type']) : '';
            
            if (!$configId || !$type) {
                jsonError('Configuration ID and webhook type are required');
            }
            
            // Get webhook URL and additional config based on type
            $webhookUrl = '';
            $additionalConfig = [];
            
            switch ($type) {
                case 'wework':
                    $webhookUrl = $input['webhook_url'] ?? '';
                    if (isset($input['msg_type'])) {
                        $additionalConfig['msg_type'] = $input['msg_type'];
                    }
                    break;
                    
                case 'feishu':
                case 'dingtalk':
                case 'bark':
                case 'slack':
                    $webhookUrl = $input['webhook_url'] ?? '';
                    break;
                    
                case 'telegram':
                    $webhookUrl = $input['webhook_url'] ?? ''; // Bot token
                    if (isset($input['chat_id'])) {
                        $additionalConfig['chat_id'] = $input['chat_id'];
                    }
                    break;
                    
                case 'email':
                    $additionalConfig = [
                        'from' => $input['from'] ?? '',
                        'password' => $input['password'] ?? '',
                        'to' => $input['to'] ?? '',
                        'smtp_server' => $input['smtp_server'] ?? '',
                        'smtp_port' => $input['smtp_port'] ?? '',
                    ];
                    break;
                    
                case 'ntfy':
                    $webhookUrl = $input['webhook_url'] ?? ''; // Topic name
                    $additionalConfig = [
                        'server_url' => $input['server_url'] ?? 'ntfy.sh',
                        'token' => $input['token'] ?? '',
                    ];
                    break;
                    
                default:
                    jsonError('Invalid webhook type');
            }
            
            $config->saveWebhook(
                $configId, 
                $type, 
                $webhookUrl, 
                !empty($additionalConfig) ? $additionalConfig : null,
                1
            );
            
            // Log the operation
            $opLog->log(
                OperationLog::ACTION_WEBHOOK_SAVE,
                OperationLog::TARGET_WEBHOOK,
                $configId,
                ['type' => $type]
            );
            
            jsonSuccess(null, 'Webhook saved successfully');
            break;
            
        case 'PUT':
            $id = isset($input['id']) ? (int)$input['id'] : null;
            if (!$id) {
                jsonError('Webhook ID is required');
            }
            
            $data = [];
            $logDetails = [];
            if (isset($input['is_enabled'])) {
                $data['is_enabled'] = $input['is_enabled'] ? 1 : 0;
                $logDetails['is_enabled'] = $data['is_enabled'];
            }
            
            if (empty($data)) {
                jsonError('No data to update');
            }
            
            // Direct database update for webhook
            $db = Database::getInstance();
            $db->update('webhooks', $data, 'id = ?', [$id]);
            
            // Log the operation
            $opLog->log(
                OperationLog::ACTION_WEBHOOK_UPDATE,
                OperationLog::TARGET_WEBHOOK,
                $id,
                $logDetails
            );
            
            jsonSuccess(null, 'Webhook updated successfully');
            break;
            
        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) {
                jsonError('Webhook ID is required');
            }
            
            $config->deleteWebhook($id);
            
            // Log the operation
            $opLog->log(
                OperationLog::ACTION_WEBHOOK_DELETE,
                OperationLog::TARGET_WEBHOOK,
                $id,
                null
            );
            
            jsonSuccess(null, 'Webhook deleted successfully');
            break;
            
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
