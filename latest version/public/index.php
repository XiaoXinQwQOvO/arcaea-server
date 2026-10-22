<?php
declare(strict_types=1);

/**
 * 登录页
 */

require_once __DIR__ . '/lib/auth.php';

// 已登录则直接进入主页
if (current_user() !== null) {
    redirect('profile.php');
}

$error = null;
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '会话已过期，请刷新页面后重试';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($name === '' || $password === '') {
            $error = '请输入用户名和密码';
        } else {
            [$ok, $msg] = user_login($name, $password);
            if ($ok) {
                redirect('profile.php');
            }
            $error = $msg;
        }
    }
}

$csrf = csrf_token();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录 · Arcaea 用户中心</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="logo">
        <div class="logo-mark">A</div>
        <h1>Arcaea 用户中心</h1>
        <p>登录以查看你的 UID、好友码与 PTT</p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php foreach ($flash as $f): ?>
        <div class="alert alert-<?= e($f['type'] === 'success' ? 'success' : 'error') ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="index.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="field">
            <label for="name">用户名</label>
            <input type="text" id="name" name="name" value="<?= e($name) ?>" required autofocus
                   autocomplete="username" placeholder="游戏内用户名" maxlength="16">
        </div>
        <div class="field">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required
                   autocomplete="current-password" placeholder="密码">
        </div>
        <button type="submit" class="btn btn-primary btn-block">登 录</button>
    </form>

    <p class="switch">还没有账号？<a href="register.php">立即注册</a></p>
</div>
</body>
</html>