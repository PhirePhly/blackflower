#!/usr/bin/env php
<?php
/**
 * Black Flower CAD - Administrator Password Reset Utility
 * 
 * This utility resets the administrator password and unlocks the account.
 * Run this script from the command line with appropriate privileges.
 * 
 * Usage:
 *   php reset-admin-password.php [username] [new-password]
 *   
 * If username is omitted, defaults to "Administrator"
 * If password is omitted, you'll be prompted to enter it
 * 
 * Example:
 *   php reset-admin-password.php Administrator newpass123
 *   php reset-admin-password.php admin
 *   php reset-admin-password.php
 */

// Ensure this is run from command line only
if (php_sapi_name() !== 'cli') {
    die("ERROR: This script must be run from the command line for security reasons.\n");
}

echo "\n";
echo "================================================================================\n";
echo "Black Flower CAD - Administrator Password Reset Utility\n";
echo "================================================================================\n";
echo "\n";

// Load configuration and database connection
if (!file_exists('cad.conf')) {
    die("ERROR: Cannot find cad.conf configuration file.\n" .
        "       Make sure you run this script from the CAD installation directory.\n\n");
}

require_once('cad.conf');

// Connect to database
$link = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$link) {
    die("ERROR: Cannot connect to database.\n" .
        "       Host: $DB_HOST\n" .
        "       User: $DB_USER\n" .
        "       Database: $DB_NAME\n" .
        "       Error: " . mysqli_connect_error() . "\n\n");
}

echo "✓ Connected to database successfully\n\n";

// Get username from command line or use default
$username = isset($argv[1]) ? $argv[1] : 'Administrator';

// Get password from command line or prompt
if (isset($argv[2])) {
    $new_password = $argv[2];
} else {
    // Prompt for password (disable echo for security)
    echo "Enter new password for user '$username': ";
    
    // Disable echo on Unix-like systems
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo');
    }
    
    $new_password = trim(fgets(STDIN));
    
    // Re-enable echo
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty echo');
    }
    
    echo "\n";
    
    if (empty($new_password)) {
        mysqli_close($link);
        die("ERROR: Password cannot be empty.\n\n");
    }
    
    // Confirm password
    echo "Confirm new password: ";
    
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo');
    }
    
    $confirm_password = trim(fgets(STDIN));
    
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty echo');
    }
    
    echo "\n\n";
    
    if ($new_password !== $confirm_password) {
        mysqli_close($link);
        die("ERROR: Passwords do not match.\n\n");
    }
}

// Validate password strength (basic check)
if (strlen($new_password) < 6) {
    echo "WARNING: Password is less than 6 characters. This is not recommended.\n";
    echo "Continue anyway? (yes/no): ";
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'yes') {
        mysqli_close($link);
        die("Password reset cancelled.\n\n");
    }
    echo "\n";
}

// Check if user exists
$username_escaped = mysqli_real_escape_string($link, $username);
$query = "SELECT id, username, name, access_level FROM users WHERE username = '$username_escaped'";
$result = mysqli_query($link, $query);

if (!$result) {
    mysqli_close($link);
    die("ERROR: Database query failed: " . mysqli_error($link) . "\n\n");
}

if (mysqli_num_rows($result) === 0) {
    mysqli_close($link);
    die("ERROR: User '$username' not found in database.\n\n");
}

$user = mysqli_fetch_object($result);
mysqli_free_result($result);

echo "Found user:\n";
echo "  Username: " . $user->username . "\n";
echo "  Name: " . $user->name . "\n";
echo "  Access Level: " . $user->access_level . "\n";
echo "  User ID: " . $user->id . "\n";
echo "\n";

// Hash the new password using PHP's native password_hash() (PHP 5.5+)
// This creates a bcrypt hash which is much more secure than the old PasswordHash library
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

if ($password_hash === false) {
    mysqli_close($link);
    die("ERROR: Password hashing failed.\n\n");
}

// Update the user record
$query = "UPDATE users SET " .
         "password = '" . mysqli_real_escape_string($link, $password_hash) . "', " .
         "failed_login_count = 0, " .
         "locked_out = 0, " .
         "change_password = 0 " .
         "WHERE id = " . (int)$user->id;

$result = mysqli_query($link, $query);

if (!$result) {
    mysqli_close($link);
    die("ERROR: Failed to update password: " . mysqli_error($link) . "\n\n");
}

$affected = mysqli_affected_rows($link);

if ($affected !== 1) {
    mysqli_close($link);
    die("ERROR: Expected to update 1 row, but updated $affected rows.\n\n");
}

mysqli_close($link);

echo "================================================================================\n";
echo "✓ SUCCESS!\n";
echo "================================================================================\n";
echo "\n";
echo "The following changes have been made:\n";
echo "  ✓ Password has been reset\n";
echo "  ✓ Account has been unlocked\n";
echo "  ✓ Failed login count has been reset to 0\n";
echo "  ✓ Password change flag has been cleared\n";
echo "\n";
echo "You can now log in with:\n";
echo "  Username: " . $user->username . "\n";
echo "  Password: [the password you just set]\n";
echo "\n";
echo "SECURITY REMINDER:\n";
echo "  - Change this password to something secure after logging in\n";
echo "  - Consider this a temporary administrative password\n";
echo "  - Review failed login attempts in the logs\n";
echo "\n";

?>

