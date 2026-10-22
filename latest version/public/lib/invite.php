<?php
declare(strict_types=1);

/**
 * 邀请码 / 管理后台 逻辑
 *
 * - 邀请码表 invite_code（新增表，Python 服务器不感知、不影响其运行）
 * - 管理员判定：user_role 表中存在该 user_id 记录
 * - 删除账号：清理主库 + 日志库中该 user_id 在全部表里的数据
 */

require_once __DIR__ . '/auth.php';

/* ============================ 邀请码表 ============================ */

function invite_table_init(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    app_db()->exec(
        'CREATE TABLE IF NOT EXISTS invite_code (
            code       TEXT PRIMARY KEY,
            created_at INTEGER,
            created_by INTEGER,
            used_by    INTEGER,
            used_at    INTEGER,
            note       TEXT
        )'
    );
    app_db()->exec(
        'CREATE INDEX IF NOT EXISTS invite_code_used_by ON invite_code (used_by)'
    );
    $done = true;
}

/** 生成单个邀请码：12 位大写字母数字（去掉易混淆字符 0/O/1/I） */
function invite_generate_one(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($chars) - 1;
    $code = '';
    for ($i = 0; $i < 12; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}

/**
 * 批量生成邀请码。
 *
 * @return array{0: bool, 1: ?string} [是否成功, 错误信息]
 */
function invite_batch_generate(int $count, int $createdBy, string $note = ''): array
{
    if ($count < 1 || $count > 500) {
        return [false, '生成数量需在 1-500 之间'];
    }

    $note = trim($note);
    $noteLen = function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : strlen($note);
    if ($noteLen > 50) {
        $note = function_exists('mb_substr')
            ? mb_substr($note, 0, 50, 'UTF-8')
            : substr($note, 0, 50);
    }

    invite_table_init();

    $db = app_db();
    $now = (int) round(microtime(true) * 1000);

    try {
        $db->beginTransaction();
        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO invite_code (code, created_at, created_by, used_by, used_at, note)
             VALUES (:code, :created_at, :created_by, NULL, NULL, :note)'
        );

        $created = 0;
        $attempts = 0;
        // 生成足够的唯一码（保守上限）
        while ($created < $count && $attempts < $count * 20 + 50) {
            $attempts++;
            $code = invite_generate_one();
            $stmt->execute([
                ':code'       => $code,
                ':created_at' => $now,
                ':created_by' => $createdBy,
                ':note'       => $note,
            ]);
            if ($stmt->rowCount() === 1) {
                $created++;
            }
        }

        $db->commit();
        if ($created === 0) {
            return [false, '生成失败，请重试'];
        }
        return [true, "成功生成 {$created} 个邀请码"];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return [false, '生成失败，请稍后重试'];
    }
}

/**
 * 删除邀请码（可多个 code，逗号分隔）。
 *
 * @return array{0: bool, 1: ?string}
 */
function invite_batch_delete(string $codesRaw): array
{
    $codes = array_values(array_filter(array_map('trim', explode(',', $codesRaw)), function ($c) {
        return $c !== '';
    }));
    if (!$codes) {
        return [false, '请填写要删除的邀请码'];
    }
    if (count($codes) > 500) {
        return [false, '一次最多删除 500 个邀请码'];
    }

    invite_table_init();

    $db = app_db();
    try {
        $db->beginTransaction();
        $stmt = $db->prepare('DELETE FROM invite_code WHERE code = :code');
        $deleted = 0;
        foreach ($codes as $c) {
            $stmt->execute([':code' => $c]);
            $deleted += $stmt->rowCount();
        }
        $db->commit();
        return [true, "已删除 {$deleted} 个邀请码"];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return [false, '删除失败，请稍后重试'];
    }
}

/**
 * 邀请码列表（关联使用者用户名），支持状态筛选。
 * status: all / unused / used
 */
function invite_list(string $status = 'all'): array
{
    invite_table_init();

    $sql = 'SELECT c.code, c.created_at, c.created_by, c.used_by, c.used_at, c.note,
                   u.name AS used_by_name
            FROM invite_code c
            LEFT JOIN user u ON u.user_id = c.used_by';
    if ($status === 'unused') {
        $sql .= ' WHERE c.used_by IS NULL';
    } elseif ($status === 'used') {
        $sql .= ' WHERE c.used_by IS NOT NULL';
    }
    $sql .= ' ORDER BY c.created_at DESC, c.code';

    $rows = app_db()->query($sql)->fetchAll();
    return $rows;
}

