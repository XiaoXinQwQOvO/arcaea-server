<?php
declare(strict_types=1);

/**
 * 管理后台
 * - 邀请码：批量生成 / 删除 / 查看使用者
 * - 账号：删除（清理该 user_id 在所有表中的数据）
 * 管理员判定：user_role 表中有此 user_id 记录。
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/invite.php';
require_once __DIR__ . '/../lib/layout.php';

require_admin();

$msg = null;
$error = null;

$csrf = csrf_token();

/* ---------- 处理表单 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = '会话已过期，请刷新页面后重试';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'generate') {
            $count = (int)($_POST['count'] ?? 0);
            $note = (string)($_POST['note'] ?? '');
            [$ok, $r] = invite_batch_generate($count, (int)current_user()['user_id'], $note);
            $ok ? $msg = $r : $error = $r;
        } elseif ($action === 'delete_codes') {
            $codes = (string)($_POST['codes'] ?? '');
            [$ok, $r] = invite_batch_delete($codes);
            $ok ? $msg = $r : $error = $r;
        } elseif ($action === 'delete_code') {
            $code = (string)($_POST['code'] ?? '');
            [$ok, $r] = invite_batch_delete($code);
            $ok ? $msg = $r : $error = $r;
        } elseif ($action === 'delete_user') {
            $identifier = (string)($_POST['identifier'] ?? '');
            [$ok, $r] = admin_delete_user($identifier);
            $ok ? $msg = $r : $error = $r;
        } else {
            $error = '未知操作';
        }
    }
}

/* ---------- 数据 ---------- */
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'unused', 'used'], true)) {
    $status = 'all';
}

try {
    $codes = invite_list($status);

    // 统计数据
    $db = app_db();
    $statTotal = (int)$db->query('SELECT COUNT(*) FROM invite_code')->fetchColumn();
    $statUnused = (int)$db->query('SELECT COUNT(*) FROM invite_code WHERE used_by IS NULL')->fetchColumn();
    $statUsed = $statTotal - $statUnused;
    $statUsers = (int)$db->query('SELECT COUNT(*) FROM user')->fetchColumn();
} catch (Throwable $e) {
    $error = '数据库读取失败：' . $e->getMessage();
    $codes = [];
    $statTotal = $statUnused = $statUsed = $statUsers = 0;
}

function fmt_ts($ts): string
{
    if (empty($ts)) {
        return '—';
    }
    return date('Y-m-d H:i:s', (int)((int)$ts / 1000));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理后台 · Arcaea 控制台</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="profile-page">
<?php render_topbar('admin'); ?>

<div class="admin-wrap">
    <h1 class="page-title">管理后台</h1>

    <?php if ($msg !== null): ?>
        <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="stat-grid admin-stats">
        <div class="stat-card"><div class="stat-label">邀请码总数</div><div class="stat-value"><?= (int)$statTotal ?></div></div>
        <div class="stat-card"><div class="stat-label">未使用</div><div class="stat-value"><?= (int)$statUnused ?></div></div>
        <div class="stat-card"><div class="stat-label">已使用</div><div class="stat-value"><?= (int)$statUsed ?></div></div>
        <div class="stat-card"><div class="stat-label">注册用户数</div><div class="stat-value"><?= (int)$statUsers ?></div></div>
    </div>

    <!-- 批量生成邀请码 -->
    <div class="panel">
        <h2>批量生成邀请码</h2>
        <form method="post" action="index.php" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="generate">
            <div class="field">
                <label for="count">生成数量</label>
                <input type="number" id="count" name="count" value="10" min="1" max="500" required>
            </div>
            <div class="field">
                <label for="note">备注（可选）</label>
                <input type="text" id="note" name="note" maxlength="50" placeholder="例如：玩家群 / 活动批次">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">生成</button>
        </form>
    </div>

    <!-- 删除邀请码 -->
    <div class="panel">
        <h2>删除邀请码</h2>
        <form method="post" action="index.php" class="form-grid delete-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="delete_codes">
            <div class="field field-wide">
                <label for="codes">邀请码（多个用英文逗号分隔）</label>
                <textarea id="codes" name="codes" rows="2" placeholder="CODE1,CODE2,..."></textarea>
            </div>
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('确认删除这些邀请码？')">删除</button>
        </form>
    </div>

    <!-- 邀请码列表 -->
    <div class="panel">
        <div class="panel-head">
            <h2>邀请码列表</h2>
            <div class="tabs">
                <a href="index.php?status=all" class="<?= $status === 'all' ? 'active' : '' ?>">全部</a>
                <a href="index.php?status=unused" class="<?= $status === 'unused' ? 'active' : '' ?>">未使用</a>
                <a href="index.php?status=used" class="<?= $status === 'used' ? 'active' : '' ?>">已使用</a>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>邀请码</th>
                    <th>备注</th>
                    <th>生成时间</th>
                    <th>使用者</th>
                    <th>使用时间</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$codes): ?>
                    <tr><td colspan="7" class="empty">暂无邀请码</td></tr>
                <?php endif; ?>
                <?php foreach ($codes as $c): ?>
                    <?php $used = $c['used_by'] !== null; ?>
                    <tr>
                        <td class="mono"><?= e($c['code']) ?></td>
                        <td><?= e((string)($c['note'] ?? '')) === '' ? '—' : e((string)($c['note'] ?? '')) ?></td>
                        <td><?= e(fmt_ts($c['created_at'])) ?></td>
                        <td>
                            <?php if ($used): ?>
                                <?= e((string)($c['used_by_name'] ?? '')) === '' ? 'UID ' . e((string)$c['used_by']) : e((string)$c['used_by_name']) . '（UID ' . e((string)$c['used_by']) . '）' ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= e(fmt_ts($c['used_at'])) ?></td>
                        <td>
                            <span class="badge <?= $used ? 'badge-used' : 'badge-unused' ?>"><?= $used ? '已使用' : '未使用' ?></span>
                        </td>
                        <td>
                            <form method="post" action="index.php" class="inline-form">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="delete_code">
                                <input type="hidden" name="code" value="<?= e($c['code']) ?>">
                                <button type="submit" class="btn btn-ghost btn-sm"
                                        onclick="return confirm('确认删除该邀请码？')">删除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 删除账号 -->
    <div class="panel danger-panel">
        <h2>删除账号</h2>
        <p class="panel-tip">输入要删除账号的 <strong>user_id</strong>、<strong>用户名</strong> 或 <strong>好友码</strong>。
            将删除该账号在所有表中的数据（成绩、好友、角色、物品、存档、登录记录等），
            其注册时使用的邀请码会被释放为未使用。此操作不可恢复！</p>
        <form method="post" action="index.php" class="form-grid delete-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="delete_user">
            <div class="field field-wide">
                <label for="identifier">user_id / 用户名 / 好友码</label>
                <input type="text" id="identifier" name="identifier" required
                       placeholder="例如：2000001 或 玩家名 或 123456789">
            </div>
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('确认删除该账号及其全部数据？此操作不可恢复！')">删除账号</button>
        </form>
    </div>
</div>

</body>
</html>