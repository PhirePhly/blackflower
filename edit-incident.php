<?php
/* edit-incident.php
 *
 * This is executed in a "popup" window. Input can be via GET, as follows,
 * or by POST, as documented below:
 *
 * Parameters via GET:
 *
 * edit-incident.php?incident_id=N
 *      Displays the incident indicated by N
 */

  // Initialize subsystem
  $subsys="incidents";

  // Required Files
  require_once('db-open.php');
  require('local-dls.php');
  require_once('session.inc');

  // Initialize the COMPLETED state to false.
  $completed=0;

  // Check major POST points
  /* Three possible POST points exist here:
   * 1. We have a new incident but we want to abort it before saving.
   * 2. We have a working incident and we want to discard any changes we made.
   * 3. We have a working incident and we want to save any changes we made.
   */

  // Major POST Point: Abort Incident
  if (isset($_POST["incident_abort"])) {
    if (!isset($_POST["incident_id"]))
      die("Error while aborting, no incident ID seen in POST.");

    $incident_id = MysqlClean($_POST, "incident_id", 20);
    $query = "DELETE FROM incidents WHERE incident_id=$incident_id";
    mysql_query($query) or die("delete query failed: ".mysql_error());

    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    die("(Error: JavaScript not enabled or not present) Incident has been aborted as you requested.");
  }

  // Major POST Point: Cancel Changes
  elseif (isset($_POST["cancel_changes"])) {
    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    die("(Error: JavaScript not enabled or not present) Close this window to Cancel any changes you made.");
  }

  // Major POST Point: Save Changes
  elseif (isset($_POST["incident_id"])) {
    /* Here we have POSTed an incident_id, which for sure means we want to save the POST set... but multiple entry
     * points exist for the form submission.  Valid buttons that could bring us here include:
     *
     * save_incident
     * save_incident_closewin
     * unit_to_attach
     * attach_unit
     * arrived_unit_*
     * release_unit_*
     *
     * In addition, any <ENTER> keystroke in a text input in the form could bring us here.
     * We will check all form elements for data along the way.
     *
     * If we determine we got here via save_incident_closewin, we want to close the window afterwards.
     * Otherwise save and continue editing.
     *
     * TODO: fill this in - non header?
     */

    // Clean and store the incident ID
    $incident_id = MysqlClean($_POST, "incident_id", 20);

    /* Check to make sure that new data was not entered into the DB between
     * loading the incident and savint the incident. If any data has changed,
     * note it and alert the user.
     */

    // find a better way to word the alert?
    // TODO: The logic tying this section to the rest ($authorized) is a little flimsy.  Will still do the other updates
    // you may have come for.  Consider redoing it when you can.

    // Initialize the DB update authorization value to false
    $authorized=0;

    // Load the current data from the DB, DIEing if it is not present and placing it in OLDLINE if it is
    $query = "SELECT * FROM incidents WHERE incident_id=$incident_id";
    $result = mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
    if (mysql_num_rows($result) != 1)
      die ("In query: $query<br>\nBad number of rows returned:".mysql_num_rows($result)." (expected 1)\n");
    $oldline = mysql_fetch_array($result, MYSQL_ASSOC);
    mysql_free_result($result);

    // Check to see if the DB update time and the POSTed hidden update time differ
    if (isset($_POST["updated"]) && ($oldline["updated"] <> $_POST["updated"])) {
      // Initialize the CHANGES array.
      $changes = array();

      // Populate the CHANGES array.
      if ($oldline["disposition"] != $_POST["disposition"])
        array_push($changes , "Disposition [db: ".$oldline["disposition"]." / you: ".MysqlClean($_POST,"disposition",80));
      if ($oldline["call_type"] != $_POST["call_type"])
        array_push($changes , "Call Type [db: ".$oldline["call_type"]." / you: ".MysqlClean($_POST,"call_type",40));
      if ($oldline["call_details"] != $_POST["call_details"])
        array_push($changes , "Call Details [db: ".$oldline["call_details"]." / you: ".MysqlClean($_POST,"call_details",80));
      if ($oldline["location"] != $_POST["location"])
        array_push($changes , "Location [db: ".$oldline["location"]." / you: ".MysqlClean($_POST,"location",80));
      if ($oldline["reporting_pty"] != $_POST["reporting_pty"])
        array_push($changes , "Reporting Party [db: ".$oldline["reporting_pty"]." / you: ".MysqlClean($_POST,"reporting_pty",80));
      if ($oldline["contact_at"] != $_POST["contact_at"])
        array_push($changes , "Contact At [db: ".$oldline["contact_at"]." / you: ".MysqlClean($_POST,"contact_at",80));
      if ($oldline["ts_opened"] != $_POST["ts_opened"])
        array_push($changes , "Time Opened [db: ".$oldline["ts_opened"]." / you: ".MysqlClean($_POST,"ts_opened",40));
      if ($oldline["ts_dispatch"] != $_POST["ts_dispatch"])
        array_push($changes , "Time Dispatched [db: ".$oldline["ts_dispatch"]." / you: ".MysqlClean($_POST,"ts_dispatch",40));
      if ($oldline["ts_arrival"] != $_POST["ts_arrival"])
        array_push($changes , "Time Arrival [db: ".$oldline["ts_arrival"]." / you: ".MysqlClean($_POST,"ts_arrival",40));
      if ($oldline["ts_complete"] != $_POST["ts_complete"])
        array_push($changes , "Time Completed [db: ".$oldline["ts_complete"]." / you: ".MysqlClean($_POST,"ts_complete",40));

      // If there were CHANGES, an update is not authorized, otherwise an update is authorized.
      if (sizeof($changes) > 0) $authorized=0; else $authorized = 1;
    }
    else {
      // If the DB update time and the POSTed hidden update time do not differ, an update is authorized.
      $authorized = 1;
    }

    // If authrized to update the changes into the DB, prepare the data.
    if ($authorized) {

      /* Check POST entry points, moving from the specific to the general */

      // Update a note if a message is present
      if (isset($_POST["note_message"]) && $_POST["note_message"] <> "") {
        $unit = MysqlClean($_POST,"note_unit",20);
        $message = MysqlClean($_POST,"note_message",255);

        if (isset($_SESSION['username']) && $_SESSION['username'] != '')
          $creator = $_SESSION['username'];
        else
          $creator = "";

        $note_query = "INSERT INTO incident_notes (incident_id, ts, unit, message, creator) ".
                      "VALUES ($incident_id, NOW(), '$unit', '$message', '$creator')";
        mysql_query($note_query) or die("In query: $note_query<br>\nError: ".mysql_error());
      }


      // Clean and store the dispatch timestamp
      $ts_dispatch = MysqlClean($_POST, "ts_dispatch", 40);

      // Try to attach a unit if we POSTed from the attach unit button
      if (isset($_POST["attach_unit"]) && (isset($_POST["unit_to_attach"]) && $_POST["unit_to_attach"] != "")) {
        // Can we attach the selected unit?

        // Get the unit's current status from the DB
        $unit = MysqlClean($_POST, "unit_to_attach", 20);
        $lockquery = "LOCK TABLES units WRITE, incident_units WRITE, messages WRITE";
        mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());
        $unitquery = "SELECT * FROM units where unit='$unit'";
        $unitresult = mysql_query($unitquery) or die ("In query: $unitquery<br>\nError: ".mysql_error());
        if (mysql_num_rows($unitresult) != 1)
          die("In query: $unitquery<br>\nIncorrect number of rows returned (expecting 1): ".
              mysql_num_rows($unitresult));
        $unitrow = mysql_fetch_array($unitresult, MYSQL_ASSOC);

        // Determine if the unit's status is not in the "available" set
        if ($unitrow["status"] != "In Service" &&
            $unitrow["status"] != "Available on Pager" &&
            $unitrow["status"] != "Busy") {

            // The unit is not available to be attached. Alert the user.
            $lockquery = "UNLOCK TABLES";
            mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());
            print "<SCRIPT LANGUAGE=\"JavaScript\">alert(\"Unit is not available - ".
                  $unitrow["status"].
                  "\");</SCRIPT>";
        }
        else {
          // The unit is available to be attached.
          $attachquery = "INSERT INTO incident_units (incident_id, unit, dispatch_time, is_primary, is_generic) ".
                         "VALUES ('$incident_id', '$unit', NOW(), 0";
          if ($unitrow["type"] == "Generic") {
            $attachquery .= ",1)";
          }
          else {
            $attachquery .= ",0)";
            $unitquery = "UPDATE units SET status='Attached to Incident', ".
                         "status_comment='Attached to Incident #$incident_id at ".date('H:m:s') . "', ".
                         "update_ts=NOW() WHERE unit='$unit'";
            mysql_query($unitquery) or die ("In query: $unitquery<br>\nError: ".mysql_error());
            $messagequery = "INSERT INTO messages (ts, unit, message, creator) ".
                            "VALUES (NOW(), '$unit', 'Status Change: Attached to Incident (was: ".
                            $unitrow["status"].")', '".$_SESSION['username']."')";
            mysql_query($messagequery) or die("In query: $messagequery<br>\nError: ".mysql_error());
          }
          mysql_query($attachquery) or die ("In query: $attachquery<br>\nError: ".mysql_error());
          $lockquery = "UNLOCK TABLES";
          mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());

          // If this is the first unit to be attached to this incident, set the dispatched timestamp
          if ($ts_dispatch == "" || $ts_dispatch == "0000-00-00 00:00:00") {
            $ts_dispatch = "NOW()";
          }
        }
      }


      // Clean and store the arrival timestamp
      $ts_arrival = MysqlClean($_POST, "ts_arrival", 40);

      // Test the POST array for units arriving or being released (prior to incident completion)
      foreach (array_keys($_POST) as $unittestkey) {
        // Store the unit ID if we POSTed a unit arrival button
        if (substr($unittestkey, 0, 13) == "arrived_unit_") {
          $update_arrived_unit_uid = substr($unittestkey, 13);
          if ($_POST["ts_arrival"] == "" || $_POST["ts_arrival"] == "0000-00-00 00:00:00")
            $ts_arrival = "NOW()";
        }
        // Store the unit ID if we POSTed a unit release button
        if (substr($unittestkey,0,13) == "release_unit_") {
           $update_release_unit_uid = substr($unittestkey, 13);
        }
      }

      // If we have a unit that has arrived, save the information in the DB
      if (isset($update_arrived_unit_uid)) {
        $lockquery = "LOCK TABLES incident_units WRITE";
        mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());

        $arrivedquery = "UPDATE incident_units SET arrival_time=NOW() where uid='$update_arrived_unit_uid'";
        mysql_query($arrivedquery) or die ("In query: $arrivedquery<br>\nError: ".mysql_error());

        $lockquery = "UNLOCK TABLES";
        mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());
      }

      // If we have a unit that has been released, save the information in the DB
      if (isset($update_release_unit_uid)) {
        $lockquery = "LOCK TABLES units WRITE, incident_units WRITE, messages WRITE";
        mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());

        $releasequery = "UPDATE incident_units SET cleared_time=NOW() where uid='$update_release_unit_uid'";
        mysql_query($releasequery) or die ("In query: $releasequery<br>\nError: ".mysql_error());

        $unitquery = "SELECT * FROM incident_units where uid='$update_release_unit_uid'";
        $unitresult = mysql_query($unitquery) or die ("In query: $unitquery<br>\nError: ".mysql_error());
        if (mysql_num_rows($unitresult) <> 1)
          die ("In query: $unitquery<br>\nExpected 1 result row, got: ".mysql_num_rows($unitresult));
        $unitrow = mysql_fetch_array($unitresult, MYSQL_ASSOC);
        $release_unit_name = $unitrow["unit"];
        mysql_free_result($unitresult);

        $unitprevstatus = FindPrevUnitStatus($unitrow["unit"]);

        $unitstatusquery = "UPDATE units SET status='$unitprevstatus', ".
                           "status_comment='Released from Incident #$incident_id at ".date('H:m:s')."', ".
                           "update_ts=NOW() where unit='$release_unit_name'";
        mysql_query($unitstatusquery) or die ("In query: $unitstatusquery<br>\nError: ".mysql_error());

        $messagequery="INSERT INTO messages (ts, unit, message) ".
                      "VALUES (NOW(), '$release_unit_name', 'Status Change: In Service (was: Attached to Incident)')";
        mysql_query($messagequery) or die("In query: $messagequery<br>\nError: ".mysql_error());

        $lockquery = "UNLOCK TABLES";
        mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());
      }


      // Check to see if we have POSTed a completed incident
      if (isset($_POST["ts_complete"]) && $_POST["ts_complete"] <> "" && $_POST["ts_complete"] <> "0000-00-00 00:00:00") {
        // Mark incident as completed
        $completed = 1;

        // Check the DB for a previous completion of this incident
        $checkcompletedquery = "SELECT completed FROM incidents WHERE incident_id=$incident_id";
        $checkcompletedresult = mysql_query($checkcompletedquery) or
          die ("Couldn't check incident completion: ".mysql_error());
        $checkcompletedrow = mysql_fetch_array($checkcompletedresult, MYSQL_ASSOC);
        $previously_completed = $checkcompletedrow["completed"];
        mysql_free_result($checkcompletedresult);

        // Check to see if units need to be released from the completed incident
        if (!$previously_completed && isset($_POST["release_query"])) {

          $stackids = array();
          $stackunits = array();

          $unitsrelquery = "SELECT * FROM incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL";
          $unitsrelresult = mysql_query($unitsrelquery) or die("In query: $unitsrelquery<br>\nError: ".mysql_error());
          if (mysql_num_rows($unitsrelresult) > 0) {
            $i=1;
            while ($unitsrelrow = mysql_fetch_array($unitsrelresult, MYSQL_ASSOC)) {
              $stackids[$i++] = $unitsrelrow["uid"];
              $stackunits[$i++] = $unitsrelrow["unit"];
            }
            mysql_free_result($unitsrelresult);

            foreach ($stackids as $stackid) {
              $iuquery = "UPDATE incident_units SET cleared_time=NOW() WHERE uid='$stackid'";
              mysql_query($iuquery) or die("In query: $iuquery<br>\nError: ".mysql_error());
            }

            foreach ($stackunits as $stackunit) {
              $unitprevstatus = FindPrevUnitStatus($stackunit);
              $unitsquery="UPDATE units SET status='$unitprevstatus', ".
                          "status_comment='Released from Incident #$incident_id at ".date('H:m:s')."', ".
                          "update_ts=NOW() WHERE unit='$stackunit'";
              mysql_query($unitsquery) or die("In query: $unitsquery<br>\nError: ".mysql_error());
              $messagequery="INSERT INTO messages (ts, unit, message) ".
                            "VALUES (NOW(), '$stackunit', 'Status Change: $unitprevstatus (was: Attached to Incident)')";
              mysql_query($messagequery) or die("In query: $messagequery<br>\nError: ".mysql_error());
            }
          }
        }
      }


      // Build the master incident query to save the incident row in the incidents table
      $incidentquery = "UPDATE incidents SET ".
                       "call_type='". MysqlClean($_POST,"call_type",40) . "',".
                       "call_details='". MysqlClean($_POST,"call_details",80) . "',".
                       "ts_opened='". MysqlClean($_POST,"ts_opened",40) . "',".
                       "ts_dispatch='$ts_dispatch',".
                       "ts_arrival='$ts_arrival',".
                       "ts_complete='". MysqlClean($_POST,"ts_complete",40) . "',".
                       "location='". MysqlClean($_POST,"location",80) . "',".
                       "reporting_pty='". MysqlClean($_POST,"reporting_pty",80) . "',".
                       "contact_at='". MysqlClean($_POST,"contact_at",80) . "',";
      // If the incident is marked as completed, set visible/completed info in the DB
      if ($completed) {
        $incidentquery .= "visible=0, completed=1, ";
      }
      else {
        // Note: Important for first full save of incident
        $incidentquery .= "visible=1, ";
      }
      $incidentquery .=
                       "disposition='". MysqlClean($_POST,"disposition",80) . "' ".
                       ", updated=NOW() WHERE incident_id=$incident_id";

      $incidentquery = str_replace("'NOW()'", "NOW()", $incidentquery);

      // Enter the master incident query into the DB
      mysql_query($incidentquery) or die("In query: $incidentquery<br>\nError: ".mysql_error());


      // If the save_incident_closewin button was explicitly activated, set
      // the "force close" by clearing the default reload flag.  This is the
      // only way that the window will be closed; otherwise, reload it.

      if (isset($_POST["save_incident_closewin"])) {
        print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
      }
      else {
        print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()}</SCRIPT>";
      }
    }

    // If not authorized to update the changes into the DB, alert the user.
    elseif (!$authorized) {
      $changemsg = "Warning: incident data in table has changed. ".
                   "The following data would have been overwritten: ".
                   implode("\\n] ", $changes).
                   "\\n"."Please make note of this data, click the cancel button, and then re-enter your changes.";
      print "<SCRIPT language=\"JavaScript\">alert('$changemsg');</SCRIPT>";
    }
  }


  // Check the Major GET points
  // Major GET point: Display incident
  elseif (isset($_GET["incident_id"])) {
    // Is this an existing incident?
    if ($_GET["incident_id"] <> "new") {
      $incident_id = MysqlClean($_GET,"incident_id",20);
    }
    // Or a new incident?
    else {
      // Ok. We have a new incident. Create the placeholder first in the database to assign the incident ID number.
      $lockquery = "LOCK TABLES incidents WRITE";
      mysql_query($lockquery) or
        die ("Database could not lock table - is another incident being created right now?<br>Error was: " . mysql_error());

      $newincidentquery = "INSERT INTO incidents (ts_opened, visible) VALUES (NOW(), 0)";
      mysql_query($newincidentquery) or die ("Could not create new incident row: ". mysql_error());
      if (mysql_affected_rows() != 1)
        die("Critical error: ".mysql_affected_rows()." is a bad number of rows when inserting new incident.");
      $findlastIDquery = "SELECT LAST_INSERT_ID()";
      $findlastIDresult = mysql_query($findlastIDquery) or die ("Could not select new incident row: ". mysql_error());
      $newIDrow = mysql_fetch_array($findlastIDresult, MYSQL_NUM);
      $incident_id = $newIDrow[0];
      mysql_free_result($findlastIDresult);

      $lockquery = "UNLOCK TABLES";
      mysql_query($lockquery) or die ("Could not unlock tables: ". mysql_error());

      header("Location: edit-incident.php?incident_id=$incident_id");
    }
  }
  else {
    die("Internal error: Must specify incident ID in URL request.");
  }

  /* ASSERT: the only way to get to this point is to have a numerical GET
   * value for the incident_id key which is NOT equal to the "new" magic value.
   * (P.A. What's the magic value?)
   * Meaning, a user has requested this script to load and display an incident.
   * So now we get it out of the database:
   */
  if (!isset($incident_id)) die ("Critical error: incident_id wasn't set at this point.");

  $incidentdataquery = "SELECT * FROM incidents WHERE incident_id=$incident_id";
  $incidentdataresult = mysql_query($incidentdataquery) or die("Select query failed: $incidentdataquery " . mysql_error());
  if (mysql_num_rows($incidentdataresult) != 1) {
    die("Critical error: ".mysql_num_rows($incidentdataresult).
        " is a bad number of rows when looking for incident_id $incident_id");
  }
  $row = mysql_fetch_object($incidentdataresult);


  /* Functions
   *
   * Oft-used functions in edit-incident.php
   *
   */

  // FindPrevUnitStatus
  // Nasty hack to find a unit's previous status before being attached to an incident
  // from the text in their status comment
  function FindPrevUnitStatus($unit) {
    $lockquery = "LOCK TABLES status_options READ, messages READ";
    mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());

    $statusquery = "SELECT * from status_options";
    $statusresult = mysql_query($statusquery) or
      die ("Status query failed: $statusquery " . mysql_error());
    $lastunitmsgquery = "SELECT message FROM messages WHERE unit='$unit' ORDER BY oid DESC LIMIT 1";
    $lastunitmsgresult = mysql_query($lastunitmsgquery) or
      die ("Unit's Last Message query failed: $lastunitmsgquery " .  mysql_error());

    if ($msgline = mysql_fetch_array($lastunitmsgresult, MYSQL_ASSOC)) {
      $msg = $msgline["message"];
    }
    else {
      $msg = "";
    }

    $return = "";
    while ($statusline = mysql_fetch_array($statusresult, MYSQL_ASSOC)) {
      if (strpos($msg, $statusline["status"])) $return = $statusline["status"];
    }

    $lockquery = "UNLOCK TABLES";
    mysql_query($lockquery) or die ("In query: $lockquery<br>\nError: ".mysql_error());

    return $return;
  }
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<html>
<head>
  <title>Dispatch :: Edit Incident #<?php print $incident_id?></title>
  <meta http-equiv="content-language" content="en" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <link rel="StyleSheet"
        href="style.css"
        type="text/css"
        media="screen, print" />
  <link rel="shortcut icon"
        href="favicon.ico"
        type="image/x-icon" />
  <meta http-equiv="Content-Script-Type" content="text/javascript" />

  <script src="js/clock.js" type="text/javascript"></script>
  <script type="text/javascript">
    function stampTimestamp() {
      var stampTime = new Date()
      var hours = stampTime.getHours()
      hours=((hours < 10) ? "0" : "") + hours
      var minutes = stampTime.getMinutes()
      minutes=((minutes < 10) ? "0" : "") + minutes
      var seconds = stampTime.getSeconds()
      seconds=((seconds < 10) ? "0" : "") + seconds
      var value = hours + ":" + minutes + ":" + seconds
      return (value)
    }

    function stampFulltime() {
      var stampTime = new Date()
      var year = stampTime.getFullYear()
      var month = stampTime.getMonth()+1
      month=((month < 10) ? "0" : "") + month
      var day = stampTime.getDate()
      day=((day < 10) ? "0" : "") + day
      var mytime = year + "-" + month + "-" + day + " " + stampTimestamp()
      return (mytime)
    }

    function handleIncidentType() {
      if (document.myform.call_type.value == "not selected") {
        document.myform.disposition.selectedIndex = 0;
      }
    }

    function handleDisposition() {
      // Do we really need this?
      //document.myform.dts_complete.disabled = false;
      if (document.myform.disposition.value != "") {
        // We don't want to complete the incident unless it has a defined type assiciated with it
        if (document.myform.call_type.value == "not selected") {
          document.myform.disposition.selectedIndex = 0;
          alert('You must choose a Call Type before marking the incident as Completed.');
        }
        else {
          // If the completed timestamps do not already have values, fill them in now
          // just in case maybe we're changing the disposition type after completion of the incident
          if (document.myform.ts_complete.value == "0000-00-00 00:00:00" && document.myform.dts_complete.value == "") {
            document.myform.ts_complete.value = stampFulltime();
            document.myform.dts_complete.value = stampTimestamp();
          }
          // If the release_query checkbox is present, enable it and populate default values
          if (document.myform.release_query != null) {
            document.myform.release_query.disabled = 0;
            document.myform.release_query.checked = 1;
            document.myform.release_query.value = 1;
          }
        }
      }
      else {
        document.myform.ts_complete.value = "0000-00-00 00:00:00";
        document.myform.dts_complete.value = "";
        // If the release_query checkbox is present, disable it and reset values
        if (document.myform.release_query != null) {
          document.myform.release_query.disabled = 1;
          document.myform.release_query.checked = 0;
          document.myform.release_query.value = 0;
        }
      }
    }
  </script>
