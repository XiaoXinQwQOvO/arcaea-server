<?php
declare(strict_types=1);

/**
 * 退出登录
 */

require_once __DIR__ . '/lib/auth.php';

app_session_start();
$_SESSION = [];
session_destroy();

redirect('index.php');