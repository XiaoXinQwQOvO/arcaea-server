<?php
declare(strict_types=1);

/**
 * 用户主页：展示 UID、好友码、PTT
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/layout.php';

require_login();

$user = current_user();
if ($user === null) {
    redirect('index.php');
}

$uid = (int)$user['user_id'];
$friendCode = (string)$user['user_code'];
$ptt = is_numeric($user['rating_ptt'])
    ? number_format((float)$user['rating_ptt'], 2, '.', '')
    : '0.00';
$name = (string)$user['name'];
$email = (string)($user['email'] ?? '');
$joinDate = !empty($user['join_date'])
    ? date('Y-m-d H:i:s', (int)((int)$user['join_date'] / 1000))
    : '—';

$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>我的账号 · Arcaxa 用户中心</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="profile-page">
<?php render_topbar('profile'); ?>

<div class="profile-wrap">
    <?php foreach ($flash as $f): ?>
        <div class="alert alert-<?= e($f['type'] === 'success' ? 'success' : 'error') ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>

    <div class="profile-head">
        <div class="avatar"><?= e(first_char($name)) ?></div>
        <div>
            <h1><?= e($name) ?></h1>
            <p class="sub"><?= e($email !== '' ? $email : '未绑定邮箱') ?> · 注册于 <?= e($joinDate) ?></p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">UID</div>
            <div class="stat-value"><?= e((string)$uid) ?></div>
            <div class="stat-note">用户唯一编号</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">好友码</div>
            <div class="stat-value code" id="friend-code"><?= e($friendCode) ?></div>
            <div class="stat-note">
                <button type="button" class="copy-btn" data-copy="<?= e($friendCode) ?>">复制</button>
            </div>
        </div>
        <div class="stat-card highlight">
            <div class="stat-label">PTT</div>
            <div class="stat-value ptt"><?= e($ptt) ?></div>
            <div class="stat-note">Potential 数值，游戏内游玩后更新</div>
        </div>
    </div>
</div>

<script src="assets/main.js"></script>
</body>
</html>