</head>

<body onload="displayClockStart()" onunload="displayClockStop()" onBlur="self.focus()">
<font face="tahoma,ariel,sans">
<form name="myform" action="edit-incident.php" method="post">
<table cellspacing=3 cellpadding=0 width="970">
<tr>
<td colspan=2 bgcolor="darkblue" class="text">
<table width="100%"><tr><td><font color="white" size="+1"><b>Incident #<?=$incident_id?></b> </font>
<?php
  if ($row->visible == 0) {
    if ($row->completed) {
      print "&nbsp; &nbsp; <font color=\"#FF0000\"><b>&nbsp; &nbsp;[Completed Incident]</b></font>";
    }
  }
?>
</td>
  <td align=right>
  <input type="text" name="displayClock" readonly disabled STYLE="color: black; background-color: white" size="6">
  </td>
</tr>
</table>
</tr>

<tr><td colspan=2 >

<table border cellspacing=0 cellpadding=0 width="970">
<tr><td colspan="2" bgcolor="#bbbbbb" class="text">
<table cellpadding=0 cellspacing=0 width="100%">
<tr><td>
<table>
<!-- ****************************************** -->

<tr>
    <td align=right class="label">Deta<u>i</u>ls
      <input type="hidden" name="incident_id" value="<?php print $incident_id?>">
      <input type="hidden" name="updated" value="<?php print $row->updated?>">
    </td>

    <td align=left class="text">
    <label for="call_details" accesskey="i">
    <input type="text" name="call_details" id="call_details" tabindex="1" size="50" maxlength="80"
     value="<?php print MysqlUnClean($row->call_details) ?>">
    </lable>
    </td>

    <td width="30" align=right class="label">Call&nbsp;T<u>y</u>pe</td>
    <td width="50" align=left class="text">
      <Label for="call_type" accesskey="y">
      <select name="call_type" id="call_type" tabindex="5" onChange="handleIncidentType()" onKeyUp="handleIncidentType()">
