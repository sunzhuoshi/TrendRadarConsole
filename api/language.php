<?php
/**
 * TrendRadarConsole - Language API
 */

session_start();
require_once '../includes/helpers.php';

$method = getMethod();
$input = getInput();

if ($method === 'POST') {
    $lang = $input['lang'] ?? '';
    
    if (empty($lang)) {
        jsonError('Language is required');
    }
    
    $supportedLangs = ['zh', 'en'];
    if (!in_array($lang, $supportedLangs)) {
        jsonError('Unsupported language');
    }
    
    setLanguage($lang);
    
    jsonSuccess(['lang' => $lang], __('language') . ' ' . ($lang === 'zh' ? __('chinese') : __('english')));
} elseif ($method === 'GET') {
    $lang = getCurrentLanguage();
    jsonSuccess(['lang' => $lang, 'supported' => ['zh', 'en']]);
} else {
    jsonError('Method not allowed', 405);
}
