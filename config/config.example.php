<?php
/**
 * TrendRadarConsole - Database Configuration
 * 
 * Copy this file to config.php and update with your database credentials.
 * IMPORTANT: Never commit config.php to version control!
 */

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'trendradar_console',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'debug' => false,
        'timezone' => 'Asia/Shanghai',
        'base_url' => ''
    ]
];
