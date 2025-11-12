<?php
  $subsys = "admin";
  require_once('session.inc');
  require_once('db-open.php');
  require_once('functions.php');
  require_once('local-dls.php');
  SessionErrorIfReadonly();

  if (isset($DEBUG) && $DEBUG) {
    foreach ($_POST as $key => $value) {
      if ($key == 'password' || $key == 'password2') {
        syslog(LOG_INFO, "\$_POST[$key] = (omitted)");
      }
      else {
        syslog(LOG_INFO, "\$_POST[$key] = $value");
      }
    }
  }
  #
  # Verify access level
  #
  if (!CheckAuthByLevel('edit_report_filters', $_SESSION['access_level'])) {
    syslog(LOG_WARNING, "Report unit filter editing attempted without permissions by user ". $_SESSION['username'] ." level ". $_SESSION['access_level']);
    echo "Access level insufficient for this operation.<br />\n";
    echo "User: " . $_SESSION['username'] . "<br />\n";
    echo "Level: " . $_SESSION['access_level'] . "<br />\n";
    exit;
  }

  $check_password = 0;
  $check_accesslevel = 0;
  $check_accessacl = 0;
  $check_timeout = 0;
  $check_errormsg = "";

  /*  TESTING UI 2013-09-12
   *
  if (isset($_GET["delete"]) && $_GET["delete"]) {
    $cleandelete = MysqlClean($_GET, "delete", 20);
    $levelrow = MysqlQuery("SELECT row_description, row_regexp FROM $DB_NAME.unit_filter_sets WHERE idx='$cleandelete'");
    $levelobj = mysqli_fetch_object($levelrow);
    $username = $levelobj->username;
    if ($_SESSION['access_level'] < $levelobj->access_level ) {
      syslog(LOG_WARNING, "User [".$_SESSION['username']."] (level ".$_SESSION["access_level"].") attempted to delete [$username] (level ".$levelobj->access_level.")!");
      echo "ERROR: Cannot delete users with a higher access level than your own.";
      exit;
    }
    else {
      syslog(LOG_INFO, "Unit filter set [$description] entry [$regexp] was deleted by [".$_SESSION['username']."]");
      MysqlQuery("DELETE FROM $DB_NAME.unit_filter_sets WHERE idx='$cleandelete'");
      header('Location: config-unitfilters.php?action=Deleted&username='.$username);
    }
  }
  elseif (isset($_POST["update"]) && $_POST["update"]) {
  #
  # Submitted a user form to save - check validation.
  #
    if ($DEBUG) syslog (LOG_INFO, "entering post update");
    if (!isset($_POST["password"]) || $_POST["password"]=='') {
      if (isset($_POST['newuser']) && $_POST['newuser']) {
        print "Must set password when adding new user.<br />\n";
        print "<a href=\"config-unitfilters.php?adduser\">Return to form and try again</a><br>\n";
        exit;
      }
    }
    elseif ($_POST["password"] != $_POST["password2"])
    {
      $check_password = 1;
      $check_errormsg .= "Passwords do not match.<br />";
    }
    if ($_POST["username"] == $_SESSION['username'] &&
            $_POST["access_level"] < 10)
    {
      $check_accesslevel = 1;
      $check_errormsg .= "Cannot set own access level to a non-administrator level (less than 10.)<br>";
    }

    if ($check_errormsg != "") {
    # If we did not pass validation, continue to the GET update case, and flag corrections...
      $_GET["edituserid"] = $_POST["id"];
    }
    else {
      if ($DEBUG) syslog (LOG_INFO, "saving user");
      # Save user and return to user listing
      $cleanuser = MysqlClean($_POST, "username", 40);
      $cleanid = MysqlClean($_POST, "id", 40);
      $cleanname = MysqlClean($_POST, "name", 40);
      $tainted_password = $_POST["password"];
      $cleanaccesslevel = MysqlClean($_POST, "access_level", 10);
      $cleanaccessacl = MysqlClean($_POST, "access_acl", 10);
      $cleantimeout = MysqlClean($_POST, "timeout", 10);
      $locked_out = (int)$_POST["locked_out"];
      $change_password = (int)$_POST["change_password"];
      # TODO: form values currently nullify the database default for numerical values

      $levelrow = MysqlQuery("SELECT username, access_level FROM $DB_NAME.users WHERE id='$cleanid'");
      $levelobj = mysqli_fetch_object($levelrow);
      $username = $levelobj->username;
      $pwhash_query_frag = '';
      $hash = '';
      if ($tainted_password != '') {
        // Use modern password_hash() for new/changed passwords
        $hash = password_hash($tainted_password, PASSWORD_DEFAULT);
        if ($hash === false) {
          syslog(LOG_ERR, "Password hashing failed for user [$username]");
          echo "ERROR: Password hashing failed. Please try again.<br>";
          exit;
        }
        $pwhash_query_frag = "password='$hash', ";
      }

      if ($_SESSION['access_level'] < $levelobj->access_level ) {
        syslog(LOG_WARNING, "User [".$_SESSION['username']."] (level ".$_SESSION["access_level"].") attempted to modify [$username] (level ".$levelobj->access_level.")!");
        echo "ERROR: Cannot modify users with a higher access level than your own.<br>";
        exit;

        #TODO: go back to editor rather than exit ignominiously
        $check_errormsg .= "ERROR: Cannot modify users with a higher access level than your own.<br>";
      }
      elseif (isset($_POST["newuser"]) && $_POST['newuser']) {
        if ($_SESSION['access_level'] < $cleanaccesslevel ) {
          syslog(LOG_WARNING, "User [".$_SESSION['username']."] (level ".$_SESSION['access_level'].") attempted to add [$username] with greater access level $cleanaccesslevel!");
          echo "ERROR: Cannot add users with a higher access level than your own.<br>";
          #TODO: go back to editor rather than exit ignominiously
          $check_errormsg .= "ERROR: Cannot add users with a higher access level than your own.<br>";
          exit;

        }
        else {
          syslog(LOG_INFO, "User [$cleanuser] was added by [".$_SESSION['username']."]");
          MysqlQuery("INSERT INTO $DB_NAME.users (username, password, name, access_level, access_acl, timeout, locked_out, change_password) VALUES ('$cleanuser', '$hash', '$cleanname', '$cleanaccesslevel', '$cleanaccessacl', '$cleantimeout', $locked_out, $change_password)");
          header('Location: config-unitfilters.php?moduser='.mysqli_insert_id($link).'&action=Added');
          exit;
        }
      }
      elseif ($cleanid) {
        syslog(LOG_INFO, "User [$cleanuser] was edited by [".$_SESSION['username']."]");
        MysqlQuery("UPDATE $DB_NAME.users SET $pwhash_query_frag  access_level='$cleanaccesslevel', access_acl='$cleanaccessacl', timeout='$cleantimeout', name='$cleanname', locked_out=$locked_out, change_password=$change_password WHERE id='$cleanid'");
        header('Location: config-unitfilters.php?moduser='.$cleanid.'&action=Saved');
        exit;
      }
      else {
        #TODO: error
        exit;
      }
    }
  }

  if (isset($_GET["edit"]) || isset($_GET["adduser"]) || $check_errormsg != "") {
  #
  # Loaded a user to edit (could have edited and come back to this screen)
  #
    if ($DEBUG) syslog (LOG_INFO, "Entering GET edituserid/GET adduser / checkerrmsg");
    $user = array();
    $user['access_level'] = 1;
    $user['timeout'] = 300;
    $user['name'] = '';
    $user['username'] = '';
    $user['name'] = '';
    $user['name'] = '';
    $user['name'] = '';

    if ($_GET["edituserid"]) {
      if ($DEBUG) syslog (LOG_INFO, "entering GET edituserid display");
      $edituserid = MysqlClean($_GET, "edituserid", 20);
      $oneuser = MysqlQuery("SELECT * FROM $DB_NAME.users WHERE id='$edituserid'");
      if (mysqli_num_rows($oneuser) != 1) {
        syslog(LOG_CRITICAL, "Expected 1 row for config-unitfilters.php?edituserid=$edituserid -- got " . mysqli_num_rows($oneuser));
        echo "INTERNAL ERROR: bad number of rows (". mysqli_num_rows($oneuser) . ") for user ID [$edituserid] (expected 1).<p>";
        exit;
      }
      else {
        $user = mysqli_fetch_array($oneuser, MYSQLI_ASSOC);
  # TODO: if any new columns WERE accepted in previous form validation that set check_errormsg, recall and use the changed ones.
      }
    }
    header_html("Dispatch :: Configuration :: Users");

?>
<body vlink="blue" link="blue" alink="cyan">
<?php include('include-title.php'); ?>
<p>
<span style='h1'><b>Editing User Values</b></span>
      <form method="post" action="<?php print $_SERVER["PHP_SELF"]?>">
      <table>
      <tr>
      <?php if (isset($_GET["adduser"])) { ?>

          <td class="cell">New User Login name:</td>
          <td><input size="20"0 type="text" name="username" onChange="this.style.backgroundColor='yellow'" />
          <input type="hidden" name="newuser" value="1" />
          <script language="javascript">document.forms[0].username.focus();</script></td>
      <?php  } else { ?>
          <td class="cell">Editing User</td>
          <td class="cell b">
          <input type="hidden" name="id" value="<?php print $user['id']; ?>" />
          <input type="hidden" name="username" value="<?php print $user['username'];?>" />
          <?php print $user['username'] ?> </td>
      <?php  } ?>

      </tr>
      <tr><td><input type=hidden name="update" value="1"/> </td></tr>
      </tr>
      <tr><td class="cell">Full Name
          <td><input type="text" size="40" name="name"
               value="<?php print MysqlUnClean($user['name']);?>"
               onChange="this.style.backgroundColor='yellow'" />
               </td>
      <?php if (!isset($_GET["adduser"])) {
          echo "<script type=\"text/javascript\">document.forms[0].name.focus();</script>\n";
          }?>
      </tr>
      <tr><td class="cell">Password
          <td><input type="password" size="40" id="password_input" name="password"
              value=""
               onChange="this.style.backgroundColor='yellow'" />
               <?php if ($check_password) echo "<font color=\"red\">*"; ?>
               </td>
      </tr>
      <tr><td class="cell">Password (verify)
          <td><input type="password" size="40" name="password2"
               value=""
               onChange="this.style.backgroundColor='yellow'" />
               <?php if ($check_password) echo "<font color=\"red\">*"; ?>
               </td>
      </tr>
      <tr><td class="cell">Access Level
          <td><input type="text" size="10" name="access_level"
               value="<?php print MysqlUnClean($user['access_level']);?>"
               onChange="this.style.backgroundColor='yellow'" />
               <?php if ($check_accesslevel) echo "<font color=\"red\">*"; ?>
               <span class=text> (1 = normal user; 5 = supervisor; 9 = asst/dep/chief; 10 = system admin)</span>
               </td>
      </tr>
      <tr><td class="cell">Access ACL
          <td><input type="text" size="10" name="access_acl"
               value="<?php print MysqlUnClean($user['access_acl']);?>"
               onChange="this.style.backgroundColor='yellow'" />
               <?php if ($check_accessacl) echo "<font color=\"red\">*"; ?>
               </td>
      </tr>
      <tr><td class="cell">Timeout
          <td><input type="text" size="10" name="timeout"
               value="<?php print MysqlUnClean($user['timeout']);?>"
               onChange="this.style.backgroundColor='yellow'" />
               <?php if ($check_timeout) echo "<font color=\"red\">*"; ?>
               </td>
      </tr>
      <tr><td class="cell">Password expired?
          <td class="cell">Yes<input type="radio" id="change_password" name="change_password" value="1" 
              <?php if ($user['change_password']) print "checked";?> >  &nbsp;&nbsp;
              No<input type="radio" name="change_password" value="0"
              <?php if (!$user['change_password']) print "checked";?> >  &nbsp;&nbsp;
          <span style="color:red; font-weight: bold" id="expireinstrs"></span>
               </td>
      </tr>
      <tr><td class="cell">User locked out?
          <td class="cell">Yes<input type="radio" name="locked_out" value="1" 
              <?php if ($user['locked_out']) print "checked";?> >  &nbsp;&nbsp;
              No<input type="radio" name="locked_out" value="0"
              <?php if (!$user['locked_out']) print "checked";?> 
              onclick="
                alert('Unlocking user: set their password to a temporary value now (will expire on next login).');
                document.getElementById('unlockinstrs').innerHTML = 'Unlocked.  Set a temporary password.';
                document.getElementById('expireinstrs').innerHTML = 'Expired.';
                document.forms[0].change_password[0].checked = true;
                document.getElementById('password_input').focus();
                document.getElementById('password_input').select();
              ">  &nbsp;&nbsp;

          <span style="color:red; font-weight: bold" id="unlockinstrs"></span>
          </td>
      </tr>

      <?php if ($check_errormsg) {
        ?>
        <tr><td colspan="2" style="font-weight: bold">Error:  settings flagged above are invalid, reverting:</td> </tr>
        <tr><td colspan="2" style="color: red"><?php print $check_errormsg;?></td></tr>
        <?php }
        ?>

      </table>
      <input value="Save Changes" type="submit"><input value="Clear Changes" type="reset" />
      <?php if (!isset($_GET["adduser"])) {
          echo "&nbsp;&nbsp;&nbsp;    <a class=button href=\"config-unitfilters.php?delete=".$user['id']."\">Delete This User</a><br>";
          }?>
      </form>
      <p>
<p>
      <a class=button href="config-unitfilters.php">Abort Changes, Go Back To Config::Users</a>
      <a class=button href="admin.php">Abort Changes, Go Back To Config main menu</a>
      </body>
      </html>

      <?php
    exit;
  }
  else {
  #
  # Display list of users
  #
    $moduser = "";
    $action = "";
    if (isset($_GET["moduser"]) && $_GET["moduser"]) {
      $moduser = $_GET["moduser"];
    }
    if (isset($_GET["action"]) && $_GET["action"]) {
      $action = $_GET["action"];
    }
    
   ******************************************************************************************************/

    header_html('Dispatch :: Configuration :: Unit Filter Sets')
