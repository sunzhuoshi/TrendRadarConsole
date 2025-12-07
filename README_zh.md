# TrendRadar控制台

[English](README.md) | [简体中文](README_zh.md)

[TrendRadar](https://github.com/sunzhuoshi/TrendRadar) - 热点话题监控和通知系统的网页配置管理系统。

## 功能特性

### 用户管理
- **用户认证**：登录和注册系统，采用安全的密码哈希加密
- **用户独立配置**：每个用户拥有自己独立的配置空间
- **用户独立 GitHub 设置**：每个用户可以配置自己的 GitHub 仓库（所有者/仓库名/PAT）
- **管理员角色**：首位注册用户自动成为管理员，拥有提升的权限
- **用户管理**：管理员可以授予或撤销其他用户的管理员权限

### 配置管理
- **默认配置**：注册时自动创建，包含：
  - 11个预配置的热门中文平台（今日头条、百度、微博、知乎、Bilibili等）
  - "技术与AI监控"默认关键词（2个关键词组，共12个关键词）
  - 默认报告设置（增量模式、排名阈值、权重配置）
- **平台管理**：配置要监控的平台（微博、知乎、头条等）
- **关键词配置**：设置关键词及过滤器、必选词和限制条件
- **通知 Webhooks**：配置多个通知渠道（企业微信、飞书、钉钉、Telegram、邮箱、ntfy、Bark、Slack）
- **报告设置**：自定义报告模式、权重和推送时间窗口

### 配置同步
- **GitHub 同步**：直接加载和保存 `CONFIG_YAML` 和 `FREQUENCY_WORDS` 到您的 GitHub 仓库变量
- **Docker 部署**：部署容器时自动生成 `config.yaml` 和 `frequency_words.txt`

### 附加功能
- **高级模式**：启用 Docker 工作机管理和其他高级功能
- **操作日志**：跟踪所有配置更改的详细审计日志
- **多语言支持**：在中英文界面之间切换
- **功能开关**：管理员可以为所有用户启用或禁用功能（GitHub 部署、Docker 部署、高级模式、用户注册）

### 移动端支持
- **响应式设计**：支持移动浏览器，带有汉堡菜单导航
- **触控友好**：优化了触屏控制
- **自适应布局**：布局自动适配小屏幕

## 系统要求

- PHP 7.2+ 且支持 cURL 扩展
- MySQL 5.6+
- Web 服务器（Apache/Nginx）

## 安装步骤

### 1. 部署文件

通过 FTP 或其他方式将所有文件上传到您的 Web 服务器。

### 2. 配置 Web 服务器

**Apache（已包含 .htaccess）：**
```apache
# 本项目已包含 .htaccess 文件，无需额外配置
```

**Nginx：**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/TrendRadarConsole;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(git|htaccess) {
        deny all;
    }
}
```

### 3. 创建数据库

```sql
CREATE DATABASE trendradar_console CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. 运行安装程序

1. 访问 `http://your-domain.com/install.php`
2. 输入您的数据库凭据
3. 点击"安装 TrendRadarConsole"

安装程序将自动：
- 创建所有必需的数据表（users, configurations, platforms, keywords, webhooks, settings）
- 生成配置文件

### 5. 注册和登录

1. 访问 `http://your-domain.com/register.php` 创建您的账户
2. 注册后，您将自动登录并跳转到仪表盘
3. **系统会自动创建默认配置**，包含：
   - 11个预配置的热门中文平台（今日头条、百度、微博、知乎、Bilibili等）
   - 2个关键词组，共12个"技术与AI监控"默认关键词（包括AI、ChatGPT等）
   - 默认报告设置（增量模式、排名阈值：5、权重配置）
   - 基本通知和爬虫设置
4. 每个用户都有自己独立的配置空间

### 6. 开始使用

登录后，您可以：
1. 在 **GitHub 部署** 中设置您的 GitHub 仓库
2. 在 **平台** 中管理平台
3. 在 **关键词** 中配置关键词
4. 在 **通知** 中设置 webhooks
5. 在 **设置** 中调整报告设置
6. 在 **Docker 部署** 中通过 Docker 部署，或同步到 GitHub
7. 在 **设置** 中启用 **高级模式** 以管理 Docker 工作机
8. 在 **操作日志** 中查看操作历史

## 与 TrendRadar 配合使用

### GitHub 部署（推荐）

TrendRadarConsole 可以直接将配置同步到您的 GitHub 仓库。每个用户存储自己的 GitHub 设置：

