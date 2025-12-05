<?php
/**
 * TrendRadarConsole - Authentication Helper
 */

require_once __DIR__ . '/database.php';

class Auth
{
    private $db;
    
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
        
        // Insert user
        $userId = $this->db->insert('users', [
            'username' => $username,
            'password_hash' => $passwordHash,
            'email' => $email
        ]);
        
        // Create default configuration for user
        $configId = $this->db->insert('configurations', [
            'user_id' => $userId,
            'name' => 'Default',
            'description' => 'Default TrendRadar configuration',
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
        
        foreach ($defaultPlatforms as $index => $p) {
            $this->db->insert('platforms', [
                'config_id' => $configId,
                'platform_id' => $p['id'],
                'platform_name' => $p['name'],
                'is_enabled' => 1,
                'sort_order' => $index + 1
            ]);
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
        
        foreach ($defaultSettings as $key => $value) {
            $this->db->insert('settings', [
                'config_id' => $configId,
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
        
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
}

/**
 * Helper function to require login
 */
function requireLogin()
{
    Auth::requireLogin();
}
