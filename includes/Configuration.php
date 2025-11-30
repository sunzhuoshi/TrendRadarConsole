<?php
/**
 * TrendRadarConsole - Configuration Model
 */

require_once __DIR__ . '/Database.php';

class Configuration
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get all configurations
     */
    public function getAll()
    {
        return $this->db->fetchAll('SELECT * FROM configurations ORDER BY created_at DESC');
    }
    
    /**
     * Get configuration by ID
     */
    public function getById($id)
    {
        return $this->db->fetchOne('SELECT * FROM configurations WHERE id = ?', [$id]);
    }
    
    /**
     * Get active configuration
     */
    public function getActive()
    {
        return $this->db->fetchOne('SELECT * FROM configurations WHERE is_active = 1 LIMIT 1');
    }
    
    /**
     * Create new configuration
     */
    public function create($name, $description = '')
    {
        return $this->db->insert('configurations', [
            'name' => $name,
            'description' => $description,
            'config_data' => '{}',
            'is_active' => 0
        ]);
    }
    
    /**
     * Update configuration
     */
    public function update($id, $data)
    {
        return $this->db->update('configurations', $data, 'id = ?', [$id]);
    }
    
    /**
     * Delete configuration
     */
    public function delete($id)
    {
        return $this->db->delete('configurations', 'id = ?', [$id]);
    }
    
    /**
     * Set configuration as active
     */
    public function setActive($id)
    {
        // First, deactivate all
        $this->db->query('UPDATE configurations SET is_active = 0');
        // Then activate the selected one
        return $this->db->update('configurations', ['is_active' => 1], 'id = ?', [$id]);
    }
    
    /**
     * Get platforms for a configuration
     */
    public function getPlatforms($configId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM platforms WHERE config_id = ? ORDER BY sort_order',
            [$configId]
        );
    }
    
    /**
     * Add platform to configuration
     */
    public function addPlatform($configId, $platformId, $platformName, $sortOrder = 0)
    {
        return $this->db->insert('platforms', [
            'config_id' => $configId,
            'platform_id' => $platformId,
            'platform_name' => $platformName,
            'is_enabled' => 1,
            'sort_order' => $sortOrder
        ]);
    }
    
    /**
     * Update platform
     */
    public function updatePlatform($id, $data)
    {
        return $this->db->update('platforms', $data, 'id = ?', [$id]);
    }
    
    /**
     * Delete platform
     */
    public function deletePlatform($id)
    {
        return $this->db->delete('platforms', 'id = ?', [$id]);
    }
    
    /**
     * Get keywords for a configuration
     */
    public function getKeywords($configId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM keywords WHERE config_id = ? ORDER BY keyword_group, sort_order',
            [$configId]
        );
    }
    
    /**
     * Add keyword to configuration
     */
    public function addKeyword($configId, $keyword, $type = 'normal', $group = 0, $sortOrder = 0, $limitValue = null)
    {
        return $this->db->insert('keywords', [
            'config_id' => $configId,
            'keyword' => $keyword,
            'keyword_type' => $type,
            'keyword_group' => $group,
            'sort_order' => $sortOrder,
            'limit_value' => $limitValue
        ]);
    }
    
    /**
     * Update keyword
     */
    public function updateKeyword($id, $data)
    {
        return $this->db->update('keywords', $data, 'id = ?', [$id]);
    }
    
    /**
     * Delete keyword
     */
    public function deleteKeyword($id)
    {
        return $this->db->delete('keywords', 'id = ?', [$id]);
    }
    
    /**
     * Delete all keywords for a configuration
     */
    public function deleteAllKeywords($configId)
    {
        return $this->db->delete('keywords', 'config_id = ?', [$configId]);
    }
    
    /**
     * Get webhooks for a configuration
     */
    public function getWebhooks($configId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM webhooks WHERE config_id = ?',
            [$configId]
        );
    }
    
    /**
     * Add or update webhook
     */
    public function saveWebhook($configId, $type, $url, $additionalConfig = null, $isEnabled = 1)
    {
        // Check if webhook exists
        $existing = $this->db->fetchOne(
            'SELECT id FROM webhooks WHERE config_id = ? AND webhook_type = ?',
            [$configId, $type]
        );
        
        $data = [
            'webhook_url' => $url,
            'additional_config' => $additionalConfig ? json_encode($additionalConfig) : null,
            'is_enabled' => $isEnabled
        ];
        
        if ($existing) {
            return $this->db->update('webhooks', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['config_id'] = $configId;
            $data['webhook_type'] = $type;
            return $this->db->insert('webhooks', $data);
        }
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook($id)
    {
        return $this->db->delete('webhooks', 'id = ?', [$id]);
    }
    
    /**
     * Get settings for a configuration
     */
    public function getSettings($configId)
    {
        $rows = $this->db->fetchAll(
            'SELECT setting_key, setting_value FROM settings WHERE config_id = ?',
            [$configId]
        );
        
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
    
    /**
     * Save setting
     */
    public function saveSetting($configId, $key, $value)
    {
        // Check if setting exists
        $existing = $this->db->fetchOne(
            'SELECT id FROM settings WHERE config_id = ? AND setting_key = ?',
            [$configId, $key]
        );
        
        if ($existing) {
            return $this->db->update('settings', ['setting_value' => $value], 'id = ?', [$existing['id']]);
        } else {
            return $this->db->insert('settings', [
                'config_id' => $configId,
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
    }
    
    /**
     * Export configuration as YAML-compatible array
     */
    public function exportAsYaml($configId)
    {
        $config = $this->getById($configId);
        if (!$config) {
            return null;
        }
        
        $settings = $this->getSettings($configId);
        $platforms = $this->getPlatforms($configId);
        $keywords = $this->getKeywords($configId);
        $webhooks = $this->getWebhooks($configId);
        
        // Build config.yaml structure
        $yaml = [
            'app' => [
                'version_check_url' => 'https://raw.githubusercontent.com/sansan0/TrendRadar/refs/heads/master/version',
                'show_version_update' => true
            ],
            'crawler' => [
                'request_interval' => 1000,
                'enable_crawler' => $settings['enable_crawler'] === 'true',
                'use_proxy' => false,
                'default_proxy' => 'http://127.0.0.1:10086'
            ],
            'report' => [
                'mode' => $settings['report_mode'] ?? 'incremental',
                'rank_threshold' => (int)($settings['rank_threshold'] ?? 5),
                'sort_by_position_first' => $settings['sort_by_position_first'] === 'true',
                'max_news_per_keyword' => (int)($settings['max_news_per_keyword'] ?? 0)
            ],
            'notification' => [
                'enable_notification' => $settings['enable_notification'] === 'true',
                'message_batch_size' => 4000,
                'dingtalk_batch_size' => 20000,
                'feishu_batch_size' => 30000,
                'bark_batch_size' => 4000,
                'slack_batch_size' => 4000,
                'batch_send_interval' => 3,
                'feishu_message_separator' => '━━━━━━━━━━━━━━━━━━━',
                'push_window' => [
                    'enabled' => $settings['push_window_enabled'] === 'true',
                    'time_range' => [
                        'start' => $settings['push_window_start'] ?? '20:00',
                        'end' => $settings['push_window_end'] ?? '22:00'
                    ],
                    'once_per_day' => $settings['push_window_once_per_day'] === 'true',
                    'push_record_retention_days' => 7
                ],
                'webhooks' => $this->buildWebhooksConfig($webhooks)
            ],
            'weight' => [
                'rank_weight' => (float)($settings['rank_weight'] ?? 0.6),
                'frequency_weight' => (float)($settings['frequency_weight'] ?? 0.3),
                'hotness_weight' => (float)($settings['hotness_weight'] ?? 0.1)
            ],
            'platforms' => array_map(function($p) {
                return [
                    'id' => $p['platform_id'],
                    'name' => $p['platform_name']
                ];
            }, array_filter($platforms, function($p) {
                return $p['is_enabled'];
            }))
        ];
        
        return $yaml;
    }
    
    /**
     * Build webhooks configuration array
     */
    private function buildWebhooksConfig($webhooks)
    {
        $config = [
            'feishu_url' => '',
            'dingtalk_url' => '',
            'wework_url' => '',
            'wework_msg_type' => 'markdown',
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
            'email_from' => '',
            'email_password' => '',
            'email_to' => '',
            'email_smtp_server' => '',
            'email_smtp_port' => '',
            'ntfy_server_url' => 'https://ntfy.sh',
            'ntfy_topic' => '',
            'ntfy_token' => '',
            'bark_url' => '',
            'slack_webhook_url' => ''
        ];
        
        foreach ($webhooks as $webhook) {
            if (!$webhook['is_enabled']) continue;
            
            switch ($webhook['webhook_type']) {
                case 'feishu':
                    $config['feishu_url'] = $webhook['webhook_url'];
                    break;
                case 'dingtalk':
                    $config['dingtalk_url'] = $webhook['webhook_url'];
                    break;
                case 'wework':
                    $config['wework_url'] = $webhook['webhook_url'];
                    if ($webhook['additional_config']) {
                        $extra = json_decode($webhook['additional_config'], true);
                        $config['wework_msg_type'] = $extra['msg_type'] ?? 'markdown';
                    }
                    break;
                case 'telegram':
                    $config['telegram_bot_token'] = $webhook['webhook_url'];
                    if ($webhook['additional_config']) {
                        $extra = json_decode($webhook['additional_config'], true);
                        $config['telegram_chat_id'] = $extra['chat_id'] ?? '';
                    }
                    break;
                case 'email':
                    if ($webhook['additional_config']) {
                        $extra = json_decode($webhook['additional_config'], true);
                        $config['email_from'] = $extra['from'] ?? '';
                        $config['email_password'] = $extra['password'] ?? '';
                        $config['email_to'] = $extra['to'] ?? '';
                        $config['email_smtp_server'] = $extra['smtp_server'] ?? '';
                        $config['email_smtp_port'] = $extra['smtp_port'] ?? '';
                    }
                    break;
                case 'ntfy':
                    $config['ntfy_topic'] = $webhook['webhook_url'];
                    if ($webhook['additional_config']) {
                        $extra = json_decode($webhook['additional_config'], true);
                        $config['ntfy_server_url'] = $extra['server_url'] ?? 'https://ntfy.sh';
                        $config['ntfy_token'] = $extra['token'] ?? '';
                    }
                    break;
                case 'bark':
                    $config['bark_url'] = $webhook['webhook_url'];
                    break;
                case 'slack':
                    $config['slack_webhook_url'] = $webhook['webhook_url'];
                    break;
            }
        }
        
        return $config;
    }
    
    /**
     * Export keywords as frequency_words.txt format
     */
    public function exportKeywords($configId)
    {
        $keywords = $this->getKeywords($configId);
        if (empty($keywords)) {
            return '';
        }
        
        $output = [];
        $currentGroup = -1;
        
        foreach ($keywords as $keyword) {
            // Add empty line between groups
            if ($currentGroup !== -1 && $keyword['keyword_group'] !== $currentGroup) {
                $output[] = '';
            }
            $currentGroup = $keyword['keyword_group'];
            
            // Format keyword based on type
            switch ($keyword['keyword_type']) {
                case 'required':
                    $output[] = '+' . $keyword['keyword'];
                    break;
                case 'filter':
                    $output[] = '!' . $keyword['keyword'];
                    break;
                case 'limit':
                    $output[] = '@' . $keyword['limit_value'];
                    break;
                default:
                    $output[] = $keyword['keyword'];
            }
        }
        
        return implode("\n", $output);
    }
}