1. 在侧边栏中导航至 **GitHub 部署**
2. 输入您的 GitHub 仓库详情（所有者/仓库名）
3. 创建一个具有 **Variables: Read and write** 权限的 **Fine-grained Personal Access Token**
4. 点击 **保存设置** 来存储您的 GitHub 凭据
5. 使用 **从 GitHub 加载** 来导入现有配置
6. 使用 **保存到 GitHub** 来推送您的配置

这会自动设置 `CONFIG_YAML` 和 `FREQUENCY_WORDS` 仓库变量，供 TrendRadar 在 GitHub Actions 工作流执行期间使用。

### 手动 GitHub Actions 部署

或者，您可以手动复制配置：

1. 在 TrendRadarConsole 中使用 **GitHub 部署** 加载您的配置（这将显示生成的 `config.yaml` 和 `frequency_words.txt` 内容）
2. 前往您的 TrendRadar fork 的 **Settings → Secrets and variables → Actions**
3. 点击 **Variables** 标签
4. 创建两个仓库变量：
   - `CONFIG_YAML` - 粘贴生成的 `config.yaml` 的完整内容
   - `FREQUENCY_WORDS` - 粘贴生成的 `frequency_words.txt` 的内容

这些变量将在 GitHub Actions 工作流执行期间自动覆盖配置文件。

**注意：** 对于敏感的 webhook URL，请使用 **Secrets**（而非 Variables）：
- `FEISHU_WEBHOOK_URL`, `DINGTALK_WEBHOOK_URL`, `WEWORK_WEBHOOK_URL`
- `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`
- `EMAIL_FROM`, `EMAIL_PASSWORD`, `EMAIL_TO`
- 等

### Docker 部署

TrendRadarConsole 现在支持本地 Docker 部署，作为 GitHub Actions 的替代方案。Docker 命令通过 SSH 在远程 Docker 工作机服务器上执行。

**快速开始**：

1. **设置 Docker 工作机**：在您的 Docker 服务器上以 root 身份运行设置脚本：
   ```bash
   curl -O https://trendingnews.cn/scripts/setup-docker-worker.sh
   chmod +x setup-docker-worker.sh
   sudo ./setup-docker-worker.sh
   ```

2. **配置您的设置**：在 TrendRadarConsole 中，设置您的平台、关键词和 webhooks

3. **运行容器**：导航至 **Docker 部署** 并点击 **运行容器**
   - 您的 `config.yaml` 和 `frequency_words.txt` 会根据当前配置自动生成
   - 只需要设置基本的运行时参数：CRON_SCHEDULE、RUN_MODE、IMMEDIATE_RUN

**功能特性**：
- **自动生成配置**：运行/重启容器时，配置文件会根据当前设置自动创建
- **隔离的工作空间**：每个用户拥有自己的容器和工作空间（在高级模式下带有可选的 `-dev` 后缀）
- **简单控制**：一键运行、启动、停止、重启或删除容器
- **实时监控**：查看容器状态和日志

**技术细节**：
- 容器名称：`trendradar-{userId}`
- 配置路径：`/srv/trendradar/user-{userId}/config`（或高级模式下的 `user-{userId}-dev`）
- 输出路径：`/srv/trendradar/user-{userId}/output`（或高级模式下的 `user-{userId}-dev`）
- Docker 镜像：`wantcat/trendradar:latest`

**系统要求**：
- 已安装 Docker 的 Docker 工作机服务器
- Web 服务器上的 PHP SSH2 扩展（`php-ssh2`）

## 管理员功能

### 管理员角色

首位注册的用户会自动获得管理员权限。管理员可以访问**管理面板**，在其中可以：

1. **用户管理**
   - 查看所有注册用户及其登录历史
   - 授予其他用户管理员角色
   - 撤销用户的管理员角色（不能撤销自己的或最后一个管理员）

2. **功能开关**
   - 为所有用户全局启用或禁用功能：
     - **GitHub 部署**：开启/关闭 GitHub 集成访问（默认禁用）
     - **Docker 部署**：开启/关闭 Docker 部署访问
     - **高级模式**：开启/关闭高级功能如 Docker 工作机管理
     - **用户注册**：开启/关闭新用户注册功能

### 访问管理面板

1. 以管理员用户登录（首位注册用户或被授予管理员权限）
2. 点击侧边栏中的**管理面板**（🔑）
3. 从管理界面管理用户和功能

所有管理员操作都会记录在**操作日志**中以供审计。

### 用户注册控制

当管理员禁用**用户注册**功能时：
- 登录页面隐藏注册链接
- 直接访问注册页面（`register.php`）会重定向到登录页面并显示错误消息
- 现有用户仍可正常登录
- 只有管理员可以通过管理面板重新启用注册功能

