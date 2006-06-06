<?php
  $subsys = "config";
  require_once('session.inc');
  require_once('db-open.php');
  
  #
  # Verify access level
  #
  if ($_SESSION['access_level'] < 10) {
    syslog(LOG_WARNING, "User editing attempted without permissions by user ". $_SESSION['username'] ." level ". $_SESSION['access_level']);
    echo "Access level insufficient for this operation.<br>\n";
    echo "User: " . $_SESSION['username'] . "<br>\n";
    echo "Level: " . $_SESSION['access_level'] . "<br>\n";
    exit;
  }

  $check_password = 0;
  $check_accesslevel = 0;
  $check_accessacl = 0;
  $check_timeout = 0;
  $check_errormsg = "";

  if (isset($_GET["delete"]) && $_GET["delete"]) {
    $cleandelete = MysqlClean($_GET, "delete", 20);
    $levelrow = MysqlQuery("SELECT username, access_level FROM cad.users WHERE id='$cleandelete'");
    $levelobj = mysql_fetch_object($levelrow);
    $username = $levelobj->username;
    if ($_SESSION['access_level'] < $levelobj->access_level ) {
      syslog(LOG_WARNING, "User [".$_SESSION['username']."] (level ".$_SESSION["access_level"].") attempted to delete [$username] (level ".$levelobj->access_level.")!");
      echo "ERROR: Cannot delete users with a higher access level than your own.";
      exit;
    }
    else {
      syslog(LOG_INFO, "User [$username] was deleted by [".$_SESSION['username']."]");
      MysqlQuery("DELETE FROM cad.users WHERE id='$cleandelete'");
      header('Location: config-users.php?action=Deleted&username='.$username);
    }
  }
  elseif (isset($_POST["edituser"]) && $_POST["edituser"]) {
  #
  # Submitted a user form to save - check validation.
  #
    if (!isset($_POST["password"]) ||
        $_POST["password"] != $_POST["password2"] ||
        $_POST["password"] == "")
    {
      $check_password = 1;
      $check_errormsg .= "Passwords do not match.<br>";
    }
    if ($_POST["edituser"] == $_SESSION['username'] &&
            $_POST["access_level"] < 10)
    {
      $check_accesslevel = 1;
      $check_errormsg .= "Cannot set own access level to a non-administrator level (less than 10.)<br>";
    }

    if ($check_errormsg != "") {
    # If we did not pass validation, continue to the GET edituser case, and flag corrections...
      $_GET["edituser"] = $_POST["edituser"];
    }
    else {
      # Save user and return to user listing
      $cleanuser = MysqlClean($_POST, "edituser", 40);
      $cleanid = MysqlClean($_POST, "id", 40);
      $cleanname = MysqlClean($_POST, "name", 40);
      $cleanpassword = MysqlClean($_POST, "password", 64);
      $cleanaccesslevel = MysqlClean($_POST, "access_level", 10);
      $cleanaccessacl = MysqlClean($_POST, "access_acl", 10);
      $cleantimeout = MysqlClean($_POST, "timeout", 10);
      # TODO: form values currently nullify the database default for numerical values

      $levelrow = MysqlQuery("SELECT username, access_level FROM cad.users WHERE id='$cleanid'");
      $levelobj = mysql_fetch_object($levelrow);
      $username = $levelobj->username;
      if ($_SESSION['access_level'] < $levelobj->access_level ) {
        syslog(LOG_WARNING, "User [".$_SESSION['username']."] (level ".$_SESSION["access_level"].") attempted to modify [$username] (level ".$levelobj->access_level.")!");
        echo "ERROR: Cannot modify users with a higher access level than your own.<br>";
        exit;

        #TODO: go back to editor rather than exit ignominiously
        $check_errormsg .= "ERROR: Cannot modify users with a higher access level than your own.<br>";
      }
      elseif ($_POST["adduser"]) {
        if ($_SESSION['access_level'] < $cleanaccesslevel ) {
          syslog(LOG_WARNING, "User [".$_SESSION['username']."] (level ".$_SESSION['access_level'].") attempted to add [$username] with greater access level $cleanaccesslevel!");
          echo "ERROR: Cannot add users with a higher access level than your own.<br>";
          exit;

          #TODO: go back to editor rather than exit ignominiously
          $check_errormsg .= "ERROR: Cannot add users with a higher access level than your own.<br>";
        }
        else {
          syslog(LOG_INFO, "User [$cleanuser] was added by [".$_SESSION['username']."]");
          MysqlQuery("INSERT INTO cad.users (username, password, name, access_level, access_acl, timeout) VALUES ('$cleanuser', PASSWORD('" . $cleanpassword . "'), '$cleanname', '$cleanaccesslevel', '$cleanaccessacl', '$cleantimeout')");
          header('Location: config-users.php?moduser='.mysql_insert_id().'&action=Added');
        }
      }
      elseif ($cleanid) {
        syslog(LOG_INFO, "User [$cleanuser] was edited by [".$_SESSION['username']."]");
        MysqlQuery("UPDATE cad.users SET password=PASSWORD('" . $cleanpassword . "'), access_level='$cleanaccesslevel', access_acl='$cleanaccessacl', timeout='$cleantimeout', name='$cleanname' WHERE id='$cleanid'");
        header('Location: config-users.php?moduser='.$cleanid.'&action=Saved');
      }
      else {
        #TODO: error
        exit;
      } 
    }
  }
  if (isset($_GET["edituser"]) || isset($_GET["adduser"]) || $check_errormsg != "") {
  #
  # Loaded a user to edit
  #
    $user = "";
    $user->access_level = 1;
    $user->timeout = 300;

    if ($_GET["edituser"]) {
      $edituser = MysqlClean($_GET, "edituser", 20);
      $oneuser = MysqlQuery("SELECT * FROM users WHERE id='$edituser'");
      if (mysql_num_rows($oneuser) != 1) {
        syslog(LOG_WARNING, "Expected 1 row for config-users.php?edituser=$edituser -- got " . mysql_num_rows($oneuser));
        echo "System error, unexpected number of users from database: " . mysql_num_rows($oneuser) . "<p>";
        exit;
      }
      else {
        $user = mysql_fetch_object($oneuser);
  # TODO: if any new columns WERE accepted in previous form validation that set check_errormsg, recall and use the changed ones.
      }
    }

      ?>
<HTML>
<HEAD>
  <TITLE>Dispatch :: Configuration :: Users</title>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA=screen>
</head>
<body vlink=blue link=blue alink=cyan>
<?php include('include-title.php') ?>
<p>
<span style=h1><b>Editing User Values</b></span>
      <form method=post action="<?php print $_SERVER["PHP_SELF"]?>">
      <table style="border: black groove 1px"><tr><td>
      <table>
      <tr>
      <?php if (isset($_GET["adduser"])) { ?>

          <td class="message">New User Login name:</td>
          <td><input size=40 type=text name=edituser onChange="this.style.backgroundColor='yellow'">
          <input type=hidden name=adduser value="1">
          <script language="javascript">document.forms[0].edituser.focus();</script></td>
      <?php  } else { ?>
          <td class="th">Editing User</td>
          <td class="th">
          <input type=hidden name=id value="<?php print $user->id ?>">
          <input type=hidden name=edituser value="<?php print $user->username?>">
          <?php print $user->username ?> </td>
      <?php  } ?>

      <tr><td class="message">Full Name  
          <td><input type=text size=40 name=name 
               value="<?php print $user->name ?>" 
               onChange="this.style.backgroundColor='yellow'">
               </td>
      <?php if (!isset($_GET["adduser"])) { 
          echo "<script language=\"javascript\">document.forms[0].name.focus();</script>\n";
          }?>
      <tr><td class="message">Password   
          <td><input type=password size=40 name=password 
              value="<?php print $user->password ?>"
               onChange="this.style.backgroundColor='yellow'">
               <?php if ($check_password) echo "<font color=red>*"; ?>
               </td>
      <tr><td class="message">Password (verify)  
          <td><input type=password size=40 name=password2 
               value="<?php print $user->password   ?>"
               onChange="this.style.backgroundColor='yellow'">
               <?php if ($check_password) echo "<font color=red>*"; ?>
               </td>
      <tr><td class="message">Access Level   
          <td><input type=text size=10 name=access_level 
               value="<?php print $user->access_level   ?>"
               onChange="this.style.backgroundColor='yellow'">
               <?php if ($check_accesslevel) echo "<font color=red>*"; ?>
               </td>
      <tr><td class="message">Access ACL     
          <td><input type=text size=10 name=access_acl 
               value="<?php print $user->access_acl   ?>"
               onChange="this.style.backgroundColor='yellow'">
               <?php if ($check_accessacl) echo "<font color=red>*"; ?>
               </td>
      <tr><td class="message">Timeout   
          <td><input type=text size=10 name=timeout 
               value="<?php print $user->timeout   ?>"
               onChange="this.style.backgroundColor='yellow'">
               <?php if ($check_timeout) echo "<font color=red>*"; ?>
               </td>
      </tr>

      <?php if ($check_errormsg) {
        ?>
        <tr><td colspan=2 style="font-weight: bold">Error:  settings flagged above are invalid, reverting:</td> </tr>
        <tr><td colspan=2 style="color: red"><?php echo $check_errormsg ?></td> </tr>
        <?php }
        ?>

      </table>
      </td></tr></table>
      <input value="Save Changes" type=submit><input value="Clear Changes" type=reset>
      <?php if (!isset($_GET["adduser"])) { 
          echo "&nbsp;&nbsp;&nbsp;    <a href=\"config-users.php?delete=".$user->id."\">Delete This User</a><br>";
          }?>
      </form>
      <p>
      <a href="config-users.php">Abort Changes, Go Back To Config::Users</a><br>
      <a href="config.php">Abort Changes, Go Back To Config main menu</a><br>
      <p>
      <?php include('include-footer.php') ?>
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

?>
<HTML>
<HEAD>
  <TITLE>Dispatch :: Configuration :: Users</title>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA=screen>
</head>
<body vlink=blue link=blue alink=cyan>
<?php include('include-title.php') ?>

<p>
<span style=h1><b>User Administration</b></span>
<table style="border: blue ridge 3px; background-color: gray" >
<tr>
  <td class=th>Login</td>
  <td class=th>Name</td>
  <td class=th>Access Level</td>
  <td class=th>Access ACL</td>
  <td class=th>Timeout</td>
  <?php if ($moduser) {
    echo "<td class=th>Status</td>\n";
  }
  ?>
</tr>
<?php
    $userlist = MysqlQuery("SELECT * FROM users ORDER BY username");
    while ($user = mysql_fetch_object($userlist)) {
      echo "<tr><td class=message><a href=\"config-users.php?edituser=$user->id\">" . $user->username . "</a></td>\n";
      echo "<td class=message>" . $user->name . "</td>\n";
      echo "<td class=message>" . $user->access_level . "</td>\n";
      echo "<td class=message>" . $user->access_acl . "</td>\n";
      echo "<td class=message>" . $user->timeout . "</td>\n";
      if ($moduser) {
        if ($moduser == $user->id) {
          echo "<td class=notice>$action user.</td>\n";
        }
        else {
          echo "<td class=message>&nbsp;</td>\n";
        }
      }
      
      echo "</tr>\n\n";
    }
    if ($action == "Deleted") {
      echo "<tr><td colspan=100% class=notice>Deleted user '".$_GET["username"]."'.</td></tr>";
    }
?>
</table>
<p>
<a href="config-users.php?adduser">Add New User</a><p>
<a href="config.php">Back to Main Configuration Menu</a>
      <p>
      <?php include('include-footer.php') ?>
    <?php
  }

  echo "</body></html>";