<?php
        $type_query = "SELECT * from incident_types";
	$type_result = mysql_query($type_query) or die ("error in call type query: ".mysql_error());
	if (!$row->call_type || !strcmp("not selected", $row->call_type))
	  echo "<option selected value=\"not selected\">not selected</option>\n";
	while ($type_row = mysql_fetch_object($type_result)) {
          echo "<option ";
	  if (!strcmp($type_row->call_type, $row->call_type)) echo "selected ";
	  echo "value=\"". $type_row->call_type ."\">".$type_row->call_type ."</option>\n";
	}
	mysql_free_result($type_result);
	?>
       </select>
       </label>
     </td>

    <td width="100" align=right class="label">Received</td>
    <td align=left class="text">
       <input type="hidden" name="ts_opened" value="<?php print $row->ts_opened ?>">
       <input type="text" name="dts_opened" tabindex="121" class="time" size=6 readonly disabled style="color: black" 
              value="<?php print date("H:i:s", strtotime($row->ts_opened)) ?>">
    </td>
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label"><u>L</u>ocation</td>
    <td align=left colspan=3 class="text">
    <label for="location" accesskey="l">
    <input type="text" name="location" id="location" tabindex="2" size="50" maxlength="80"
     value="<?php print MysqlUnClean($row->location)  ?>">
    </label>
    </td>

    <td width="100" align=right class="label">Dispatched</td>
    <td align=left class="text">
       <input type="hidden" name="ts_dispatch" value="<?php print $row->ts_dispatch  ?>">
       <input type="text" name="dts_dispatch" tabindex="122" class="time" size=6 readonly disabled style="color: black"
              value="<?php if ($row->ts_dispatch) print dls_hmstime($row->ts_dispatch) ?>">
    </td>
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label"><u>R</u>eporting&nbsp;Party</td>
    <td align=left class="text">
    <label for="reporting_pty" accesskey="r">
    <input type="text" name="reporting_pty" id="reporting_pty" tabindex="3" size="50" maxlength="80"
     value="<?php print MysqlUnClean($row->reporting_pty)  ?>">
    </label>
    </td>

    <td class="label"></td><td align=left class="label"><!--a href="">Other Units...</a--></td>

    <td width="100" align=right class="label">Unit&nbsp;On&nbsp;Scene</td>
    <td align=left class="text">
       <input type="hidden" name="ts_arrival" value="<?php print $row->ts_arrival  ?>">
       <input type="text" name="dts_arrival" tabindex="123" class="time" size=6 readonly disabled style="color: black"
              value="<?php if ($row->ts_arrival) print dls_hmstime($row->ts_arrival) ?>">
    </td>
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label"><u>C</u>ontact&nbsp;At</td>
    <td align=left class="text">
    <label for="contact_at" accesskey="c">
    <input type="text" name="contact_at" id="contact_at" tabindex="4" size="50" maxlength="80"
     value="<?php print MysqlUnClean($row->contact_at)  ?>">
    </label>
    </td>

    <td align=right class="label">Dis<u>p</u>osition</td>
    <td align=left class="text">
    <label for="disposition" accesskey="p">
    <select name="disposition" id="disposition" tabindex="61" onChange="handleDisposition()" onKeyUp="handleDisposition()">
