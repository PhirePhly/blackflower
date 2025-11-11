<?php
  $subsys = "config";

  require_once('session.inc');
  require_once('functions.php');
  SessionErrorIfReadonly();

  if (isset($_POST["saving"])) {
    if (isset($_POST["incidents_open_only"]))
      setcookie("incidents_open_only", "yes");
    else
      setcookie("incidents_open_only", "no");

    if (isset($_POST["incidents_show_units"]))
      setcookie("incidents_show_units", "yes");
    else
      setcookie("incidents_show_units", "no");

    if (isset($_POST["incidents_hide_units_oos"]))
      setcookie("incidents_hide_units_oos", "yes");
    else
      setcookie("incidents_hide_units_oos", "no");

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
      $tainted_oldpw = $_POST['oldpw'];
      $tainted_newpw1 = $_POST['newpw'];
      $tainted_newpw2 = $_POST['confirmpw'];
      if ($tainted_oldpw == "") {
          print "Old password is missing.";
          exit;
      }

      $pwcheck = MysqlQuery("SELECT password FROM $DB_NAME.users WHERE username='".$_SESSION['username']."'");
      $rows = mysqli_num_rows($pwcheck);
      if ($rows != 1) {
        syslog(LOG_CRITICAL, "Checking [".$_SESSION['username']."] password for change, found $rows rows (expected 1)");
        print "INTERNAL ERROR [config.php] SELECT password found $rows rows (expected 1) for username ".$_SESSION['username'];
        exit;
      }
      else {
        $hash = $t_hasher->HashPassword($tainted_newpw1);
        $answer = mysqli_fetch_object($pwcheck);
        if ($DEBUG && $tainted_newpw1 && $tainted_newpw1 == $tainted_newpw2) {
          print "<!-- Hash of new password is: $hash -->\n\n\n";
        }
        if (!$t_hasher->CheckPassword($tainted_oldpw, $answer->password)) {
          print "Old password is incorrect.";
          exit;
        }
        elseif ($tainted_newpw1 == "") {
          print "New password is missing.";
          exit;
        }
        elseif ($tainted_newpw2 == "") {
          print "New password (confirmation) is missing.";
          exit;
        }
        elseif ($tainted_newpw1 != $tainted_newpw2) {
          print "New password does not match.";
          exit;
        }
        else {
          MysqlQuery("UPDATE $DB_NAME.users SET password='$hash' WHERE username='".$_SESSION['username']."'");
          setcookie("config-changedpw", "yes");
        }
      }
    }
    header('Location: config.php');
  }

  if (isset($_COOKIE['config-changedpw']) && $_COOKIE['config-changedpw'] == 'yes') {
    $config_changedpw = 1;
    setcookie("config-changedpw", "no");
  }
  else {
    $config_changedpw = 0;
  }
  header_html("Dispatch :: Configuration")
?>
<body vlink="blue" link="blue" alink="cyan" onLoad='unitCheckboxState();'>
<?php include('include-title.php'); ?>

<script language="JavaScript">

function unitCheckboxState() {
  if (document.getElementById('incidents_show_units').checked == true) {
    document.getElementById('incidents_hide_units_oos').disabled = false;
  }
  else {
    document.getElementById('incidents_hide_units_oos').disabled = true;
  }
}
</script>

<table>
<tr>
  <td align="left" width="400">
  <form name="myform" method="post" action="config.php">

<!-- Incidents -->
<br />&nbsp;<b>Incidents Preferences</b><br />
  <table width="350" style="background-color: #dddddd; border: 1px solid gray">
  <tr>
    <td align="right"><input type="checkbox" name="incidents_open_only" <?php
        if (!isset($_COOKIE["incidents_open_only"]) || $_COOKIE["incidents_open_only"] == "yes") print "checked"?>
        value="yes" /></td>
    <td colspan=2>Show open incidents only</td>
  </tr>
  <tr>
    <td align="right"><input type="checkbox" name="incidents_show_units" id="incidents_show_units" <?php
        if (!isset($_COOKIE["incidents_show_units"]) || $_COOKIE["incidents_show_units"] == "yes") print "checked";?>
        value="yes" onChange='unitCheckboxState();' /></td>
    <td colspan=2>Show unit availability</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td align="right"><input type="checkbox" name="incidents_hide_units_oos" id="incidents_hide_units_oos" <?php
        if ($_COOKIE["incidents_hide_units_oos"] == "yes") print "checked";?>
        value="yes" /></td>
    <td>Hide Out of Service units</td>
  </tr>
  <tr>
    <td align="right"><input type="checkbox" name="incidents_show_creator" <?php
        if (!isset($_COOKIE["incidents_show_creator"]) || $_COOKIE["incidents_show_creator"] == "yes") print "checked"?>
        value="yes" /></td>
    <td colspan=2>Show incident notes creator</td>
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
<?php
  if ($USE_MESSAGE_TYPE) {
    print "<tr>\n";
    print "  <td align=\"right\"><input type=\"checkbox\" name=\"cad_show_message_type\" ";
    if (!isset($_COOKIE["cad_show_message_type"]) ||
               $_COOKIE["cad_show_message_type"] == "yes")
      print "checked ";
    print "value=\"yes\" /></td>\n";
    print "  <td colspan=2>Use Message Type field<br /></td>\n";
    print "</tr>\n";
  }
?>
</table>

<!-- Change Password -->
<br />&nbsp;<b>Change Password</b><br />
<table width="350" style="background-color: #dddddd; border: 1px solid gray">
  <tr><td colspan="2">Current password:</td><td><input type="password" size="15" name="oldpw" value=""/></td></tr>
  <tr><td colspan="3"><hr /></td></tr>
  <tr><td colspan="2">New password:</td><td><input type="password" size="15" name="newpw" /></td></tr>
  <tr><td colspan="2">Confirm new password:</td><td><input type="password" size="15" name="confirmpw" /></td></tr>
</td></tr>
</table>
<?php
  if ($config_changedpw) {
    print "&nbsp; &nbsp; &nbsp; <font color=purple>Password changed.";
  }
?>

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
