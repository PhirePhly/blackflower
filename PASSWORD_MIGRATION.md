# Password Hashing Migration - PasswordHash to password_hash()

## Overview

Black Flower CAD has been updated to use PHP's native `password_hash()` and `password_verify()` functions instead of the legacy PasswordHash library. This provides:

- **Better Security**: Modern bcrypt hashing with automatic salt generation
- **PHP 8.x Compatibility**: No more array offset warnings
- **Native Support**: No external dependencies
- **Automatic Migration**: Seamless transition for existing users

## What Changed

### Before (Legacy PasswordHash)

The application used the PasswordHash library (phpass) which:
- Was designed for PHP 4/5 compatibility
- Used custom MD5-based or blowfish hashing
- Had PHP 8.x compatibility issues
- Required external library

### After (Native password_hash)

The application now uses PHP's built-in functions (available since PHP 5.5):
- `password_hash(PASSWORD_DEFAULT)` - Creates bcrypt hashes
- `password_verify()` - Verifies passwords
- Fully compatible with PHP 7.4+ and 8.x
- No external dependencies

## Backward Compatibility

### Hybrid Authentication System

The updated `session.inc` supports **BOTH** hash formats:

1. **Legacy PasswordHash hashes** - Start with `$P$` or `$H$`
   - Existing users can still log in
   - Verified using PasswordHash library

2. **Modern bcrypt hashes** - Start with `$2y$`, `$2a$`, or `$2b$`
   - New passwords and resets use this format
   - Verified using `password_verify()`

### Detection Logic

```php
if (substr($stored_hash, 0, 3) === '$2y' || 
    substr($stored_hash, 0, 3) === '$2a' || 
    substr($stored_hash, 0, 3) === '$2b') {
    // Modern password_hash() format
    $valid = password_verify($password, $stored_hash);
} else {
    // Legacy PasswordHash format
    $valid = $t_hasher->CheckPassword($password, $stored_hash);
}
```

## Migration Path

### Automatic Migration

Users are automatically migrated when they:

1. **Reset their password** via `reset-admin-password.php`
   - New hash created with `password_hash()`
   - Can log in immediately with new password

2. **Change their password** via Settings → Change Password
   - New hash created with `password_hash()`
   - Old PasswordHash no longer used

### No Action Required

- Existing users can continue logging in with old passwords
- Old PasswordHash hashes remain valid
- Migration happens naturally as passwords are changed

### Force Migration (Optional)

To force all users to modern hashing:

1. Notify users to change passwords
2. Set `change_password = 1` for specific users:
   ```sql
   UPDATE users SET change_password = 1 WHERE id = X;
   ```
3. Users will be prompted to change password on next login

## Hash Format Examples

### Legacy PasswordHash Hash
```
$P$B1234567890abcdefghijklmnopqrstuvwxyz...
```
- Starts with `$P$` (or `$H$` for phpBB3)
- 34 characters total
- Uses custom encoding

### Modern bcrypt Hash
```
$2y$10$abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQR
```
- Starts with `$2y$` (PHP), `$2a$` (older), or `$2b$` (newer)
- 60 characters total
- Industry-standard bcrypt format

## Password Strength

### PasswordHash (Legacy)
- Configurable iteration count (8 by default)
- MD5-based with multiple rounds
- Reasonably secure for its time

### password_hash() (Modern)
- Bcrypt with cost factor 10 by default
- Automatically increases as PHP evolves
- Industry standard, widely audited
- Supports future algorithms via PASSWORD_DEFAULT

## Database Schema

No database changes required. The `password` field in the `users` table:
- Current definition: VARCHAR or TEXT (sufficient for both formats)
- Legacy hashes: 34 characters
- Modern hashes: 60 characters
- Field accommodates both

## Security Implications

### Improved Security

1. **Automatic Salt**: bcrypt includes salt in the hash
2. **Adaptive Hashing**: Cost increases over time
3. **Native Implementation**: Less prone to bugs
4. **Future-Proof**: Algorithm can upgrade automatically

### Existing Passwords

