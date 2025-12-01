<?php
/**
 * TrendRadarConsole - Helper Functions
 */

/**
 * Sanitize input to prevent XSS attacks
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Success JSON response
 */
function jsonSuccess($data = null, $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Error JSON response
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message,
        'data' => null
    ], $statusCode);
}

/**
 * Redirect helper
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Get base URL
 */
function baseUrl($path = '') {
    $configFile = __DIR__ . '/../config/config.php';
    if (file_exists($configFile)) {
        $config = require $configFile;
        $base = $config['app']['base_url'] ?? '';
        if ($base) {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $base = $protocol . '://' . $host . ($scriptDir === '/' ? '' : $scriptDir);
    
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get request method
 */
function getMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Get input data (supports both form and JSON)
 */
function getInput() {
    if (getMethod() === 'GET') {
        return $_GET;
    }
    
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?: [];
    }
    
    return $_POST;
}

/**
 * Convert array to YAML string (simple implementation)
 */
function arrayToYaml($array, $indent = 0) {
    $yaml = '';
    $prefix = str_repeat('  ', $indent);
    
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            if (isset($value[0])) {
                // Indexed array
                $yaml .= $prefix . $key . ":\n";
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $yaml .= $prefix . "  - " . arrayToYamlInline($item) . "\n";
                    } else {
                        $yaml .= $prefix . "  - " . formatYamlValue($item) . "\n";
                    }
                }
            } else {
                // Associative array
                $yaml .= $prefix . $key . ":\n";
                $yaml .= arrayToYaml($value, $indent + 1);
            }
        } else {
            $yaml .= $prefix . $key . ': ' . formatYamlValue($value) . "\n";
        }
    }
    
    return $yaml;
}

/**
 * Format YAML value
 */
function formatYamlValue($value) {
    if ($value === true) return 'true';
    if ($value === false) return 'false';
    if ($value === null) return '""';
    if ($value === '') return '""';
    if (is_numeric($value)) return $value;
    if (preg_match('/^[\w\-\.\/]+$/', $value)) return $value;
    return '"' . addslashes($value) . '"';
}

/**
 * Convert array to inline YAML
 */
function arrayToYamlInline($array) {
    $parts = [];
    foreach ($array as $key => $value) {
        $parts[] = $key . ': ' . formatYamlValue($value);
    }
    return implode(', ', $parts);
}

/**
 * Flash message helpers
 */
function setFlash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * CSRF token helpers
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Language/Translation helpers
 */

// Global variable to store loaded translations
$GLOBALS['__translations'] = null;
$GLOBALS['__current_lang'] = null;

/**
 * Get current language code
 * Priority: session > cookie > default (zh)
 */
function getCurrentLanguage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check session first
    if (isset($_SESSION['lang'])) {
        return $_SESSION['lang'];
    }
    
    // Check cookie
    if (isset($_COOKIE['lang'])) {
        $_SESSION['lang'] = $_COOKIE['lang'];
        return $_COOKIE['lang'];
    }
    
    // Default to Chinese
    return 'zh';
}

/**
 * Set the current language
 */
function setLanguage($lang) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Only allow supported languages
    $supportedLangs = ['zh', 'en'];
    if (!in_array($lang, $supportedLangs)) {
        $lang = 'zh';
    }
    
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, time() + (365 * 24 * 60 * 60), '/'); // 1 year
    $GLOBALS['__current_lang'] = $lang;
    $GLOBALS['__translations'] = null; // Force reload translations
    
    return $lang;
}

/**
 * Load translations for the current language
 */
function loadTranslations($lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    // Return cached translations if already loaded for this language
    if ($GLOBALS['__translations'] !== null && $GLOBALS['__current_lang'] === $lang) {
        return $GLOBALS['__translations'];
    }
    
    $langFile = __DIR__ . '/../lang/' . $lang . '.php';
    
    // Fall back to Chinese if language file doesn't exist
    if (!file_exists($langFile)) {
        $langFile = __DIR__ . '/../lang/zh.php';
        $lang = 'zh';
    }
    
    $GLOBALS['__translations'] = require $langFile;
    $GLOBALS['__current_lang'] = $lang;
    
    return $GLOBALS['__translations'];
}

/**
 * Translate a string
 * Usage: __('key') or __('key', ['param' => 'value'])
 */
function __($key, $params = []) {
    $translations = loadTranslations();
    
    $text = isset($translations[$key]) ? $translations[$key] : $key;
    
    // Replace parameters if any
    if (!empty($params)) {
        foreach ($params as $param => $value) {
            $text = str_replace(':' . $param, $value, $text);
        }
    }
    
    return $text;
}

/**
 * Echo translated string (shorthand for echo __())
 */
function _e($key, $params = []) {
    echo __($key, $params);
}

/**
 * Get all JavaScript translations as JSON for client-side use
 */
function getJsTranslations() {
    $translations = loadTranslations();
    
    // Filter only JS-related translations (those starting with 'js_')
    $jsTranslations = [];
    foreach ($translations as $key => $value) {
        if (strpos($key, 'js_') === 0) {
            // Remove 'js_' prefix for cleaner JS usage
            $jsKey = substr($key, 3);
            $jsTranslations[$jsKey] = $value;
        }
    }
    
    // Also add some common translations needed in JS
    $commonKeys = [
        'confirm_remove_platform', 'confirm_remove_webhook', 'platform_updated',
        'platform_added', 'platform_removed', 'webhook_saved', 'webhook_updated',
        'webhook_removed', 'settings_saved', 'setting_updated', 'keywords_saved',
        'file_downloaded', 'copied_to_clipboard', 'failed_to_update', 'failed_to_add',
        'failed_to_remove', 'failed_to_save', 'failed_to_load', 'example_loaded',
        'keywords_cleared', 'replace_keywords_confirm', 'clear_keywords_confirm',
        'github_settings_saved', 'connection_successful', 'connection_failed',
        'config_loaded_from_github', 'confirm_save_to_github', 'config_saved_to_github',
        'fill_all_fields', 'configure_github_first', 'display_name_required',
        'enter_both_id_name', 'group', 'no_keywords_preview', 'weight_sum_is', 'should_be',
        'weight_sum_message'
    ];
    
    foreach ($commonKeys as $key) {
        if (isset($translations[$key]) && !isset($jsTranslations[$key])) {
            $jsTranslations[$key] = $translations[$key];
        }
    }
    
    return json_encode($jsTranslations, JSON_UNESCAPED_UNICODE);
}
