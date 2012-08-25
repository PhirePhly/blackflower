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

  $set_ts_dispatch = 0; $set_ts_arrival = 0; $set_ts_complete = 0;

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

        // NASTY HACK to restore unit's previous status based on their status_comment. (or default to In Service).
        // TODO: issues with bug 23, others should change this.
        $unitprevstatus = "In Service";
        $msg = MysqlGrabData("SELECT message FROM messages WHERE unit='$unit_name' ORDER BY oid DESC LIMIT 1");
        if ($msg != '') {
          $statusresult = MysqlQuery("SELECT * from status_options");
          while ($statusline = mysql_fetch_array($statusresult, MYSQL_ASSOC)) {
            if (strpos($msg, '(was: '.$statusline["status"].')')) 
              $unitprevstatus = $statusline["status"];
          }
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
    mysql_query($query) or die("delete query failed: ".mysql_error());

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
      //if (mysql_num_rows($incident_lock_results) == 1) {
        //$incident_lock = mysql_fetch_object($incident_lock_results);
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

      if (mysql_num_rows($incident_lock_results) == 1) {
        $incident_lock = mysql_fetch_object($incident_lock_results);
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
      SET completed=0, visible=1,
          ts_complete='0000-00-00 00:00:00', 
          disposition='',
          duplicate_of_incident_id=null 
      WHERE incident_id=$incident_id
      ");
    syslog(LOG_INFO, "$username reopened incident $incident_id");
    header("Location: edit-incident.php?incident_id=$incident_id");
    exit;
  }  
  

  /***********************************************************************/
  // Otherwise, below this point, assume we want to Save Changes.
  // Prep the standard fields for saving, then evaluate conditionals.

  $result = MysqlQuery("SELECT * FROM incidents WHERE incident_id=$incident_id");
  if (mysql_num_rows($result) != 1) 
    die ("Critical error: Expected to find 1 database row for incident $incident_id, got " .  mysql_num_rows($result));
  $oldline = mysql_fetch_array($result, MYSQL_ASSOC);
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
      

  /* POST: completed is true *********************************************/
  if (isset($_POST["disposition"]) && $_POST["disposition"] != "") {
    $set_ts_complete = 1;

    // Check for DB completion status of this incident, and if not, do units need to be released?
    $previously_completed = MysqlGrabData("SELECT completed FROM incidents WHERE incident_id=$incident_id");
    if (!$previously_completed && isset($_POST["release_query"])) {
      syslog(LOG_INFO, "User $username marked call [$call_number] (incident $incident_id) as complete");

      $unitsrelresult = MysqlQuery("SELECT * FROM incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL");
      if (mysql_num_rows($unitsrelresult) > 0) {
        while ($unitsrelrow = mysql_fetch_object($unitsrelresult)) {
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
    MysqlQuery("LOCK TABLES units WRITE, incident_units WRITE, messages WRITE");
    $unit_status      = MysqlGrabData("SELECT status FROM units WHERE unit='$unit'");
    $unit_type        = MysqlGrabData("SELECT type FROM units WHERE unit='$unit'");
    $already_attached = MysqlGrabData("SELECT COUNT(*) FROM incident_units 
                                       WHERE incident_id=$incident_id AND unit='$unit' AND cleared_time IS NULL");

    // Guard against simultaneous attachments, and double-safety-net against duplicate attachments of Generics.
    if (($unit_type != 'Generic' && !in_array($unit_status, array("In Service", "Available On Pager"))) || 
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
      if (isset($_POST["ts_dispatch"]) && ($_POST["ts_dispatch"] == "" || $_POST["ts_dispatch"] == "0000-00-00 00:00:00")) {
        $set_ts_dispatch = 1;
      }

      // Do Auto-Pageout AFTER unlocking CAD tables, since there may be delays entering batches and messages.
      // TODO 1.7: convert to paging API
      if (isset($DB_PAGING_NAME) && isset($USE_PAGING_LINK) && $USE_PAGING_LINK && 
          (!isset($_POST['call_type']) || $_POST['call_type'] != 'TRAINING')) {
        # It is CRITICAL that if this call fails, CAD only abort the *pageout* attempt; it should save the rest of the update data rather than dieing.
        if ($DEBUG) {
          syslog(LOG_DEBUG, "Debug: auto-paging for $unit");
        }
        $paginglink = mysql_connect($DB_PAGING_HOST, $DB_PAGING_USER, $DB_PAGING_PASS);
        if (!$paginglink) { syslog(LOG_WARNING, "Error connecting to paging db on $DB_PAGING_HOST"); }

        $pageout_query = MysqlQuery("SELECT * FROM unit_incident_paging WHERE unit='$unit'");
        if (mysql_num_rows($pageout_query) && $paginglink) {
          $fromuser = 'CAD Auto Page';
          $ipaddr = $_SERVER['REMOTE_ADDR'];
          $message = ">>> $unit Assigned to Call #$call_number";
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
                           " VALUES (0, '$ipaddr', '$message', NOW() )", $paginglink) || mysql_affected_rows() != 1) {
            syslog(LOG_WARNING, "Error inserting row into DB $DB_PAGING_NAME.batches as [$DB_PAGING_HOST/$DB_PAGING_USER]");
          }
          else {
            $batch_id = mysql_insert_id();
            
            while ($pageout_rcpt = mysql_fetch_object($pageout_query)) {
              if (!mysql_query("INSERT into $DB_PAGING_NAME.messages (from_user_id, to_person_id, message) VALUES ".
                    "(0, " . $pageout_rcpt->to_person_id . ", '$message')", $paginglink) || mysql_affected_rows() != 1) {
                syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.messages as [$DB_PAGING_HOST/$DB_PAGING_USER]");
              }
              $msg_id = mysql_insert_id();
            
              if (!mysql_query("INSERT into $DB_PAGING_NAME.batch_messages (batch_id, msg_id) VALUES ".
                                  "($batch_id, $msg_id)", $paginglink) || mysql_affected_rows() != 1) {
                syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.bat_msgs as [$DB_PAGING_HOST/$DB_PAGING_USER]");
              }

              if (!mysql_query("INSERT into $DB_PAGING_NAME.send_queue (status, msg_id, queued) VALUES ".
                               "('Queued', $msg_id, NOW())", $paginglink) || mysql_affected_rows() != 1) {
                syslog(LOG_WARNING, "Error inserting row into $DB_PAGING_NAME.send_queue as [$DB_PAGING_HOST/$DB_PAGING_USER]");
              }
            }
            syslog(LOG_INFO, "User $username auto-paged [$unit] [$call_number (incident $incident_id)] paging batch $batch_id");
          }
          mysql_close($paginglink);
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
  if ($set_ts_dispatch) $incidentquery .= " ts_dispatch=NOW(), ";    // Always save first unit timestamps if needed, 
  if ($set_ts_arrival)  $incidentquery .= " ts_arrival=NOW(), ";     // even when text fields are read-only.


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

    if (mysql_num_rows($incident_lock_results) != 1) {
      syslog(LOG_NOTICE, "Read-write privileges disappeared while editing incident $incident_id, number of locks: " . mysql_num_rows($incident_lock_results));
      print "<html><body><SCRIPT LANGUAGE=\"JavaScript\"> alert(\"Read-write privileges disappeared while editing incident $incident_id (". mysql_num_rows($incident_lock_results) . " locks).  DO NOT MANUALLY REFRESH THIS WINDOW (Control-R or equivalent); doing so will cause this error.  Otherwise, contact the system administrator with this message.\"); window.location=\"edit-incident.php?incident_id=$incident_id\"; </SCRIPT></body></html>\n";
      exit;
    }

    $incident_lock = mysql_fetch_object($incident_lock_results);
    
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
        $incidentquery .= "$col_name = '" .  MysqlClean($_POST, $col_name, $lockable_column_widths[$col_name]) . "', ";
    }
    
    if($_POST["disposition"] == "Duplicate") {
      $incidentquery .= "duplicate_of_incident_id = '" . MysqlClean($_POST, 'duplicate_of', 11) . "', ";
    }
      
    if ($set_ts_complete) $incidentquery .= " ts_complete=NOW(), visible=0, completed=1, ";
    if (!$set_ts_complete && $oldline["visible"] == 0) {   // Make visible after first full save for $AVOID_NEWINCIDENT_DIALOG
      $incidentquery .= "visible=1, ";
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
