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
  require_once('functions.php');

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
    syslog(LOG_INFO, $_SESSION['username'] . " aborted (deleted) incident $incident_id");
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

        MysqlQuery("INSERT INTO incident_notes (incident_id, ts, unit, message, creator) ".
                   "VALUES ($incident_id, NOW(), '$unit', '$message', '$creator')");
        syslog(LOG_INFO, $_SESSION['username'] . " added a note to call " . $oldline["call_number"] . " (incident $incident_id)");
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
          if ($unitrow["type"] == "Generic") {
            $unit_is_generic_bool = 1;
          }
          else {
            $unit_is_generic_bool = 0;
            MysqlQuery("UPDATE units SET status='Attached to Incident', ".
                       "status_comment='Attached to Call #" .  $oldline["call_number"]. " at ".date('H:m:s') . "', ".
                       "update_ts=NOW() WHERE unit='$unit'");
            MysqlQuery("INSERT INTO messages (ts, unit, message, creator) ".
                       "VALUES (NOW(), '$unit', 'Status Change: Attached to Incident (was: ".
                       $unitrow["status"].")', '".$_SESSION['username']."')");
          }
          MysqlQuery("INSERT INTO incident_units (incident_id, unit, dispatch_time, is_primary, is_generic) ".
                     "VALUES ('$incident_id', '$unit', NOW(), 0, $unit_is_generic_bool)");
          MysqlQuery("UNLOCK TABLES");
          syslog(LOG_INFO, $_SESSION['username'] . " attached unit [$unit] to call [".  $oldline["call_number"]."] (incident $incident_id)");

          // If this is the first unit to be attached to this incident, set the dispatched timestamp
          if ($ts_dispatch == "" || $ts_dispatch == "0000-00-00 00:00:00") {
            $ts_dispatch = "NOW()";
          }

          // If there are Auto-Pageout pagers associated with this unit, page them out (insert into
          // paging tables..)  Do this AFTER unlocking the CAD tables, since there may be delays
          // getting the page batches and messages all built and entered.
          if (isset($DB_PAGING_NAME) && isset($USE_PAGING_LINK) && $USE_PAGING_LINK) {
            $pageout_query = MysqlQuery("SELECT * FROM unit_incident_paging WHERE unit='$unit'");
            if (mysql_num_rows($pageout_query)) {
              $paginglink = mysql_connect($DB_PAGING_HOST, $DB_PAGING_USER, $DB_PAGING_PASS) 
                or die("Could not connect : " . mysql_error());
          
              $fromuser = 'CAD Auto Page';
              $ipaddr = $_SERVER['REMOTE_ADDR'];
              $message = ">>> $unit Assigned to Call #" .  $oldline["call_number"];
              if (isset($_POST['location'])) {
                $message .= ' - Location [' . MysqlClean($_POST, 'location', 80) . ']';
              }
  
              if (strlen($message) < 110 && isset($_POST['call_details'])) {
                $message = $message . ' - ' . MysqlClean($_POST, 'call_details', 80);
              }
              if (strlen($message) >= 128) {
                $message = substr($message, 0, 127);
              }
            
              if (!mysql_query("INSERT into $DB_PAGING_NAME.batches (from_user_id, from_ipaddr, orig_message, entered) ".
                               " VALUES (0, '$ipaddr', '$message', NOW() )", $paginglink) || 
                  mysql_affected_rows() != 1) {
                syslog(LOG_WARNING, "Error inserting row into database $DB_PAGING_NAME.batches as [$DB_PAGING_HOST/$DB_PAGING_USER]");
              }
              else {
                $batch_id = mysql_insert_id();
            
                while ($pageout_rcpt = mysql_fetch_object($pageout_query)) {
                  if (!mysql_query("INSERT into $DB_PAGING_NAME.messages (from_user_id, to_pager_id, message) VALUES ".
                                   "(0, " . $pageout_rcpt->to_pager_id . ", '$message')", $paginglink) ||
                      mysql_affected_rows() != 1) {
                    syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.messages as [$DB_PAGING_HOST/$DB_PAGING_USER]");
                  }
                  $msg_id = mysql_insert_id();
                
                  if (!mysql_query("INSERT into $DB_PAGING_NAME.batch_messages (batch_id, msg_id) VALUES ".
                                      "($batch_id, $msg_id)", $paginglink) ||
                      mysql_affected_rows() != 1) {
                    syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.batch_messages as [$DB_PAGING_HOST/$DB_PAGING_USER]");
                  }

                  if (!mysql_query("INSERT into $DB_PAGING_NAME.send_queue (status, msg_id, queued) VALUES ".
                                      "('Queued', $msg_id, NOW())", $paginglink) ||
                      mysql_affected_rows() != 1) {
                    syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.send_queue as [$DB_PAGING_HOST/$DB_PAGING_USER]");
                  }
                }
                syslog(LOG_INFO, $_SESSION['username'] . " auto-paged unit [$unit] to call [" .  $oldline["call_number"].  "] (incident $incident_id) with paging batch $batch_id");
              }
              mysql_close($paginglink);
            }
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
        $unit = mysql_fetch_object( MysqlQuery("SELECT unit FROM incident_units WHERE uid='$update_arrived_unit_uid'") );

        MysqlQuery('LOCK TABLES incident_units WRITE');
        MysqlQuery("UPDATE incident_units SET arrival_time=NOW() where uid='$update_arrived_unit_uid'");
        MysqlQuery('UNLOCK TABLES');
        syslog(LOG_INFO, $_SESSION['username'] . " recorded unit [" . $unit->unit . "] arrival at call [" .  $oldline["call_number"].  "] (incident $incident_id)");
      }

      // If we have a unit that has been released, save the information in the DB
      if (isset($update_release_unit_uid)) {
        MysqlQuery('LOCK TABLES units WRITE, incident_units WRITE, messages WRITE');
        MysqlQuery("UPDATE incident_units SET cleared_time=NOW() where uid='$update_release_unit_uid'");

        $release_unit_name  = MysqlGrabData("SELECT unit FROM incident_units where uid='$update_release_unit_uid'");
        $unit_type = MysqlGrabData("SELECT type FROM units WHERE unit='$release_unit_name'");

        if ($unit_type <> 'Generic') {
          $unitprevstatus = FindPrevUnitStatus($release_unit_name);
          MysqlQuery("UPDATE units SET status='$unitprevstatus', ".
                     "status_comment='Released from Call #" .  $oldline["call_number"]. " at ".date('H:m:s')."', ".
                     "update_ts=NOW() where unit='$release_unit_name'");
          MysqlQuery("INSERT INTO messages (ts, unit, message) ".
                     "VALUES (NOW(), '$release_unit_name', 'Status Change: In Service (was: Attached to Incident)')");
        }

        MysqlQuery("UNLOCK TABLES");
        syslog(LOG_INFO, $_SESSION['username'] . " recorded unit [$release_unit_name] release from call [" .  $oldline["call_number"]. "] (incident $incident_id)");
      }


      // Check to see if we have POSTed a completed incident
      if (isset($_POST["ts_complete"]) && $_POST["ts_complete"] <> "" && $_POST["ts_complete"] <> "0000-00-00 00:00:00") {
        // Mark incident as completed
        $completed = 1;

        // Check the DB for a previous completion of this incident
        $checkcompletedresult = MysqlQuery("SELECT completed FROM incidents WHERE incident_id=$incident_id");
        $checkcompletedrow = mysql_fetch_array($checkcompletedresult, MYSQL_ASSOC);
        $previously_completed = $checkcompletedrow["completed"];
        mysql_free_result($checkcompletedresult);

        // Check to see if units need to be released from the completed incident
        if (!$previously_completed && isset($_POST["release_query"])) {
          syslog(LOG_INFO, $_SESSION['username'] . " marked call [" .  $oldline["call_number"].  "] (incident $incident_id) as complete");

          $stackids = array();
          $stackunits = array();

          $unitsrelresult = MysqlQuery("SELECT * FROM incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL");
          if (mysql_num_rows($unitsrelresult) > 0) {
            $i=1;
            while ($unitsrelrow = mysql_fetch_array($unitsrelresult, MYSQL_ASSOC)) {
              $stackids[$i++] = $unitsrelrow["uid"];
              $stackunits[$i++] = $unitsrelrow["unit"];
            }
            mysql_free_result($unitsrelresult);

            foreach ($stackids as $stackid) {
              MysqlQuery("UPDATE incident_units SET cleared_time=NOW() WHERE uid='$stackid'");
            }

            foreach ($stackunits as $stackunit) {
              $unit_type = MysqlGrabData("SELECT type FROM units WHERE unit='$stackunit'");
              if ($unit_type <> 'Generic') {
                $unitprevstatus = FindPrevUnitStatus($stackunit);
                MysqlQuery("UPDATE units SET status='$unitprevstatus', ".
                           "status_comment='Released from Call #" .  $oldline["call_number"]. " at ".date('H:m:s')."', ".
                           "update_ts=NOW() WHERE unit='$stackunit'");
                MysqlQuery("INSERT INTO messages (ts, unit, message) ".
                           "VALUES (NOW(), '$stackunit', 'Status Change: $unitprevstatus (was: Attached to Incident)')");
                syslog(LOG_INFO, $_SESSION['username'] . " released unit [$stackunit] from call [" .  $oldline["call_number"]. "] (incident $incident_id) upon completion");
              }
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
      MysqlQuery($incidentquery);
      syslog(LOG_INFO, $_SESSION['username'] . " updated call [" .  $oldline["call_number"]. "] (incident $incident_id)");


      // If the save_incident_closewin button was explicitly activated, set
      // the "force close" by clearing the default reload flag.  This is the
      // only way that the window will be closed; otherwise, reload it.

      if (isset($_POST["save_incident_closewin"])) {
        print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
        exit;
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

  // end - save updated incident


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
      MysqlQuery("LOCK TABLES incidents WRITE");
      // if this fails ... is another incident being created right now?

      MysqlQuery("INSERT INTO incidents (ts_opened, visible) VALUES (NOW(), 0)");
      if (mysql_affected_rows() != 1)
        die("Critical error: ".mysql_affected_rows()." is a bad number of rows when inserting new incident.");
      $findlastIDquery = "SELECT LAST_INSERT_ID()";
      $findlastIDresult = MysqlQuery($findlastIDquery) or die ("Could not select new incident row: ". mysql_error());
      $newIDrow = mysql_fetch_array($findlastIDresult, MYSQL_NUM);
      $incident_id = $newIDrow[0];
      mysql_free_result($findlastIDresult);
      MysqlQuery("UPDATE incidents SET call_number='" .CallNumber($incident_id) . "' WHERE incident_id=$incident_id ");
      MysqlQuery("UNLOCK TABLES");
      syslog(LOG_INFO, $_SESSION['username'] . " created call [" . CallNumber($incident_id). "] (incident $incident_id)");
      header("Location: edit-incident.php?incident_id=$incident_id");
      exit;
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

  $incidentdataresult = MysqlQuery("SELECT * FROM incidents WHERE incident_id=$incident_id");
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
  // from the text in their status comment -- TODO: bug 23, others should change this:
  function FindPrevUnitStatus($unit) {
    MysqlQuery("LOCK TABLES status_options READ, messages READ");

    $msg = "";
    if ($msgline = 
      mysql_fetch_array(
        MysqlQuery("SELECT message FROM messages WHERE unit='$unit' ORDER BY oid DESC LIMIT 1"),
        MYSQL_ASSOC)) {
      $msg = $msgline["message"];
    }

    $return = "";
    $statusresult = MysqlQuery("SELECT * from status_options");
    while ($statusline = mysql_fetch_array($statusresult, MYSQL_ASSOC)) {
      if (strpos($msg, $statusline["status"])) $return = $statusline["status"];
    }

    MysqlQuery("UNLOCK TABLES");
    return $return;
  }

  header_html("Dispatch :: Call #" .$row -> call_number,
              "  <script src=\"js/clock.js\" type=\"text/javascript\"></script>\n");
?>
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

function handleDisposition() {
  if (document.myform.disposition.value != "") {
    // We don't want to complete the incident unless it has a defined type assiciated with it
    if (document.myform.call_type.value == "not selected") {
      document.myform.disposition.selectedIndex = 0;
      alert('You must choose a Call Type before marking the incident as Completed.');
    }
    else {
      //alert('type ok setting times and release'); -- debugging code!  don't leave in production checkins...
      // If the completed timestamps do not already have values, fill them in now
      // just in case maybe we're changing the disposition type after completion of the incident
      if ((document.myform.ts_complete.value == "0000-00-00 00:00:00" ||
           document.myform.ts_complete.value == "")
          && document.myform.dts_complete.value == "") {
        document.myform.ts_complete.value = stampFulltime();
        document.myform.dts_complete.value = stampTimestamp();
      }
      // If the release_query checkbox is present, enable
      if (document.myform.release_query != null) {
        document.myform.release_query.disabled = false;
        document.getElementById('mustassign').textContent = ' ';
      }
    }
  }
  else {
    document.myform.ts_complete.value = "0000-00-00 00:00:00";
    document.myform.dts_complete.value = "";
    // If the release_query checkbox is present, disable it
    if (document.myform.release_query != null) {
      document.myform.release_query.disabled = true;
      document.getElementById('mustassign').textContent = '(Must Assign a Disposition first)';
    }
  }
}
</script>

<body onload="displayClockStart()" onunload="displayClockStop()" onBlur="self.focus()">
<font face="tahoma,ariel,sans">
<form name="myform" action="edit-incident.php" method="post">
<table cellspacing=3 cellpadding=0 width="970">
<tr>
<td colspan=2 bgcolor="darkblue" class="text">
<table width="100%"><tr><td>
<?php 
  print "<font color=\"white\" size=\"+1\">\n";
  print "Call #<b>" . $row->call_number . "</b></font>";
  if ($_SESSION['access_level'] >= 10 || $row->call_number == '') {
    print "<font size=\"-1\" color=\"lightgray\"> &nbsp; (Incident $incident_id)</font>";
  }
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
	if (!$row->call_type || !strcmp("not selected", $row->call_type))
	  echo "<option selected value=\"not selected\">not selected</option>\n";

	$type_result = MysqlQuery("SELECT * from incident_types");
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
   $dispresult = MysqlQuery("SELECT disposition FROM incident_disposition_types");
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
   <td colspan=2 class="label" align=left>&nbsp;
   <noscript><b>Warning</b>: Javascript is disabled. Close this incident popup to cancel changes.</noscript>
   </td>
   <?php
   if (!$row->completed) {
     print "<td class=\"label\" rowspan=2 align=right valign=top style=\"padding-top: 5px;\">".
           "<input type=\"checkbox\" checked name=\"release_query\" tabindex=\"62\" disabled value=\"0\">".
           "</td>\n";
     print "<td class=\"label\" rowspan=2 colspan=3 valign=top style=\"padding-top: 5px;\">".
           "Release Assigned Units on Incident Completion<br />".
           "<span id=\"mustassign\">(Must Assign a Disposition first)</span>".
           "</td>\n";
   }
   else {
     print "<td rowspan=2>&nbsp;</td><td rowspan=2 colspan=3>&nbsp;</td>\n";
   }
   ?>
</tr>

<!-- ****************************************** -->

<tr>
   <td class="label">&nbsp;</td>
   <td class="label" align="middle">
   <button type="submit" name="save_incident" tabindex="41" accesskey="1"><u>1</u>  Save</button>
   <button type="submit" name="save_incident_closewin" tabindex="42" value="Save & Return" accesskey="2"><u>2</u>  Save & Return</button>
<?php
  if (!$row->visible && !$row->completed) {
    echo "<button type=\"submit\" name=\"incident_abort\" tabindex=\"43\" accesskey=\"3\"><u>3</u>  Abort Incident</button>\n";
    echo "</td>\n";
  }
  else {
    echo "<button type=\"button\" name=\"cancel_changes\" tabindex=\"43\" accesskey=\"3\" ";
    echo "onClick='if (window.opener){window.opener.location.reload()} self.close()'><u>3</u>  Cancel</button>";
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
    $formresult = MysqlQuery("SELECT unit FROM units");

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
   $unitresult = MysqlQuery("SELECT unit FROM units WHERE status IN ('In Service', 'Available on Pager', 'Busy') OR type='Generic'");

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
     $attachedunitsresult = MysqlQuery(
       "SELECT * from incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL ORDER BY dispatch_time DESC");

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
     $prevunitsresult = MysqlQuery(
       "SELECT * from incident_units WHERE incident_id=$incident_id AND cleared_time IS NOT NULL ORDER BY dispatch_time DESC");

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