<?php
   $dispquery = "SELECT disposition FROM incident_disposition_types";
   $dispresult = mysql_query($dispquery) or die ("Error querying units; ".mysql_error());
   if (!$row->disposition)
     echo "<option selected value=\"\"></option>\n";
   while ($disprow = mysql_fetch_array($dispresult,MYSQL_ASSOC)) {
    echo "<option ";
     if (!strcmp($disprow["disposition"], $row->disposition)) {
       echo "selected ";
     }
     echo "value=\"" . $disprow["disposition"]."\">". $disprow["disposition"] . "</option>\n";
   }
   mysql_free_result($dispresult);
?>
    </select>
    </label>
    </td>

    <td width="100" align=right class="label">Completed</td>
    <td align=left class="text">
       <input type="hidden" name="ts_complete" value="<?php print $row->ts_complete  ?>">
       <input type="text" name="dts_complete" tabindex="124" class="time" size=6 readonly
              <?php if (!$row->disposition || !strcmp($row->disposition, "")) print "disabled"?>
              value="<?php if ($row->ts_complete) print dls_hmstime($row->ts_complete) ?>">
    </td>
</tr>

<!-- ****************************************** -->
<tr>
   <td colspan="3" class="label" align=left></td>
   <?php
   if (!$row->completed) {
     print "<td class=\"label\" colspan=3 valign=top>".
           "<span name=\"release_label\" title=\"This option becomes available after a disposition is set.\">".
           "Release Assigned Units On Incident Completion".
           "<input type=\"checkbox\" checked name=\"release_query\" tabindex=\"62\" disabled value=\"0\">".
           "</span></td>\n";
   }
   else {
     print "<td>&nbsp;</td>\n";
   }
   ?>
