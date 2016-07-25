<?php
  $subsys = "admin";
  require_once('session.inc');
  require_once('db-open.php');
  require_once('functions.php');
  require_once('local-dls.php');
  SessionErrorIfReadonly();

  #
  # Verify access level
  #
  if (!CheckAuthByLevel('edit_staging_locations', $_SESSION['access_level'])) {
    syslog(LOG_WARNING, "Staging location editing attempted without permissions by user ". $_SESSION['username'] ." level ". $_SESSION['access_level']);
    echo "Access level insufficient for this operation.<br />\n";
    echo "User: " . $_SESSION['username'] . "<br />\n";
    echo "Level: " . $_SESSION['access_level'] . "<br />\n";
    exit;
  }

  if (isset($_POST["release_staged_unit_by_id"]) && $_POST["release_staged_unit_by_id"]) {
    $assignment_id = (int)$_POST["release_staged_unit_by_id"];
    $staging_id = (int)$_POST["staging_id"];
    MysqlQuery("LOCK TABLES unit_staging_assignments WRITE, units WRITE, messages WRITE");
    // TODO: error check that target unit is still staged? -- but likely doesn't matter.

    $unit_name = MysqlGrabData("SELECT unit_name FROM unit_staging_assignments WHERE staging_assignment_id = $assignment_id");

    MysqlQuery("
      UPDATE $DB_NAME.unit_staging_assignments 
         SET time_reassigned=NOW() 
       WHERE staging_assignment_id=$assignment_id 
    ");
    //
    // TODO: need to check about generics.  Shouldn't do these next two for them, should we?
    MysqlQuery("
      UPDATE $DB_NAME.units 
         SET status='In Service' 
       WHERE unit='$unit_name'
    ");
    MysqlQuery("
      INSERT INTO $DB_NAME.messages (ts, unit, message, creator) 
      VALUES (NOW(), 
              '$unit_name', 
              'Status Change: In Service (was: Staged At Location)', 
              '".$_SESSION['username']."')
      ");

    syslog(LOG_INFO, $_SESSION['username'] . " released staged unit [$unit_name] from staging location id [$staging_id]");
    print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> if (window.opener){window.opener.location.reload()} self.location = \"edit-staging.php?staging_id=$staging_id\"; </script>\n</body></html>\n";
    exit;
  }

  if (isset($_POST["attach_staged_unit_by_id"]) && $_POST["attach_staged_unit_by_id"]) {
    $assignment_id = (int)$_POST["attach_staged_unit_by_id"];
    $staging_id = (int)$_POST["staging_id"];
    $incident_id = 0;
    $unit = MysqlGrabData("SELECT unit_name FROM unit_staging_assignments WHERE staging_assignment_id = $assignment_id");
    // TODO: error check
    $unit_type = MysqlGrabData("SELECT type FROM units WHERE unit ='$unit'");
    $location= MysqlGrabData("SELECT location FROM $DB_NAME.staging_locations WHERE staging_id=$staging_id");
    syslog(LOG_INFO, $_SESSION['username'] . " entered attach_staged_unit_by_id // unit [$unit] incident [$incident_id] staging id [$staging_id] assignment_id [$assignment_id]");

    if (isset($_POST["incident_id"])) {
      $incident_id = (int)$_POST["incident_id"];
      $incidents_result = MysqlQuery ("SELECT * FROM $DB_NAME.incidents WHERE incident_id=$incident_id");
      $incident = mysql_fetch_object($incidents_result);
      MysqlQuery("LOCK TABLES unit_staging_assignments WRITE, units WRITE, messages WRITE, incident_units WRITE");
      // TODO: error check that target unit is still staged? -- but likely doesn't matter.


    // TODO: Prevent multiple assignments for same generic unit to a given incident.
      $unit_already_attached= MysqlGrabData("SELECT COUNT(*) FROM $DB_NAME.incident_units WHERE incident_id=$incident_id AND unit='$unit' AND cleared_time IS NULL");
      if (!$unit_already_attached) {

        // next 22 lines duplicated from edit-incident-post.php:  bad coder, no donut:
        MysqlQuery("
          INSERT INTO incident_units (incident_id, unit, dispatch_time) 
          VALUES ('$incident_id', 
                  '$unit', 
                  NOW())
          ");
    
        if ($unit_type != "Generic") {
          MysqlQuery("
            UPDATE $DB_NAME.unit_staging_assignments 
               SET time_reassigned=NOW() 
             WHERE staging_assignment_id=$assignment_id 
          ");
  
          MysqlQuery("
            UPDATE units 
            SET status='Attached to Incident', 
                status_comment='Attached to Call #$incident->call_number at " . date('H:m:s') . "',
                update_ts=NOW() WHERE unit='$unit'
            ");
          MysqlQuery("
            INSERT INTO messages (ts, unit, message, creator) 
            VALUES (NOW(), 
                    '$unit', 
                    'Status Change: Attached to Incident (was: Staged At Location)', 
                    '".$_SESSION['username']."')
          ");

          syslog(LOG_INFO, $_SESSION['username'] . " attached staged unit [$unit] to incident [$incident_id] from staging location [$location] staging_id [$staging_id]");
          print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> if (window.opener){window.opener.location.reload()} self.location = \"edit-staging.php?staging_id=$staging_id\"; </script>\n</body></html>\n";
        }
        else {
          syslog(LOG_INFO, $_SESSION['username'] . " attached staged unit [$unit] to incident [$incident_id] from staging location [$location] staging_id [$staging_id]");

          print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> alert('Attached Generic unit $unit to incident $incident->call_number -- if no other $unit units are staged at $location, click \"Release From Staging\".'); if (window.opener){window.opener.location.reload()} self.location = \"edit-staging.php?staging_id=$staging_id\"; </script>\n</body></html>\n";
        }
      }
      else {
        print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> alert ('Can\'t attach:  $unit is already attached to this incident (#$incident->call_number / $incident->call_details)'); if (window.opener){window.opener.location.reload()} self.location = \"edit-staging.php?staging_id=$staging_id\"; </script>\n</body></html>\n";
      }
      exit;
    }

    else {
?>
<body vlink="blue" link="blue" alink="cyan">
<link rel="stylesheet"
        href="style.css"
        type="text/css"
        media="screen,print">
  <link rel="shortcut icon"
        href="/cad/favicon.ico"
        type="image/x-icon">
      <form method="post" action="<?php print $_SERVER["PHP_SELF"]?>">
<?php
      print "<input type=\"hidden\" name=\"attach_staged_unit_by_id\" value=\"$assignment_id\">\n";
      print "<input type=\"hidden\" name=\"staging_id\" value=\"$staging_id\">\n";
      print "Select incident to attach <b>$unit</b>, or Cancel:<p>\n";

      print "<table border cellpadding=1 cellspacing=1>\n";
      print "<tr><th>Call No.</th><th>Details</th><th>Location</th><th>Call Type</th></tr>\n";
      $incidents_result = MysqlQuery("
        SELECT * FROM $DB_NAME.incidents 
        WHERE incident_status='Open'
        ORDER BY incident_id DESC
        ");
      while ($incident_row = mysql_fetch_object($incidents_result)) {
        print "<tr><td><button type=submit name=\"incident_id\" value=\"$incident_row->incident_id\">$incident_row->call_number</button></td>\n
          <td>$incident_row->call_details</td>\n
          <td>$incident_row->location</td>\n
          <td>$incident_row->call_type</td>\n
          </tr>\n\n";
      }
      print "</table>\n";
      print "<input value=\"Cancel\" type=reset onClick=\"window.location='edit-staging.php?staging_id=$staging_id';\">\n";
      print "</form>\n";
      exit;
    }

  }

  if (isset($_POST["release_location_id"]) && $_POST["release_location_id"]) {
    $release_location_id = (int)$_POST["release_location_id"];
    // TODO: Even though UI disallows deletion if there are units staged, verify there are in fact no units staged at the location.  (e.g. race condition)
    
    MysqlQuery("UPDATE $DB_NAME.staging_locations SET time_released=NOW() WHERE staging_id=$release_location_id ");
    syslog(LOG_INFO, "Staging location id [$release_location_id] was deleted by [".$_SESSION['username']."]");
    print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> if (window.opener){window.opener.location.reload()} self.close(); </script>\n</body></html>\n";
    exit;
  }

  elseif (isset($_POST["save_new_location"])) {
    syslog(LOG_INFO, "Saving new staging location id by [".$_SESSION['username']."]");
    $location = strtoupper(MysqlClean($_POST, "location", 80));
    $staging_notes = MysqlClean($_POST, "staging_notes", 1024);
  
    if (strlen(trim($location)) < 1) {
      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\">alert('Error: You must provide a staging location name.'); window.location=\"incidents.php\"; </SCRIPT></body></html>\n";
      exit;
    } 
    MysqlQuery("INSERT INTO $DB_NAME.staging_locations (location, created_by, time_created, staging_notes) VALUES ('$location', '".$_SESSION['username']."', NOW(), '$staging_notes')");
    // TODO: better error checking?
    syslog(LOG_INFO, "Staging location [$location] was created by [".$_SESSION['username']."]");

    $rid = mysql_insert_id();
    print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> if (window.opener){window.opener.location.reload()} window.location = \"edit-staging.php?staging_id=$rid\"; </script>\n</body></html>\n";
    //header("Location: edit-staging.php?staging_id=$rid");
    exit;
  }

  elseif (isset($_POST["save_notes"]) && $_POST["staging_id"]) {
    $staging_id = (int)MysqlClean($_POST, "staging_id", 40);
    syslog(LOG_INFO, "Saving notes for staging id $staging_id by [".$_SESSION['username']."]");
    //$location = MysqlClean($_POST, "location", 80);
    $staging_notes = MysqlClean($_POST, "staging_notes", 1024);
  
    if ($staging_id) {
      syslog(LOG_INFO, "Staging location [$staging_id] was edited by [".$_SESSION['username']."]");
      MysqlQuery("UPDATE $DB_NAME.staging_locations SET staging_notes='$staging_notes' WHERE staging_id=$staging_id");
      // TODO: better error checking?
      //header("Location: incidents.php");
      //print "<html><body onload<script language=\"javascript\">myWindow = getObjectByName(\"staging-$staging_id\"); myWindow.close();</script>";
      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> if (window.opener){window.opener.location.reload()} self.close(); </script>";
      print "</body></html>\n";
      exit;
    }
    else {
      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\">alert('Error, staging location ($staging_id) does not exist to save changes.'); window.close(); </SCRIPT></body></html>\n";
      exit;
    }
  }

  elseif (isset($_POST['stage_unit_at_location']) && $_POST['stage_unit_name'] != '') {
    $unit_name = MysqlClean($_POST, 'stage_unit_name', 20);
    $staging_id = (int)MysqlClean($_POST, 'staging_id', 20);
    syslog(LOG_INFO, "User ". $_SESSION['username'] . " posted stage_unit_at_location for unit $unit_name / staging_id $staging_id");
    MysqlQuery('LOCK TABLES staging_locations WRITE, unit_staging_assignments WRITE, messages WRITE, units WRITE');
    $location_name = MysqlGrabData ("SELECT location FROM $DB_NAME.staging_locations WHERE staging_id=$staging_id");

  //// TODO: handle multiple staging locations appropriately for generic units.  Then, what if we try to assign one?  If we do it through edit-incident, don't touch the generic staging!!
    
    
    $unit_result = MysqlQuery("SELECT * FROM $DB_NAME.units WHERE unit='$unit_name'");
    if (mysql_num_rows($unit_result) != 1) {
      syslog('LOG_CRITICAL', "edit-staging.php POST staging_id=$staging_id unit=$unit_name -- expected 1 row got " . mysql_num_rows($unit_result));
      echo "INTERNAL ERROR: bad number of unit rows (". mysql_num_rows($unit_result) . ") for staging ID [$staging_id] while staging unit $unit_name - (expected 1 row).<p>";
      MysqlQuery('UNLOCK TABLES');
      exit;
    }
    $unit_row = mysql_fetch_object($unit_result);
    $unit_status = $unit_row->status;
    $unit_type = $unit_row->type;

    if (in_array($unit_status, array('In Service', 'Available On Pager', 'Staged At Location'))) {
      MysqlQuery("
        INSERT INTO unit_staging_assignments (staged_at_location_id, unit_name, time_staged)
        VALUES ($staging_id, '$unit_name', NOW())
        ");
      MysqlQuery("
        INSERT INTO messages (ts, unit, message, creator) 
        VALUES (NOW(), 
                '$unit_name', 
                'Status Change: Staged At Location (was: $unit_status)', 
                '".$_SESSION['username']."')
      ");

      if ($unit_type != "Generic") {
        MysqlQuery("
          UPDATE units 
          SET status='Staged At Location', 
              status_comment='Staged At Location $staging_id ($location_name) at " . date('H:m:s') . "',
              update_ts=NOW() WHERE unit='$unit_name'
          ");
        syslog(LOG_INFO, "User ". $_SESSION['username'] . " staged unit $unit_name at location $staging_id");
      }
      else {
        syslog(LOG_INFO, "User ". $_SESSION['username'] . " staged generic unit $unit_name at location $staging_id");
      }
    }
    else {
        syslog(LOG_CRIT, "edit-staging.php POST staging_id=$staging_id unit=$unit_name -- unit is not available (current status: $unit_status)");
        echo "ERROR: unit $unit_name (status: $unit->status) not available to stage at location $staging_id - may have been simultaneously updated at another station.<p>";
    }
     
    MysqlQuery('UNLOCK TABLES');
    print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> if (window.opener){window.opener.location.reload()} self.location = \"edit-staging.php?staging_id=$staging_id\"; </script>\n</body></html>\n";
    //header("Location: edit-staging.php?staging_id=$staging_id");
    exit;
  }

  ///////////////////////////////////////////////////////////////////////////
  
  elseif (isset($_GET['staging_id']) || isset($_GET['add_staging_location'])) {
    $location = '';
    $notes = '';
    $staging_id;
    $created_by;
    $created_at;
    $units_ary = array();
    $time_Staged_ary = array(); // hack in lieu of multi level hash

    if (isset($_GET['add_staging_location'])) {
      $staging_id = 'new';
    }

    else {  // by definition, edit existing channel id:
      $staging_id = (int)$_GET['staging_id'];
      $locations = MysqlQuery("SELECT * FROM $DB_NAME.staging_locations WHERE staging_id=$staging_id");
      if (mysql_num_rows($locations) == 1) {
        $row = mysql_fetch_object($locations);
        $created_by = $row->created_by;
        $created_at = $row->time_created;
        $location = $row->location;
        $notes = $row->staging_notes;
      }
      else {
        syslog(LOG_CRITICAL, "Expected 1 row for edit-staging.php?edit_staging_id=$staging_id -- got " . mysql_num_rows($locations));
        echo "INTERNAL ERROR: bad number of rows (". mysql_num_rows($locations) . ") for staging ID [$staging_id] (expected 1).<p>";
        exit;
      }
      $staging_assignments_query = "SELECT * FROM $DB_NAME.unit_staging_assignments WHERE staged_at_location_id=$staging_id AND time_reassigned IS NULL";
      $staging_assignments_result = MysqlQuery($staging_assignments_query);
      while ($staging_assignments_row = mysql_fetch_object($staging_assignments_result)) {
        $units_ary[$staging_assignments_row->staging_assignment_id] = $staging_assignments_row->unit_name;
        $time_staged_ary[$staging_assignments_row->staging_assignment_id] = $staging_assignments_row->time_staged;
      }
      asort($units_ary);
    }

?>
<body vlink="blue" link="blue" alink="cyan">
<link rel="stylesheet"
        href="style.css"
        type="text/css"
        media="screen,print">
  <link rel="shortcut icon"
        href="/cad/favicon.ico"
        type="image/x-icon">

      <form method="post" action="<?php print $_SERVER["PHP_SELF"]?>">


      <?php if (isset($_GET["add_staging_location"])) { ?>

          <div class="cell">New Staging Location Name</div>
          <div><input size="40" type="text" name="location" />
            <input type="hidden" name="save_new_location" value="1" />
            <script language="javascript">document.forms[0].location.focus();</script>
          </div>
<p>
      <?php  } else { 

    print "<span class=\"h2\">Staging Location <span class=\"b\">$location</span></span><p>\n";

    if (count($units_ary)) {
      print "<div class=cell> Units Staged Here:</div>\n";
      print "<table>\n";
      foreach (array_keys($units_ary) as $staging_assignment_id) {
        $unit_name = $units_ary[$staging_assignment_id];
        print "<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;<span class=cell style=\"font-size: 16; background-color: yellow\">$unit_name </span>\n";
        print "<input type=\"hidden\" name=\"staging_id\" value=\"$staging_id\"> </td>\n";
        print "<td class=cell>staged at ".dls_utime($time_staged_ary[$staging_assignment_id])." </td>\n";
        print "<td><button type=\"submit\" name=\"release_staged_unit_by_id\" value=\"$staging_assignment_id\">Release From Staging</button></td> \n"; // TODO: release by assignment id not by unit name?
        print "<td><button type=\"submit\" name=\"attach_staged_unit_by_id\" value=\"$staging_assignment_id\">Attach To Incident</button></td> \n";
        print "</tr>\n";
      }
      print "</table>\n";
    } 
    else
    {
      print "<div class=cell>No units are staged here yet.</div>\n";
    }

    $avail_units_result = MysqlQuery ("SELECT * from $DB_NAME.units where status IN ('In Service', 'Available On Pager') OR type='Generic' ORDER BY unit");  // TODO: is this what we want ???
    $avail_units = array();

    while ($avail_unit = mysql_fetch_object($avail_units_result)) {
      if (!in_array($avail_unit->unit, array_values($units_ary))) { // Do not print option if generic unit is already staged here
        array_push($avail_units, $avail_unit->unit);
      }
    }

    if (count($avail_units)) {
      print "<p><div class=cell>Available units to stage:</div>\n";
      print "<div>&nbsp;&nbsp;&nbsp;&nbsp;<select name=\"stage_unit_name\">\n";
      foreach ($avail_units as $this_unit) {
        print "<option value=\"$this_unit\"> $this_unit </option>\n";
      }
      print "</select>\n";
      print "<button type=\"submit\" name=\"stage_unit_at_location\" value=\"stage_unit_at_location\">Stage This Unit Here</button></div>\n";
    } 
    else {
      print "<p><div class=cell style=\"color: red\">No units currently available to stage.  (Must be In Service or Available On Pager)\n</div>\n";
    }
?>


<input type="hidden" name="staging_id" value="<?php print $staging_id?>" />
    <br> 
      <?php  } ?>



      <span class=cell style="vertical-align:top;">Notes: </span><span><textarea name="staging_notes" rows=2 cols=40><?php print $notes?></textarea> </span>
<br>
<?php 
   if (!isset($_GET["add_staging_location"])) { 
     print "<div class=cell style=\"color: #999999\">This staging location created by <b> ". MysqlUnClean($created_by)." </b> at ". MysqlUnClean($created_at) . " </div>\n";
   }
?>
<p>

<div width=100%>
      <span style="float: left">
        <input name='save_notes' value="Save & Close" type="submit">
        <button type="reset" onClick="window.close()" name="cancel" value="cancel">Cancel</button>
      </span>

      <?php if (!isset($_GET["add_staging_location"]) && !count($units_ary)) {
          echo "<span style=\"float:right\"><div><button type=\"submit\" name=\"release_location_id\" value=\"$staging_id\">Delete This Staging Location</button></div>";
          }?>

</div>

      </form>
      </body>
      </html>

      <?php
    exit;
  }
  // we shouldn't need this should we?
  //else {
  #
  # Display list of users
  #
    //$modchannel = "";
    //$action = "";
    //if (isset($_GET["modchannel"]) && $_GET["modchannel"]) {
      //$modchannel = $_GET["modchannel"];
    //}
    //if (isset($_GET["action"]) && $_GET["action"]) {
      //$action = $_GET["action"];
    //}
    //header_html('Dispatch :: Edit Staging Locations')
//? >


//<body vlink="blue" link="blue" alink="cyan">
//<?php
  ////include('include-title.php');
//? >


    //<?php
  //}

  //echo "</body>\n</html>\n";
