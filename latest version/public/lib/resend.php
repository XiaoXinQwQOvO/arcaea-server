<?php
declare(strict_types=1);

/**
 * Resend.com 邮件发送客户端
 * 文档：https://resend.com/docs/api-reference/emails/send-email
 *
 * POST https://api.resend.com/emails
 * Authorization: Bearer re_xxxxxxxx
 * Content-Type: application/json
 * body: { "from": "Name <addr@domain>", "to": ["user@example.com"], "subject": "...", "html": "..." }
 * 成功返回 200 {"id": "..."}；失败返回非 200 + {"message": "..."}
 */

function resend_config(): array
{
    static $resend = null;
    if ($resend === null) {
        $config = require __DIR__ . '/config.php';
        $resend = $config['resend'] ?? [];
    }
    return $resend;
}

/**
 * 发送一封 HTML 邮件。
 *
 * @return array{0: bool, 1: ?string} [是否成功, 错误信息]
 */
function resend_send(string $to, string $subject, string $html): array
{
    $conf = resend_config();

    if (empty($conf['enabled']) || empty($conf['api_key'])) {
        return [false, 'Resend 未配置：请填写 public/lib/config.php 中的 api_key 并将 enabled 设为 true'];
    }

    $from = $conf['from'] ?? '';
    if ($from === '') {
        return [false, 'Resend 未配置发件地址（from）'];
    }

    $payload = [
        'from'    => $from,
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $html,
    ];

    $ch = curl_init($conf['api_url'] ?? 'https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $conf['api_key'],
            'Content-Type: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return [false, '邮件发送失败（网络错误）：' . $error];
    }

    if ($status < 200 || $status >= 300) {
        $msg = 'HTTP ' . $status;
        $resp = json_decode((string)$body, true);
        if (is_array($resp) && !empty($resp['message'])) {
            $msg .= '：' . $resp['message'];
        }
        return [false, '邮件发送失败：' . $msg];
    }

    return [true, null];
}

/** 生成注册验证码邮件正文（HTML） */
function resend_verification_html(int $code, int $ttlSeconds): string
{
    $minutes = intdiv($ttlSeconds, 60);
    $minutes = max(1, $minutes);

    return '<div style="font-family:\'Helvetica Neue\',Arial,sans-serif;max-width:520px;margin:0 auto;padding:32px 24px;background:#f7f7fb;border-radius:12px;color:#2b2b3a;">'
        . '<h2 style="margin:0 0 8px;font-size:20px;">Arcaea 账号注册</h2>'
        . '<p style="margin:0 0 20px;color:#5b5b70;font-size:14px;">感谢注册本服务器，您的邮箱验证码为：</p>'
        . '<p style="margin:0 0 20px;font-size:40px;font-weight:700;letter-spacing:8px;color:#6c4bd8;'
        . 'background:#fff;border-radius:10px;text-align:center;padding:16px 0;border:1px dashed #c9bef5;">'
        . $code . '</p>'
        . '<p style="margin:0;color:#8a8aa0;font-size:13px;">验证码 ' . $minutes . ' 分钟内有效，请勿泄露给他人。'
        . '如果不是您本人操作，请忽略此邮件。</p>'
        . '</div>';
}