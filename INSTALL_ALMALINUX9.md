# Black Flower CAD Installation Guide for AlmaLinux 9

This guide provides updated installation instructions for running Black Flower CAD on AlmaLinux 9 with modern PHP (7.4+ / 8.x) and Apache.

## Overview

Black Flower CAD has been modernized to support:
- AlmaLinux 9
- PHP 7.4, 8.0, 8.1, 8.2, 8.3
- MySQL 8.0+ / MariaDB 10.3+
- Apache 2.4+

## Server Requirements

### Required Packages
- **Operating System**: AlmaLinux 9 (or compatible RHEL-based distribution)
- **PHP**: Version 7.4 or higher (PHP 8.x recommended)
- **Database**: MySQL 8.0+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ with mod_php or php-fpm
- **PHP Extensions**: 
  - php-mysqli (required for database connectivity)
  - php-session
  - php-json

## Installation Steps

### 1. Install Required System Packages

```bash
# Enable EPEL repository (if not already enabled)
sudo dnf install -y epel-release

# Install Apache web server
sudo dnf install -y httpd

# Install PHP and required extensions
sudo dnf install -y php php-mysqli php-json php-mbstring

# Install MariaDB (recommended) or MySQL
sudo dnf install -y mariadb-server mariadb

# Start and enable services
sudo systemctl start httpd
sudo systemctl enable httpd
sudo systemctl start mariadb
sudo systemctl enable mariadb
```

### 2. Configure MariaDB/MySQL

```bash
# Secure the MySQL installation
sudo mysql_secure_installation

# Log into MySQL as root
sudo mysql -u root -p
```

Create the CAD database and user:

```sql
CREATE DATABASE cad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cad'@'localhost' IDENTIFIED BY 'your_secure_password_here';
GRANT ALL PRIVILEGES ON cad.* TO 'cad'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Deploy Black Flower CAD

```bash
# Navigate to web root
cd /var/www/html

# Extract the CAD distribution
# Assuming you have the blackflower directory
sudo cp -r /path/to/blackflower /var/www/html/cad

# Set proper ownership
sudo chown -R apache:apache /var/www/html/cad

# Set proper SELinux contexts (AlmaLinux 9)
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/cad(/.*)?"
sudo restorecon -Rv /var/www/html/cad
```

### 4. Configure Black Flower CAD

```bash
cd /var/www/html/cad

# Run the initialization script
sudo -u apache bash ./initialize.sh
```

The script will prompt you for:
- MySQL root password
- Desired CAD database name (default: cad)
- Desired CAD database user (default: cad)
- Password for the CAD database user
- CAD administrator username
- CAD administrator password

Alternatively, manually configure:

```bash
# Copy the example configuration
cp cad.conf.example cad.conf

# Edit the configuration file
vi cad.conf
```

Update the database connection settings:

```php
$DB_HOST = "localhost";
$DB_USER = "cad";
$DB_PASS = "your_secure_password_here";
$DB_NAME = "cad";
```

Set secure permissions:

```bash
sudo chown apache:apache cad.conf
sudo chmod 600 cad.conf
```

### 5. Initialize the Database

If not using initialize.sh, manually load the database schema:

```bash
mysql -u cad -p cad < data/schema.sql
```

### 6. Configure Apache

Create an Apache configuration file:

```bash
sudo vi /etc/httpd/conf.d/cad.conf
```

Add the following configuration:

```apache
<Directory "/var/www/html/cad">
    Options -Indexes +FollowSymLinks
    AllowOverride Limit
    Require all granted
    
    <IfModule mod_php.c>
        php_value upload_max_filesize 10M
        php_value post_max_size 10M
        php_value max_execution_time 300
    </IfModule>
</Directory>

<Directory "/var/www/html/cad/data">
    Require all denied
</Directory>

<Directory "/var/www/html/cad/font">
    Require all denied
