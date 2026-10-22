<?php
/**
 * Arcaea 用户前端 配置文件
 *
 * 本前端是独立项目，不注册到 Python 服务器的 main.py 中，
 * 直接读写同一份 SQLite 数据库（database/arcaea_database.db）。
 *
 * 修改后无需重启服务，每次请求都会重新读取本文件。
 * 部署时请注意：请勿把填写了真实密钥的 config.php 提交到公开仓库。
 */

return [

    /*
     * SQLite 数据库文件路径。
     * 默认指向同项目下 database/arcaea_database.db。
     * 注意：数据库由 Python 服务器（python main.py）在首次启动时初始化，
     * 请先运行一次 Python 服务器，再启动本前端。
     */
    'db_path' => __DIR__ . '/../../database/arcaea_database.db',

    /*
     * SendCloud 配置（https://www.sendcloud.net）
     * 注册页面的邮箱验证码通过 SendCloud 发送。
     * 在 SendCloud 后台创建发信域名并完成验证后，把发件地址填入 from。
     */
    'sendcloud' => [
        'enabled'   => false,                  // 配置完成后改为 true
        'api_user'  => '',                     // API_USER（SendCloud 登录账号）
        'api_key'   => '',                     // API_KEY
        'from'      => 'noreply@example.com',  // 发件邮箱（需在 SendCloud 后台验证）
        'from_name' => 'Arcaea 服务器',         // 发件人名称
        'api_url'   => 'https://api.sendcloud.net/apiv2/mail/send',
    ],

    /*
     * 注册时初始的 Memories（记忆源点）。
     * 请与 Python 服务器 config.py 中的 DEFAULT_MEMORIES 保持一致。
     */
    'default_memories' => 0,

    /* 邮箱验证码有效期（秒） */
    'code_ttl' => 600,

    /* 验证码重发冷却时间（秒） */
    'code_resend_cooldown' => 60,

    /* 同一 IP 每天最多发送验证码次数（防止滥用） */
    'code_daily_limit' => 20,
];