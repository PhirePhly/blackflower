<?php
  $subsys="unit";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');
  SessionErrorIfReadonly();

  if (isset($_POST['unit'])) {
      $unit = strtoupper(MysqlClean($_POST,'unit',20));
      // TODO: validate data.
      if (isset($_POST['status'])) $status = MysqlClean($_POST,'status',30); else $status="In Service";
      // if (isset($_POST['status_comment'])) $status_comment = MysqlClean($_POST,'status_comment',255); else $status_comment="";
      if (isset($_POST['type'])) $type = MysqlClean($_POST,'type',20); else $type="Unit";
      if (isset($_POST['assignment'])) $assignment = MysqlClean($_POST,'assignment',20); else $assignment="";
      if (isset($_POST['role'])) $role = MysqlClean($_POST,'role',20); else $role="Other";
      if (isset($_POST['location'])) $location = MysqlClean($_POST,'location',100); else $location="";
      if (isset($_POST['notes'])) $notes = MysqlClean($_POST,'notes',100); else $notes="";
      if (isset($_POST['personnel'])) $personnel = MysqlClean($_POST,'personnel',100); else $personnel="";
  }

  if (isset($_POST['saveunit']) || isset($_POST['saveunit_closewin'])) {
    if (isset($_POST['new-unit-entered'])) {
      $unit = strtoupper(MysqlClean($_POST,'unit',20));
      $pattern = "/[\\/[\]'!@#$\^%&*()+=,;:{}|<>~`?\"]/";
      $replacement = "";
      if ($unit == "") {
        die('Cannot create unit with empty name.');
      }
      if (preg_match($pattern, $unit)) {
        die('Bad characters in name: '.$unit. "\n  Only letters, numbers, space, dash or underscore are valid characters.\n  Use your browser Back feature to resolve the problem and try again.");
      }
      // update status
      if ($personnel != "")
        MysqlQuery("INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Unit created - personnel: $personnel')");
      else
        MysqlQuery("INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Unit created.')");

      syslog(LOG_INFO, $_SESSION['username'] . " created unit [$unit]");
      // update units
      // TODO: sanity check $unit input characters here?
      MysqlQuery("INSERT INTO units (unit, status, status_comment, type, role, personnel, update_ts, assignment, personnel_ts, location, location_ts, notes, notes_ts) VALUES ('$unit', '$status', '$status_comment', '$type', '$role', '$personnel', NOW(), '$assignment', NOW(), '$location', NOW(), '$notes', NOW())");
    }

    else {
      // update status
      if ($_POST['status'] <> $_POST['previous_status']) {
        $fragment = "status='$status', status_comment='Status changed to: $status', update_ts=NOW(),";

        if ($_POST['previous_status'] <> "")
          MysqlQuery("INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Status change: $status (was: ".MysqlClean($_POST, 'previous_status', 200).")')");
        else
          MysqlQuery("INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Status change: $status')");

        if ($_POST['previous_status'] == 'Attached to Incident') {
          MysqlQuery("UPDATE incident_units SET cleared_time=NOW() WHERE unit='$unit' AND cleared_time IS NULL");
        }
      }
      else {
        $fragment="";
      }

      // update location
      // not implimented yet

      // update notes
      // not implimented yet 

      // update personnel
      if ($_POST['personnel'] <> $_POST['previous_personnel']) {
        MysqlQuery("INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Personnel change logged: $personnel')");
      }

      syslog(LOG_INFO, $_SESSION['username'] . " edited unit [$unit]");
      // update units
      MysqlQuery("UPDATE units SET $fragment type='$type', role='$role', assignment='$assignment', location='$location', notes='$notes', personnel='$personnel' WHERE unit='$unit'");
    }

    if (isset($_POST['saveunit_closewin'])) {
      print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
      die("(Error: JavaScript not enabled or not present) Action completed. Close this window to continue.");
    }
    else {
      header("Location: edit-unit.php?unit=".$_POST['unit']);
      exit;
    }
  }

  elseif (isset($_POST["abortdelete"])) {
     // Noop, just reload
  }
  elseif (isset($_POST["deleteunit"])) {
    if (isset($_POST['deleteforsure'])) {
      syslog(LOG_INFO, $_SESSION['username'] . ' deleted unit [' . $_POST['unit'] . ']');
      MysqlQuery("DELETE FROM units WHERE unit='".MysqlClean($_POST,"unit",20)."'");
      MysqlQuery("INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Unit deleted.')");
      print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
      die("(Error: JavaScript not enabled or not present) Action completed. Close this window to continue.");
    }
    else {
      $_GET['unit'] = MysqlClean($_POST,'unit',20);
      $unit = $_GET['unit'];
      $newunit = 0;
      $unitresult = MysqlQuery("SELECT * from units where unit = '$unit'");
      $unitline = mysql_fetch_array($unitresult, MYSQL_ASSOC) or die ("Unit $unit not found in table!");
       mysql_free_result($unitresult);
    }
  }
  elseif (isset($_GET["new-unit"])) {
    $unitline["unit"] = "";
    $unit = "";
    $newunit = 1;
    $unitline["status"] = "In Service";
    $unitline["role"] = "Other";
    $unitline["type"] = "Unit";
    $unitline["assignment"] = "";
    $unitline["status_comment"] = "";
    $unitline["personnel"] = "";
    $unitline["update_ts"] = "";
  }
  elseif (isset($_GET["unit"])) {
    $unit = MysqlClean($_GET,"unit",20);
    $newunit = 0;
    $unitresult = MysqlQuery("SELECT * from units where unit = '$unit'");
    $unitline = mysql_fetch_array($unitresult, MYSQL_ASSOC) or die ("unit not found in table");
     mysql_free_result($unitresult);
  }
  elseif (isset($_POST['add_pageout'])) {
    $newval = MysqlClean($_POST, 'newpageout', 20);
    if ($newval != '0') {
      syslog(LOG_INFO, $_SESSION['username'] . " added page-out of pager ID $newval to unit [$unit]");
      MysqlQuery("INSERT INTO unit_incident_paging (unit, to_person_id) VALUES ('$unit', $newval)");
    }
    header("Location: edit-unit.php?unit=".$_POST['unit']);
    exit;
  }
  elseif (isset($_POST['pageunit']) &&              # manual unit paging
          isset($DB_PAGING_NAME) &&
          isset($USE_PAGING_LINK) && $USE_PAGING_LINK) {

    $paginglink = mysql_connect($DB_PAGING_HOST, $DB_PAGING_USER, $DB_PAGING_PASS) 
      or die("Could not connect : " . mysql_error());

    $ipaddr = $_SERVER['REMOTE_ADDR'];
    $to_person_id = MysqlClean($_POST,'to_person_id',30);
    $unit = MysqlClean($_POST,'unit',40);
    $pagetext = MysqlClean($_POST,'pagetext',80);
    $message = "[CAD] FR " . $_SESSION['username'] . " TO $unit: " . $_POST['pagetext'];
    $success = 1; // unless overridden below:

    if (strlen($message) >= 128) {
    # TODO: try to work around this limitation?  reimplement part of paging... or wait for API?
      $message = substr($message, 0, 127);
    }

    # TODO 1.7: replace this with api call
    #
    
    if (!mysql_query("INSERT into $DB_PAGING_NAME.batches (from_user_id, from_ipaddr, orig_message, entered) ".
                     " VALUES (0, '$ipaddr', '$message', NOW() )", $paginglink) || 
        mysql_affected_rows() != 1) {
      syslog(LOG_WARNING, "Error inserting row into database $DB_PAGING_NAME.batches as [$DB_PAGING_HOST/$DB_PAGING_USER]");
      $success = 0;
    }
    else {
      $batch_id = mysql_insert_id();
   
      if (!mysql_query("INSERT into $DB_PAGING_NAME.messages (from_user_id, to_person_id, message) VALUES ".
                       "(0, $to_person_id, '$message')", $paginglink) ||
            mysql_affected_rows() != 1) {
        syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.messages as [$DB_PAGING_HOST/$DB_PAGING_USER]");
        $success = 0;
      }
      $msg_id = mysql_insert_id();
        
      if (!mysql_query("INSERT into $DB_PAGING_NAME.batch_messages (batch_id, msg_id) VALUES ".
                          "($batch_id, $msg_id)", $paginglink) ||
          mysql_affected_rows() != 1) {
        syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.batch_messages as [$DB_PAGING_HOST/$DB_PAGING_USER]");
        $success = 0;
      }

      if (!mysql_query("INSERT into $DB_PAGING_NAME.send_queue (status, msg_id, queued) VALUES ".
                          "('Queued', $msg_id, NOW())", $paginglink) ||
          mysql_affected_rows() != 1) {
        syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.send_queue as [$DB_PAGING_HOST/$DB_PAGING_USER]");
        $success = 0;
      }
    }
    if ($success) {
      syslog(LOG_INFO, $_SESSION['username'] . " sent a manual page to unit [$unit], person_id [$to_person_id].");
    }
    mysql_close($paginglink);

    header("Location: edit-unit.php?unit=".$_POST['unit']."&pagesent=".$success);
    exit;
  }

  // TODO: In future, replace this handler when you replace the method
  else {
    $action = 0;
    foreach (array_keys($_POST) as $postkey) {
      if (preg_match("/delete_pageout_(\d+)_(\d+)/", $postkey, $matches)) {
        syslog(LOG_INFO, $_SESSION['username'] . ' deleted page-out of pager ID ' . $matches[2] . ' from unit [' . $_POST['unit'].']');
        MysqlQuery("DELETE FROM unit_incident_paging WHERE row_id=" . $matches[1] );
        if (mysql_affected_rows() != 1) {
          syslog(LOG_WARNING, "Error while deleting row_id " . $matches[1] . " from unit_incident_paging");
        }
        $action = 1;
        header("Location: edit-unit.php?unit=".$_POST['unit']);
        exit;
      }
    }
    // If no row was found in the pattern match case
    if (!$action) {
      die ('Unknown options to edit-unit.php page load.');
    }
  }


  header_html('Dispatch :: Unit Details');

  if (isset($_POST["deleteunit"]) || isset($_POST['deleteforsure']))
    $disabledp = ' disabled ';
  else 
    $disabledp = '';
