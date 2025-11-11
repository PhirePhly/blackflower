# Black Flower CAD - Administrator Account Recovery

## Overview

This guide explains how to recover access to Black Flower CAD when you've been locked out or forgotten the administrator password.

## Password Reset Utility

Black Flower CAD includes a command-line utility to reset passwords and unlock accounts.

### Location

```
reset-admin-password.php
```

### Requirements

- SSH/console access to the server
- Appropriate file system permissions to read `cad.conf`
- Database access (credentials from `cad.conf`)

## Usage

### Basic Usage (Interactive)

Run the script and it will prompt you for the password:

```bash
cd /var/www/html/cad
php reset-admin-password.php
```

This will:
1. Reset the password for the default "Administrator" user
2. Prompt you to enter and confirm the new password (hidden input)
3. Unlock the account
4. Reset failed login attempts

### Specify Username

To reset a different user's password:

```bash
php reset-admin-password.php username
```

Example:
```bash
php reset-admin-password.php admin
php reset-admin-password.php dispatcher1
```

### Non-Interactive Mode

For scripted use, provide both username and password:

```bash
php reset-admin-password.php username newpassword
```

**Warning**: This shows the password in the command line history. Use interactive mode for better security.

## Examples

### Example 1: Reset Default Administrator

```bash
$ cd /var/www/html/cad
$ php reset-admin-password.php

================================================================================
Black Flower CAD - Administrator Password Reset Utility
================================================================================

✓ Connected to database successfully

Enter new password for user 'Administrator': [hidden]
Confirm new password: [hidden]

Found user:
  Username: Administrator
  Name: System Administrator
  Access Level: 10
  User ID: 1

================================================================================
✓ SUCCESS!
================================================================================

The following changes have been made:
  ✓ Password has been reset
  ✓ Account has been unlocked
  ✓ Failed login count has been reset to 0
  ✓ Password change flag has been cleared

You can now log in with:
  Username: Administrator
  Password: [the password you just set]
```

### Example 2: Reset Specific User

```bash
$ php reset-admin-password.php dispatcher
```

### Example 3: Check if Script Works

```bash
$ php -l reset-admin-password.php
No syntax errors detected in reset-admin-password.php
```

## What the Utility Does

When you run the password reset utility, it performs the following actions:

1. **Connects to Database**: Uses credentials from `cad.conf`
2. **Finds User**: Searches for the specified username
3. **Hashes Password**: Uses the same PasswordHash library as the application
4. **Updates Database**: Executes SQL UPDATE to modify the user record:
   - Sets new password hash
   - Sets `failed_login_count = 0`
   - Sets `locked_out = 0`
   - Sets `change_password = 0`

## Troubleshooting

### Error: Cannot find cad.conf

**Problem**: Script cannot locate the configuration file.

**Solution**: Make sure you're running the script from the CAD installation directory:

```bash
cd /var/www/html/cad
php reset-admin-password.php
```

### Error: Cannot connect to database

**Problem**: Database connection failed.

**Possible Causes**:
- MySQL/MariaDB service not running
- Incorrect credentials in `cad.conf`
- Database user doesn't have permissions

**Solutions**:

1. Check database service:
   ```bash
   sudo systemctl status mariadb
   ```

2. Verify database credentials:
   ```bash
   cat cad.conf | grep DB_
   ```

3. Test database connection manually:
   ```bash
   mysql -u cad -p cad
   ```

### Error: User not found

**Problem**: The specified username doesn't exist in the database.

**Solution**: Check available usernames:

```bash
mysql -u cad -p cad -e "SELECT id, username, name FROM users;"
```

### Error: Permission denied

**Problem**: Script cannot read `cad.conf` or access the database.

**Solution**: Run with appropriate privileges:

```bash
sudo -u apache php reset-admin-password.php
```

Or adjust file permissions (temporarily):

