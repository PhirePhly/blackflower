<?php
  $subsys = "config";
  
  require_once('session.inc');

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

    if ($_POST["cadmode"] == "all") {
      setcookie("cadmode", "all");
    }
    elseif ($_POST["cadmode"] == "hourly") {
      setcookie("cadmode", "hourly");
    }
    else {
      setcookie("cadmode", "last25");
    }
    if (isset($_POST["cad_show_creator"]))
      setcookie("cad_show_creator", "yes");
    else
      setcookie("cad_show_creator", "no");

    if (isset($_POST["cad_show_message_type"]))
      setcookie("cad_show_message_type", "yes");
    else
      setcookie("cad_show_message_type", "no");

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
 
      $pwcheck = MysqlQuery("SELECT PASSWORD('$oldpw') AS enteredpw, password FROM cad.users WHERE username='".$_SESSION['username']."'");
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
          MysqlQuery("UPDATE cad.users SET password=PASSWORD('$newpw') WHERE username='".$_SESSION['username']."'");
          //setcookie("pwchanged", "yes");
        }
      }
    }
    header('Location: config.php');
  }

?> 

<html>
<head>
  <title>Dispatch :: Configuration</title>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA="screen, print">
  
</head>
<body vlink=blue link=blue alink=cyan>
<?php include('include-title.php') ?>

<table>
<tr>
<td align=left width=400>

<h1>User Preferences</h1>
<form name="myform" method="post" action="config.php">
&nbsp; <b>Incidents</b><br>
<table width=350 style="background-color: #dddddd; border: 1px solid gray">
  <tr> 
    <td align=right><input type="checkbox" name="incidents_show_units" <?php 
        if (!isset($_COOKIE["incidents_show_units"]) || $_COOKIE["incidents_show_units"] == "yes") print "checked";?>
        value="yes"></td>
    <td>Show unit availability</td>
  </tr>
  <tr> 
    <td align=right><input type="checkbox" name="incidents_open_only" <?php 
        if (!isset($_COOKIE["incidents_open_only"]) || $_COOKIE["incidents_open_only"] == "yes") print "checked"?> 
        value="yes"></td>
    <td>Show open incidents only</td>
  </tr>
  <tr> 
    <td align=right><input type="checkbox" name="incidents_show_creator" <?php 
        if (!isset($_COOKIE["incidents_show_creator"]) || $_COOKIE["incidents_show_creator"] == "yes") print "checked"?> 
        value="yes"></td>
    <td>Show incident notes creator</td>
  </tr>
</table>
<br>

&nbsp; <b>Units</b><br>
<table width=350 style="background-color: #dddddd; border: 1px solid gray">
  <tr> 
    <td align=right><input type="checkbox" name="units_color" <?php 
    if (!isset($_COOKIE["units_color"]) || $_COOKIE["units_color"] == "yes") 
      print "checked"?> value="yes"></td>
    <td>Color-code units by type</td>
  </tr>
</table>

<br>

&nbsp; <b>Log Viewer</b><br>
<table width=350 style="background-color: #dddddd; border: 1px solid gray">

  <tr> 
    <td align=right><input type="checkbox" name="cad_show_creator" <?php 
    if (!isset($_COOKIE["cad_show_creator"]) || 
               $_COOKIE["cad_show_creator"] == "yes") 
      print "checked"?> value="yes"></td>
    <td>Show message creator</td>
  </tr>
  <tr> 
    <td align=right><input type="checkbox" name="cad_show_message_type" <?php 
    if (!isset($_COOKIE["cad_show_message_type"]) || 
               $_COOKIE["cad_show_message_type"] == "yes") 
      print "checked"?> value="yes"></td>
    <td>Use Message Type field<br></td>
  </tr>

  <tr><td></td></tr>
  <tr><td></td></tr>

  <tr>
    <td align=right>
       <input type="radio" name="cadmode" <?php 
         if (!isset($_COOKIE["cadmode"]) || $_COOKIE["cadmode"] == "last25") print "checked"?> 
         value="last25"></td>
    <td>Display Most Recent 25 Messages</td></tr>
  <tr>
    <td align=right>
       <input type="radio" name="cadmode" <?php 
         if (isset($_COOKIE["cadmode"]) && $_COOKIE["cadmode"] == "hourly") print "checked"?> 
         value="hourly"></td>
    <td>Display Hourly View</td></tr>
  <tr>
    <td align=right>
       <input type="radio" name="cadmode" <?php if (isset($_COOKIE["cadmode"]) && $_COOKIE["cadmode"] == "all") print "checked"?> value="all"></td>
    <td>Display All Log Messages</td>
  </tr>
</table>

<br>

&nbsp; <b>Change Password</b><br>
<table width=350 style="background-color: #dddddd; border: 1px solid gray">
  <tr> <td colspan=2>Current password:</td><td><input type=password size=15 name=oldpw></td></tr>
  <tr><td></td></tr>
  <tr><td></td></tr>
  <tr> <td colspan=2>New password:</td><td><input type=password size=15 name=newpw></td></tr>
  <tr> <td colspan=2>Confirm new password:</td><td><input type=password size=15 name=confirmpw></td></tr>
</td></tr>
</table>

</td>
<?php
if ($_SESSION['access_level'] >= 10) {
 ?>
<td align=left width=400 valign=top>

<table width=350 style="border: 3px ridge blue; padding: 5px; background-color: #dddddd">
<tr><td><h1>Administration</h1></td></tr>
  <tr><td><a href="config-users.php">Edit Users</a></td></tr>
  <tr><td><a href="config-cleardb.php">Archive and Clear Database</a></td></tr>
  <tr><td><font color="gray">Manage Database Archives</font>  (Not developed yet)</td></tr>
</table>
</td>

<?php
}
?>

</tr>
</table>

<br>
<input type="submit" name="saving" value="Save Settings">
</ul>
</form>

</ul>

<?php  include ('include-footer.php') ?>
</body>
</html>