/*-------------------------------------------------------------------------*/
?>
<body vlink="blue" link="blue" alink="cyan">
<form name="myform" action="edit-unit.php" method="post">

<!-- Begin Outer Table -->
<table cellspacing="3" cellpadding="0">

<!-- Begin Outer Table Row 1 -->
<tr>
<td colspan="3" bgcolor="darkblue" class="text">

  <!-- Title Bar Table -->
  <table width=100%>
  <tr>
  <td><font color="white" size="+1"><b><?php if (!$newunit) print "Unit: $unit"; else print "Creating a New Unit"; ?></b></font></td>
  </tr>
  </table>

</td>
</tr>

<tr>
<td colspan="3" bgcolor="#bbbbbb">

  <!-- Begin Unit Form Outer Table -->
  <table cellpadding="0" cellspacing="0" border="1" width="100%">
  <tr>
  <td>

    <!-- Begin Unit Form Inner Table -->
    <table cellpadding="2" cellspacing="0" width="100%">

    <!-- Begin Unit Form Row: Unit Name -->
    <?php
      if ($newunit) {
        print "<tr>";
        print "<td class=\"label\" align=\"right\"><b>Unit name</b></td>\n";
        print "<td colspan=\"5\" class=\"text\"><input type=\"text\" style=\"text-transform: uppercase\" maxlength=\"20\" name=\"unit\"><input type=\"hidden\" name=\"new-unit-entered\"></td>\n";
        print "</tr>\n";
      }
      else {
        print "<input type=\"hidden\" name=\"unit\" value=\"".$unit."\" />\n";
      }
    ?>

    <!-- Begin Unit Form Row: Branch / Type / Assignment -->
    <tr>
    <td class="label" align="right">B<u>r</u>anch</td>
    <td class="text" width="100%">
    <label for="role" accesskey="role">
    <select name="role" <?php print $disabledp ?> id="role">
    <?php
      $roles = MysqlQuery("SELECT role FROM unit_roles");
      print "<option value=\"\"></option>\n";
      while ($role_row = mysql_fetch_object($roles)) {
        $role = $role_row->role;
        if ($unitline["role"] == $role) 
          print "<option selected value=\"$role\">$role</option>\n";
        else 
          print "<option value=\"$role\">$role</option>\n";

      }
    ?>
    </select>
    </label>
    </td>

    <td class="label" align="right">T<u>y</u>pe</td>
    <td class="text" width="100%">
    <label for="type" accesskey="y">
    <select name="type" <?php print $disabledp ?> id="type">
    <?php
      $avail_types = array('Unit', 'Individual', 'Generic');
      if (array_search($unitline["type"], $avail_types) === FALSE)
        print "<option selected value=\"\">(none)</option>\n";
      foreach ($avail_types as $type) {
        print "<option ";
        if ($unitline["type"] == $type)
          print "selected ";
        print "value=\"$type\">$type</option>\n";
      }
    ?>

    </select>
    </label>
    </td>

    <td class="label" align="right"><u>A</u>ssignment</td>
    <td class="text" width="100%">
    <label for="assignment" accesskey="a">
    <select name="assignment" <?php print $disabledp ?> id="assignment">
    <?php
      $avail_asses = MysqlQuery('SELECT * FROM unit_assignments');

      print "<option value=\"\">(none)</option>\n";
      while ($avail_assignment = mysql_fetch_object ($avail_asses)) {
        print "<option ";
        if ($unitline["assignment"] == $avail_assignment->assignment)
          print "selected ";
        print "value=\"".$avail_assignment->assignment."\">".
              $avail_assignment->description."</option>\n";
      }
    ?>
    </select>
    </td>
    </tr>

    <!-- Begin Unit Form Row: Spacer -->
    <tr>
    <td colspan="6"></td>
    </tr>

    <!-- Begin Unit Form Row: Status -->
    <tr>
    <td class="label" align="right"><u>S</u>tatus</td>
    <td class="text" width="100%">
    <label for="status" accesskey="s">
    <select name="status" <?php print $disabledp ?> id="status">
    <?php
      $statusset=0;
      $statusresult = MysqlQuery("SELECT * from status_options");
      while ($line = mysql_fetch_array($statusresult, MYSQL_ASSOC)) {
        echo "        <option ";
        if (!strcmp($line["status"], $unitline["status"])) {
          $statusset=1;
          echo "selected ";
        }
        echo "value=\"". MysqlUnClean($line["status"])."\">". $line["status"]."</option>\n";
      }
      if (!$statusset) {
        echo "        <option selected value=\"\">\n";
      }
      mysql_free_result($statusresult);
    ?>
    </select>
    </label>
    <input type="hidden" name="previous_status" value="<?php print MysqlUnClean($unitline["status"]);?>" />
    </td>

    <td colspan="2">&nbsp;</td>

    <td class="label" align="right">Updated</td>
    <td class="text"><?php print dls_utime($unitline["update_ts"]);?></td>
    </tr>

    <!-- Begin Unit Form Row: Status Redux -->
    <tr>
    <td>&nbsp;</td>
    <td class="label" colspan="5" nowrap>Comment: <?php print MysqlUnClean($unitline["status_comment"])?></td>
    </tr>

    <!-- Begin Unit Form Row: Spacer -->
    <tr>
    <td colspan="6"></td>
    </tr>

    <!-- Begin Unit Form Row: Last Location -->
    <tr>
    <td class="label" align="right" nowrap>Last <u>L</u>ocation</td>
    <td class="text" colspan="5">
    <label for="location" accesskey="l">
    <input name="location" id="location" <?php print $disabledp ?> type="text" maxlength="250" size="80"
     value="<?php print MysqlUnClean($unitline["location"])?>" />
    </label>
    <input type="hidden" name="previous_location" value="<?php print $unitline["location"];?>" />
    </td>
    </tr>

    <!-- Begin Unit Form Row: Personnel -->
    <tr>
    <td class="label" align="right"><u>P</u>ersonnel</td>
    <td class="text" colspan="5">
    <label for="personnel" accesskey="p">
    <input name="personnel" id="personnel" <?php print $disabledp ?> type="text" maxlength="250" size="80"
     value="<?php print MysqlUnClean($unitline["personnel"]);?>" />
    </label>
    <input type="hidden" name="previous_personnel" value="<?php print $unitline["personnel"];?>" />
    </td>
    </tr>

    <!-- Begin Unit Form Row: Notes -->
    <tr>
    <td class="label" align="right">N<u>o</u>tes</td>
    <td class="text" colspan="5">
    <label for="notes" accesskey="o">
    <input name="notes" id="notes" <?php print $disabledp ?> type="text" maxlength="250" size="80"
     value="<?php print MysqlUnClean($unitline["notes"])?>" />
    </label>
    <input type="hidden" name="previous_notes" value="<?php print $unitline["notes"];?>" />
    </td>
    </tr>