?>
<body vlink="blue" link="blue" alink="cyan">
<?php
  include('include-title.php');

?>

  <script type=text/javascript>
  function ShowPane(panename) {
    document.getElementById(panename).style.display = "inline";
  }
  function HidePane(panename) {
    document.getElementById(panename).style.display = "none";
  }

  </script>
<p>
<span style="h1"><b>Unit Filter Sets</b></span><p>
Click to view or modify.

<div style="border: black solid 1px;" >
<?php
    $filters = MysqlQuery("SELECT DISTINCT filter_set_name FROM $DB_NAME.unit_filter_sets ORDER BY filter_set_name");
    while ($filter = mysqli_fetch_object($filters)) {
      print "  <span style=\"display:inline; float: left\" class=\"\"><a href=\"javascript:void(0)\" onclick=\"ShowPane('$filter->filter_set_name');\">" .  MysqlUnClean($filter->filter_set_name) . "</a></span>\n";
      print "  <span style=\"display:none; float: left\" id=\"$filter->filter_set_name\"> ";
      print "      <a href=\"javascript:void(0)\" onclick=\"HidePane('$filter->filter_set_name');\"> [Hide] </a>\n";
      print "  Viewing pane for $filter->filter_set_name <br>";
      print "  Viewing pane 2 for $filter->filter_set_name <br>";
      print "  Viewing pane 3 for $filter->filter_set_name <br>";
      print "  Viewing pane 4 for $filter->filter_set_name <br>";
      print " </span>\n";
      print "<br>\n\n";
    }
?>
</div>
<p>
<div>
<a class=button href="config-unitfilters.php?new">Add New Filter Set</a><p>
<a class=button href="admin.php">Back to Main Configuration Menu</a><p>
</div>
    <?php

  echo "</body>\n</html>\n";