Users with legacy PasswordHash hashes:
- Are still secure (PasswordHash uses blowfish if available)
- Should change passwords when convenient
- Will automatically upgrade on password change

## Troubleshooting

### User Can't Log In After Reset

**Symptom**: Password reset completes but login fails.

**Cause**: Mismatch between hash format and verification method.

**Solution**: Verify `session.inc` includes hybrid authentication code.

### PHP Warning About PasswordHash

**Symptom**: Warnings about array offsets in PasswordHash.php.

**Cause**: User has legacy hash, PasswordHash library has PHP 8.x issues.

**Solution**: User should reset password to get modern hash, OR suppress warnings, OR fix PasswordHash library.

### New Users Get Legacy Hashes

**Symptom**: Newly created users have `$P$` hashes instead of `$2y$`.

**Cause**: User creation code still uses PasswordHash.

**Solution**: Update user creation code in `config-users.php` to use `password_hash()`.

## User Creation Code

If creating users programmatically, use:

```php
// CORRECT - Modern
$hash = password_hash($password, PASSWORD_DEFAULT);

// WRONG - Legacy
$hasher = new PasswordHash(8, FALSE);
$hash = $hasher->HashPassword($password);
```

## Files Modified

1. **reset-admin-password.php**
   - Removed: `require_once('PasswordHash.php')`
   - Removed: `$hasher = new PasswordHash(8, FALSE)`
   - Added: `password_hash($password, PASSWORD_DEFAULT)`

2. **session.inc**
   - Added: Hybrid authentication in login flow
   - Added: Hybrid authentication in password change
   - Kept: PasswordHash for backward compatibility

3. **config-users.php** (Future)
   - TODO: Update user creation to use password_hash()

## Performance

### PasswordHash
- ~50-100ms per hash (depending on iteration count)
- Configurable iteration count (8 default)

### password_hash()
- ~100-200ms per hash (bcrypt cost 10)
- Intentionally slow to prevent brute force
- Performance acceptable for authentication

## Testing

### Test Login with Legacy Hash

1. Find user with `$P$` hash:
   ```sql
   SELECT username, password FROM users WHERE password LIKE '$P$%';
   ```

2. Test login - should work normally

### Test Login with Modern Hash

1. Reset a user's password:
   ```bash
   php reset-admin-password.php testuser
   ```

2. Verify hash in database:
   ```sql
   SELECT password FROM users WHERE username = 'testuser';
   ```
   Should start with `$2y$`

3. Test login - should work normally

### Test Password Change

1. Log in with any user
2. Go to Settings → Change Password
3. Change password
4. Check database - hash should be modern `$2y$`
5. Log out and back in - should work

## Future Improvements

### Complete Migration

Once all users have modern hashes:
1. Remove PasswordHash library dependency
2. Remove hybrid authentication code
3. Simplify to use only `password_verify()`

### Check Migration Status

Query to see how many users still have legacy hashes:

```sql
SELECT 
  COUNT(*) as total_users,
  SUM(CASE WHEN password LIKE '$P$%' THEN 1 ELSE 0 END) as legacy_hashes,
  SUM(CASE WHEN password LIKE '$2y%' THEN 1 ELSE 0 END) as modern_hashes
FROM users;
```

### Force Complete Migration

SQL to require all users to change password:

```sql
UPDATE users 
SET change_password = 1 
WHERE password LIKE '$P$%';
```

## Version History

- **2025-11-11**: Implemented hybrid authentication system
  - Added support for password_hash()
  - Maintained backward compatibility with PasswordHash
  - Updated reset-admin-password.php utility
  - Updated session.inc authentication

## References

- [PHP password_hash() documentation](https://www.php.net/manual/en/function.password-hash.php)
- [PHP password_verify() documentation](https://www.php.net/manual/en/function.password-verify.php)
- [PasswordHash (phpass) library](https://www.openwall.com/phpass/)
- [bcrypt](https://en.wikipedia.org/wiki/Bcrypt)

---

**Note**: This migration strategy ensures zero downtime and no user disruption while modernizing the password hashing system.