/**
 * 校验并占用邀请码（在注册事务内调用，使用同一个 PDO）。
 * 返回 [true] 或 [false, 错误信息]。
 */
function invite_claim(PDO $db, string $code, int $newUserId): array
{
    invite_table_init();

    $stmt = $db->prepare(
        'SELECT code, used_by FROM invite_code WHERE code = :code'
    );
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch();

    if ($row === false) {
        return [false, '邀请码不存在'];
    }
    if ($row['used_by'] !== null) {
        return [false, '邀请码已被使用'];
    }

    $stmt = $db->prepare(
        'UPDATE invite_code SET used_by = :user_id, used_at = :used_at WHERE code = :code AND used_by IS NULL'
    );
    $stmt->execute([
        ':user_id' => $newUserId,
        ':used_at' => (int) round(microtime(true) * 1000),
        ':code'    => $code,
    ]);
    if ($stmt->rowCount() !== 1) {
        return [false, '邀请码已被占用，请刷新后重试'];
    }

    return [true, null];
}

/* ============================ 管理员判定 ============================ */

function is_admin_user(int $userId): bool
{
    try {
        $stmt = app_db()->prepare('SELECT 1 FROM user_role WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/** 未登录或不是管理员则跳转到登录页 */
function require_admin(): void
{
    $user = current_user();
    if ($user === null || !is_admin_user((int)$user['user_id'])) {
        redirect(app_base() . '/index.php');
    }
}

/** 当前用户是否是管理员（供模板使用） */
function is_current_admin(): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }
    return is_admin_user((int)$user['user_id']);
}

/* ============================ 删除账号 ============================ */

/**
 * 删除一个账号。
 * 主库 user 表及其所有关联表中的数据全部删除；
 * 日志库（arcaea_log.db）中的 user_score / user_rating 一并删除；
 * 该账号占用过的邀请码会被释放（重新变为未使用）。
 *
 * @param string $identifier user_id 或用户名或好友码
 * @return array{0: bool, 1: ?string}
 */
function admin_delete_user(string $identifier): array
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return [false, '请输入要删除的 user_id / 用户名 / 好友码'];
    }

    $db = app_db();

    // 找到目标 user_id
    if (ctype_digit($identifier)) {
        $stmt = $db->prepare('SELECT user_id FROM user WHERE user_id = :id');
        $stmt->execute([':id' => (int)$identifier]);
    } else {
        $stmt = $db->prepare(
            'SELECT user_id FROM user WHERE name = :id OR user_code = :id'
        );
        $stmt->execute([':id' => $identifier]);
    }
    $row = $stmt->fetch();
    if ($row === false) {
        return [false, '该用户不存在'];
    }
    $userId = (int)$row['user_id'];

    // 不能删除自己
    $me = current_user();
    if ($me !== null && (int)$me['user_id'] === $userId) {
        return [false, '不能删除当前登录的管理员账号'];
    }

    try {
        $db->beginTransaction();

        invite_table_init();

        // 主库中含 user_id 的各表
        $mainTables = [
            'user', 'login', 'best_score', 'user_char', 'user_char_full',
            'recent30', 'user_world', 'songplay_token', 'user_item',
            'user_save', 'user_present', 'user_redeem', 'user_course',
            'user_mission', 'user_kvdata', 'user_role', 'api_login',
        ];
        foreach ($mainTables as $table) {
            $db->exec('DELETE FROM `' . $table . '` WHERE user_id = ' . $userId);
        }

        // friend 是双向关系
        $db->exec('DELETE FROM friend WHERE user_id_me = ' . $userId . ' OR user_id_other = ' . $userId);

        // 释放该账号占用过的邀请码
        $db->exec(
            'UPDATE invite_code SET used_by = NULL, used_at = NULL WHERE used_by = ' . $userId
        );

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return [false, '删除失败：' . $e->getMessage()];
    }

    // 日志库（不存在则跳过）
    $logDbPath = __DIR__ . '/../../database/arcaea_log.db';
    if (is_file($logDbPath)) {
        try {
            $logPdo = new PDO('sqlite:' . $logDbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10,
            ]);
            $logPdo->exec('PRAGMA busy_timeout = 10000');
            $logPdo->exec('DELETE FROM user_score WHERE user_id = ' . $userId);
            $logPdo->exec('DELETE FROM user_rating WHERE user_id = ' . $userId);
        } catch (Throwable $e) {
            // 日志库删除失败不阻塞主流程（避免部分配置下日志库损坏导致无法删除账号）
        }
    }

    return [true, '账号（user_id=' . $userId . '）及其全部数据已删除'];
}