</tr>

<!-- ****************************************** -->

<tr>
   <td class="label">&nbsp;</td>
   <td class="label" align="middle">
   <input type="submit" name="save_incident" tabindex="41" value="Save">
   <input type="submit" name="save_incident_closewin" tabindex="42" value="Save & Return">
<?php
  if (!$row->visible && !$row->completed) {
    echo "<input type=\"submit\" name=\"incident_abort\" tabindex=\"43\" value=\"Abort Incident\">\n";
    echo "</td>\n<td class=\"label\" colspan=\"4\" align=\"left\">&nbsp;</td>\n";
  }
  else {
    echo "<input type=\"button\" name=\"cancel_changes\" tabindex=\"43\" value=\"Cancel\" ";
    echo "onClick='if (window.opener){window.opener.location.reload()} self.close()'>\n";
    echo "</td>\n<td class=\"label\" colspan=\"4\" align=\"left\">&nbsp;";
    echo "<NOSCRIPT><B>Warning</B>: Javascript is disabled. Close this incident popup to cancel changes.</NOSCRIPT>";
    echo "</td>\n";
  }
?>

</tr>
</table>
</td></tr>
</table>
</td></tr>
</table>
</td>
</tr>
<!-- whitespace acting as horizontal rule -->

<tr>
<td valign=top width=700>
<table cellspacing=0 cellpadding=0 border> <!-- outer color table for incident notes -->
  <tr><td colspan="2" bgcolor="#bbbbbb" class="text">
  <table cellspacing=1 cellpadding=0>  <!-- layout table for incident notes -->

    <!-- AT THIS POINT, INSERT FRAME OF INCIDENT NOTES -->

    <tr>
       <td colspan=2 class="label" align=left><b>Incident Notes </b></td>
    </tr>

    <tr><td></td></tr>
    <tr><td></td></tr>

    <tr><td class="label">Fr<u>o</u>m:</td>
        <td>
          <label for="note_unit" accesskey="o">
          <select name="note_unit" id="note_unit" tabindex="81">
