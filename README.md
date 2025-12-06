# TrendRadarConsole

A web-based configuration management system for [TrendRadar](https://github.com/sunzhuoshi/TrendRadar) - the hot topic monitoring and notification system.

## Features

### User Management
- **User Authentication**: Login and registration system with secure password hashing
- **Per-User Configurations**: Each user has their own isolated configurations
- **Per-User GitHub Settings**: Each user can configure their own GitHub repository (owner/repo/PAT)

### Configuration Management
- **Platform Management**: Configure which platforms to monitor (Weibo, Zhihu, Toutiao, etc.)
- **Keyword Configuration**: Set up keywords with filters, required words, and limits
- **Notification Webhooks**: Configure multiple notification channels (WeChat Work, Feishu, DingTalk, Telegram, Email, ntfy, Bark, Slack)
- **Report Settings**: Customize report mode, weights, and push time windows

### Configuration Sync
- **GitHub Sync**: Load and save `CONFIG_YAML` and `FREQUENCY_WORDS` directly to your GitHub repository variables
- **Docker Deployment**: Automatically generates `config.yaml` and `frequency_words.txt` when deploying containers

### Mobile Support
- **Responsive Design**: Works on mobile browsers with hamburger menu navigation
- **Touch-Friendly**: Optimized controls for touch screens
- **Adaptive Layout**: Layouts adjust for small screens

## Requirements

- PHP 7.2+ with cURL extension
- MySQL 5.6+
- Web server (Apache/Nginx)

## Installation

### 1. Deploy Files

Upload all files to your web server via FTP or other methods.

### 2. Configure Web Server

**For Apache (.htaccess included):**
```apache
# The project includes .htaccess, no additional configuration needed
```

**For Nginx:**
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

### 3. Create Database

```sql
CREATE DATABASE trendradar_console CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Run Installation

1. Visit `http://your-domain.com/install.php`
2. Enter your database credentials
3. Click "Install TrendRadarConsole"

The installer will automatically:
- Create all necessary tables (users, configurations, platforms, keywords, webhooks, settings)
- Generate the config file

### 5. Register and Login

1. Visit `http://your-domain.com/register.php` to create your account
2. After registration, you'll be automatically logged in
3. Each user has their own isolated configurations

### 6. Start Using

After login, you can:
1. Set up your GitHub repository in **GitHub Sync**
2. Manage platforms
3. Configure keywords
4. Set up notification webhooks
5. Adjust report settings
6. Sync configurations to GitHub

## Using with TrendRadar

### GitHub Sync (Recommended)

TrendRadarConsole can directly sync configurations to your GitHub repository. Each user stores their own GitHub settings:

1. Navigate to **GitHub Sync** in the sidebar
2. Enter your GitHub repository details (owner/repo)
3. Create a **Fine-grained Personal Access Token** with **Variables: Read and write** permission
4. Click **Save Settings** to store your GitHub credentials
5. Use **Load from GitHub** to import existing configurations
6. Use **Save to GitHub** to push your configuration

This automatically sets `CONFIG_YAML` and `FREQUENCY_WORDS` repository variables that TrendRadar uses during GitHub Actions workflow execution.

### Manual GitHub Actions Deployment

Alternatively, you can manually copy configurations:

1. Use **GitHub Sync** in TrendRadarConsole to load your configuration (this will show you the generated `config.yaml` and `frequency_words.txt` content)
2. Go to your TrendRadar fork's **Settings → Secrets and variables → Actions**
3. Click on the **Variables** tab
4. Create two repository variables:
   - `CONFIG_YAML` - Paste the entire content of the generated `config.yaml`
   - `FREQUENCY_WORDS` - Paste the content of the generated `frequency_words.txt`

These variables will automatically override the config files during GitHub Actions workflow execution.

**Note:** For sensitive webhook URLs, use **Secrets** (not Variables) instead:
- `FEISHU_WEBHOOK_URL`, `DINGTALK_WEBHOOK_URL`, `WEWORK_WEBHOOK_URL`
- `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`
- `EMAIL_FROM`, `EMAIL_PASSWORD`, `EMAIL_TO`
- etc.

### Docker Deployment

TrendRadarConsole now supports local Docker deployment as an alternative to GitHub Actions. Docker commands are executed via SSH to a remote Docker worker server.

**Quick Start**:

1. **Set up Docker Worker**: Run the setup script on your Docker server (as root):
   ```bash
   curl -O https://trendingnews.cn/scripts/setup-docker-worker.sh
   chmod +x setup-docker-worker.sh
   sudo ./setup-docker-worker.sh
   ```

2. **Configure your settings**: In TrendRadarConsole, set up your platforms, keywords, and webhooks

3. **Run Container**: Navigate to **Docker Deployment** and click **Run Container**
   - Your `config.yaml` and `frequency_words.txt` are auto-generated from your current configuration
   - Only basic runtime settings are needed: CRON_SCHEDULE, RUN_MODE, IMMEDIATE_RUN

**Features**:
- **Auto-generated config**: Configuration files are created from your current settings when running/restarting the container
- **Isolated workspace**: Each user has their own container and workspace
- **Simple controls**: Run, start, stop, restart, or remove your container with one click
- **Real-time monitoring**: View container status and logs

**Technical Details**:
- Container name: `trend-radar-{userId}`
- Config path: `/srv/trendradar/{userId}/config`
- Output path: `/srv/trendradar/{userId}/output`
- Docker image: `wantcat/trendradar:latest`

**Requirements**:
- Docker worker server with Docker installed
- PHP SSH2 extension (`php-ssh2`) on the web server

## Directory Structure

```
TrendRadarConsole/
├── api/                    # API endpoints
│   ├── advanced-mode.php   # Advanced mode API
│   ├── config-action.php
│   ├── docker-workers.php  # Docker workers management API
│   ├── docker.php          # Docker management API
│   ├── github.php
│   ├── keywords.php
│   ├── language.php
│   ├── logs.php
│   ├── platforms.php
│   ├── settings.php
│   └── webhooks.php
├── assets/
│   ├── css/
│   │   └── style.css       # Responsive styles with mobile support
│   └── js/
│       └── app.js
├── config/
│   ├── config.example.php  # Configuration template
│   └── config.php          # Your configuration (created during install)
├── includes/
│   ├── auth.php            # Authentication class
│   ├── configuration.php   # Configuration model
│   ├── database.php        # Database connection
│   ├── github.php          # GitHub API integration
│   ├── helpers.php         # Helper functions
│   └── ssh.php             # SSH connection helper for Docker deployment
├── scripts/
│   └── setup-docker-worker.sh  # Docker worker setup script (run on Docker server)
├── sql/
│   ├── migrations/         # Database migrations
│   └── schema.sql          # Database schema (users, configurations, etc.)
├── templates/
│   └── sidebar.php         # Sidebar template with mobile hamburger menu
├── config-edit.php         # Configuration edit page
├── docker-workers.php      # Docker workers management (advanced mode)
├── docker.php              # Docker deployment management page
├── github-deployment.php   # GitHub deployment setup and management
├── index.php               # Dashboard
├── install.php             # Installation wizard
├── keywords.php            # Keywords management
├── login.php               # Login page
├── logs.php                # Operation logs viewer
├── logout.php              # Logout handler
├── platforms.php           # Platforms management
├── register.php            # Registration page
├── settings.php            # Settings page
└── webhooks.php            # Webhooks management
```

## CentOS 7 Deployment Guide

### Install Required Packages

```bash
# Install Apache, PHP 7.2, and MySQL 5.6
yum install httpd
yum install https://dev.mysql.com/get/mysql57-community-release-el7-11.noarch.rpm
yum install mysql-server

# For PHP 7.2, use Remi repository
yum install https://rpms.remirepo.net/enterprise/remi-release-7.rpm
yum-config-manager --enable remi-php72
yum install php php-mysqlnd php-json php-mbstring
```

### Start Services

```bash
systemctl start httpd
systemctl enable httpd
systemctl start mysqld
systemctl enable mysqld
```

### Configure MySQL

```bash
# Get temporary password
grep 'temporary password' /var/log/mysqld.log

# Secure installation
mysql_secure_installation

# Create database
mysql -u root -p
CREATE DATABASE trendradar_console CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'trendradar'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON trendradar_console.* TO 'trendradar'@'localhost';
FLUSH PRIVILEGES;
```

### Deploy Files via FTP

Use any FTP client (FileZilla, WinSCP, etc.) to upload files to `/var/www/html/TrendRadarConsole/`.

### Set Permissions

```bash
chown -R apache:apache /var/www/html/TrendRadarConsole
chmod -R 755 /var/www/html/TrendRadarConsole
chmod 777 /var/www/html/TrendRadarConsole/config
```

### Configure Firewall

```bash
firewall-cmd --permanent --add-service=http
firewall-cmd --reload
```

## Security Notes

1. **User Authentication**: All pages require login except installation and registration
2. **CSRF Protection**: All API endpoints are protected with CSRF tokens
3. **Password Security**: Passwords are hashed using PHP's `password_hash()` function
4. **Data Isolation**: Each user can only access their own configurations
5. **Protect config.php**: Ensure `config/config.php` is not accessible directly from the web
6. **Use HTTPS**: For production, always use HTTPS to protect sensitive data
7. **GitHub PAT**: Personal Access Tokens are stored per-user - use minimal permissions
8. **Database credentials**: Use strong passwords for database access

## License

GPL-3.0 License

## Related Projects

- [TrendRadar](https://github.com/sunzhuoshi/TrendRadar) - The main hot topic monitoring system
