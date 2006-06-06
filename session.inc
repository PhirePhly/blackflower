<?php
  require_once('db-open.php');

  define_syslog_variables();
  if (strpos($_SERVER["SERVER_SOFTWARE"], "(Win32")) {
    openlog('cad', LOG_CONS|LOG_PID, LOG_USER);
  }
  else {
    openlog('cad', LOG_CONS|LOG_PID, LOG_LOCAL4);
  }

function HTMLLoginDialog($failureCount) {
?>
<html class="login">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA="screen">
  <LINK REL=StyleSheet HREF="pager.css" TYPE="text/css" MEDIA="screen">
  <SCRIPT type="text/JavaScript">
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

<form method="post" action="index.php">
<table id="pagelayout" >
<tr height="100"> <td>&nbsp;</td></tr>
<tr height="100"> <td class="login" align=center width=500> 

  <table style="border: black solid 1px">
  <tr> <td class="logintitle" colspan=2>CAD :: Login</td></tr>

  <tr> <td class=text> Username:</td>
       <td class=text> <input id="username" type="text" size="15" name="username"></td>
  </tr>
  <tr> <td class=text> Password: </td>
       <td class=text> <input type="password" size="15" name="password"></td>
  </tr>
  <tr> <td></td><td ><input class="loginlabel" type=submit value="Log in"></td> </tr>
  </table>

</td></tr>
<?php 
  if ($failureCount) { 
    print "<tr valign=top><td><center><font color=\"red\">Incorrect username or password, try again.</font></center><input type=\"hidden\" value=\"$failureCount\" name=\"ival\"></td></tr>";
} ?>
<tr height=50% valign=bottom>
<td width=100%>
&nbsp;</td></tr>
</table>
</form>
</body>
<?php
}

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

    $users = MysqlQuery("SELECT id, username, name, password, PASSWORD('$testpw') as testpw, access_level, access_acl, timeout from cad.users WHERE UPPER(username) = '".strtoupper($username)."'");
    if (mysql_num_rows($users) == 1) {
      $answer = mysql_fetch_object($users);
      if ($answer->password == $answer->testpw) {
        $_SESSION['id'] = $answer->id;
        $_SESSION['name'] = $answer->name;
        $_SESSION['username'] = $answer->username;
        $_SESSION['access_level'] = $answer->access_level;
        $_SESSION['access_acl'] = $answer->access_acl;
        $_SESSION['timeout'] = $answer->timeout;
        session_write_close();
        syslog(LOG_INFO, "User logged in [".$answer->username . "/". $_SERVER['REMOTE_ADDR']."]");
        header("Location: index.php");
      }
      else {
        if ($previousFailures) {
          sleep(2 * $previousFailures);
        }
        syslog(LOG_WARNING, "Failed login attempt: Incorrect password for user [$username]/IP ". $_SERVER['REMOTE_ADDR']."]/password attempt [$password]");
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
    session_destroy();
    header("Location: index.php");
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
