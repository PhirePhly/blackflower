# Black Flower CAD - Quick Reference Guide

## Common Administrative Tasks

### Reset Administrator Password

If you're locked out or forgot the password:

```bash
cd /var/www/html/cad
php reset-admin-password.php
```

Follow the prompts to enter a new password. See `ADMIN_RECOVERY.md` for details.

### Reset Specific User Password

```bash
php reset-admin-password.php username
```

### Check PHP Version

```bash
php -v
```

### Check PHP Extensions

```bash
php -m | grep mysqli
```

### View Apache Error Log

```bash
sudo tail -50 /var/log/httpd/error_log
```

### Restart Apache

```bash
sudo systemctl restart httpd
```

### Restart MariaDB/MySQL

```bash
sudo systemctl restart mariadb
```

### Check Service Status

```bash
sudo systemctl status httpd
sudo systemctl status mariadb
```

### Test Database Connection

```bash
mysql -u cad -p cad
```

### View CAD Configuration

```bash
cat /var/www/html/cad/cad.conf
```

### Check File Permissions

```bash
ls -la /var/www/html/cad/cad.conf
```

Should be: `-rw-------` (600) owned by `apache:apache`

### Check SELinux Context

```bash
ls -Z /var/www/html/cad/cad.conf
```

### View Syslog Messages

```bash
sudo journalctl -t cad -n 50
```

Or on older systems:
```bash
sudo grep cad /var/log/messages | tail -50
```

### Backup Database

```bash
mysqldump -u cad -p cad > cad_backup_$(date +%Y%m%d).sql
```

### Restore Database

```bash
mysql -u cad -p cad < cad_backup_YYYYMMDD.sql
```

### Check Disk Space

```bash
df -h /var/www/html/cad
df -h /var/lib/mysql
```

### Test PHP Script Syntax

```bash
php -l /var/www/html/cad/filename.php
```

## File Locations

### Production Installation
```
/var/www/html/cad/           - Web root
/var/www/html/cad/cad.conf   - Configuration (600 permissions!)
```

### Source Code
```
/home/kenneth/src/blackflower/
```

### Logs
```
/var/log/httpd/error_log     - Apache errors
/var/log/httpd/access_log    - Apache access
/var/log/mariadb/            - Database logs
```

### Configuration Files
```
/etc/httpd/conf.d/cad.conf   - Apache CAD config
/etc/php.ini                 - PHP configuration
/etc/my.cnf.d/               - MariaDB configuration
```

## Quick Diagnostics

### Can't Log In - 500 Error

1. Check Apache error log:
   ```bash
   sudo tail -f /var/log/httpd/error_log
   ```

2. Check for PHP errors in the log

3. Verify mysqli extension:
   ```bash
   php -m | grep mysqli
   ```

### Can't Connect to Database

1. Check MariaDB is running:
   ```bash
   sudo systemctl status mariadb
   ```

2. Test connection:
   ```bash
   mysql -u cad -p cad
   ```

3. Verify credentials in `cad.conf`

### SELinux Blocking Access

1. Check for denials:
   ```bash
   sudo ausearch -m avc -ts recent
   ```

2. Set httpd to connect to database:
   ```bash
   sudo setsebool -P httpd_can_network_connect_db 1
   ```

3. Fix file contexts:
   ```bash
   sudo restorecon -Rv /var/www/html/cad
   ```

### Locked Out of Admin Account

Use the password reset utility:
```bash
cd /var/www/html/cad
php reset-admin-password.php
```

## Security Checklist

- [ ] `cad.conf` has 600 permissions
- [ ] `cad.conf` is owned by apache:apache  
- [ ] .htaccess is blocking access to sensitive files
- [ ] Test: http://yourserver/cad/cad.conf should return 403
- [ ] SELinux is in enforcing mode
- [ ] Firewall allows only HTTP/HTTPS
- [ ] Database only accepts localhost connections
- [ ] All users have strong passwords
- [ ] Regular backups are configured
- [ ] System is up to date (dnf update)

## Useful SQL Queries

### List All Users
```sql
SELECT id, username, name, access_level, locked_out, failed_login_count 
FROM users;
```

### Unlock User Manually
```sql
UPDATE users 
SET locked_out = 0, failed_login_count = 0 
WHERE username = 'Administrator';
```

### List Recent Incidents
```sql
SELECT incident_id, call_number, call_type, incident_status, ts_opened 
FROM incidents 
ORDER BY ts_opened DESC 
LIMIT 20;
```

### List Active Units
```sql
SELECT unit, status, update_ts 
FROM units 
WHERE status IS NOT NULL 
ORDER BY unit;
```

## Documentation Files

| File | Purpose |
|------|---------|
| `README` | Original Black Flower documentation |
| `INSTALL_ALMALINUX9.md` | Complete installation guide for AlmaLinux 9 |
| `MODERNIZATION.md` | Technical details of PHP 8.x modernization |
| `MIGRATION_SUMMARY.txt` | Executive summary of changes |
| `REQUIREMENTS.txt` | System requirements |
| `ADMIN_RECOVERY.md` | Password reset and account recovery |
| `QUICK_REFERENCE.md` | This file - common tasks |
| `CHANGES` | Historical changelog |

## Support Contacts

**For modernization issues:**
- Review documentation in the files listed above
- Check logs: `/var/log/httpd/error_log`

**For general Black Flower CAD:**
- Email: cad-info@forlorn.net
- Website: http://www.forlorn.net/cad/

## Version Information

Run from CAD directory to check versions:

```bash
php -v                        # PHP version
mysql -V                      # MySQL/MariaDB version
httpd -v                      # Apache version
cat VERSION                   # CAD version
```

## Emergency Procedures

### System Won't Start

1. Check all services:
   ```bash
   sudo systemctl status httpd mariadb
   ```

2. Review recent changes:
   ```bash
   sudo journalctl -xe
   ```

3. Test configuration:
   ```bash
   sudo httpd -t
   ```

### Database Corruption

1. Stop application
2. Restore from backup:
   ```bash
   mysql -u cad -p cad < latest_backup.sql
   ```
3. Restart services

### Complete System Failure

1. Have backups ready:
   - Database dumps
   - `cad.conf` file
   - Custom modifications

2. Follow `INSTALL_ALMALINUX9.md` to rebuild

3. Import database and configuration

## Performance Tuning

### For Heavy Load

Edit `/etc/php.ini`:
```ini
memory_limit = 256M
max_execution_time = 300
```

Edit `/etc/httpd/conf.d/mpm.conf` for more workers.

Edit `/etc/my.cnf.d/server.cnf` for database tuning.

Then restart services:
```bash
sudo systemctl restart httpd mariadb
```

---

**Quick Tip**: Bookmark this file for easy reference during operations!