<?php
    $query="SELECT unit FROM units";
    $formresult = mysql_query($query) or die("In query: $query  <br>\nError: " . mysql_error());

    # TODO: we do a very similar query below - instead, select * here, and dynamically
    # fill a second array if it meets the conditionals for the second query.
    $unitnames = array();
    $unitarray = array();
    while ($unitrow = mysql_fetch_array($formresult, MYSQL_ASSOC)) {
      array_push($unitnames, $unitrow["unit"]);
      $unitarray[$unitrow["unit"]] = $unitrow;
    }
    natsort($unitnames);

    echo "<option selected value=\"\"></option>\n";
    foreach ($unitnames as $u_name) {
      $unitrow = $unitarray[$u_name];
      echo "<option value=\"" . $unitrow["unit"]."\">". $unitrow["unit"] . "</option>\n";
    }

    mysql_free_result($formresult);
?>
         </select>
         </label>
      </td>
    </tr>

    <tr><td></td></tr>

    <tr>
       <td class="label">Note:</td>
         <td>
         <input type="text" name="note_message" id="note_message" tabindex="82" size=80 maxlength=250> &crarr;
       </td>
    </tr>

    <tr><td colspan="2">
        <iframe border=0 frameborder=0 name="notes" tabindex="-1"
         src="incident-notes.php?incident_id=<?php print $incident_id?>"
         width=600 height=274 marginheight=0 marginwidth=0 scrolling="auto"></iframe>
    </td></tr>
  </table>
  </td>
