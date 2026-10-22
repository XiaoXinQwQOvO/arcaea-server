<?php
declare(strict_types=1);

/**
 * SQLite 数据库连接（PDO）
 * 直接读写 arcaea-server 的 database/arcaea_database.db。
 *
 * 使用与 Python 服务器一致的参数：
 * - WAL 模式（已在 tables.sql 中设置，SQLite 会记住该模式）
 * - busy_timeout，避免并发写锁冲突
 */

require_once __DIR__ . '/config.php';

function app_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $path = $config['db_path'];

    if (!is_file($path)) {
        throw new RuntimeException(
            '数据库文件不存在：' . $path
            . '。请先启动 Python 服务器（python main.py）完成数据库初始化。'
        );
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 10, // sqlite busy timeout（秒）
    ]);
    // 与 Python 服务器 sqlite3.connect(timeout=10) 相同的等待时长
    $pdo->exec('PRAGMA busy_timeout = 10000');

    return $pdo;
}