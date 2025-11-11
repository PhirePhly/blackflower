<?php
  require_once('db-open.php');
  require_once('PasswordHash.php');

  // define_syslog_variables() removed in PHP 5.4, constants are now always defined

  if (strpos($_SERVER["SERVER_SOFTWARE"], "(Win32")) {
    openlog('cad', LOG_CONS|LOG_PID, LOG_USER);
  }
  else {
    openlog('cad', LOG_CONS|LOG_PID, $SYSLOG_FACILITY);
  }

/*
 * Disable session.use_trans_sid to mitigate performance-penalty
 * (do it before any output is started)
 */
  if (!defined('SID')) {
    @ini_set('session.use_trans_sid', 0);
  }

  /* Specify that when an assertion fails, we terminate right away. */
  assert_options(ASSERT_WARNING, 1);
  assert_options(ASSERT_BAIL, 1);

  define('FAILURE_INCORRECT', "Incorrect username or password, try again.");
  define('FAILURE_LOCKEDOUT', "This account has been locked, contact your system administrator.");
  define('RELOAD', "");

// --------------------------------------------------------------------------


function PrintLoginDialogHeader() {
?>
<html class="login">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  <meta name="viewport" content="width=device-width, maximum-scale=1.0" />
  <link rel="stylesheet" href="style.css" type="text/css" media="screen">
  <script src="js/utils.js" type="text/javascript"></script>
  <script type="text/javascript">
// <!--
function element(id) {
  if (document.getElementById != null) { return document.getElementById(id); }
  else if (document.all != null) { return document.all[id]; }
  else if (document.layers != null) { return document.layers[id]; }
  else { return null; }
}

function setFocus(str) {
  element(str).focus();
  element(str).select();
}
// -->
</script>
</head>
<?php
}

function HTMLLoginDialog($failureReason=FAILURE_INCORRECT, $hash='') {
  global $DEBUG;
  PrintLoginDialogHeader();
?>
<body class="login" onload="setFocus('login_username')">
<noscript>
  <br />
  <table cellpadding="0" cellspacing="0" border="0" class="WhiteTable PublicError">
    <tr><td id="Top"><img src="/cad/Images/1.gif" width="1" height="1" alt=""></td></tr>
    <tr>
      <td id="Mid">
        <div class="EmptyContianerImage">
        <div class="EmptyContianerText">
        Set Up Your Web Browser To Allow JavaScript</div>
        <div class="EmptyContianerExplain">
        JavaScript is required to use this site. Please enable your web browser
        to run JavaScript. For steps in configuring this, see the Help for
        your browser.&nbsp;&nbsp;
        </div>
        </div>
      </td>
    </tr>
    <tr><td id="Bot"><img src="/cad/Images/1.gif" width="1" height="1" alt=""></td></tr>
  </table><br />
</noscript>
<form method="post" action="main.php">
<table id="pagelayout" >
<tr height="100"> <td>&nbsp;</td></tr>
<tr height="100"> <td class="login" align="center" width="500">

  <table style="border: black solid 1px">
  <tr> <td class="logintitle" colspan="2">CAD :: Login</td></tr>

  <tr> <td class="text"> Username:</td>
       <td class="text"> <input id="login_username" type="text" size="15" name="login_username"></td>
  </tr>
  <tr> <td class="text"> Password: </td>
       <td class="text"> <input type="password" size="15" name="login_password"></td>
  </tr>
  <tr> <td></td><td><input class="loginlabel" type="submit" value="Log in"></td> </tr>
  </table>

</td></tr>
<?php
  if ($failureReason != RELOAD) {
    print "<tr valign=top><td><center><font color=\"red\">$failureReason</font></center></td></tr>\n";
    if (isset($DEBUG) && $DEBUG && $hash != '') {
      print "<tr valign=top><td><center><font size=\"-1\" color=\"#888888\">Hash: $hash</font></center></td></tr>\n";
    }
  } ?>
<tr height="40%" valign="bottom">
<td width="100%">
&nbsp;</td></tr>
</table>
</form>
</body>
<?php
}

