<?php
declare(strict_types=1);

/**
 * 会话管理与用户登录 / 注册逻辑
 *
 * 与 Python 服务器（core/user.py）保持同一套规则：
 * - 密码：SHA-256（hex 小写），PHP 的 hash('sha256') 与 hashlib.sha256().hexdigest() 一致
 * - 好友码：9 位纯数字，随机生成且全局唯一
 * - user_id：max(user_id) + 1；空表从 2000001 开始
 * - 封禁：password = '' 为永久封禁；ban_flag 格式 "天数:封禁结束毫秒时间戳" 为临时封禁
 * - 注册同时写入初始角色 user_char 与全解锁 user_char_full
 */

require_once __DIR__ . '/db.php';

/* ============================ 会话 ============================ */

function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('arcaea_web');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** 密码散列：与 Python 服务器 hashlib.sha256(password).hexdigest() 完全一致 */
function hash_password(string $password): string
{
    return hash('sha256', $password);
}

/** 当前登录用户（每次从数据库读取最新数据）；未登录返回 null */
function current_user(): ?array
{
    app_session_start();

    $uid = $_SESSION['uid'] ?? null;
    if ($uid === null || !is_numeric($uid)) {
        return null;
    }

    try {
        $stmt = app_db()->prepare(
            'SELECT user_id, name, user_code, rating_ptt, email, join_date, ban_flag
             FROM user WHERE user_id = :id'
        );
        $stmt->execute([':id' => (int)$uid]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

/* ============================ 通用工具 ============================ */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** 页面跳转（必须在任何输出之前调用） */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * 计算到站点根目录的相对路径前缀。
 * 页面在 public/ 根目录时为 ''；在 public/admin/ 子目录时为 '..'。
 * 供跨页面链接使用，兼容站点挂载在子目录的情况。
 */
function app_base(): string
{
    $dir = basename((string)dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return $dir === 'admin' ? '..' : '';
}

/** 未登录则跳转到登录页 */
function require_login(): void
{
    if (current_user() === null) {
        redirect('index.php');
    }
}

/* ---------- 一次性提示消息 ---------- */

function flash_set(string $type, string $message): void
{
    app_session_start();
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

/** 取出并清空所有提示消息 */
function flash_get(): array
{
    app_session_start();
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    app_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    app_session_start();
    $sent = (string)($_POST['csrf'] ?? '');
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/** 把秒数格式化为“x天x小时x分” */
function format_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' 天';
    }
    if ($hours > 0) {
        $parts[] = $hours . ' 小时';
    }
    if ($minutes > 0 || $parts === []) {
        $parts[] = max(1, $minutes) . ' 分';
    }
    return implode(' ', $parts);
}

/* ============================ 登录 ============================ */

/**
 * 登录校验，规则与 Python 服务器 server/auth.py + core/user.py 一致。
 * 本前端只做会话登录，不会向 login 表写入 token，因此不影响游戏的设备数限制。
 *
 * @return array{0: bool, 1: ?string} [是否成功, 错误信息]
 */
function user_login(string $name, string $password): array
{
    try {
        $stmt = app_db()->prepare(
            'SELECT user_id, name, password, ban_flag, user_code, rating_ptt, email
             FROM user WHERE name = :name'
        );
        $stmt->execute([':name' => $name]);
        $user = $stmt->fetch();
    } catch (Throwable $e) {
        return [false, '数据库不可用，请确认 Python 服务器已完成数据库初始化'];
    }

    if ($user === false) {
        return [false, '用户名不存在'];
    }

    $now = (int) round(microtime(true) * 1000);

    // 临时封禁（ban_flag 格式："天数:封禁结束时间戳(ms)"）
    if (!empty($user['ban_flag'])) {
        $parts = explode(':', (string)$user['ban_flag']);
        if (isset($parts[1]) && (int)$parts[1] > $now) {
            $remainSec = (int) ceil(((int)$parts[1] - $now) / 1000);
            return [false, '账号暂时封禁，剩余 ' . format_duration($remainSec)];
        }
    }

    // 永久封禁（密码被置空）
    if ($user['password'] === '') {
        return [false, '账号已被封禁'];
    }

    // 密码比对（sha256）
    if (!hash_equals((string)$user['password'], hash_password($password))) {
        return [false, '用户名或密码错误'];
    }

    app_session_start();
    $_SESSION['uid'] = (int)$user['user_id'];
    session_regenerate_id(true);

    return [true, null];
}

/* ============================ 注册 ============================ */

/** 参数校验，返回错误信息；合法返回 null */
function validate_register_input(string $name, string $password, string $email): ?string
{
    $nameLen = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($nameLen < 3 || $nameLen > 16) {
        return '用户名长度需为 3-16 个字符';
    }

    $pwdLen = strlen($password);
    if ($pwdLen < 8 || $pwdLen > 32) {
        return '密码长度需为 8-32 个字符';
    }

    // 与 Python core/user.py set_email 的判断保持一致
    $emailLen = function_exists('mb_strlen') ? mb_strlen($email) : strlen($email);
    if ($emailLen < 4 || $emailLen > 64 || !str_contains($email, '@') || !str_contains($email, '.')) {
        return '邮箱格式不正确';
    }

    return null;
}

/**
 * 创建账号，逻辑与 Python 服务器 core/user.py UserRegister.register() 保持一致：
 * 1. 用户名 / 邮箱唯一性检查
 * 2. 生成 user_id（max + 1）
 * 3. 生成 9 位随机好友码
 * 4. 写入 user 记录、初始角色 user_char、全解锁 user_char_full
 * 5. 若提供 $claimInvite 回调（例如邀请码占用），在同一个事务内执行
 * 6. 成功后直接完成登录
 *
 * @param callable(PDO $db, int $userId): array|null $claimInvite 返回 [bool, ?string]
 * @return array{0: bool, 1: ?string} [是否成功, 错误信息]
 */
function user_register(string $name, string $password, string $email, ?callable $claimInvite = null): array
{
    $invalid = validate_register_input($name, $password, $email);
    if ($invalid !== null) {
        return [false, $invalid];
    }

    $config = app_config();
    $defaultMemories = (int)($config['default_memories'] ?? 0);

    $db = app_db();

    try {
        // 立即加锁，避免并发注册时 user_id / 好友码 冲突
        $db->beginTransaction();

        $stmt = $db->prepare('SELECT 1 FROM user WHERE name = :name');
        $stmt->execute([':name' => $name]);
        if ($stmt->fetch()) {
            $db->rollBack();
            return [false, '用户名已被使用'];
        }

        $stmt = $db->prepare('SELECT 1 FROM user WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $db->rollBack();
            return [false, '该邮箱已被注册'];
        }

        // 新 user_id：max + 1，空表从 2000001 开始（与 _build_user_id 一致）
        $maxId = (int)$db->query('SELECT MAX(user_id) FROM user')->fetchColumn();
        $userId = $maxId > 0 ? $maxId + 1 : 2000001;

        // 生成 9 位数字好友码（与 _build_user_code 一致）
        $userCode = null;
        for ($i = 0; $i <= 1000; $i++) {
            $code = '';
            for ($j = 0; $j < 9; $j++) {
                $code .= (string)random_int(0, 9);
            }
            $stmt = $db->prepare('SELECT 1 FROM user WHERE user_code = :code');
            $stmt->execute([':code' => $code]);
            if (!$stmt->fetch()) {
                $userCode = $code;
                break;
            }
        }
        if ($userCode === null) {
            $db->rollBack();
            return [false, '暂时无法生成好友码，请稍后重试'];
        }

        $now = (int) round(microtime(true) * 1000);

        // 插入用户（列与 Python UserRegister.register() 完全相同）
        $stmt = $db->prepare(
            'INSERT INTO user
                (user_id, name, password, join_date, user_code, rating_ptt,
                 character_id, is_skill_sealed, is_char_uncapped, is_char_uncapped_override,
                 is_hide_rating, favorite_character, max_stamina_notification_enabled,
                 current_map, ticket, prog_boost, email, is_allow_marketing_email)
             VALUES
                (:user_id, :name, :password, :join_date, :user_code, 0, 0, 0, 0, 0, 0,
                 -1, 0, \'\', :memories, 0, :email, 0)'
        );
        $stmt->execute([
            ':user_id'   => $userId,
            ':name'      => $name,
            ':password'  => hash_password($password),
            ':join_date' => $now,
            ':user_code' => $userCode,
            ':memories'  => $defaultMemories,
            ':email'     => $email,
        ]);

        // 初始角色：角色 0（Hikari）与 1（Tairitsu）
        $stmt = $db->prepare(
            'INSERT INTO user_char
                (user_id, character_id, level, exp, is_uncapped, is_uncapped_override, skill_flag)
             VALUES (:user_id, :cid, 1, 0, 0, 0, 0)'
        );
        foreach ([0, 1] as $cid) {
            $stmt->execute([':user_id' => $userId, ':cid' => $cid]);
        }

        // user_char_full：全解锁所有角色（与 _insert_user_char 一致）
        $charRows = $db->query('SELECT character_id, max_level, is_uncapped FROM character')->fetchAll();
        $stmt = $db->prepare(
            'INSERT OR REPLACE INTO user_char_full
                (user_id, character_id, level, exp, is_uncapped, is_uncapped_override, skill_flag)
             VALUES (:user_id, :cid, :level, :exp, :uncap, 0, 0)'
        );
        foreach ($charRows as $ch) {
            $maxLevel = (int)$ch['max_level'];
            $stmt->execute([
                ':user_id' => $userId,
                ':cid'     => $ch['character_id'],
                ':level'   => $maxLevel,
                ':exp'     => $maxLevel === 30 ? 25000 : 10000,
                ':uncap'   => (int)$ch['is_uncapped'],
            ]);
        }

        // 若注册带了邀请码，在事务内占用（失败则整体回滚）
        if ($claimInvite !== null) {
            [$ok, $err] = $claimInvite($db, $userId);
            if (!$ok) {
                $db->rollBack();
                return [false, $err];
            }
        }

        $db->commit();

        // 注册成功，直接登录
        app_session_start();
        $_SESSION['uid'] = $userId;
        session_regenerate_id(true);

        return [true, null];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return [false, '注册失败，请稍后重试'];
    }
}

/** 读取配置（return-array 的 config.php，带静态缓存） */
function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

/** 取字符串第一个字符（UTF-8 安全，不依赖 mbstring） */
function first_char(string $s): string
{
    if ($s === '') {
        return '?';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, 1, 'UTF-8');
    }
    if (preg_match('/./us', $s, $m)) {
        return $m[0];
    }
    return $s[0];
}