```bash
sudo chmod 644 cad.conf
php reset-admin-password.php
sudo chmod 600 cad.conf  # Restore secure permissions
```

### Error: This script must be run from the command line

**Problem**: Attempting to access the script via web browser.

**Solution**: This is a security feature. The script can ONLY be run from the command line (SSH/console), not through a web browser. This prevents unauthorized password resets.

## Security Considerations

### Access Control

- The script must be run with appropriate system-level access
- Requires read access to `cad.conf` (which contains database credentials)
- Cannot be accessed via web browser (security feature)

### Password Security

1. **Use Interactive Mode**: Passwords entered interactively are hidden and don't appear in command history
2. **Strong Passwords**: Use passwords with:
   - Minimum 8 characters (preferably 12+)
   - Mix of uppercase, lowercase, numbers, and symbols
   - Not based on dictionary words
3. **Temporary Passwords**: Treat reset passwords as temporary and change them after logging in

### Audit Trail

Password resets are **not** logged in the syslog. Consider:

1. **Manual Logging**: Document when and why you reset passwords
2. **Review Access**: Check `/var/log/secure` or `/var/log/auth.log` for SSH access
3. **Database Audit**: Check `last_login_time` in the users table

### File Permissions

The script should have restricted permissions:

```bash
chmod 750 reset-admin-password.php
chown root:apache reset-admin-password.php
```

This allows:
- Root to execute it
- Apache group to execute it (if needed)
- No world access

## Manual Password Reset (Alternative Method)

If the script doesn't work, you can reset the password manually via SQL:

### Step 1: Generate Password Hash

Create a temporary PHP script:

```php
<?php
require_once('PasswordHash.php');
$hasher = new PasswordHash(8, FALSE);
$hash = $hasher->HashPassword('your-new-password');
echo "Password hash: $hash\n";
?>
```

Run it:
```bash
php genhash.php
```

### Step 2: Update Database

```bash
mysql -u cad -p cad
```

```sql
UPDATE users 
SET password = 'PASTE_HASH_HERE',
    failed_login_count = 0,
    locked_out = 0,
    change_password = 0
WHERE username = 'Administrator';
```

### Step 3: Verify

```sql
SELECT username, locked_out, failed_login_count FROM users WHERE username = 'Administrator';
```

## Post-Recovery Steps

After successfully resetting the password:

1. **Test Login**: Verify you can log in with the new password
2. **Change Password**: Use the application's "Settings" → "Change Password" feature
3. **Review Users**: Check for unauthorized user accounts
4. **Review Logs**: Look for suspicious activity
5. **Update Documentation**: Record the reset in your change log
6. **Secure Script**: Ensure the reset script has proper permissions

## Prevention

To avoid needing password resets:

1. **Password Manager**: Use a password manager to store admin credentials
2. **Multiple Admins**: Create multiple administrator accounts
3. **Documentation**: Keep secure documentation of recovery procedures
4. **Backup**: Regularly backup the database
5. **Access Control**: Implement proper access level policies

## Emergency Contact

If you cannot recover access:

1. Contact your system administrator
2. Check backup/DR procedures
3. Review database backups
4. Consider restoring from backup if needed

## Related Documentation

- `INSTALL_ALMALINUX9.md` - Installation procedures
- `MODERNIZATION.md` - Technical details
- `README` - General CAD documentation
- `cad.conf.example` - Configuration reference

## Script Location in Production

After installation, ensure the script is accessible:

```bash
# Development
/home/kenneth/src/blackflower/reset-admin-password.php

# Production
/var/www/html/cad/reset-admin-password.php
```

## Version History

- **v1.0** (2025-11-11): Initial version with modernized mysqli support
  - Interactive password entry with confirmation
  - Account unlock functionality
  - Failed login count reset
  - Compatible with PHP 7.4+ / 8.x

---

**Remember**: This utility is a powerful administrative tool. Use it responsibly and only when necessary.

