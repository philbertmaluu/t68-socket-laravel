# AlmaLinux Deployment Guide

## Recommended Environment Versions

Based on your current application stack:

### Backend (Laravel)
- **PHP**: 8.2 or 8.3 (Laravel 12 requires PHP 8.2+)
- **Composer**: 2.7+ (latest stable)
- **MySQL/MariaDB**: 10.11+ or MySQL 8.0+
- **Redis**: 7.0+ (optional, for queues/cache)
- **Laravel Reverb**: 1.0+ (WebSocket server)

### Frontend (Next.js)
- **Node.js**: 20.x LTS (recommended) or 22.x
- **npm**: 10.x+ (comes with Node.js 20+)
- **Next.js**: 16.1.1 (your current version)

### Web Server
- **Nginx**: 1.24+ (recommended) or Apache 2.4+

### Process Managers
- **Supervisor**: 4.2+ (for Laravel queue workers)
- **PM2**: Latest (optional, for Node.js processes)

---

## Installation Steps for AlmaLinux

### 1. Update System

```bash
sudo dnf update -y
sudo dnf install -y epel-release
```

### 2. Install PHP 8.3 (Recommended)

```bash
# Add Remi repository
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm

# Enable PHP 8.3 module
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.3 -y

# Install PHP and required extensions
sudo dnf install -y php php-cli php-fpm php-mysqlnd php-zip php-devel \
    php-gd php-mbstring php-curl php-xml php-pear php-bcmath php-json \
    php-redis php-opcache php-pdo php-pdo_mysql php-fileinfo php-intl

# Verify installation
php -v
# Should show PHP 8.3.x
```

### 3. Install Composer

```bash
cd /tmp
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify
composer --version
```

### 4. Install Node.js 20.x LTS

```bash
# Install Node.js 20.x using NodeSource repository
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs

# Verify installation
node --version
# Should show v20.x.x
npm --version
# Should show 10.x.x
```

### 5. Install MySQL/MariaDB

**Option A: MariaDB (Recommended for AlmaLinux)**

```bash
sudo dnf install -y mariadb-server mariadb
sudo systemctl enable mariadb
sudo systemctl start mariadb
sudo mysql_secure_installation
```

**Option B: MySQL 8.0**

```bash
sudo dnf install -y mysql-server mysql
sudo systemctl enable mysqld
sudo systemctl start mysqld
sudo mysql_secure_installation
```

### 6. Install Redis (Optional but Recommended)

```bash
sudo dnf install -y redis
sudo systemctl enable redis
sudo systemctl start redis

# Verify
redis-cli ping
# Should return: PONG
```

### 7. Install Nginx

```bash
sudo dnf install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx

# Verify
sudo nginx -t
```

### 8. Install Supervisor

```bash
sudo dnf install -y supervisor
sudo systemctl enable supervisord
sudo systemctl start supervisord
```

### 9. Install PM2 (Optional, for Node.js process management)

```bash
sudo npm install -g pm2
```

---

## Version Summary Table

| Component | Recommended Version | Minimum Version | Notes |
|-----------|---------------------|-----------------|-------|
| **PHP** | 8.3.x | 8.2.0 | Laravel 12 requires 8.2+ |
| **Composer** | 2.7.x | 2.6.0 | Latest stable |
| **Node.js** | 20.x LTS | 18.17.0 | Next.js 16 requires 18.17+ |
| **npm** | 10.x | 9.x | Comes with Node.js 20 |
| **MySQL** | 8.0+ | 5.7+ | Or MariaDB 10.11+ |
| **Redis** | 7.0+ | 6.0+ | Optional but recommended |
| **Nginx** | 1.24+ | 1.18+ | Or Apache 2.4+ |
| **Supervisor** | 4.2+ | 4.0+ | For queue workers |
| **PM2** | Latest | - | Optional |

---

## Quick Verification Commands

After installation, verify all components:

```bash
# PHP
php -v
php -m  # Check installed extensions

# Composer
composer --version

# Node.js
node --version
npm --version

# MySQL
mysql --version
sudo systemctl status mariadb  # or mysqld

# Redis
redis-cli --version
sudo systemctl status redis

# Nginx
nginx -v
sudo systemctl status nginx

# Supervisor
supervisord --version
sudo systemctl status supervisord
```

---

## PHP Extensions Required for Laravel

Ensure these PHP extensions are installed:

- ✅ `php-mysqlnd` (MySQL/MariaDB driver)
- ✅ `php-pdo` (PDO support)
- ✅ `php-mbstring` (String functions)
- ✅ `php-xml` (XML parsing)
- ✅ `php-curl` (HTTP requests)
- ✅ `php-zip` (Archive handling)
- ✅ `php-gd` (Image processing)
- ✅ `php-bcmath` (Arbitrary precision math)
- ✅ `php-json` (JSON support)
- ✅ `php-fileinfo` (File type detection)
- ✅ `php-opcache` (Performance)
- ✅ `php-redis` (Redis support, optional)
- ✅ `php-intl` (Internationalization)

---

## Next Steps After Installation

1. **Configure PHP-FPM** for Nginx
2. **Set up Nginx virtual host** for Laravel API
3. **Set up Nginx virtual host** for Next.js frontend
4. **Configure Supervisor** for Laravel queue workers
5. **Set up Laravel Reverb** as a service
6. **Configure firewall** (firewalld)
7. **Set up SSL certificates** (Let's Encrypt)

---

## Notes

- **AlmaLinux 9** is recommended (current LTS version)
- All versions listed are tested and compatible with your application stack
- PHP 8.3 is recommended over 8.2 for better performance
- Node.js 20.x LTS is the most stable for Next.js 16
- Consider using **PM2** for managing Next.js production server
- Use **Supervisor** for Laravel queue workers and Reverb server

---

## Troubleshooting

### PHP Version Issues
```bash
# Check active PHP version
php -v

# If wrong version, switch using:
sudo dnf module list php
sudo dnf module enable php:remi-8.3
sudo dnf install -y php php-cli php-fpm
```

### Node.js Version Issues
```bash
# Check Node.js version
node --version

# If wrong version, reinstall from NodeSource
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs
```

### Permission Issues
```bash
# Fix ownership for web directory
sudo chown -R nginx:nginx /var/www/html
# or
sudo chown -R apache:apache /var/www/html
```
