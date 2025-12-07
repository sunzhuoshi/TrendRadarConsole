<?php
/**
 * TrendRadarConsole - Authentication Helper
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/operation_log.php';

class Auth
{
    private $db;
    
    /**
     * The first user ID - this user is automatically granted admin privileges
     * and cannot have admin revoked to ensure system integrity
     */
    const FIRST_USER_ID = 1;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Register a new user
     */
    public function register($username, $password, $email = null)
    {
        // Check if username exists
        $existing = $this->db->fetchOne(
            'SELECT id FROM users WHERE username = ?',
            [$username]
        );
        
        if ($existing) {
            throw new Exception('Username already exists');
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if this is the first user - if so, make them admin
        $userCount = $this->db->fetchOne('SELECT COUNT(*) as count FROM users');
        $isFirstUser = ($userCount && (int)$userCount['count'] === 0);
        
        // Insert user
        $userId = $this->db->insert('users', [
            'username' => $username,
            'password_hash' => $passwordHash,
            'email' => $email,
            'is_admin' => $isFirstUser ? 1 : 0
        ]);
        
        // Create default configuration for user
        $configId = $this->db->insert('configurations', [
            'user_id' => $userId,
            'name' => 'Default',
            'description' => 'Default TrendRadar configuration with Tech & AI Monitoring keywords',
            'config_data' => '{}',
            'is_active' => 1
        ]);
        
        // Insert default platforms
        $defaultPlatforms = [
            ['id' => 'toutiao', 'name' => '今日头条'],
            ['id' => 'baidu', 'name' => '百度热搜'],
            ['id' => 'wallstreetcn-hot', 'name' => '华尔街见闻'],
            ['id' => 'thepaper', 'name' => '澎湃新闻'],
            ['id' => 'bilibili-hot-search', 'name' => 'bilibili 热搜'],
            ['id' => 'cls-hot', 'name' => '财联社热门'],
            ['id' => 'ifeng', 'name' => '凤凰网'],
            ['id' => 'tieba', 'name' => '贴吧'],
            ['id' => 'weibo', 'name' => '微博'],
            ['id' => 'douyin', 'name' => '抖音'],
            ['id' => 'zhihu', 'name' => '知乎'],
        ];
        
        $platformCount = 0;
        foreach ($defaultPlatforms as $index => $p) {
            $this->db->insert('platforms', [
                'config_id' => $configId,
                'platform_id' => $p['id'],
                'platform_name' => $p['name'],
                'is_enabled' => 1,
                'sort_order' => $index + 1
            ]);
            $platformCount++;
        }
        
        // Insert default settings
        $defaultSettings = [
            'report_mode' => 'incremental',
            'rank_threshold' => '5',
            'sort_by_position_first' => 'false',
            'max_news_per_keyword' => '0',
            'rank_weight' => '0.6',
            'frequency_weight' => '0.3',
            'hotness_weight' => '0.1',
            'enable_crawler' => 'true',
            'enable_notification' => 'true',
            'push_window_enabled' => 'false',
            'push_window_start' => '20:00',
            'push_window_end' => '22:00',
            'push_window_once_per_day' => 'true',
        ];
        
        $settingCount = 0;
        foreach ($defaultSettings as $key => $value) {
            $this->db->insert('settings', [
                'config_id' => $configId,
                'setting_key' => $key,
                'setting_value' => $value
            ]);
            $settingCount++;
        }
        
        // Insert default keywords (Tech & AI Monitoring example)
        $defaultKeywords = [
            // Group 0: AI & Tech
            ['keyword' => 'AI', 'type' => 'normal', 'group' => 0, 'order' => 0],
            ['keyword' => '人工智能', 'type' => 'normal', 'group' => 0, 'order' => 1],
            ['keyword' => 'ChatGPT', 'type' => 'normal', 'group' => 0, 'order' => 2],
            ['keyword' => '技术', 'type' => 'required', 'group' => 0, 'order' => 3],
            ['keyword' => '培训', 'type' => 'filter', 'group' => 0, 'order' => 4],
            ['keyword' => '广告', 'type' => 'filter', 'group' => 0, 'order' => 5],
            ['keyword' => '', 'type' => 'limit', 'group' => 0, 'order' => 6, 'limit' => 15],
            // Group 1: Tech Companies & Products
            ['keyword' => '苹果', 'type' => 'normal', 'group' => 1, 'order' => 0],
            ['keyword' => '华为', 'type' => 'normal', 'group' => 1, 'order' => 1],
            ['keyword' => '小米', 'type' => 'normal', 'group' => 1, 'order' => 2],
            ['keyword' => '发布', 'type' => 'required', 'group' => 1, 'order' => 3],
            ['keyword' => '新品', 'type' => 'required', 'group' => 1, 'order' => 4],
        ];
        
        $keywordCount = 0;
        foreach ($defaultKeywords as $kw) {
            $this->db->insert('keywords', [
                'config_id' => $configId,
                'keyword' => $kw['keyword'],
                'keyword_type' => $kw['type'],
                'keyword_group' => $kw['group'],
                'sort_order' => $kw['order'],
                'limit_value' => $kw['limit'] ?? null
            ]);
            $keywordCount++;
        }
        
        // Log the user registration and default configuration creation
        $opLog = new OperationLog($userId);
        $opLog->log(
            OperationLog::ACTION_USER_REGISTER,
            OperationLog::TARGET_USER,
            $userId,
            [
                'username' => $username,
                'default_config_id' => $configId,
                'default_config_name' => 'Default',
                'platforms_count' => $platformCount,
                'settings_count' => $settingCount,
                'keywords_count' => $keywordCount
            ]
        );
        
        return $userId;
    }
    
    /**
     * Authenticate user
     */
    public function login($username, $password)
    {
        $user = $this->db->fetchOne(
            'SELECT * FROM users WHERE username = ?',
            [$username]
        );
        
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        // Update last login
        $this->db->update('users', 
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user['id']]
        );
        
        return $user;
    }
    
    /**
     * Start session for user
     */
    public function startSession($user)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current username
     */
    public static function getUsername()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['username'] ?? null;
    }
    
    /**
     * Logout user
     */
    public static function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Require login - redirect if not logged in
     */
    public static function requireLogin()
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    /**
     * Get user by ID
     */
    public function getUser($userId)
    {
        return $this->db->fetchOne(
            'SELECT id, username, email, github_owner, github_repo, created_at, last_login FROM users WHERE id = ?',
            [$userId]
        );
    }
    
    /**
     * Update user's GitHub settings
     */
    public function updateGitHubSettings($userId, $owner, $repo, $token = null)
    {
        $data = [
            'github_owner' => $owner,
            'github_repo' => $repo
        ];
        
        if ($token !== null) {
            $data['github_token'] = $token;
        }
        
        return $this->db->update('users', $data, 'id = ?', [$userId]);
    }
    
    /**
     * Get user's GitHub settings
     */
    public function getGitHubSettings($userId)
    {
        $user = $this->db->fetchOne(
            'SELECT github_owner, github_repo, github_token FROM users WHERE id = ?',
            [$userId]
        );
        
        return $user ?: ['github_owner' => '', 'github_repo' => '', 'github_token' => ''];
    }
    
    /**
     * Check if advanced mode is enabled for user
     */
    public function isAdvancedModeEnabled($userId)
    {
        $user = $this->db->fetchOne(
            'SELECT advanced_mode FROM users WHERE id = ?',
            [$userId]
        );
        
        return $user && (int)$user['advanced_mode'] === 1;
    }
    
    /**
     * Set advanced mode for user
     */
    public function setAdvancedMode($userId, $enabled)
    {
        return $this->db->update(
            'users',
            ['advanced_mode' => $enabled ? 1 : 0],
            'id = ?',
            [$userId]
        );
    }
    
    /**
     * Get advanced mode status for user
     * Static method for convenience with caching
     */
    public static function checkAdvancedMode()
    {
        static $advancedModeCache = null;
        static $cachedUserId = null;
        
        $userId = self::getUserId();
        if (!$userId) {
            return false;
        }
        
        // Return cached result if available for the same user
        if ($advancedModeCache !== null && $cachedUserId === $userId) {
            return $advancedModeCache;
        }
        
        $auth = new self();
        $advancedModeCache = $auth->isAdvancedModeEnabled($userId);
        $cachedUserId = $userId;
        
        return $advancedModeCache;
    }
    
    /**
     * Get Docker worker for user (first active worker)
     */
    public function getDockerWorker($userId)
    {
        $worker = $this->db->fetchOne(
            'SELECT * FROM docker_workers WHERE user_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1',
            [$userId]
        );
        
        if (!$worker) {
            return [
                'id' => null,
                'name' => '',
                'ssh_host' => '',
                'ssh_port' => 22,
                'ssh_username' => '',
                'ssh_password' => '',
                'workspace_path' => '/srv/trendradar'
            ];
        }
        
        return $worker;
    }
    
    /**
     * Get Docker SSH settings for user (backwards compatibility alias)
     */
    public function getDockerSSHSettings($userId)
    {
        $worker = $this->getDockerWorker($userId);
        return [
            'docker_ssh_host' => $worker['ssh_host'] ?? '',
            'docker_ssh_port' => $worker['ssh_port'] ?? 22,
            'docker_ssh_username' => $worker['ssh_username'] ?? '',
            'docker_ssh_password' => $worker['ssh_password'] ?? '',
            'docker_workspace_path' => $worker['workspace_path'] ?? '/srv/trendradar',
            'worker_id' => $worker['id'] ?? null,
            'worker_name' => $worker['name'] ?? ''
        ];
    }
    
    /**
     * Save or update Docker worker for user
     */
    public function saveDockerWorker($userId, $host, $port, $username, $password = null, $workspacePath = null, $name = null)
    {
        // Check if user has an existing worker
        $existing = $this->db->fetchOne(
            'SELECT id FROM docker_workers WHERE user_id = ? LIMIT 1',
            [$userId]
        );
        
        $data = [
            'ssh_host' => $host,
            'ssh_port' => (int)$port ?: 22,
            'ssh_username' => $username
        ];
        
        if ($password !== null) {
            $data['ssh_password'] = $password;
        }
        
        if ($workspacePath !== null) {
            $data['workspace_path'] = $workspacePath;
        }
        
        if ($name !== null) {
            $data['name'] = $name;
        }
        
        if ($existing) {
            // Update existing worker
            return $this->db->update('docker_workers', $data, 'id = ?', [$existing['id']]);
        } else {
            // Create new worker
            $data['user_id'] = $userId;
            $data['name'] = $name ?: 'Default Worker';
            return $this->db->insert('docker_workers', $data);
        }
    }
    
    /**
     * Update Docker SSH settings for user (backwards compatibility alias)
     */
    public function updateDockerSSHSettings($userId, $host, $port, $username, $password = null, $workspacePath = null)
    {
        return $this->saveDockerWorker($userId, $host, $port, $username, $password, $workspacePath);
    }
    
    /**
     * Check if Docker SSH is configured for user
     */
    public function isDockerSSHConfigured($userId)
    {
        $worker = $this->getSelectedDockerWorker($userId);
        return !empty($worker['ssh_host']) && !empty($worker['ssh_username']);
    }
    
    /**
     * Get all Docker workers available to user (own workers + public workers)
     */
    public function getAvailableDockerWorkers($userId)
    {
        // Get own workers and public workers from other users (only active workers)
        $workers = $this->db->fetchAll(
            'SELECT dw.*, u.username as owner_username 
             FROM docker_workers dw 
             LEFT JOIN users u ON dw.user_id = u.id 
             WHERE (dw.user_id = ? OR dw.is_public = 1) AND dw.is_active = 1
             ORDER BY CASE WHEN dw.user_id = ? THEN 0 ELSE 1 END, dw.name ASC',
            [$userId, $userId]
        );
        
        return $workers ?: [];
    }
    
    /**
     * Get all Docker workers owned by user
     */
    public function getUserDockerWorkers($userId)
    {
        $workers = $this->db->fetchAll(
            'SELECT * FROM docker_workers WHERE user_id = ? ORDER BY name ASC',
            [$userId]
        );
        
        return $workers ?: [];
    }
    
    /**
     * Get a specific Docker worker by ID
     */
    public function getDockerWorkerById($workerId)
    {
        return $this->db->fetchOne(
            'SELECT * FROM docker_workers WHERE id = ?',
            [$workerId]
        );
    }
    
    /**
     * Check if user can access a Docker worker (own or public)
     */
    public function canAccessDockerWorker($userId, $workerId)
    {
        $worker = $this->getDockerWorkerById($workerId);
        if (!$worker) {
            return false;
        }
        return $worker['user_id'] == $userId || $worker['is_public'] == 1;
    }
    
    /**
     * Create a new Docker worker
     */
    public function createDockerWorker($userId, $name, $host, $port, $username, $password, $workspacePath, $isPublic = false)
    {
        return $this->db->insert('docker_workers', [
            'user_id' => $userId,
            'name' => $name,
            'ssh_host' => $host,
            'ssh_port' => (int)$port ?: 22,
            'ssh_username' => $username,
            'ssh_password' => $password,
            'workspace_path' => $workspacePath ?: '/srv/trendradar',
            'is_public' => $isPublic ? 1 : 0,
            'is_active' => 1
        ]);
    }
    
    /**
     * Update an existing Docker worker
     */
    public function updateDockerWorkerById($workerId, $name, $host, $port, $username, $password = null, $workspacePath = null, $isPublic = null)
    {
        $data = [
            'name' => $name,
            'ssh_host' => $host,
            'ssh_port' => (int)$port ?: 22,
            'ssh_username' => $username
        ];
        
        if ($password !== null) {
            $data['ssh_password'] = $password;
        }
        
        if ($workspacePath !== null) {
            $data['workspace_path'] = $workspacePath;
        }
        
        if ($isPublic !== null) {
            $data['is_public'] = $isPublic ? 1 : 0;
        }
        
        return $this->db->update('docker_workers', $data, 'id = ?', [$workerId]);
    }
    
    /**
     * Delete a Docker worker
     */
    public function deleteDockerWorker($workerId, $userId)
    {
        // Only allow deleting own workers
        return $this->db->delete('docker_workers', 'id = ? AND user_id = ?', [$workerId, $userId]);
    }
    
    /**
     * Get selected Docker worker for user (stored in session or first available)
     */
    public function getSelectedDockerWorker($userId)
    {
        // Check if user has a selected worker in session
        if (isset($_SESSION['selected_docker_worker_id'])) {
            $workerId = $_SESSION['selected_docker_worker_id'];
            $worker = $this->getDockerWorkerById($workerId);
            if ($worker && $this->canAccessDockerWorker($userId, $workerId)) {
                return $worker;
            }
        }
        
        // Otherwise return first available worker (including public workers)
        $availableWorkers = $this->getAvailableDockerWorkers($userId);
        if (!empty($availableWorkers)) {
            // Return first worker (already filtered to active workers)
            return $availableWorkers[0];
        }
        
        // No workers available, return empty structure
        return [
            'id' => null,
            'name' => '',
            'ssh_host' => '',
            'ssh_port' => 22,
            'ssh_username' => '',
            'ssh_password' => '',
            'workspace_path' => '/srv/trendradar'
        ];
    }
    
    /**
     * Set selected Docker worker for user
     */
    public function setSelectedDockerWorker($userId, $workerId)
    {
        if ($this->canAccessDockerWorker($userId, $workerId)) {
            $_SESSION['selected_docker_worker_id'] = $workerId;
            return true;
        }
        return false;
    }
    
    /**
     * Check if a user is an admin
     */
    public function isAdmin($userId)
    {
        $user = $this->db->fetchOne(
            'SELECT is_admin FROM users WHERE id = ?',
            [$userId]
        );
        
        return $user && (int)$user['is_admin'] === 1;
    }
    
    /**
     * Check if current logged-in user is an admin (static method)
     */
    public static function checkIsAdmin()
    {
        static $isAdminCache = null;
        static $cachedUserId = null;
        
        $userId = self::getUserId();
        if (!$userId) {
            return false;
        }
        
        // Return cached result if available for the same user
        if ($isAdminCache !== null && $cachedUserId === $userId) {
            return $isAdminCache;
        }
        
        $auth = new self();
        $isAdminCache = $auth->isAdmin($userId);
        $cachedUserId = $userId;
        
        return $isAdminCache;
    }
    
    /**
     * Grant admin role to a user
     */
    public function grantAdmin($userId, $grantedByUserId)
    {
        // Check if the granting user is an admin
        if (!$this->isAdmin($grantedByUserId)) {
            throw new Exception('Only admins can grant admin role');
        }
        
        return $this->db->update(
            'users',
            ['is_admin' => 1],
            'id = ?',
            [$userId]
        );
    }
    
    /**
     * Revoke admin role from a user
     */
    public function revokeAdmin($userId, $revokedByUserId)
    {
        // Check if the revoking user is an admin
        if (!$this->isAdmin($revokedByUserId)) {
            throw new Exception('Only admins can revoke admin role');
        }
        
        // Prevent revoking own admin role
        if ((int)$userId === (int)$revokedByUserId) {
            throw new Exception(__('cannot_revoke_self'));
        }
        
        // Prevent revoking admin from the first user (ID: 1)
        if ((int)$userId === self::FIRST_USER_ID) {
            throw new Exception(__('cannot_revoke_first_user'));
        }
        
        // Check if this is the last admin
        $adminCount = $this->db->fetchOne('SELECT COUNT(*) as count FROM users WHERE is_admin = 1');
        if ($adminCount && (int)$adminCount['count'] <= 1) {
            throw new Exception(__('cannot_revoke_last_admin'));
        }
        
        return $this->db->update(
            'users',
            ['is_admin' => 0],
            'id = ?',
            [$userId]
        );
    }
    
    /**
     * Get all users (admin only)
     */
    public function getAllUsers()
    {
        return $this->db->fetchAll(
            'SELECT id, username, email, is_admin, created_at, last_login FROM users ORDER BY created_at DESC'
        );
    }
    
    /**
     * Get feature toggle status
     */
    public function isFeatureEnabled($featureKey)
    {
        $feature = $this->db->fetchOne(
            'SELECT is_enabled FROM feature_toggles WHERE feature_key = ?',
            [$featureKey]
        );
        
        // If feature not found in database, default to disabled for security
        // (deny by default principle)
        if (!$feature) {
            return false;
        }
        
        return (int)$feature['is_enabled'] === 1;
    }
    
    /**
     * Toggle feature on/off (admin only)
     */
    public function toggleFeature($featureKey, $enabled, $adminUserId)
    {
        // Check if the user is an admin
        if (!$this->isAdmin($adminUserId)) {
            throw new Exception('Only admins can toggle features');
        }
        
        // Check if feature exists
        $feature = $this->db->fetchOne(
            'SELECT id FROM feature_toggles WHERE feature_key = ?',
            [$featureKey]
        );
        
        if (!$feature) {
            throw new Exception('Feature not found');
        }
        
        return $this->db->update(
            'feature_toggles',
            ['is_enabled' => $enabled ? 1 : 0],
            'feature_key = ?',
            [$featureKey]
        );
    }
    
    /**
     * Get all feature toggles
     */
    public function getAllFeatureToggles()
    {
        return $this->db->fetchAll(
            'SELECT * FROM feature_toggles ORDER BY feature_key ASC'
        );
    }
    
    /**
     * Update Docker configuration sync timestamp
     */
    public function updateDockerConfigSyncTime($userId)
    {
        return $this->db->update(
            'users',
            ['docker_config_synced_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$userId]
        );
    }
    
    /**
     * Check if Docker configuration has changed since last sync
     * Returns true if configuration changed after last sync or if never synced
     */
    public function hasDockerConfigChanged($userId)
    {
        // Get last sync time
        $user = $this->db->fetchOne(
            'SELECT docker_config_synced_at FROM users WHERE id = ?',
            [$userId]
        );
        
        if (!$user || !$user['docker_config_synced_at']) {
            // Never synced - consider as changed if there's an active config
            $activeConfig = $this->db->fetchOne(
                'SELECT id FROM configurations WHERE user_id = ? AND is_active = 1',
                [$userId]
            );
            return (bool)$activeConfig;
        }
        
        $lastSyncTime = $user['docker_config_synced_at'];
        
        // Check if any configuration-related data has been updated after last sync
        // Check configurations table
        $configChanged = $this->db->fetchOne(
            'SELECT id FROM configurations WHERE user_id = ? AND updated_at > ?',
            [$userId, $lastSyncTime]
        );
        
        if ($configChanged) {
            return true;
        }
        
        // Check platforms table (through configurations)
        $platformsChanged = $this->db->fetchOne(
            'SELECT p.id FROM platforms p 
             INNER JOIN configurations c ON p.config_id = c.id 
             WHERE c.user_id = ? AND p.updated_at > ?',
            [$userId, $lastSyncTime]
        );
        
        if ($platformsChanged) {
            return true;
        }
        
        // Check keywords table (through configurations)
        $keywordsChanged = $this->db->fetchOne(
            'SELECT k.id FROM keywords k 
             INNER JOIN configurations c ON k.config_id = c.id 
             WHERE c.user_id = ? AND k.updated_at > ?',
            [$userId, $lastSyncTime]
        );
        
        if ($keywordsChanged) {
            return true;
        }
        
        // Check webhooks table (through configurations)
        $webhooksChanged = $this->db->fetchOne(
            'SELECT w.id FROM webhooks w 
             INNER JOIN configurations c ON w.config_id = c.id 
             WHERE c.user_id = ? AND w.updated_at > ?',
            [$userId, $lastSyncTime]
        );
        
        if ($webhooksChanged) {
            return true;
        }
        
        // Check settings table (through configurations)
        $settingsChanged = $this->db->fetchOne(
            'SELECT s.id FROM settings s 
             INNER JOIN configurations c ON s.config_id = c.id 
             WHERE c.user_id = ? AND s.updated_at > ?',
            [$userId, $lastSyncTime]
        );
        
        if ($settingsChanged) {
            return true;
        }
        
        return false;
    }
}

/**
 * Require user to be logged in, or redirect to login page.
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}