这对于初始设置后控制用户访问或维护期间非常有用。

## 目录结构

```
TrendRadarConsole/
├── api/                    # API 端点
│   ├── advanced-mode.php   # 高级模式 API
│   ├── config-action.php
│   ├── docker-workers.php  # Docker 工作机管理 API
│   ├── docker.php          # Docker 管理 API
│   ├── github.php
│   ├── keywords.php
│   ├── language.php
│   ├── logs.php
│   ├── platforms.php
│   ├── settings.php
│   └── webhooks.php
├── assets/
│   ├── css/
│   │   └── style.css       # 响应式样式，支持移动端
│   └── js/
│       └── app.js
├── config/
│   ├── config.example.php  # 配置模板
│   └── config.php          # 您的配置（在安装过程中创建）
├── includes/
│   ├── auth.php            # 认证类
│   ├── configuration.php   # 配置模型
│   ├── database.php        # 数据库连接
│   ├── github.php          # GitHub API 集成
│   ├── helpers.php         # 辅助函数
│   ├── operation_log.php   # 操作日志类
│   └── ssh.php             # Docker 部署的 SSH 连接辅助类
├── scripts/
│   └── setup-docker-worker.sh  # Docker 工作机设置脚本（在 Docker 服务器上运行）
├── sql/
│   ├── migrations/         # 数据库迁移
│   └── schema.sql          # 数据库架构（users, configurations, docker_workers, operation_logs 等）
├── templates/
│   └── sidebar.php         # 侧边栏模板，带有移动端汉堡菜单
├── config-edit.php         # 配置编辑页面
├── docker-workers.php      # Docker 工作机管理（仅限高级模式）
├── docker.php              # Docker 部署管理页面
├── github-deployment.php   # GitHub 部署设置和管理
├── index.php               # 仪表盘
├── install.php             # 安装向导
├── keywords.php            # 关键词管理
├── login.php               # 登录页面
├── logs.php                # 操作日志查看器
├── logout.php              # 登出处理器
├── platforms.php           # 平台管理
├── register.php            # 注册页面
├── settings.php            # 设置页面（包含高级模式开关）
├── setup-github.php        # 新用户的 GitHub 设置向导
├── version.php             # 版本跟踪文件
└── webhooks.php            # Webhooks 管理
```

## CentOS 7 部署指南

### 安装所需包

```bash
# 安装 Apache、PHP 7.2 和 MySQL 5.6
yum install httpd
yum install https://dev.mysql.com/get/mysql57-community-release-el7-11.noarch.rpm
yum install mysql-server

# 对于 PHP 7.2，使用 Remi 仓库
yum install https://rpms.remirepo.net/enterprise/remi-release-7.rpm
yum-config-manager --enable remi-php72
yum install php php-mysqlnd php-json php-mbstring
```

### 启动服务

```bash
systemctl start httpd
systemctl enable httpd
systemctl start mysqld
systemctl enable mysqld
```

### 配置 MySQL

```bash
# 获取临时密码
grep 'temporary password' /var/log/mysqld.log

# 安全安装
mysql_secure_installation

# 创建数据库
mysql -u root -p
CREATE DATABASE trendradar_console CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'trendradar'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON trendradar_console.* TO 'trendradar'@'localhost';
FLUSH PRIVILEGES;
```

### 通过 FTP 部署文件

使用任何 FTP 客户端（FileZilla、WinSCP 等）将文件上传到 `/var/www/html/TrendRadarConsole/`。

### 设置权限

```bash
chown -R apache:apache /var/www/html/TrendRadarConsole
chmod -R 755 /var/www/html/TrendRadarConsole
chmod 777 /var/www/html/TrendRadarConsole/config
```

### 配置防火墙

```bash
firewall-cmd --permanent --add-service=http
firewall-cmd --reload
```

## 安全注意事项

1. **用户认证**：除安装和注册页面外，所有页面都需要登录
2. **CSRF 保护**：所有 API 端点都使用 CSRF 令牌进行保护
3. **密码安全**：密码使用 PHP 的 `password_hash()` 函数进行哈希加密
4. **数据隔离**：每个用户只能访问自己的配置
5. **保护 config.php**：确保 `config/config.php` 无法从 Web 直接访问
6. **使用 HTTPS**：在生产环境中，始终使用 HTTPS 来保护敏感数据
7. **GitHub PAT**：个人访问令牌按用户存储 - 使用最小权限
8. **数据库凭据**：为数据库访问使用强密码

## 开源协议

GPL-3.0 License

## 相关项目

- [TrendRadar](https://github.com/sunzhuoshi/TrendRadar) - 主要的热点话题监控系统