</Directory>
```

### 7. Configure Firewall

```bash
# Allow HTTP traffic
sudo firewall-cmd --permanent --add-service=http

# If using HTTPS (recommended for production):
sudo firewall-cmd --permanent --add-service=https

# Reload firewall
sudo firewall-cmd --reload
```

### 8. Restart Apache

```bash
sudo systemctl restart httpd
```

### 9. Verify Installation

Test the .htaccess security by attempting to access:
```
http://your-server/cad/cad.conf
```

This should return a 403 Forbidden error. If the file contents are displayed, review your Apache configuration.

Access the CAD system:
```
http://your-server/cad/
```

Log in with the administrator credentials created during setup.

## PHP Version Compatibility

### PHP 7.4 - 8.3 Support

Black Flower CAD has been updated to use:
- **mysqli** extension (replaces deprecated mysql extension)
- Modern PHP session handling
- Removal of deprecated functions (get_magic_quotes_gpc, split, etc.)

### Configuration Recommendations

For PHP 8.x, ensure error reporting is appropriately configured in `/etc/php.ini`:

```ini
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
log_errors = On
error_log = /var/log/php-fpm/error.log
```

## Security Considerations

### File Permissions

```bash
# Set directory permissions
sudo find /var/www/html/cad -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/html/cad -type f -exec chmod 644 {} \;

# Secure configuration file
sudo chmod 600 /var/www/html/cad/cad.conf
```

### SELinux Configuration

AlmaLinux 9 runs SELinux in enforcing mode by default:

```bash
# Allow Apache to connect to the database
sudo setsebool -P httpd_can_network_connect_db 1

# If you encounter issues, check SELinux denials
sudo ausearch -m avc -ts recent
```

### Database Security

- Use strong passwords for database users
- Restrict MySQL to localhost-only connections unless needed
- Regularly update and patch the system

## Troubleshooting

### PHP Module Not Loading

If mysqli is not available:

```bash
sudo dnf install php-mysqli
sudo systemctl restart httpd
php -m | grep mysqli
```

### Permission Denied Errors

Check SELinux context:

```bash
ls -Z /var/www/html/cad/cad.conf
sudo restorecon -v /var/www/html/cad/cad.conf
```

### Database Connection Errors

Verify MySQL is running and credentials are correct:

```bash
sudo systemctl status mariadb
mysql -u cad -p cad
```

### Apache Logs

Check logs for errors:

```bash
sudo tail -f /var/log/httpd/error_log
sudo tail -f /var/log/httpd/access_log
```

## Time Synchronization

Install and configure chrony (NTP client) for time synchronization:

```bash
sudo dnf install -y chrony
sudo systemctl start chronyd
sudo systemctl enable chronyd
```

## Migration from Old PHP5 System

If migrating from an older installation:

1. **Backup your existing database**:
   ```bash
   mysqldump -u cad -p cad > cad_backup_$(date +%Y%m%d).sql
   ```

2. **Backup configuration**:
   ```bash
   cp cad.conf cad.conf.old
   ```

3. Follow the installation steps above

4. Import your old database:
   ```bash
   mysql -u cad -p cad < cad_backup_YYYYMMDD.sql
   ```

5. Compare and merge cad.conf settings

## Production Deployment Recommendations

1. **Enable HTTPS**: Use Let's Encrypt or a commercial SSL certificate
2. **Set up automated backups**: Database and configuration files
3. **Configure log rotation**: For Apache and PHP logs
4. **Monitor system resources**: CPU, memory, disk space
5. **Keep system updated**: Regularly apply security updates

## Support

For issues specific to the modernization:
- Check PHP error logs: `/var/log/httpd/error_log`
- Verify mysqli extension is loaded: `php -m | grep mysqli`
- Ensure database credentials in cad.conf are correct

For general Black Flower CAD issues:
- Email: cad-info@forlorn.net
- Website: http://www.forlorn.net/cad/

## License

Black Flower CAD is open source software. See LICENSE file for details.

