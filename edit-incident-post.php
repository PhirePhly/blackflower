<?php
/* edit-incident-post.php
 * Perform POST actions for edit-incident.php, then redirect that popup window back there, or close it, as appropriate.
 */

  $subsys="incidents";
  require_once('db-open.php');
  require_once('session.inc');
  require_once('functions.php');
  require('local-dls.php');
  SessionErrorIfReadonly();

  /* Debugging */
  if ($DEBUG) {
    $out = print_r($_POST, true);
    syslog (LOG_DEBUG, "POST contents: $out");
  }

  /* Initialize variables */

  $username = "(unknown)";
  if (isset($_SESSION['username']) && $_SESSION['username'] != '')
    $username = $_SESSION['username'];

  $userid = 0;
  if (isset($_SESSION['id']) && (int)$_SESSION['id'] > 0)
    $userid = $_SESSION['id'];

  $incident_id = 0;
  if (!isset($_POST["incident_id"]))
    die("Error while aborting, no incident ID seen in POST.");
  elseif ((int)$_POST["incident_id"] <= 0)
    die("Error while aborting, no incident ID seen in POST.");
  else
    $incident_id = (int)MysqlClean($_POST, "incident_id", 20);

  // $set_ts_dispatch = 0; $set_ts_arrival = 0;    // OBSOLETED 1.11.0
  $set_ts_complete = 0;

  /* Local functions
   * 
   * ************************************************************************
   * DoUnitAction() - shared logic for an attached unit's timestamp actions.
   * TODO: Why are you locking tables here?  Do these REALLY need to be locked?
   * ************************************************************************
   */

  function DoUnitAction($incident_id, $call_number, $unit_id, $logfield, $db_column) {
    global $username;
    $unit_id = (int) $unit_id;
    $unit_name = MysqlGrabData("SELECT unit FROM incident_units WHERE uid='$unit_id'");

    if ($db_column == 'cleared_time') {
      $unit_type = MysqlGrabData("SELECT type FROM units WHERE unit='$unit_name'");
      if ($unit_type <> 'Generic') {
        MysqlQuery('LOCK TABLES units WRITE, incident_units WRITE, messages WRITE, status_options READ');

        // old comment: NASTY HACK to restore unit's previous status based on their status_comment. (or default to In Service).
        // old comment: TODO: issues with bug 23, others should change this.
        // As of 1.12.1, need to ignore the previous status and just set a released unit to In Service.
        $status = MysqlGrabData("SELECT status fROM units WHERE unit='$unit_name'");
        $cleared_time = MysqlGrabData("SELECT cleared_time fROM incident_units WHERE uid='$unit_id'");

        if ($status != 'Attached to Incident' || $cleared_time != '') {
          syslog(LOG_INFO, "ERROR - User $username tried to record unit [$unit_name] $logfield call [$call_number] (incident $incident_id) -- but that unit was not attached!  (status [$status] cleared_time [$cleared_time] -- Simultaneous update?");
          print "<html><body><script language=\"JavaScript\">alert(\"That unit is not attached to the incident.  Did another operator already release them?\"); window.location=\"edit-incident.php?incident_id=$incident_id\"; </script>";
          exit;
        }
        else {
          $unitprevstatus = "In Service";
          $msg = MysqlGrabData("SELECT message FROM messages WHERE unit='$unit_name' ORDER BY oid DESC LIMIT 1");
          if ($msg != '') {
            $statusresult = MysqlQuery("SELECT * from status_options");
            while ($statusline = mysqli_fetch_array($statusresult, MYSQLI_ASSOC)) {
              if (strpos($msg, '(was: '.$statusline["status"].')')) 
                $unitprevstatus = $statusline["status"];
            }
          }
          if ($unitprevstatus == 'Staged At Location') {  // handle special case: don't automatically assume unit will re-stage.
            $unitprevstatus = 'In Service';
          }
          MysqlQuery("
            UPDATE units 
            SET status='$unitprevstatus', 
                status_comment='Released from Call #$call_number at ".date('H:m:s')."', 
                update_ts=NOW() where unit='$unit_name'
            ");
          MysqlQuery("
            INSERT INTO messages (ts, unit, message) 
            VALUES (NOW(), 
                   '$unit_name', 
                   'Status Change: $unitprevstatus (was: Attached to Incident)')
            ");
        }
      }
    }

    else {
      MysqlQuery('LOCK TABLES incident_units WRITE');
    }

    MysqlQuery("UPDATE incident_units SET $db_column=NOW() where uid='$unit_id'");
    MysqlQuery("UNLOCK TABLES");
    syslog(LOG_INFO, "User $username recorded unit [$unit_name] $logfield call [$call_number] (incident $incident_id)");
  }

  /*
   * *******************************************************************
   * Begin handling POSTs themselves.  Expected POST logic indications:
   *
   * abort_incident   - We have a new incident but we want to abort it before saving.
   * cancel_incident  - We have a working incident and we want to discard any changes we made.
   * save_incident    - We have a working incident and we want to save any changes we made.
   * save_incident_closewin - Variant in which we will close the popup after saving.
   * reopen_incident  - We have a closed incident and we want to reopen it for editing.
   * try_to_edit      - We are readonly on this incident and we want to try to open it for editing.
   * takeover_editing - We are readonly on this incident and we want to forcibly take over its editing.
   * attach_unit      - We have a working incident and we want to attach a new unit.
   * arrived_unit_*, transpo_unit_*, transdn_unit_*, release_unit_* - Timestamp button pressed.
   * note_message     - Content was entered in the note_message field before the POST.
   *                    (Any <ENTER> keystroke in a text input in the form could bring us here.)
   */

  /* POST: incident_abort **********************************************/

  if (isset($_POST["incident_abort"])) {
    if (isset($USE_INCIDENT_LOCKING) && $USE_INCIDENT_LOCKING && isset($_POST["incident_row_locked"])) {
      if ($DEBUG) {
        syslog(LOG_DEBUG, "Aborting: Clearing incident_locks on incident_id $incident_id, user_id $userid, session_id ".session_id());
      }
      MysqlQuery ("LOCK TABLES incident_locks WRITE");
      MysqlQuery("
        DELETE FROM incident_locks 
        WHERE incident_id=$incident_id 
          AND user_id=$userid
          AND session_id='" . session_id() . "'
        ");
      MysqlQuery ("UNLOCK TABLES");
    }

    syslog(LOG_INFO, "User $username aborted and deleted incident $incident_id");
    $query = "DELETE FROM incidents WHERE incident_id=$incident_id";
    mysqli_query($link, $query) or die("delete query failed: ".mysqli_error($link));

    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    die("(Error: JavaScript not enabled or not present) Incident has been aborted as you requested.");
    exit;
  }

  /* POST: cancel_changes **********************************************/

  elseif (isset($_POST["cancel_changes"])) {
    if (isset($USE_INCIDENT_LOCKING) && $USE_INCIDENT_LOCKING && isset($_POST["incident_row_locked"])) {
      syslog(LOG_INFO, "Canceling: Clearing incident_locks on incident_id $incident_id, user_id $userid, session_id ".session_id());
      MysqlQuery ("LOCK TABLES incident_locks WRITE");
      MysqlQuery("
        DELETE FROM incident_locks 
        WHERE incident_id=$incident_id 
          AND user_id=$userid 
          AND session_id='" . session_id() . "'
        ");
      MysqlQuery ("UNLOCK TABLES");
    }
    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    die("(Error: JavaScript not enabled or not present) Close this window to Cancel any changes you made.");
    // echo "onClick='if (window.opener){window.opener.location.reload()} self.close()'>";
    exit;
  }


  /* POST: note_message  *************************************************/

  elseif (isset($_POST["save_note"])) {
    if($_POST["note_message"] <> "") {
      $unit = MysqlClean($_POST,"note_unit",20);
      $message = MysqlClean($_POST,"note_message",255);

      MysqlQuery("INSERT INTO incident_notes (incident_id, ts, unit, message, creator) ".
                 "VALUES ($incident_id, NOW(), '$unit', '$message', '$username')");
      syslog(LOG_INFO, "User $username added a note to incident $incident_id");
    }
    header("Location: edit-incident.php?incident_id=$incident_id");
    exit;
  }

  /* POST: takeover ****************************************************/

  elseif (isset($_POST["try_to_edit"])) {
    //if (isset($USE_INCIDENT_LOCKING) && $USE_INCIDENT_LOCKING && isset($_POST["incident_row_locked"])) {
      //MysqlQuery ("LOCK TABLES incident_locks WRITE");
      //$incident_lock_results = MysqlQuery ("
        //SELECT * FROM incident_locks
        //WHERE incident_id=$incident_id 
        //AND takeover_timestamp IS NULL ");
//
      //if (mysqli_num_rows($incident_lock_results) == 1) {
        //$incident_lock = mysqli_fetch_object($incident_lock_results);
        //MysqlQuery(" 
          //UPDATE incident_locks
          //SET takeover_by_userid=$userid,
              //takeover_timestamp=NOW(),
              //takeover_ipaddr='" . $_SERVER['REMOTE_ADDR'].  "'
              //WHERE lock_id=" . $incident_lock->lock_id 
        //);
      //}
//
      //MysqlQuery("
        //INSERT INTO incident_locks (incident_id, user_id, timestamp, ipaddr, session_id)
        //VALUES ($incident_id, 
                //$userid, 
                //NOW(), 
                //'" . $_SERVER['REMOTE_ADDR'].  "', 
                //'" . session_id() . "')
        //");
//
      //MysqlQuery ('UNLOCK TABLES');
      syslog(LOG_INFO, "$username try_to_edit incident $incident_id");
    //}
    header("Location: edit-incident.php?incident_id=$incident_id");
    exit;
  }

  /* POST: takeover ****************************************************/

  elseif (isset($_POST["takeover"])) {
    if (isset($USE_INCIDENT_LOCKING) && $USE_INCIDENT_LOCKING) {
      MysqlQuery ("LOCK TABLES incident_locks WRITE");
      $incident_lock_results = MysqlQuery ("
        SELECT * FROM incident_locks
        WHERE incident_id=$incident_id 
        AND takeover_timestamp IS NULL ");

      if (mysqli_num_rows($incident_lock_results) == 1) {
        $incident_lock = mysqli_fetch_object($incident_lock_results);
        if ($DEBUG) {
          syslog(LOG_DEBUG, "Taking over: Updating old incident_lock for incident_id $incident_id");
        }
        MysqlQuery(" 
          UPDATE incident_locks
          SET takeover_by_userid=$userid,
              takeover_timestamp=NOW(),
              takeover_ipaddr='" . $_SERVER['REMOTE_ADDR'].  "'
              WHERE lock_id=" . $incident_lock->lock_id 
        );
      }
      
      if ($DEBUG) {
        syslog(LOG_DEBUG, "Taking over: Inserting incident_lock for incident_id $incident_id, user_id $userid, session_id ".session_id());
      }
      MysqlQuery("
        INSERT INTO incident_locks (incident_id, user_id, timestamp, ipaddr, session_id)
        VALUES ($incident_id, 
                $userid, 
                NOW(), 
                '" . $_SERVER['REMOTE_ADDR'].  "', 
                '" . session_id() . "'  )
        ");

      MysqlQuery ('UNLOCK TABLES');
      syslog(LOG_INFO, "$username took over incident $incident_id");
    }
    header("Location: edit-incident.php?incident_id=$incident_id");
    exit;
  }

  /* POST: reopen_incident *********************************************/

  elseif (isset($_POST["reopen_incident"])) {
    $complete_date = MysqlGrabData("SELECT ts_complete FROM incidents WHERE incident_id=$incident_id");
    $disposition   = MysqlGrabData("SELECT disposition FROM incidents WHERE incident_id=$incident_id");

    MysqlQuery("
      INSERT INTO incident_notes (incident_id, ts, unit, message, creator) 
      VALUES ($incident_id, 
               NOW(), 
              '$unit', 
              'Incident was reopened (had been closed as $disposition at $complete_date)', 
              '$username')
      ");
    MysqlQuery("
      UPDATE incidents 
      SET incident_status='Open',
          ts_complete='0000-00-00 00:00:00', 
          disposition='',
          duplicate_of_incident_id=null 
      WHERE incident_id=$incident_id
      ");
    MysqlQuery ("LOCK TABLES incident_locks WRITE");
    MysqlQuery("
      DELETE FROM incident_locks 
      WHERE incident_id=$incident_id 
        AND user_id=$userid 
        AND session_id='" . session_id() . "'
      ");
    MysqlQuery ("UNLOCK TABLES");
    syslog(LOG_INFO, "$username reopened incident $incident_id");
    header("Location: edit-incident.php?incident_id=$incident_id");
    exit;
  }
  /* POST: reopen_incident_admin *********************************************/

  elseif (isset($_POST["reopen_incident_admin"])) {
    $complete_date = MysqlGrabData("SELECT ts_complete FROM incidents WHERE incident_id=$incident_id");
    $disposition   = MysqlGrabData("SELECT disposition FROM incidents WHERE incident_id=$incident_id");

    MysqlQuery("
      INSERT INTO incident_notes (incident_id, ts, unit, message, creator) 
      VALUES ($incident_id, 
               NOW(), 
              '', 
              'During supervisor review, incident was reopened by $username for administrative update at $complete_date', 
              '$username')
      ");
    MysqlQuery("
      UPDATE incidents 
      SET incident_status='Open',
          ts_complete='0000-00-00 00:00:00', 
          disposition='',
          duplicate_of_incident_id=null 
      WHERE incident_id=$incident_id
      ");
    MysqlQuery ("LOCK TABLES incident_locks WRITE");
    MysqlQuery("
      DELETE FROM incident_locks 
      WHERE incident_id=$incident_id 
        AND user_id=$userid 
        AND session_id='" . session_id() . "'
      ");
    MysqlQuery ("UNLOCK TABLES");
    syslog(LOG_INFO, "$username reopened incident $incident_id for administrative updates");
    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    die("(Error: JavaScript not enabled or not present) Incident reopened for updates; you may close this window if it does not automatically close.");
    exit;
  }  
  
  /* POST: reviewed_incident *********************************************/

  elseif (isset($_POST["reviewed_incident"])) {
    MysqlQuery("
      INSERT INTO incident_notes (incident_id, ts, unit, message, creator) 
      VALUES ($incident_id, 
               NOW(), 
              '', 
              'Incident was reviewed and content was approved by $username, setting to fully closed.', 
              '$username')
      ");
    MysqlQuery("
      UPDATE incidents 
      SET incident_status='Closed'
      WHERE incident_id=$incident_id
      ");
    MysqlQuery ("LOCK TABLES incident_locks WRITE");
    MysqlQuery("
      DELETE FROM incident_locks 
      WHERE incident_id=$incident_id 
        AND user_id=$userid 
        AND session_id='" . session_id() . "'
      ");
    MysqlQuery ("UNLOCK TABLES");
    syslog(LOG_INFO, "$username reviewed and fully closed incident $incident_id");
    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    die("(Error: JavaScript not enabled or not present) Incident reviewed and closed; you may close this window if it does not automatically close.");
    exit;
  }

  /***********************************************************************/
  // Otherwise, below this point, assume we want to Save Changes.
  // TODO: This is a really bad assumption.  When adding new post variables that aren't yet handled, it saves the incident with blank fields...
  // Prep the standard fields for saving, then evaluate conditionals.

  $result = MysqlQuery("SELECT * FROM incidents WHERE incident_id=$incident_id");
  if (mysqli_num_rows($result) != 1) 
    die ("Critical error: Expected to find 1 database row for incident $incident_id, got " .  mysqli_num_rows($result));
  $oldline = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $call_number = $oldline["call_number"];


  /* POST: (arrived|transpo|transdn|release)_unit_(\w+)  *******************/

  foreach (array_keys($_POST) as $ukey) {
    if (substr($ukey,0,13) == "transpo_unit_") { $uid_transpo = substr($ukey, 13); }
    if (substr($ukey,0,13) == "transdn_unit_") { $uid_transdn = substr($ukey, 13); }
    if (substr($ukey,0,13) == "release_unit_") { $uid_release = substr($ukey, 13); }
    if (substr($ukey,0,13) == "arrived_unit_") { $uid_arrived = substr($ukey, 13);
      if (isset($_POST["ts_arrival"]) && ($_POST["ts_arrival"] == "" || $_POST["ts_arrival"] == "0000-00-00 00:00:00")) {
        $set_ts_arrival = 1;
      }
    }
  }
  if (isset($uid_arrived)) DoUnitAction($incident_id, $call_number, $uid_arrived, 'arrived at', 'arrival_time');
  if (isset($uid_transpo)) DoUnitAction($incident_id, $call_number, $uid_transpo, 'transport begun for', 'transport_time');
  if (isset($uid_transdn)) DoUnitAction($incident_id, $call_number, $uid_transdn, 'transport done for', 'transportdone_time');
  if (isset($uid_release)) DoUnitAction($incident_id, $call_number, $uid_release, 'released from', 'cleared_time');
      

  /* POST: incident has been completed *********************************************/
  // TODO 1.10.x: this logic seems flaky.  Where do we SET incident_status=Dispositioned?  (200 lines below)  Why is it not part of this?
  if (isset($_POST["disposition"]) && $_POST["disposition"] != "" && !(isset($_POST["attach_unit"]))) {
    // TODO: Enforce business logic server side as well as client side: don't set disposition and release units if FORCE_MANUAL_UNIT_RELEASE while units are attached.
    $set_ts_complete = 1;

    // Check for DB completion status of this incident, and if not, do units need to be released?
    $previous_status = MysqlGrabData("SELECT incident_status FROM incidents WHERE incident_id=$incident_id");
    if ($previous_status != 'Dispositioned' && $previous_status != 'Closed' && isset($_POST["release_query"])) {
      syslog(LOG_INFO, "User $username marked call [$call_number] (incident $incident_id) as complete");

      $unitsrelresult = MysqlQuery("SELECT * FROM incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL");
      if (mysqli_num_rows($unitsrelresult) > 0) {
        while ($unitsrelrow = mysqli_fetch_object($unitsrelresult)) {
          DoUnitAction($incident_id, $call_number, $unitsrelrow->uid, 'released from', 'cleared_time');
        }
      }
    }

    $numch = MysqlGrabData ("SELECT count(*) FROM channels WHERE incident_id=$incident_id");
    if ($numch > 0) {
      MysqlQuery ("LOCK TABLES channels WRITE, incident_notes WRITE");
      MysqlQuery ("UPDATE channels SET incident_id=NULL WHERE incident_id=$incident_id");
      MysqlQuery ("INSERT INTO incident_notes (incident_id, ts, unit, message, creator) VALUES ($incident_id, NOW(), '', '$numch channel(s) unassigned from completed incident.', '$username') ");
      MysqlQuery ("UNLOCK TABLES");
    }
    
  }

  /* POST: attach_unit  *************************************************/

  if (isset($_POST["attach_unit"]) && (isset($_POST["attach_unit_select"]) && $_POST["attach_unit_select"] != "")) {
    $unit = MysqlClean($_POST, "attach_unit_select", 20);
    MysqlQuery("LOCK TABLES units WRITE, incident_units WRITE, messages WRITE, unit_staging_assignments WRITE");
    $unit_status      = MysqlGrabData("SELECT status FROM units WHERE unit='$unit'");
    $unit_type        = MysqlGrabData("SELECT type FROM units WHERE unit='$unit'");
    // TODO: do we need to guard this next call with an "if unit_status == staged at location" else = 0?
    // // ERROR if generic and there are multiple!!!!!!!
    $already_attached = MysqlGrabData("SELECT COUNT(*) FROM incident_units 
                                       WHERE incident_id=$incident_id AND unit='$unit' AND cleared_time IS NULL");

    // Guard against simultaneous attachments, and double-safety-net against duplicate attachments of Generics.
    if (($unit_type != 'Generic' && !in_array($unit_status, array("In Service", "Available On Pager", "Staged At Location"))) || 
         $unit_type == "Generic" && $already_attached) 
    {
      MysqlQuery("UNLOCK TABLES");
      if ($unit_type == 'Generic') 
        $context = "- You can only attach a generic unit once to each call.";
      else 
        $context = "($unit_status) - This unit was probably modified simultaneously at another terminal.";

      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> alert(\"Unit is not available $context.\"); window.location=\"edit-incident.php?incident_id=$incident_id\"; </SCRIPT></body></html>\n";
      exit;
    }

    else {
      if ($unit_status == 'Staged At Location' && $unit_type != 'Generic') {
        $staging_assignment_id  = (int)MysqlGrabData("SELECT staging_assignment_id FROM unit_staging_assignments WHERE unit_name='$unit' AND time_reassigned IS NULL");
        if ($staging_assignment_id > 0) {
          MysqlQuery("
            UPDATE unit_staging_assignments SET time_reassigned = NOW() where staging_assignment_id = $staging_assignment_id
            ");
          // TODO: what happens if we try to assign a generic staged unit?  This will pick the first id in set?
          // TODO: if it's generic, don't touch the staging!!
        }
      }

      MysqlQuery("
        INSERT INTO incident_units (incident_id, unit, dispatch_time) 
        VALUES ('$incident_id', 
                '$unit', 
                NOW())
        ");

      if ($unit_type != "Generic") {
        MysqlQuery("
          UPDATE units 
          SET status='Attached to Incident', 
              status_comment='Attached to Call #$call_number at " . date('H:m:s') . "',
              update_ts=NOW() WHERE unit='$unit'
          ");
        MysqlQuery("
          INSERT INTO messages (ts, unit, message, creator) 
          VALUES (NOW(), 
                  '$unit', 
                  'Status Change: Attached to Incident (was: $unit_status)', 
                  '$username')
        ");
      }

      MysqlQuery("UNLOCK TABLES");
      syslog(LOG_INFO, "$username attached unit [$unit] to call [$call_number] (incident $incident_id)");

      // If this is the first unit to be attached (dispatched timestamp wasn't already set), set it.
      // OBSOLETED 1.11.0
      //if (isset($_POST["ts_dispatch"]) && ($_POST["ts_dispatch"] == "" || $_POST["ts_dispatch"] == "0000-00-00 00:00:00")) {
        //$set_ts_dispatch = 1;
      //}

      // Do Auto-Pageout AFTER unlocking CAD tables, since there may be delays entering batches and messages.
      // TODO 1.7: convert to paging API
      if (isset($DB_PAGING_NAME) && isset($USE_PAGING_LINK) && $USE_PAGING_LINK && 
          (!isset($_POST['call_type']) || $_POST['call_type'] != 'TRAINING')) {
        # It is CRITICAL that if this call fails, CAD only abort the *pageout* attempt; it should save the rest of the update data rather than dieing.
        if ($DEBUG) {
          syslog(LOG_DEBUG, "Debug: auto-paging for $unit");
        }
        $paginglink = mysqli_connect($DB_PAGING_HOST, $DB_PAGING_USER, $DB_PAGING_PASS);
        if (!$paginglink) { syslog(LOG_WARNING, "Error connecting to paging db on $DB_PAGING_HOST"); }

        $pageout_query = MysqlQuery("SELECT * FROM unit_incident_paging WHERE unit='$unit'");
        if (mysqli_num_rows($pageout_query) && $paginglink) {
          $fromuser = 'CAD Auto Page';
          $ipaddr = $_SERVER['REMOTE_ADDR'];
          $message = ">>> $unit Assigned to Call #$call_number";

          $call_return = MysqlQuery("SELECT * FROM incidents where incident_id=$incident_id");
          if (mysqli_num_rows($call_return) != 1) {
            syslog(LOG_WARNING, "Error selecting data for auto-paging:  Expected 1 row for incidents id $incident_id, got " . mysqli_num_rows($call_return));
          }
          $inc = mysqli_fetch_object($call_return);
          $db_location = $inc->location;
          $db_call_details = $inc->call_details;

          $db_message = $message;

          if (isset($db_location) && $db_location != '') {
            $db_message .= " - Location [$db_location]";
          }
          // TODO -- Look at this language..  This is why they're mysteriously sometimes getting the details and sometimes not.  Auto-page really needs to go to an API that breaks it apart appropriately.  Also need to look into longer length POCSAG to Apollo pagers.
          if (strlen($db_message) < 110 && isset($db_call_details)) {
            $db_message .= " - $db_call_details";
          }
          if (strlen($db_message) >= 128) {
            $db_message = substr($db_message, 0, 127);
          }

          if (isset($_POST['location']) && $_POST['location'] != '') {                                             # bug 2013-08-28: location is not always coming through in the POST for subsequently assigned units?  or for other users' locked incidents?
            $message .= ' - Location [' . MysqlClean($_POST, 'location', 80) . ']';
          }
          if (strlen($message) < 110 && isset($_POST['call_details'])) {
            $message = $message . ' - ' . MysqlClean($_POST, 'call_details', 80);
          }
          if (strlen($message) >= 128) {
            $message = substr($message, 0, 127);
          }
        
          syslog(LOG_DEBUG, "Auto-Page text if POST data : [$message]");
          syslog(LOG_DEBUG, "Auto-Page text if DB lookup : [$db_message]");

          if (!mysqli_query($link, "INSERT into $DB_PAGING_NAME.batches (from_user_id, from_ipaddr, orig_message, entered) ".
                           " VALUES (0, '$ipaddr', '$message', NOW() )", $paginglink) || mysqli_affected_rows($link) != 1) {
            syslog(LOG_WARNING, "Error inserting row into DB $DB_PAGING_NAME.batches as [$DB_PAGING_HOST/$DB_PAGING_USER]");
          }
          else {
            $batch_id = mysqli_insert_id($link);
            
            while ($pageout_rcpt = mysqli_fetch_object($pageout_query)) {
              if (!mysqli_query($link, "INSERT into $DB_PAGING_NAME.messages (from_user_id, to_person_id, message) VALUES ".
                    "(0, " . $pageout_rcpt->to_person_id . ", '$message')", $paginglink) || mysqli_affected_rows($link) != 1) {
                syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.messages as [$DB_PAGING_HOST/$DB_PAGING_USER]");
              }
              $msg_id = mysqli_insert_id($link);
            
              if (!mysqli_query($link, "INSERT into $DB_PAGING_NAME.batch_messages (batch_id, msg_id) VALUES ".
                                  "($batch_id, $msg_id)", $paginglink) || mysqli_affected_rows($link) != 1) {
                syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.bat_msgs as [$DB_PAGING_HOST/$DB_PAGING_USER]");
              }

              if (!mysqli_query($link, "INSERT into $DB_PAGING_NAME.send_queue (status, msg_id, queued) VALUES ".
                               "('Queued', $msg_id, NOW())", $paginglink) || mysqli_affected_rows($link) != 1) {
                syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.send_queue as [$DB_PAGING_HOST/$DB_PAGING_USER]");
              }
            }
            syslog(LOG_INFO, "User $username auto-paged [$unit] [$call_number (incident $incident_id)] paging batch $batch_id message [$message]");
          }
          mysqli_close($paginglink);
        }
      }
    }
  }

  /* **********************************************************************
   * Main query section begin here
   * **********************************************************************/

  $authorized_to_write_lockable_fields = 0;
  $lockable_column_widths = array(               // TODO: read information_schema instead of using magic numbers.
    'call_type'  => 40,  'call_details'  => 80,
    'location'   => 80,  'reporting_pty' => 80,
    'contact_at' => 80,  'disposition'   => 80 );

  $incidentquery = "UPDATE incidents SET ";                          // Start building incident update.  

  // Obsoleted 1.11.0
  //if ($set_ts_dispatch) $incidentquery .= " ts_dispatch=NOW(), ";    // Always save first unit timestamps if needed, 
  //if ($set_ts_arrival)  $incidentquery .= " ts_arrival=NOW(), ";     // even when text fields are read-only.
  //


  if (!isset($USE_INCIDENT_LOCKING) || !$USE_INCIDENT_LOCKING) {
    $authorized_to_write_lockable_fields = 1;
  }
  elseif (isset($_POST["incident_row_locked"])) {   // In Read-Write mode  // TODO: fragile?  query DB, rather than form?
    MysqlQuery ("LOCK TABLES incident_locks WRITE, users READ");
    // Confirm: Do I have the read-write lock in this session?  If not, show a JS alert and delete any old lock.
    $incident_lock_query = "
        SELECT * FROM incident_locks
        WHERE incident_id=$incident_id 
        AND user_id=$userid
        AND session_id='" . session_id() . "'
      ";
        //AND takeover_timestamp IS NULL    -- added this because we were getting num_rows=2 when trying to post on a stale lock with re-editing.  clear on load instead.
    if ($DEBUG) {
      syslog(LOG_DEBUG, "Selecting lock to confirm read-write incident: $incident_lock_query");
    }
    $incident_lock_results = MysqlQuery($incident_lock_query);

    if (mysqli_num_rows($incident_lock_results) != 1) {
      syslog(LOG_NOTICE, "Read-write privileges [user_id $userid, session ". session_id() . "] disappeared while editing incident $incident_id, number of locks: " . mysqli_num_rows($incident_lock_results));
      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> alert(\"Read-write privileges disappeared while editing incident $incident_id (". mysqli_num_rows($incident_lock_results) . " locks).  DO NOT MANUALLY REFRESH THIS WINDOW (Control-R or equivalent); doing so will cause this error.  Otherwise, contact the system administrator with this message.\"); window.location=\"edit-incident.php?incident_id=$incident_id\"; </SCRIPT></body></html>\n";
      exit;
    }

    $incident_lock = mysqli_fetch_object($incident_lock_results);
    
    if ($incident_lock->takeover_timestamp != '') {     // Alert user and delete the taken-over lock from DB
      $takeover_lock_user = MysqlGrabData ("SELECT username FROM incident_locks LEFT OUTER JOIN users on incident_locks.takeover_by_userid = users.id WHERE lock_id=".$incident_lock->lock_id);
      $lock_msg = "<u>" . $takeover_lock_user ."</u> has taken over editing from you";
      $lock_msg2 = "(since ".dls_utime($incident_lock->takeover_timestamp) . ", from ".$incident_lock->takeover_ipaddr.")";
      if ($DEBUG) {
        syslog(LOG_DEBUG, "Taking over: Clearing incident_lock for lock_id $incident_lock->lock_id on incident_id $incident_id");
      }
      MysqlQuery("DELETE FROM incident_locks WHERE lock_id = ". $incident_lock->lock_id);
      if (isset($_POST["save_incident_closewin"])) 
        $window_function = "if (window.opener){window.opener.location.reload()} self.close();";
      else
        $window_function = "window.location=\"edit-incident.php?incident_id=$incident_id\";";

      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> alert(\"$takeover_lock_user has taken over the editing of this incident.  Any changes you made will be discarded.\"); $window_function </SCRIPT></body></html>\n";
      exit;
    }
    else 
      $authorized_to_write_lockable_fields = 1;

    MysqlQuery ("UNLOCK TABLES");
  }
  //else {  // In Read-Only mode
    // I think this is a NO-OP.
  //}

  if ($authorized_to_write_lockable_fields) {
    foreach (array_keys($lockable_column_widths) as $col_name) {
      if ($oldline[$col_name] != $_POST[$col_name])
      {
        $newcolval = MysqlClean($_POST, $col_name, $lockable_column_widths[$col_name]);
        $incidentquery .= "$col_name = '$newcolval', ";
        syslog(LOG_INFO, "Incident $incident_id / User $username : updating $col_name to [$newcolval]");
      }
    }
    
    if($_POST["disposition"] == "Duplicate") {
      $incidentquery .= "duplicate_of_incident_id = '" . MysqlClean($_POST, 'duplicate_of', 11) . "', ";
    }
      
    if ($set_ts_complete) 
      $incidentquery .= " ts_complete=NOW(), incident_status='Dispositioned', ";
    elseif ($oldline["incident_status"] == 'New') {   
      $incidentquery .= "incident_status='Open', ";
    }
  }

  // Always save updated timestamp, even when read-only.  Enter the master incident query into the DB
  $incidentquery .= " updated=NOW() WHERE incident_id=$incident_id";
  if ($DEBUG) {
    syslog(LOG_DEBUG, "Master incident update query:  $incidentquery");
  }
  MysqlQuery($incidentquery);
  syslog(LOG_INFO, "User $username updated call [$call_number] (incident $incident_id)");

  // Release lock if appropriate: Using locking, In Read-Write mode, and we saw a Save & Close button submit.
  if (isset($USE_INCIDENT_LOCKING) && $USE_INCIDENT_LOCKING && $authorized_to_write_lockable_fields &&
      isset($_POST["incident_row_locked"]) && isset($_POST["save_incident_closewin"]) ) {
    if ($DEBUG) {
      syslog(LOG_DEBUG, "Saw Save&Close: Clearing incident_locks on incident_id $incident_id, user_id $userid, session_id ".session_id());
    }
    MysqlQuery ("LOCK TABLES incident_locks WRITE");
    MysqlQuery("
      DELETE FROM incident_locks 
      WHERE incident_id=$incident_id 
        AND user_id=$userid
        AND session_id='" . session_id() . "'
    ");
    MysqlQuery ("UNLOCK TABLES");
  }

  if (isset($_POST["save_incident_closewin"])) {
    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    exit;
  }
  else {
    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()}</SCRIPT>";
  }

  header("Location: edit-incident.php?incident_id=$incident_id");
?>
