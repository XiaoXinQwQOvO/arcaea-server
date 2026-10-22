# Arcaea 用户前端（PHP 8.4）

独立的前端项目，放在 `public/` 目录中，**不需要**注册到 Python 服务器的 `main.py`。

功能：
- 登录（与游戏内同一套账号体系）
- 注册：通过 **Resend.com** 发送邮箱验证码，验证后创建账号
- 登录后展示：**UID**、**好友码**、**PTT**

## 环境要求

- PHP >= 8.0（建议 8.4），需要扩展：`pdo_sqlite`、`curl`、`session`
- Python 服务器（arcaea-server）已初始化数据库

## 使用方法

1. 先运行一次 Python 服务器，使其创建并初始化数据库：

   ```bash
   python main.py
   ```

   初始化完成后会生成 `database/arcaea_database.db`（WAL 模式）。
   若数据库文件尚不存在，本前端会提示先完成这一步。

2. 编辑 `public/lib/config.php`：

   - `db_path`：默认指向 `../database/arcaea_database.db`，一般无需修改
   - `resend.api_key`：填入 Resend.com 的 API Key（`re_` 开头）
   - `resend.from`：发件地址，格式 `名称 <发件邮箱>`，域名需在 Resend 后台完成验证
   - `resend.enabled`：配置完成后改为 `true`
   - 其余参数（验证码有效期、重发冷却等）按需调整

3. 启动本前端（任选其一）：

   ```bash
   # PHP 内置服务器
   php -S 0.0.0.0:8000 -t public
   ```

   或使用 Apache / Nginx，将站点根目录指向 `public/`。

4. 浏览器访问 `http://127.0.0.1:8000/`。

## 与 Python 服务器的一致性说明

- 密码采用 SHA-256（hex），与服务器 `hashlib.sha256(password).hexdigest()` 完全一致；
  通过本前端注册的账号可直接在游戏内登录。
- 注册逻辑与 `core/user.py` 的 `UserRegister.register()` 一致：
  - `user_id`：`max(user_id) + 1`，空表从 `2000001` 开始
  - 好友码：9 位随机数字，全局唯一
  - 初始角色 `user_char`（角色 0、1）与全解锁 `user_char_full`
  - 初始记忆源点由 `default_memories` 控制（应与 `config.py` 的 `DEFAULT_MEMORIES` 一致）
- 登录校验逻辑与 `server/auth.py` 一致（封禁检查、密码比对）；
  但**不会**向 `login` 表写入 token，因此不影响游戏的设备数量限制。
- PTT 显示的是数据库中 `rating_ptt` 的真实数值（如 `12.34`）。

## 目录结构

```
public/
├── index.php          # 登录页
├── register.php       # 注册页（Resend 邮箱验证码）
├── profile.php        # 登录后主页：UID / 好友码 / PTT
├── logout.php         # 退出登录
├── api/
│   └── send_code.php  # AJAX：发送邮箱验证码
├── lib/
│   ├── config.php     # 配置文件（数据库路径、Resend 密钥）
│   ├── db.php         # SQLite（PDO）连接
│   ├── auth.php       # 会话、登录、注册逻辑
│   └── resend.php     # Resend.com 邮件客户端
└── assets/
    ├── style.css
    └── main.js
```

## 安全说明

- 验证码只以 SHA-256 散列形式保存在 PHP 会话中，有有效期与错误次数限制；
- 同一邮箱有重发冷却，同一 IP 每天有发送次数上限；
- 表单带有 CSRF 校验；
- 请勿将填写了真实 Resend 密钥的 `config.php` 提交到公开仓库。
