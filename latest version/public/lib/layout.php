<?php
declare(strict_types=1);

/**
 * 共享页面布局：顶部导航栏
 */

require_once __DIR__ . '/invite.php';

/**
 * 渲染顶栏：左上角 "Arcaea 控制台"；右上角：
 * - 管理员：显示 "管理后台" 入口 + 退出登录
 * - 普通用户：仅退出登录
 */
function render_topbar(string $active = ''): void
{
    $isAdmin = is_current_admin();
    $active = e($active);
    $base = app_base();
    ?>
<div class="topbar">
    <div class="topbar-inner">
        <div class="nav-left">
            <span class="brand">Arcaea 控制台</span>
            <nav class="nav-links">
                <a href="<?= $base ?>/profile.php" class="<?= $active === 'profile' ? 'active' : '' ?>">我的账号</a>
                <?php if ($isAdmin): ?>
                    <a href="<?= $base ?>/admin/index.php" class="<?= $active === 'admin' ? 'active' : '' ?>">管理后台</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="nav-right">
            <?php if ($isAdmin): ?>
                <span class="admin-badge">管理员</span>
            <?php endif; ?>
            <a href="<?= $base ?>/logout.php" class="btn btn-ghost btn-sm">退出登录</a>
        </div>
    </div>
</div>
    <?php
}