function HTMLPasswordDialog($message='') {
  PrintLoginDialogHeader();
?>
<body class="login" onload="setFocus('chpw_current')">
<form method="post" action="main.php">
<table id="pagelayout" >
<tr height="100"> <td>&nbsp;</td></tr>
<tr height="100"> <td class="login" align="center" width="500">
  <table style="border: black solid 1px">
  <tr> <td class="logintitle" style="background-color: yellow; color: black" colspan="2"> Your password has been expired and you must change it now.</td></tr>

  <tr><td class="text" colspan=2 style="background-color: #eeeeee; color: red; font-weight: bold"><?php print $message?></td></tr>
  <tr> <td class="text"> Current password:</td>
       <td class="text"> <input id="chpw_current" type="password" size="15" name="chpw_current"></td>
  </tr>
  <tr> <td class="text"> New password: </td>
       <td class="text"> <input type="password" size="15" name="chpw_newpasswd1"></td>
  </tr>
  <tr> <td class="text"> New password (confirm): </td>
       <td class="text"> <input type="password" size="15" name="chpw_newpasswd2"></td>
  </tr>
  <tr> <td></td><td><input class="loginlabel" type="submit" value="Change password"></td> </tr>
</table>
</td></tr>
<tr height="50%" valign="bottom">
<td width="100%">
&nbsp;</td></tr>
</table>
</form>
</body>
<?php
}

function ipIsInCIDR($ip, $cidrblock) {
   $ip_addr = decbin(ip2long($ip));
   $network = explode("/", $cidrblock);
   $net_addr = decbin(ip2long($network[0]));
   $len = $network[1];
   return (substr($net_addr, 0, $len) == substr($ip_addr, 0, $len));
}

# From php.net forum, claudiu at cnixs dot com:
function ipCIDRCheck ($IP, $CIDR) {
    list ($net, $mask) = explode ("/", $CIDR);
    $ip_net = ip2long ($net);
    $ip_mask = ~((1 << (32 - $mask)) - 1);
    $ip_ip = ip2long ($IP);
    $ip_ip_net = $ip_ip & $ip_mask;
    return ($ip_ip_net == $ip_net);
  }


function SessionErrorIfReadonly() {
  if (isset($_SESSION['readonly']) && $_SESSION['readonly']) {
    print "Error: this module is unavailable while you are logged in as 'read-only' mode.  Please contact your system administrator to resolve this issue.<br>";
    syslog(LOG_WARNING, "Blocked user [" . $_SESSION["username"] . "] accessing ".$_SERVER['SCRIPT_FILENAME']." while in read-only mode.");
    exit;
  }
}





