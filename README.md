# TrendRadarConsole

A web-based configuration management system for [TrendRadar](https://github.com/sunzhuoshi/TrendRadar) - the hot topic monitoring and notification system.

## Features

- **Platform Management**: Configure which platforms to monitor (Weibo, Zhihu, Toutiao, etc.)
- **Keyword Configuration**: Set up keywords with filters, required words, and limits
- **Notification Webhooks**: Configure multiple notification channels (WeChat Work, Feishu, DingTalk, Telegram, Email, ntfy, Bark, Slack)
- **Report Settings**: Customize report mode, weights, and push time windows
- **Export**: Export configurations as `config.yaml` and `frequency_words.txt` for use with TrendRadar

## Requirements

- PHP 7.2+
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
- Create all necessary tables
- Set up default configuration
- Generate the config file

### 5. Start Using

After installation, you'll be redirected to the dashboard where you can:
1. Manage platforms
2. Configure keywords
3. Set up notification webhooks
4. Adjust report settings
5. Export configurations for TrendRadar

## Directory Structure

```
TrendRadarConsole/
├── api/                    # API endpoints
│   ├── config-action.php
│   ├── export.php
│   ├── keywords.php
│   ├── platforms.php
│   ├── settings.php
│   └── webhooks.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── config/
│   ├── config.example.php  # Configuration template
│   └── config.php          # Your configuration (created during install)
├── includes/
│   ├── Configuration.php   # Configuration model
│   ├── Database.php        # Database connection
│   └── helpers.php         # Helper functions
├── sql/
│   └── schema.sql          # Database schema
├── templates/
│   └── sidebar.php         # Sidebar template
├── config-edit.php         # Configuration edit page
├── export.php              # Export page
├── index.php               # Dashboard
├── install.php             # Installation wizard
├── keywords.php            # Keywords management
├── platforms.php           # Platforms management
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

1. **Protect config.php**: Ensure `config/config.php` is not accessible directly from the web
2. **Use HTTPS**: For production, always use HTTPS to protect sensitive data
3. **Webhook URLs**: Never expose webhook URLs publicly
4. **Database credentials**: Use strong passwords for database access

## License

GPL-3.0 License

## Related Projects

- [TrendRadar](https://github.com/sunzhuoshi/TrendRadar) - The main hot topic monitoring system
