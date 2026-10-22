<?php
declare(strict_types=1);

/**
 * 注册页：邮箱验证码（Resend.com）→ 填写账号信息 → 创建账号
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/invite.php';

// 已登录则直接进入主页
if (current_user() !== null) {
    redirect('profile.php');
}

$error = null;
$email = '';
$name = '';
$inviteCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '会话已过期，请刷新页面后重试';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $inviteCode = trim((string)($_POST['invite_code'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        if ($inviteCode === '') {
            $error = '请输入邀请码';
        } elseif ($password !== $password2) {
            $error = '两次输入的密码不一致';
        } else {
            app_session_start();
            $vc = $_SESSION['verify_code'] ?? null;

            if (!is_array($vc)
                || empty($vc['code']) || empty($vc['email']) || empty($vc['expires'])
                || !isset($vc['attempts'])) {
                $error = '请先获取邮箱验证码';
            } elseif (!hash_equals((string)$vc['email'], $email)) {
                $error = '验证码与邮箱不匹配，请重新获取';
            } elseif ((int)$vc['expires'] < time()) {
                $error = '验证码已过期，请重新获取';
                unset($_SESSION['verify_code']);
            } elseif ((int)$vc['attempts'] >= 5) {
                $error = '验证码错误次数过多，请重新获取';
                unset($_SESSION['verify_code']);
            } elseif (!hash_equals((string)$vc['code'], hash('sha256', $code))) {
                $_SESSION['verify_code']['attempts'] = (int)$vc['attempts'] + 1;
                $error = '验证码错误';
            } else {
                // 邀请码在用户创建事务内校验并占用，保证原子性
                $claim = function (PDO $db, int $userId) use ($inviteCode): array {
                    return invite_claim($db, $inviteCode, $userId);
                };
                [$ok, $msg] = user_register($name, $password, $email, $claim);
                if ($ok) {
                    unset($_SESSION['verify_code']);
                    flash_set('success', '注册成功，欢迎 ' . $name . '！');
                    redirect('profile.php');
                }
                $error = $msg;
            }
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
<title>注册 · Arcaea 用户中心</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="logo">
        <div class="logo-mark">A</div>
        <h1>创建账号</h1>
        <p>输入邮箱获取验证码，验证通过后即可注册</p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php foreach ($flash as $f): ?>
        <div class="alert alert-<?= e($f['type'] === 'success' ? 'success' : 'error') ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="register.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <div class="field">
            <label for="email">邮箱</label>
            <div class="field-row">
                <input type="email" id="email" name="email" value="<?= e($email) ?>" required
                       autocomplete="email" placeholder="name@example.com" maxlength="64">
                <button type="button" id="send-code-btn" class="btn btn-ghost">发送验证码</button>
            </div>
            <p class="hint">验证码将发送至该邮箱（通过 Resend.com）</p>
        </div>

        <div class="field">
            <label for="code">邮箱验证码</label>
            <input type="text" id="code" name="code" required inputmode="numeric"
                   pattern="[0-9]{6}" placeholder="6 位数字验证码" maxlength="6">
        </div>

        <div class="field">
            <label for="invite_code">邀请码</label>
            <input type="text" id="invite_code" name="invite_code" value="<?= e($inviteCode) ?>" required
                   autocomplete="off" placeholder="请输入邀请码" maxlength="32">
            <p class="hint">需要有效邀请码才能注册</p>
        </div>

        <div class="field">
            <label for="name">用户名</label>
            <input type="text" id="name" name="name" value="<?= e($name) ?>" required
                   autocomplete="username" placeholder="3-16 个字符（游戏内用户名）" maxlength="16">
        </div>

        <div class="field">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required
                   autocomplete="new-password" placeholder="8-32 个字符" minlength="8" maxlength="32">
        </div>

        <div class="field">
            <label for="password2">确认密码</label>
            <input type="password" id="password2" name="password2" required
                   autocomplete="new-password" placeholder="再次输入密码" minlength="8" maxlength="32">
        </div>

        <button type="submit" class="btn btn-primary btn-block">注 册</button>
    </form>

    <p class="switch">已有账号？<a href="index.php">返回登录</a></p>
</div>
<script src="assets/main.js"></script>
</body>
</html>