// --------------------------------------------------------------------------
// Actual start of session.inc

  $sname = 'PHPSESS_cad_' . implode ('_', array_slice( explode('/', str_replace('~', '_tilde_', $_SERVER['REQUEST_URI'])), 0, -1));
  session_name($sname);

  $t_hasher = new PasswordHash(8, FALSE);
  if (isset($_POST['chpw_current'])) {
    // TODO: error checking
    session_start();

    $users = MysqlQuery("SELECT * from $DB_NAME.users WHERE id=".$_SESSION['id']);
    if (mysqli_num_rows($users) != 1) {
      syslog(LOG_CRITICAL, "INTERNAL ERROR during login (password reset) - Incorrect number of $DB_NAME.users rows for id=".$_SESSION['id'].", expected 1 got ". mysqli_num_rows($users));
      print "INTERNAL ERROR during login (password reset) - Incorrect number of $DB_NAME.users rows for id=".$_SESSION['id'].", expected 1 got ". mysqli_num_rows($users);
      exit;
      }
    $answer = mysqli_fetch_object($users);
    if (!$t_hasher->CheckPassword($_POST['chpw_current'], $answer->password)) {
      HTMLPasswordDialog("Incorrect current password, try again.");
      exit;
    }
    elseif ($_POST["chpw_newpasswd1"] != $_POST["chpw_newpasswd2"]) {
      HTMLPasswordDialog("New passwords do not match, try again.");
      exit;
    }
    elseif ($_POST["chpw_newpasswd1"] == '') {
      HTMLPasswordDialog("New passwords cannot be empty, try again.");
      exit;
    }
    else {
      $hash = $t_hasher->HashPassword($_POST["chpw_newpasswd1"]);
      MysqlQuery("UPDATE $DB_NAME.users set password='$hash', change_password=0 where id=".$_SESSION['id']);
      header("Location: index.php");
      exit;
    }
  }

  if (isset($_POST['login_username']) && isset($_POST['login_password'])) {
    session_start();
    // TODO: clear session values here??
    $posted_passwordattempt = MysqlClean($_POST, "login_password", 40);
    $username = MysqlClean($_POST, "login_username", 40);

    $users = MysqlQuery("SELECT * from $DB_NAME.users WHERE UPPER(username) = '".strtoupper($username)."'");

    if (mysqli_num_rows($users) >= 1) {
      if (mysqli_num_rows($users) > 1) {
        syslog(LOG_WARNING, "During login - More than one user in db with username [$username], using first row returned."); // this should never happen but is not yet strongly constrained
      }

      $answer = mysqli_fetch_object($users);

      if (isset($answer->locked_out) && $answer->locked_out>0) {
        sleep(4);
        syslog(LOG_WARNING, "Failed login attempt to locked out account: (user:$username, IP:". $_SERVER['REMOTE_ADDR'].")");
        HTMLLoginDialog(FAILURE_LOCKEDOUT);
        exit;
      }

      if ($t_hasher->CheckPassword($posted_passwordattempt, $answer->password)) {
        # 
        # This is a successful login attempt.
        #
        $_SESSION['id'] = MysqlUnClean($answer->id);
        $_SESSION['password'] = MysqlUnClean($answer->password);
        $_SESSION['name'] = MysqlUnClean($answer->name);
        $_SESSION['username'] = MysqlUnClean($answer->username);
        $_SESSION['access_level'] = MysqlUnClean($answer->access_level);
        $_SESSION['access_acl'] = MysqlUnClean($answer->access_acl);
        $_SESSION['timeout'] = MysqlUnClean($answer->timeout);
        $_SESSION['readonly'] = 0;
        $readonlyp = '';
        if (isset($READONLY_BY_DEFAULT) && $READONLY_BY_DEFAULT) {
          # Blacklist by default:
          $_SESSION['readonly'] = 1;
          syslog (LOG_INFO, "User login attempt for [".MysqlUnClean($answer->username)."] - screening for a reason not to stay READONLY_BY_DEFAULT.");
          # Then try to override it with a whitelist match:
          if (isset($READWRITE_PERMIT_IPV4) && $READWRITE_PERMIT_IPV4) {
            foreach (explode(',', $READWRITE_PERMIT_IPV4) as $permitCIDRBlock) {
              $remote_addr=$_SERVER['REMOTE_ADDR'];
              if ($remote_addr=='::1') $remote_addr='127.0.0.1';  # I hate blithe assumptions.  Since we don't have a _PERMIT_IPV6 feature yet but IPv6 OSs insist on calling localhost ::1 ...
              if (ipIsInCIDR( $remote_addr, $permitCIDRBlock)) {
                syslog(LOG_INFO, "User [".MysqlUnClean($answer->username)."] login matched read/write on CIDR whitelist [$permitCIDRBlock] (user is from ".$_SERVER['REMOTE_ADDR'].')');
                $_SESSION['readonly'] = 0;
                break;
              }
            }
          }
          if ($_SESSION['readonly'] && isset($READWRITE_PERMIT_USERIDS) && $READWRITE_PERMIT_USERIDS) {
            foreach (explode(',', $READWRITE_PERMIT_USERIDS) as $permitUserID) {
              if ($_SESSION['id'] == $permitUserID) {
                syslog(LOG_INFO, "User [".MysqlUnClean($answer->username)."] login matched read/write on user ID whitelist [$permitUserID] (user is ID ".$_SESSION['id'].')');
                $_SESSION['readonly'] = 0;
                break;
              }
            }
          }
          if ($_SESSION['readonly'] && isset($READWRITE_PERMIT_ACLS) && $READWRITE_PERMIT_ACLS) {
            foreach (explode(',', $READWRITE_PERMIT_ACLS) as $permitACL) {
              if (strchr($_SESSION['access_acl'], $permitACL)) {
                syslog(LOG_INFO, "User [".MysqlUnClean($answer->username)."] login matched read/write on ACL whitelist [$permitACL] (user has ACLs ".$_SESSION['access_acl'].')');
                $_SESSION['readonly'] = 0;
                break;
              }
            }
          }
        }
        if (isset($READONLY_BY_DEFAULT)) {
          if ($_SESSION['readonly']) 
            $readonlyp = ' read-only';
          else
            $readonlyp = ' *READ-WRITE*';
        }
        # TODO: add preference parsing
        session_write_close();
        syslog(LOG_INFO, "User logged in$readonlyp [".MysqlUnClean($answer->username) . "/". $_SERVER['REMOTE_ADDR']."]$readonlyp");
        MysqlQuery("UPDATE $DB_NAME.users set failed_login_count=0,last_login_time=NOW() WHERE id=".$answer->id);

        // TODO: consider making password expiry effective on every screen rather than just at login time?
        if ($answer->change_password) {
          HTMLPasswordDialog();
          exit;
        }
        header("Location: index.php");
        exit;
      }

      elseif (isset($MAX_LOGIN_ATTEMPTS) && $answer->failed_login_count + 1 >= $MAX_LOGIN_ATTEMPTS  ||
              !isset($MAX_LOGIN_ATTEMPTS) && $answer->failed_login_count + 1 >= 5) {
        $numFailures = $answer->failed_login_count + 1;
        sleep(4);
        MysqlQuery("UPDATE $DB_NAME.users set failed_login_count=$numFailures, locked_out=1 WHERE id=".$answer->id);
        syslog(LOG_WARNING, "Failed login attempt: User account locked out for (user:$username, IP:". $_SERVER['REMOTE_ADDR'].")");
        HTMLLoginDialog(FAILURE_LOCKEDOUT);
        exit;
      }
      else {
        $numFailures = $answer->failed_login_count + 1;
        sleep (2 * $numFailures);
        syslog(LOG_WARNING, "Failed login attempt: Incorrect password for (user:$username, IP:". $_SERVER['REMOTE_ADDR']."), attempt #$numFailures");
        MysqlQuery("UPDATE $DB_NAME.users set failed_login_count=$numFailures WHERE id=".$answer->id);
        HTMLLoginDialog(FAILURE_INCORRECT, $t_hasher->HashPassword($posted_passwordattempt));
        exit;
      }
      
    }
    else {
      sleep(4);
      syslog(LOG_WARNING, "Failed login attempt: Unknown username for (user:$username, IP:". $_SERVER['REMOTE_ADDR'].")");
      HTMLLoginDialog(FAILURE_INCORRECT);
      exit;
    }
  }
  elseif (isset($_GET['logout'])) {
    session_start();
    syslog(LOG_INFO, "User logged out [".$_SESSION['username'] . "/". $_SERVER['REMOTE_ADDR']."]");
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
      );
    }
    session_destroy();
    header("Location: index.php");
    exit;
  }
  else {
    session_start();
    if (!isset($_SESSION['username']) || $_SESSION['username'] == "") {
      session_destroy();
      HTMLLoginDialog(RELOAD);
      exit;
    }
  }

?>