<?php
  // Begin Unit Form Row: Generic Notice
  if ($unitline["type"] == "Generic")
    print "<tr>\n<td class=\"label\" colspan=\"6\"><b>Note: As a generic unit, multiple instances of this unit may be simultaneously assigned to separate incidents.</b></td></tr>"

?>

    <!-- Begin Unit Form Row: Buttons -->
    <tr>
    <td>&nbsp;</td>
    <td class="label" colspan="5">
    <button type="submit" id="saveunit" name="saveunit" <?php print $disabledp ?> tabindex="41" accesskey="1"><u>1</u>  Save</button>
    <button type="submit" id="saveunit_closewin" name="saveunit_closewin" <?php print $disabledp ?> tabindex="42" value="Save & Return" accesskey="2"><u>2</u>   Save & Return</button>
    <button type="button" id="cancel" name="cancel" <?php print $disabledp ?> tabindex="43" accesskey="3"
     onClick='if (window.opener){window.opener.location.reload()} self.close()'><u>3</u>  Cancel</button>
<?php if (isset($_POST["deleteunit"]) || isset($_POST['deleteforsure'])) {
?>
    <button type="submit" id="abortdelete" name="abortdelete" tabindex="44" accesskey="4"><u>4</u>  Abort Deletion</button>
<?php
       }
