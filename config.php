<?php
  $subsys = "config";

  require_once('session.inc');
  require_once('functions.php');

  if (isset($_POST["saving"])) {
    if (isset($_POST["incidents_open_only"]))
      setcookie("incidents_open_only", "yes");
    else
      setcookie("incidents_open_only", "no");

    if (isset($_POST["incidents_show_units"]))
      setcookie("incidents_show_units", "yes");
    else
      setcookie("incidents_show_units", "no");

    if (isset($_POST["incidents_show_creator"]))
      setcookie("incidents_show_creator", "yes");
    else
      setcookie("incidents_show_creator", "no");

    if (isset($_POST["units_color"]))
      setcookie("units_color", "yes");
    else
      setcookie("units_color", "no");

    if (isset($_POST["cad_show_creator"]))
      setcookie("cad_show_creator", "yes");
    else
      setcookie("cad_show_creator", "no");

    if (isset($_POST["cad_show_message_type"]))
      setcookie("cad_show_message_type", "yes");
    else
      setcookie("cad_show_message_type", "no");

    if (isset($_POST["system_tooltips"]))
      setcookie("system_tooltips", "yes");
    else
      setcookie("system_tooltips", "no");

    if ((isset($_POST["oldpw"]) && $_POST["oldpw"]) ||
        (isset($_POST["newpw"]) && $_POST["newpw"]) ||
        (isset($_POST["confirmpw"]) && $_POST["confirmpw"])) {
      $oldpw = MysqlClean($_POST, 'oldpw', 64);
      $newpw = MysqlClean($_POST, 'newpw', 64);
      $confirmpw = MysqlClean($_POST, 'confirmpw', 64);
      if ($oldpw == "") {
        //setcookie("pwoldmissing", "yes");
        //header('Location: config.php');
          print "Old password is missing.";
          exit;
      }

      $pwcheck = MysqlQuery("SELECT PASSWORD('$oldpw') AS enteredpw, password FROM $DB_NAME.users WHERE username='".$_SESSION['username']."'");
      $rows = mysql_num_rows($pwcheck);
      if ($rows != 1) {
        syslog(LOG_CRITICAL, "Checking [".$_SESSION['username']."] password for change, found $rows rows (expected 1)");
        print "INTERNAL ERROR: Checking password for change in config.php, found $rows rows (expected 1)";
        exit;
      }
      else {
        $answer = mysql_fetch_object($pwcheck);
        if ($answer->password != $answer->enteredpw) {
          print "Old password is incorrect.";
          exit;
          //setcookie("pwoldincorrect", "yes");
        }
        elseif ($newpw == "") {
          print "New password is missing.";
          exit;
          //setcookie("pwnewmissing", "yes");
        }
        elseif ($confirmpw == "") {
          print "New password (confirm) is missing.";
          exit;
          //setcookie("pwconfirmmissing", "yes");
        }
        elseif ($newpw != $confirmpw) {
          print "New passwords do not match.";
          exit;
          //setcookie("pwsdontmatch", "yes");
        }
        else {
          MysqlQuery("UPDATE $DB_NAME.users SET password=PASSWORD('$newpw') WHERE username='".$_SESSION['username']."'");
          //setcookie("pwchanged", "yes");
        }
      }
    }
    header('Location: config.php');
  }
  header_html("Dispatch :: Configuration")
?>
<body vlink="blue" link="blue" alink="cyan">
<? include('include-title.php'); ?>
<table>
<tr>
  <td align="left" width="400">
  <form name="myform" method="post" action="config.php">

<!-- Incidents -->
<br />&nbsp;<b>Incidents Preferences</b><br />
  <table width="350" style="background-color: #dddddd; border: 1px solid gray">
  <tr>
    <td align="right"><input type="checkbox" name="incidents_show_units" <?php
        if (!isset($_COOKIE["incidents_show_units"]) || $_COOKIE["incidents_show_units"] == "yes") print "checked";?>
        value="yes" /></td>
    <td>Show unit availability</td>
  </tr>
  <tr>
    <td align="right"><input type="checkbox" name="incidents_open_only" <?php
        if (!isset($_COOKIE["incidents_open_only"]) || $_COOKIE["incidents_open_only"] == "yes") print "checked"?>
        value="yes" /></td>
    <td>Show open incidents only</td>
  </tr>
  <tr>
    <td align="right"><input type="checkbox" name="incidents_show_creator" <?php
        if (!isset($_COOKIE["incidents_show_creator"]) || $_COOKIE["incidents_show_creator"] == "yes") print "checked"?>
        value="yes" /></td>
    <td>Show incident notes creator</td>
  </tr>
</table>

<!-- Units -->
<br />&nbsp;<b>Units Preferences</b><br />
<table width="350" style="background-color: #dddddd; border: 1px solid gray">
  <tr>
    <td align="right"><input type="checkbox" name="units_color" <?php
    if (!isset($_COOKIE["units_color"]) || $_COOKIE["units_color"] == "yes")
      print "checked"?> value="yes" /></td>
    <td>Color-code units by type</td>
  </tr>
</table>

<!-- Log Viewer -->
<br />&nbsp;<b>Log Viewer Preferences</b><br />
<table width="350" style="background-color: #dddddd; border: 1px solid gray">
  <tr>
    <td align="right"><input type="checkbox" name="cad_show_creator" <?php
    if (!isset($_COOKIE["cad_show_creator"]) ||
               $_COOKIE["cad_show_creator"] == "yes")
      print "checked"?> value="yes" /></td>
    <td colspan=2>Show message creator</td>
  </tr>
  <tr>
    <td align="right"><input type="checkbox" name="cad_show_message_type" <?php
    if (!isset($_COOKIE["cad_show_message_type"]) ||
               $_COOKIE["cad_show_message_type"] == "yes")
      print "checked"?> value="yes" /></td>
    <td colspan=2>Use Message Type field<br /></td>
  </tr>

</table>

<!-- Change Password -->
<br />&nbsp;<b>Change Password</b><br />
<table width="350" style="background-color: #dddddd; border: 1px solid gray">
  <tr><td colspan="2">Current password:</td><td><input type="password" size="15" name="oldpw" /></td></tr>
  <tr><td colspan="3"><hr /></td></tr>
  <tr><td colspan="2">New password:</td><td><input type="password" size="15" name="newpw" /></td></tr>
  <tr><td colspan="2">Confirm new password:</td><td><input type="password" size="15" name="confirmpw" /></td></tr>
</td></tr>
</table>

<!-- System Options -->
<!-- Removed for now...
<br />&nbsp;<b>System Options</b><br />
<table width="350" style="background-color: #dddddd; border: 1px solid gray">
  <tr>
    <td align="right"><input type="checkbox" name="system_tooltips" <?php
    if (!isset($_COOKIE["system_tooltips"]) || $_COOKIE["system_tooltips"] == "yes")
      print "checked"?> value="yes" /></td>
    <td>System Tooltips</td>
  </tr>
</table>
-->

</td>
</tr>
</table>

<br />
<input type="submit" name="saving" value="Save Settings" />
</ul>
</form>
</ul>
</body>
</html>
