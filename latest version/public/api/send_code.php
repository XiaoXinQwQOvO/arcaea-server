<?php
declare(strict_types=1);

/**
 * AJAX 接口：发送邮箱注册验证码（通过 Resend.com）
 * 仅接受 POST。
 *
 * 请求：email=user@example.com
 * 响应：{"ok": true, "message": "..."} 或 {"ok": false, "message": "..."}
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/resend.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(bool $ok, string $message): never
{
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(false, '请求方式错误');
}

$email = trim((string)($_POST['email'] ?? ''));
$emailLen = function_exists('mb_strlen') ? mb_strlen($email) : strlen($email);
if ($emailLen < 4 || $emailLen > 64 || !str_contains($email, '@') || !str_contains($email, '.')) {
    json_out(false, '邮箱格式不正确');
}

try {
    $db = app_db();

    // 邮箱是否已被注册
    $stmt = $db->prepare('SELECT 1 FROM user WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        json_out(false, '该邮箱已被注册，请直接登录');
    }
} catch (Throwable $e) {
    json_out(false, '数据库不可用，请确认 Python 服务器已初始化数据库');
}

$config = app_config();
$ttl = (int)($config['code_ttl'] ?? 600) ?: 600;
$cooldown = (int)($config['code_resend_cooldown'] ?? 60) ?: 60;
$dailyLimit = (int)($config['code_daily_limit'] ?? 20) ?: 20;

app_session_start();

/* ---------- 频率限制 ---------- */
$now = time();

// 同一邮箱重发冷却
$last = $_SESSION['code_send_last'] ?? null;
if (is_array($last) && ($last['email'] ?? '') === $email) {
    $wait = $last['ts'] + $cooldown - $now;
    if ($wait > 0) {
        json_out(false, '发送过于频繁，请 ' . $wait . ' 秒后再试');
    }
}

// 同一 IP 每日次数限制
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$dayKey = date('Y-m-d');
$daily = $_SESSION['code_daily'] ?? ['day' => $dayKey, 'count' => 0];
if (($daily['day'] ?? '') !== $dayKey) {
    $daily = ['day' => $dayKey, 'count' => 0];
}
if ($daily['count'] >= $dailyLimit) {
    json_out(false, '今日验证码发送次数已达上限，请明天再试');
}

/* ---------- 生成并发送验证码 ---------- */
$code = random_int(100000, 999999);

// 先尝试发送，成功后再写入会话（避免发送失败导致验证码缓存）
$html = resend_verification_html($code, $ttl);
[$ok, $err] = resend_send($email, 'Arcaea 注册验证码', $html);

if (!$ok) {
    json_out(false, $err);
}

// 会话中仅保存验证码的 SHA-256 散列
$_SESSION['verify_code'] = [
    'code'     => hash('sha256', (string)$code),
    'email'    => $email,
    'expires'  => $now + $ttl,
    'attempts' => 0,
];
$_SESSION['code_send_last'] = ['email' => $email, 'ts' => $now];
$daily['count'] += 1;
$_SESSION['code_daily'] = $daily;

json_out(true, '验证码已发送至 ' . $email . '，请查收');