?>
    <noscript><b>Warning</b>: Javascript disabled. Close popup to cancel changes.</noscript>
    </td>
    </tr>

<?php
  // Begin Unit Form Row: If Existing Unit
  if(!$newunit) {
    print "<tr>\n";
    print "<td colspan=\"6\" class=\"label\" align=\"right\">";
    if (isset($_POST["deleteunit"])) {
      print "<input type=\"checkbox\" name=\"deleteforsure\" />&nbsp;";
      print "<span style=\"color: red; font-weight: bold; text-decoration: blink;\">Confirm</span> " .
            "you want to delete this unit?&nbsp;&nbsp;";
    }
    print "<input type=\"submit\" id=\"deleteunit\" name=\"deleteunit\" value=\"Delete Unit\" /></td>\n";
    print "</tr>\n";
  }
?>

    </table>
    <!-- End Unit Form Inner Table -->

  </td>
  </tr>
  </table>
  <!-- End Unit Form Outer Table -->

</td>
</tr>
<!-- End Outer Table Row 1 -->

<!-- Outer Table Spacer Row -->
<tr><td></td></tr>

  <?php 
  if (isset($USE_PAGING_LINK) && $USE_PAGING_LINK) {
    if ($newunit) {
      print "<tr><td colspan=3>Save unit in order to edit auto-paging links.</td></tr>";
    }
    else {
  ?>
<!-- Begin Outer Table Row 2 -->
<tr>
<td colspan="3" bgcolor="#bbbbbb">
  <!-- Begin Page Now Form Outer Table -->
  <table cellpadding="0" cellspacing="0" border="1" width="100%">
  <tr>
  <td>

    <!-- Begin Page Now Form Inner Table -->
    <table cellpadding="2" cellspacing="0" width="100%">

    <?php
    $paginglink = mysql_connect($DB_PAGING_HOST, $DB_PAGING_USER, $DB_PAGING_PASS) 
      or die("Could not connect : " . mysql_error());
    $querytext = "SELECT person_id,name FROM $DB_PAGING_NAME.people ".
      " WHERE UPPER(REPLACE(name, ' ', '')) = '" . strtoupper(str_replace(' ', '', $unit)) . "'";
    $pager_query = mysql_query($querytext, $paginglink) or die ("<b>Problem with query: </b><font color=red> $querytext </font>");
    if (mysql_num_rows($pager_query)) {
      $pager = mysql_fetch_object($pager_query);
      print "<tr><td class=label>Send page to <b>$unit</b>: \n";
      print "<INPUT type=hidden name=\"to_person_id\" value=\"" . $pager->person_id."\">\n";
      print "<INPUT type=text onfocus=\"focusPaging(true)\" name=\"pagetext\" size=\"30\" maxlength=\"80\">\n";
      print "<BUTTON type=submit name=\"pageunit\" tabindex=\"42\" accesskey=\"4\"><u>4</u>  Send Page</button>\n";
      print "<BUTTON type=button onfocus =\"focusPaging(false)\" name=\"cancelpageunit\" tabindex=\"43\" accesskey=\"5\"><u>5</u>  Cancel Page</button>\n";
      print "</td></tr>\n";
      //print "<tr><td><span class=\"label\" style=\"color:blue\" id=\"pagehelp\">&nbsp;";
    }
    else {
      print "<tr><td class=label style=\"color:blue\">Cannot page this unit directly from CAD: No pager defined for &quot;<b>$unit</b>&quot;.<br>";
      print "<font size=-1>&nbsp; If this unit has a pager, try the Paging system directly, as the pager may be named differently.</font></td></tr>\n";
    }
    
    if ($_GET['pagesent'] == 1) {
      print "Your page was sent.";
    }
    elseif (isset($_GET['pagesent']) && $_GET['pagesent'] == 0) {
      print "Error sending page.";
    }
    print "</span></td></tr>\n"; 
    ?>
    </table>

  </td>
  </tr>
  </table>

</td>
</tr>
<!-- End Outer Table Row 2 -->

<!-- Outer Table Spacer Row -->
<tr><td></td></tr>

<!-- Begin Outer Table Row 3 -->
<tr>
<td colspan="3" bgcolor="#bbbbbb">

  <!-- Begin AutoPage Form Outer Table -->
  <table cellpadding="0" cellspacing="0" border="1" width="100%">
  <tr>
  <td>

    <!-- Begin AutoPage Form Inner Table -->
    <table cellpadding="0" cellspacing="0" >

<?php 

    $pplquery = "SELECT * FROM $DB_PAGING_NAME.people WHERE is_active=1 ORDER BY name";
    $options_query = mysql_query($pplquery, $paginglink) or die ("<b>Problem with query</b>: <font color=red> $pplquery</font>");
    $Pagers = array();
    while ($pager_option = mysql_fetch_object($options_query)) {
      $Pagers[$pager_option->person_id] = $pager_option->name;
    }
    $pageout_qty = MysqlGrabData("SELECT COUNT(*) FROM unit_incident_paging WHERE unit='$unit'");
    $pageout_query = MysqlQuery("SELECT * FROM unit_incident_paging WHERE unit='$unit'");
    // TODO: set access level dynamically
    if (mysql_num_rows($pageout_query) || $_SESSION['access_level'] >= 5) {
?>

  <!-- Begin Unit AutoPage Row: Labels -->
<tr>
<td colspan=4 class="label">
<b>Automatic Paging Notification</b><br>
When <?php print $unit?> is assigned to an incident, CAD will page a notification to each of the pagers listed below.<br>
<div style="text-align:right; color:blue"><b>CLARIFICATION:</b> This <u>does not</u> page <?php print $unit?> when <u>the units listed below</u> are assigned to an incident.<br>  This list contains pagers, not units; and this Unit Details window is about a unit, not <?php print $unit?>'s pager.</div>
<font size=-3> &nbsp;<center><img src="Images/bluespacer.gif" width=600 height=1></center>&nbsp; </font>
</td>
</tr>

  <!-- Begin Unit AutoPage Row: Current List / Add -->
  <tr>
  <td valign="top" class="label" nowrap>
  Pagers being notified about <?php print $unit?> dispatches:

    <table >
<?php
      if (mysql_num_rows($pageout_query)) {
        while ($pageout_rcpt = mysql_fetch_object($pageout_query)) {
          // TODO: set access level dynamically
          if ($pageout_rcpt->to_person_id == 0) {
            print "<font color=red>Bad data needs conversion</font></td>";
          }

          else {
            print "<tr><td class=\"label\">&nbsp;&nbsp;<b>". $Pagers[$pageout_rcpt->to_person_id] .  "</b>&nbsp;&nbsp;";
            if ($_SESSION['access_level'] >= 5) {
                print "<input type=image title=\"Remove this pager from the notification list\" src=\"Images/cross-16.png\" name=\"delete_pageout_". 
                  $pageout_rcpt->row_id . '_' . $pageout_rcpt->to_person_id . "\" alt=\"Delete\"></td></tr>";
            }
           // TODO: In future, replace this submit button with a red "X", and replace the handler appropriately.
            unset($Pagers[$pageout_rcpt->to_person_id]);
          }

        }
      }
      else {
        print "<tr><td class=label style=\"color:blue\">No pagers are being notified.</td></tr>";
      }
      print "</table></td>\n";
    }

    print "</td>\n";

    if ($_SESSION['access_level'] >= 5) {


      print "<td valign=middle>\n";
      print "<img src=\"Images/bluespacer.gif\" width=1 height=".(int)(20*($pageout_qty+2)).">\n";
      print "</td>\n";
      print "<td valign=top class=\"label\" nowrap>\n";
      print "Add notify about $unit to pager:\n";

      print "<table cellpadding=\"2\" cellspacing=\"1\" width=\"100%\">\n";
      print "<tr><td class=\"label\"><SELECT name=newpageout>\n";
      print "<option value=\"0\"> -select a pager below- </option>\n";
      foreach (array_keys($Pagers) as $pager) {
        print "<option value=$pager>" . $Pagers[$pager] . "</option>\n";
      }
      print "</SELECT></td><td class=\"label\"><input type=submit value=\"Add\" name=\"add_pageout\"></td>\n";
    }
    mysql_close($paginglink);

?>
</tr>
      </table>

    </td>
    </tr>
    </table>
    <!-- End Unit AutoPage Inner Table -->

  </td>
  </tr>

  </table>
  <!-- End Unit AutoPage Outer Table -->

</td>
</tr>
<!-- End Outer Table Row 2 -->
<?php } } ?>

</table>
<!-- End Outer Table -->

</form>

</body>
</html>