</tr>
</table>

</td>
<td valign=top width=100%>

<!-- units table -->
<table width=100% height=350 cellspacing=0 cellpadding=0 border>
  <tr valign=top><td bgcolor="#bbbbbbbb" class="text">
  <table width=100% cellspacing=0 cellpadding=2>
  <tr valign=top>
    <td colspan=4 align=left valign=top class="label"><b>Units Assigned</b></td>
    </tr><tr>
    </tr><tr>
    <td colspan=4 width=100% class="label">Attach&nbsp;additional&nbsp;<u>u</u>nit:&nbsp;
    <label for="unit_to_attach" accesskey="u">
    <select name="unit_to_attach" id="unit_to_attach" tabindex="101">
<?php
   $unitquery = "SELECT unit FROM units WHERE status <> 'Attached to Incident' OR type='Generic'";
   $unitresult = mysql_query($unitquery) or die ("In query: $unitquery<br>\nError: ".mysql_error());

   $unitnames = array();
   $unitarray = array();
   while ($unitrow = mysql_fetch_array($unitresult, MYSQL_ASSOC)) {
     array_push($unitnames, $unitrow["unit"]);
     $unitarray[$unitrow["unit"]] = $unitrow;
   }
   natsort($unitnames);

   echo "<option selected value=\"\"></option>\n";
   foreach ($unitnames as $u_name) {
     $unitrow = $unitarray[$u_name];
     echo "<option value=\"" . $unitrow["unit"]."\">". $unitrow["unit"] . "</option>\n";
   }
   mysql_free_result($unitresult);
