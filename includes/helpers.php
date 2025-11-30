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
