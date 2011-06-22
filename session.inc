<?php
  require_once('db-open.php');

  if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    define_syslog_variables();  
  }

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

// --------------------------------------------------------------------------

function HTMLLoginDialog($failureCount) {
?>
<html class="login">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
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

<body class="login" onload="setFocus('username')">
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
       <td class="text"> <input id="username" type="text" size="15" name="username"></td>
  </tr>
  <tr> <td class="text"> Password: </td>
       <td class="text"> <input type="password" size="15" name="password"></td>
  </tr>
  <tr> <td></td><td><input class="loginlabel" type="submit" value="Log in"></td> </tr>
  </table>

</td></tr>
<?php
  if ($failureCount) {
    print "<tr valign=top><td><center><font color=\"red\">Incorrect username or password, try again.</font></center><input type=\"hidden\" value=\"$failureCount\" name=\"ival\"></td></tr>";
  } ?>
<tr height="50%" valign="bottom">
<td width="100%">
&nbsp;</td></tr>
</table>
</form>
</body>
<?php
}

// --------------------------------------------------------------------------
// Actual start of session.inc

  $sname = 'PHPSESS_cad_' . implode ('_', array_slice( explode('/', str_replace('~', '_tilde_', $_SERVER['REQUEST_URI'])), 0, -1));
  session_name($sname);

  if (isset($_POST['username']) && isset($_POST['password'])) {
    session_start();

    if (isset($_POST['ival'])) {
      $ival = MysqlClean($_POST, 'ival', 10);
      $previousFailures = (int)($ival);
    }
    else {
      $previousFailures = 0;
    }
    $testpw = MysqlClean($_POST, "password", 40);
    $username = MysqlClean($_POST, "username", 40);

    $users = MysqlQuery("SELECT id, username, name, password, PASSWORD('$testpw') as testpw, access_level, access_acl, timeout from $DB_NAME.users WHERE UPPER(username) = '".strtoupper($username)."'");

    if (mysql_num_rows($users) >= 1) {
      if (mysql_num_rows($users) > 1) {
        syslog(LOG_WARNING, "During login - More than one user in db with username [$username], using first returned.");
      }
      $answer = mysql_fetch_object($users);
      if ($answer->password == $answer->testpw) {
        $_SESSION['id'] = MysqlUnClean($answer->id);
        $_SESSION['password'] = MysqlUnClean($answer->password);
        $_SESSION['name'] = MysqlUnClean($answer->name);
        $_SESSION['username'] = MysqlUnClean($answer->username);
        $_SESSION['access_level'] = MysqlUnClean($answer->access_level);
        $_SESSION['access_acl'] = MysqlUnClean($answer->access_acl);
        $_SESSION['timeout'] = MysqlUnClean($answer->timeout);
        # TODO: add preference parsing
        session_write_close();
        syslog(LOG_INFO, "User logged in [".MysqlUnClean($answer->username) . "/". $_SERVER['REMOTE_ADDR']."]");
        header("Location: main.php");
      }
      else {
        if ($previousFailures) {
          sleep(2 * $previousFailures);
        }
        syslog(LOG_WARNING, "Failed login attempt: Incorrect password for user [$username]/IP ". $_SERVER['REMOTE_ADDR']."]/password attempt [$testpw]");
        HTMLLoginDialog($previousFailures + 1);
        exit;
      }
    }
    else {
      if ($previousFailures) {
        sleep(2 * $previousFailures);
      }
      syslog(LOG_WARNING, "Failed login attempt: Unknown username [$username]/". $_SERVER['REMOTE_ADDR']."]");
      HTMLLoginDialog($previousFailures + 1);
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
    header("Location: main.php");
    exit;
  }
  else {
    session_start();
    if (!isset($_SESSION['username']) || $_SESSION['username'] == "") {
      session_destroy();
      HTMLLoginDialog(0);
      exit;
    }
  }

?>
