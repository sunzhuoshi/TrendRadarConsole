<?php
/**
 * TrendRadarConsole - Logout
 */

session_start();
require_once 'includes/auth.php';

Auth::logout();
header('Location: login.php');
exit;