?>
    </select>
    </label>
    <input type="submit" name="attach_unit" tabindex="102" value="Attach">
    </td>
    </tr>
    <tr></tr>
  </table>

  <table width=100% cellspacing=1 cellpadding=0>
      <tr bgcolor="darkgray">
        <td width=100% class="ihsmall">Unit&nbsp;Name</td>
        <td class="ihsmall"><u>Dispatched</u></td>
        <td class="ihsmall">On&nbsp;Scene</td>
        <td class="ihsmall">Released</td>
      </tr>
      <tr><td>

  <?php
     // List units currently attached to this incident
     $attachedunitsquery =
       "SELECT * from incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL ORDER BY dispatch_time DESC";
     $attachedunitsresult = mysql_query($attachedunitsquery) or
       die ("In query: $attachedunitsquery<br>\nError: ".mysql_error());
     if (!mysql_num_rows($attachedunitsresult)) {
             print "<tr><td class=\"messageold\" colspan=\"4\">No units attached</td></tr>";
     }
     while ($line = mysql_fetch_array($attachedunitsresult, MYSQL_ASSOC)) {
       $safe_unit = str_replace(" ", "_", $line["unit"]);

       print "<tr>\n";
       print "<td class=\"message\" align=\"left\">".$line["unit"]."</td>\n";
       print "<td class=\"message\" align=\"right\">".dls_hmstime($line["dispatch_time"])."</td>";

       if (isset($line["arrival_time"]) && $line["arrival_time"] != "") {
         print "<td class=\"message\" align=\"right\">".dls_hmstime($line["arrival_time"])."</td>";
       }
       else {
         print "<td class=\"message\" align=\"right\">".
               "<input type=\"submit\" name=\"arrived_unit_".$line["uid"]."\" tabindex=\"-1\"".
               " style=\"font-size: 10\" value=\"On Scene\">".
               "</td>";
       }

       print "<td class=\"message\" align=right>".
             "<input type=\"submit\" name=\"release_unit_". $line["uid"]."\" tabindex=\"-1\"".
             " style=\"font-size: 10\" value=\"Release\">".
             "</td>";
     }
  ?>

      <tr>
        <td colspan=4 align=left valign=top class="label"><br><b>Units Previously Assigned</b></td>
      </tr>
      <tr bgcolor="darkgray">
        <td width=100% class="ihsmall">Unit&nbsp;Name</td>
        <td class="ihsmall"><u>Dispatched</u></td>
        <td class="ihsmall">On&nbsp;Scene</td>
        <td class="ihsmall">Released</td>
      </tr>

  <?php
     // List units previously attached to this incident
     $prevunitsquery =
       "SELECT * from incident_units WHERE incident_id=$incident_id AND cleared_time IS NOT NULL ORDER BY dispatch_time DESC";
     $prevunitsresult = mysql_query($prevunitsquery) or
       die ("In query: $prevunitsquery<br>\nError: ".mysql_error());
     if (!mysql_num_rows($prevunitsresult)) {
             print "<tr><td class=\"messageold\" colspan=\"4\">No units attached previously</td></tr>";
     }
     while ($line = mysql_fetch_array($prevunitsresult, MYSQL_ASSOC)) {
       $safe_unit = str_replace(" ", "_", $line["unit"]);
       print "<tr>\n";
       print "<td class=\"messageold\" align=\"left\">".$line["unit"]."</td>\n";
       print "<td class=\"messageold\" align=\"right\">".dls_hmstime($line["dispatch_time"])."</td>";
       print "<td class=\"messageold\" align=\"right\">".dls_hmstime($line["arrival_time"])."</td>";
       print "<td class=\"messageold\" align=\"right\">".dls_hmstime($line["cleared_time"])."</td>";
     }
  ?>

</td></tr> </table>
</td></tr> </table>
</td></tr> </table> <!-- outer page table -->

</form>
</body>
</html>

<?php mysql_free_result($incidentdataresult) ?>
<?php mysql_close($link) ?>
