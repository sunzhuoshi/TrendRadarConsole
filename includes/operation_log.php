<?php
/**
 * TrendRadarConsole - Operation Log Model
 */

require_once __DIR__ . '/database.php';

class OperationLog
{
    private $db;
    private $userId;
    
    // Action constants
    const ACTION_LOAD_FROM_GITHUB = 'load_from_github';
    const ACTION_SAVE_TO_GITHUB = 'save_to_github';
    const ACTION_TEST_CONNECTION = 'test_connection';
    const ACTION_SAVE_GITHUB_SETTINGS = 'save_github_settings';
    const ACTION_CONFIG_ACTIVATE = 'config_activate';
    const ACTION_CONFIG_DELETE = 'config_delete';
    const ACTION_CONFIG_UPDATE = 'config_update';
    const ACTION_CONFIG_CREATE = 'config_create';
    const ACTION_PLATFORM_ADD = 'platform_add';
    const ACTION_PLATFORM_UPDATE = 'platform_update';
    const ACTION_PLATFORM_DELETE = 'platform_delete';
    const ACTION_KEYWORD_SAVE = 'keyword_save';
    const ACTION_KEYWORD_CLEAR = 'keyword_clear';
    const ACTION_WEBHOOK_SAVE = 'webhook_save';
    const ACTION_WEBHOOK_UPDATE = 'webhook_update';
    const ACTION_WEBHOOK_DELETE = 'webhook_delete';
    const ACTION_SETTING_UPDATE = 'setting_update';
    const ACTION_SETTINGS_SAVE = 'settings_save';
    
    // Target type constants
    const TARGET_CONFIGURATION = 'configuration';
    const TARGET_PLATFORM = 'platform';
    const TARGET_KEYWORD = 'keyword';
    const TARGET_WEBHOOK = 'webhook';
    const TARGET_SETTING = 'setting';
    const TARGET_GITHUB = 'github';
    
    public function __construct($userId = null)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
    }
    
    /**
     * Set user ID
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }
    
    /**
     * Log an operation
     */
    public function log($action, $targetType = null, $targetId = null, $details = null)
    {
        if (!$this->userId) {
            return false;
        }
        
        $data = [
            'user_id' => $this->userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null
        ];
        
        return $this->db->insert('operation_logs', $data);
    }
    
    /**
     * Get all logs for current user with pagination
     */
    public function getAll($page = 1, $perPage = 20)
    {
        $offset = ($page - 1) * $perPage;
        
        if ($this->userId) {
            return $this->db->fetchAll(
                'SELECT * FROM operation_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
                [$this->userId, $perPage, $offset]
            );
        }
        return $this->db->fetchAll(
            'SELECT * FROM operation_logs ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$perPage, $offset]
        );
    }
    
    /**
     * Get total count of logs for current user
     */
    public function getCount()
    {
        if ($this->userId) {
            $result = $this->db->fetchOne(
                'SELECT COUNT(*) as count FROM operation_logs WHERE user_id = ?',
                [$this->userId]
            );
        } else {
            $result = $this->db->fetchOne('SELECT COUNT(*) as count FROM operation_logs');
        }
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Get logs by action type
     */
    public function getByAction($action, $limit = 50)
    {
        if ($this->userId) {
            return $this->db->fetchAll(
                'SELECT * FROM operation_logs WHERE user_id = ? AND action = ? ORDER BY created_at DESC LIMIT ?',
                [$this->userId, $action, $limit]
            );
        }
        return $this->db->fetchAll(
            'SELECT * FROM operation_logs WHERE action = ? ORDER BY created_at DESC LIMIT ?',
            [$action, $limit]
        );
    }
    
    /**
     * Clear old logs (keep last N days)
     */
    public function clearOldLogs($days = 30)
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        if ($this->userId) {
            return $this->db->delete(
                'operation_logs',
                'user_id = ? AND created_at < ?',
                [$this->userId, $date]
            );
        }
        return $this->db->delete('operation_logs', 'created_at < ?', [$date]);
    }
    
    /**
     * Get action display text key for translation
     */
    public static function getActionTextKey($action)
    {
        $map = [
            self::ACTION_LOAD_FROM_GITHUB => 'log_action_load_from_github',
            self::ACTION_SAVE_TO_GITHUB => 'log_action_save_to_github',
            self::ACTION_TEST_CONNECTION => 'log_action_test_connection',
            self::ACTION_SAVE_GITHUB_SETTINGS => 'log_action_save_github_settings',
            self::ACTION_CONFIG_ACTIVATE => 'log_action_config_activate',
            self::ACTION_CONFIG_DELETE => 'log_action_config_delete',
            self::ACTION_CONFIG_UPDATE => 'log_action_config_update',
            self::ACTION_CONFIG_CREATE => 'log_action_config_create',
            self::ACTION_PLATFORM_ADD => 'log_action_platform_add',
            self::ACTION_PLATFORM_UPDATE => 'log_action_platform_update',
            self::ACTION_PLATFORM_DELETE => 'log_action_platform_delete',
            self::ACTION_KEYWORD_SAVE => 'log_action_keyword_save',
            self::ACTION_KEYWORD_CLEAR => 'log_action_keyword_clear',
            self::ACTION_WEBHOOK_SAVE => 'log_action_webhook_save',
            self::ACTION_WEBHOOK_UPDATE => 'log_action_webhook_update',
            self::ACTION_WEBHOOK_DELETE => 'log_action_webhook_delete',
            self::ACTION_SETTING_UPDATE => 'log_action_setting_update',
            self::ACTION_SETTINGS_SAVE => 'log_action_settings_save',
        ];
        
        return $map[$action] ?? 'log_action_unknown';
    }
    
    /**
     * Get target type display text key for translation
     */
    public static function getTargetTypeTextKey($targetType)
    {
        $map = [
            self::TARGET_CONFIGURATION => 'log_target_configuration',
            self::TARGET_PLATFORM => 'log_target_platform',
            self::TARGET_KEYWORD => 'log_target_keyword',
            self::TARGET_WEBHOOK => 'log_target_webhook',
            self::TARGET_SETTING => 'log_target_setting',
            self::TARGET_GITHUB => 'log_target_github',
        ];
        
        return $map[$targetType] ?? '';
    }
}
