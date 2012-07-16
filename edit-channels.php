<?php
  $subsys = "admin";
  require_once('session.inc');
  require_once('db-open.php');
  require_once('functions.php');
  require_once('local-dls.php');
  SessionErrorIfReadonly();

  if (isset($DEBUG) && $DEBUG) {
    foreach ($_POST as $key => $value) {
      syslog(LOG_INFO, "\$_POST[$key] = $value");
    }
  }
  #
  # Verify access level
  #
  if (isset($ACCESS_LEVEL_EDITCHANNELS) && $_SESSION['access_level'] < $ACCESS_LEVEL_EDITCHANNELS ||
      !isset($ACCESS_LEVEL_EDITCHANNELS) && $_SESSION['access_level'] < 10) {
    syslog(LOG_WARNING, "Channel editing attempted without permissions by user ". $_SESSION['username'] ." level ". $_SESSION['access_level']);
    echo "Access level insufficient for this operation.<br />\n";
    echo "User: " . $_SESSION['username'] . "<br />\n";
    echo "Level: " . $_SESSION['access_level'] . "<br />\n";
    exit;
  }

  if (isset($_POST["delete_id"]) && $_POST["delete_id"]) {
    $deleteidx = (int)$_POST["delete_id"];
    syslog(LOG_INFO, "Channel id [$deleteidx] was deleted by [".$_SESSION['username']."]");
    MysqlQuery("DELETE FROM $DB_NAME.channels WHERE channel_id='$deleteidx'");
    header("Location: edit-channels.php?modchannel=$deleteidx&action=Deleted");
  }

  elseif (isset($_POST["save_new_channel"])) {
    $channel_name = MysqlClean($_POST, "channel_name", 40);
    $repeater = (int)$_POST['repeater'];
    $available = (int)$_POST['available'];
    $precedence = (int)$_POST['precedence'];
    $notes = MysqlClean($_POST, "notes", 160);
  
    if (strlen(trim($channel_name)) < 1) {
      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\">alert('Error: You must provide a channel name.'); window.location=\"edit-channels.php\"; </SCRIPT></body></html>\n";
      exit;
    } 
    syslog(LOG_INFO, "Channel [$cleanuser] was added by [".$_SESSION['username']."]");
    MysqlQuery("INSERT INTO $DB_NAME.channels (channel_name, repeater, available, precedence, notes) VALUES ('$channel_name', $repeater, $available, $precedence, '$notes')");
    // TODO: better error checking?
    $rid = mysql_insert_id();
    header("Location: edit-channels.php?modchannel=$rid&action=Added");
    exit;
  }

  elseif (isset($_POST["save_channel_id"]) && $_POST["save_channel_id"]) {
    $channel_id = MysqlClean($_POST, "save_channel_id", 40);
    $channel_name = MysqlClean($_POST, "channel_name", 40);
    $repeater = (int)$_POST['repeater'];
    $available = (int)$_POST['available'];
    $precedence = (int)$_POST['precedence'];
    $notes = MysqlClean($_POST, "notes", 160);
  
    if ($channel_id) {
      syslog(LOG_INFO, "Channel [$channel_id] was edited by [".$_SESSION['username']."]");
      MysqlQuery("UPDATE $DB_NAME.channels SET channel_name='$channel_name', repeater=$repeater, available=$available, precedence=$precedence, notes='$notes' WHERE channel_id=$channel_id");
      // TODO: better error checking?
      header("Location: edit-channels.php?modchannel=$channel_id&action=Saved");
      exit;
    }
    else {
      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\">alert('Error, channel ($channel_id) does not exist to save changes.'); window.location=\"edit-channels.php\"; </SCRIPT></body></html>\n";
      exit;
    }
  }

  elseif (isset($_GET['edit_channel_id']) || isset($_GET['add_channel'])) {
    $channel = array();
    if (isset($_GET['add_channel'])) {
      $channel = array('channel_name'=>'New channel name', 'repeater'=>0, 'available'=>1, 'precedence'=>10,'notes'=>'');
    }
    else {  // by definition, edit existing channel id:
      $channel_id = (int)$_GET['edit_channel_id'];
      $channels = MysqlQuery("SELECT * FROM $DB_NAME.channels WHERE channel_id=$channel_id");
      if (mysql_num_rows($channels) != 1) {
        syslog(LOG_CRITICAL, "Expected 1 row for edit-channels.php?edit_channel_id=$channel_id -- got " . mysql_num_rows($channels));
        echo "INTERNAL ERROR: bad number of rows (". mysql_num_rows($channels) . ") for channel ID [$channel_id] (expected 1).<p>";
        exit;
      }
      $channel = mysql_fetch_array($channels, MYSQL_ASSOC);
    }

?>
<body vlink="blue" link="blue" alink="cyan">
<?php //include('include-title.php'); ?>
<p>
<span style='h1'><b>Editing Channel</b></span>
      <form method="post" action="<?php print $_SERVER["PHP_SELF"]?>">
      <table>
      <tr>
      <?php if (isset($_GET["add_channel"])) { ?>

          <td class="cell">New Channel Name</td>
          <td><input size="40" type="text" name="channel_name" />
          <input type="hidden" name="save_new_channel" value="1" />
          <script language="javascript">document.forms[0].channel_name.focus();</script></td>
      <?php  } else { ?>
          <td class="cell">Channel Name</td>
          <td class="cell b">
          <input type="hidden" name="save_channel_id" value="<?php print $channel['channel_id']; ?>" />
          <input size="40" type="text" name="channel_name" value="<?php print $channel['channel_name'];?>" />
      <?php  } ?>

      </tr>
      <tr><td class="cell">Repeater?
          <td class="cell">Yes<input type="radio" id="repeater" name="repeater" value="1" 
              <?php if ($channel['repeater']) print "checked";?> >  &nbsp;&nbsp;
              No<input type="radio" name="repeater" value="0"
              <?php if (!$channel['repeater']) print "checked";?> >  &nbsp;&nbsp;
               </td>
      </tr>
      <tr><td class="cell">Available?
          <td class="cell">Yes<input type="radio" id="available" name="available" value="1" 
              <?php if ($channel['available']) print "checked";?> >  &nbsp;&nbsp;
              No<input type="radio" name="available" value="0"
              <?php if (!$channel['available']) print "checked";?> >  &nbsp;&nbsp;
               </td>
      </tr>
      <tr><td class="cell">Precedence?
      <td class="cell"><input type="text" name="precedence" value="<?php print $channel['precedence'];?>"  />
          </td>
      </tr>
      <tr><td class="cell">Notes
      <td class="cell"><textarea name="notes" rows=3 cols=60><?php print $channel['notes'];?></textarea>

      </table>
      <input value="Save Changes" type="submit"><input value="Clear Changes" type="reset" />
      <?php if (!isset($_GET["add_channel"])) {
          echo "&nbsp;&nbsp;&nbsp;    <button type=\"submit\" name=\"delete_id\" value=\"".$channel['channel_id']."\">Delete This Channel</button><br>";
          }?>
      </form>
      <a class=button href="edit-channels.php">Abort, Return to Channel List</a>
      </body>
      </html>

      <?php
    exit;
  }
  else {
  #
  # Display list of users
  #
    $modchannel = "";
    $action = "";
    if (isset($_GET["modchannel"]) && $_GET["modchannel"]) {
      $modchannel = $_GET["modchannel"];
    }
    if (isset($_GET["action"]) && $_GET["action"]) {
      $action = $_GET["action"];
    }
    header_html('Dispatch :: Configuration :: Users')
?>
<body vlink="blue" link="blue" alink="cyan">
<?php
  //include('include-title.php');
?>

<p>
<span style="h1"><b>Channel Administration</b></span><p>
<table style="border: black solid 1px; background-color: gray" >
<tr>
  <td class="th">Channel Name</td>
  <td class="th">Repeater?</td>
  <td class="th">Available?</td>
  <td class="th">Precedence</td>
  <td class="th">Notes</td>
<?php if ($modchannel) {
    echo "<td class=\"th\">Status</td>\n";
  }
?>
</tr>
<?php
    $channels = MysqlQuery("SELECT * FROM $DB_NAME.channels ORDER BY precedence,channel_name");
    while ($channel = mysql_fetch_object($channels)) {
      echo "<tr>\n";
      echo "  <td class=\"cell bgeee\"><a href=\"edit-channels.php?edit_channel_id=$channel->channel_id\"> " . 
           MysqlUnClean($channel->channel_name) . " </a></td>\n";
      if ($channel->repeater)   echo "  <td class=\"cell bgeee green b\">Yes</td>\n";
      else                      echo "  <td class=\"cell bgeee \">No</td>\n";
      if ($channel->available)  echo "  <td class=\"cell bgeee green b\">Yes</td>\n";
      else                      echo "  <td class=\"cell bgred b\">No</td>\n";
      echo "  <td class=\"cell bgeee\">" . MysqlUnClean($channel->precedence) . "</td>\n";
      echo "  <td class=\"cell bgeee\">" . MysqlUnClean($channel->notes) . "</td>\n";
      if ($modchannel) {
        if ($modchannel == $channel->channel_id) {
          echo "  <td class=\"notice\">$action channel.</td>\n";
        }
        else {
          echo "  <td class=\"cell bgeee\">&nbsp;</td>\n";
        }
      }

      echo "</tr>";
    }
    if ($action == "Deleted") {
      echo "<tr>";
      echo "  <td colspan=\"100%\" class=\"notice\">Deleted channel '".$_GET["modchannel"]."'.</td></tr>";
    }
?>
</table>
<p>
<a class=button href="edit-channels.php?add_channel">Add New Channel</a>
<a class=button href="javascript:if (window.opener){window.opener.location.reload()} self.close();">Done Editing</a>

    <?php
  }

  echo "</body>\n</html>\n";
