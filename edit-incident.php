<?php
  $subsys="incidents";
  
  require_once('db-open.php');
  require('local-dls.php');
  require_once('session.inc');

  $completed=0;
  if (isset($_POST["cancel_changes"])) {
    print "<SCRIPT LANGUAGE=\"JavaScript\"> window.opener.location.reload(); self.close()</SCRIPT>";
  }
  elseif (isset($_POST["incident_abort"])) {
    if (!isset($_POST["incident_id"])) die("Error while aborting, no incident ID seen in POST.");
    $incident_id = MysqlClean($_POST, "incident_id", 20);
    $query = "DELETE FROM incidents WHERE incident_id=$incident_id";
    mysql_query($query) or die("delete query failed: ".mysql_error());
    print "<SCRIPT LANGUAGE=\"JavaScript\"> window.opener.location.reload(); self.close()</SCRIPT>";
    
    die("(Error: You seem to have Javascript turned off.)  Incident has been aborted as you requested.");
  }
  elseif (isset($_POST["incident_id"])) {
    // Here we have POSTed an incident_id, which for sure means we want to save the POST set... but multiple entry
    // points exist for the form submission.  Valid buttons that could bring us here include:
    // 
    // save_incident
    // note_submit
    // unit_submit
    // add_unit
    // arrived_unit_*
    // release_unit_*
    //
    // If we determine we got here via save_incident, we want to close the window afterwards.  otherwise save and continue editing.
    $reload_not_close=0;

    $incident_id = MysqlClean($_POST, "incident_id", 20);

    // TODO: The logic tying this section to the rest ($authorized) is a little flimsy.  Will still do the other updates
    // you may have come for.  Consider redoing it when you can.
    $query = "SELECT * FROM incidents WHERE incident_id=$incident_id";
    $result = mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
    if (mysql_num_rows($result) != 1) die ("In query: $query<br>\nBad number of rows returned:".mysql_num_rows($result)." (expected 1)\n");
    $oldline = mysql_fetch_array($result, MYSQL_ASSOC);
    mysql_free_result($result);

    $authorized=0;
    if ($oldline["updated"] <> $_POST["updated"]) {
      $changes = array();
      if ($oldline["disposition"] != $_POST["disposition"]) {
        array_push($changes , "Disposition [db: ".$oldline["disposition"]." / you: ".MysqlClean($_POST,"disposition",80));
        }
      if ($oldline["call_type"] != $_POST["call_type"]) {
        array_push($changes , "Call Type [db: ".$oldline["call_type"]." / you: ".MysqlClean($_POST,"call_type",40));
        }
      if ($oldline["call_details"] != $_POST["call_details"]) {
        array_push($changes , "Call Details [db: ".$oldline["call_details"]." / you: ".MysqlClean($_POST,"call_details",80));
        }
      if ($oldline["location"] != $_POST["location"]) {
        array_push($changes , "Location [db: ".$oldline["location"]." / you: ".MysqlClean($_POST,"location",80));
        }
      if ($oldline["reporting_pty"] != $_POST["reporting_pty"]) {
        array_push($changes , "Reporting Party [db: ".$oldline["reporting_pty"]." / you: ".MysqlClean($_POST,"reporting_pty",80));
        }
      if ($oldline["contact_at"] != $_POST["contact_at"]) {
        array_push($changes , "Contact At [db: ".$oldline["contact_at"]." / you: ".MysqlClean($_POST,"contact_at",80));
        }
      if ($oldline["ts_opened"] != $_POST["ts_opened"]) {
        array_push($changes , "Time Opened [db: ".$oldline["ts_opened"]." / you: ".MysqlClean($_POST,"ts_opened",40));
        }
      if ($oldline["ts_dispatch"] != $_POST["ts_dispatch"]) {
        array_push($changes , "Time Dispatched [db: ".$oldline["ts_dispatch"]." / you: ".MysqlClean($_POST,"ts_dispatch",40));
        }
      if ($oldline["ts_arrival"] != $_POST["ts_arrival"]) {
        array_push($changes , "Time Arrival [db: ".$oldline["ts_arrival"]." / you: ".MysqlClean($_POST,"ts_arrival",40));
        }
      if ($oldline["ts_complete"] != $_POST["ts_complete"]) {
        array_push($changes , "Time Completed [db: ".$oldline["ts_complete"]." / you: ".MysqlClean($_POST,"ts_complete",40));
        }

      if (sizeof($changes) > 0) {
        $changemsg = "Warning: incident data in table has changed, you would have overwritten the following found values: ".implode("] ",$changes)."] --- A future update to this code will be able to resolve this conflict automatically.  For now, this screen will abort.  Re-load the incident from the main menu, and re-enter your changes.";
          
        print "<SCRIPT language=\"JavaScript\">alert('$changemsg');</SCRIPT>";
        $authorized=0;
      }
      else
        $authorized = 1;
    }
    else
      $authorized = 1;
    // TODO: end wonky section.


    $ts_dispatch = MysqlClean($_POST, "ts_dispatch", 40);
    $ts_arrival = MysqlClean($_POST, "ts_arrival", 40);
    if (isset($_POST["add_unit"]) && $_POST["add_unit"] != "" && ($ts_dispatch == "" || $ts_dispatch == "0000-00-00 00:00:00"))
      $ts_dispatch = "NOW()";
    else
      $ts_dispatch = "'$ts_dispatch'";

    $ts_arrival = "'$ts_arrival'";
    foreach (array_keys($_POST) as $testkey) {
      if (substr($testkey, 0, 13) == "arrived_unit_") {
        $update_arrived_unit_uid = substr($testkey, 13);
        if ($_POST["ts_arrival"] == "" || $_POST["ts_arrival"] == "0000-00-00 00:00:00") {
          $ts_arrival = "NOW()";
        }
      }
      elseif (substr($testkey,0,13) == "release_unit_") {
        # TODO: clean for taint:
        $update_release_unit_uid = substr($testkey, 13);
      }
    }

    $incident_query = "UPDATE incidents SET call_type='". MysqlClean($_POST,"call_type",40) . "',".
           "call_details='". MysqlClean($_POST,"call_details",80) . "',".
				   "ts_opened='". MysqlClean($_POST,"ts_opened",40) . "',".
				   "ts_dispatch=$ts_dispatch, ts_arrival=$ts_arrival,".
				   "ts_complete='". MysqlClean($_POST,"ts_complete",40) . "',".
           "location='". MysqlClean($_POST,"location",80) . "',".
           "reporting_pty='". MysqlClean($_POST,"reporting_pty",80) . "',".
           "contact_at='". MysqlClean($_POST,"contact_at",80) . "',";
    if (isset($_POST["ts_complete"]) && $_POST["ts_complete"] <> "" && $_POST["ts_complete"] <> "0000-00-00 00:00:00") {
      // Completion time has been entered, so deem the incident complete and perform necessary actions.
      $check_query = "SELECT completed FROM incidents WHERE incident_id=$incident_id";
      $check_result = mysql_query($check_query) or die ("Couldn't check completion: ".mysql_error());
      $row = mysql_fetch_array($check_result, MYSQL_ASSOC);
      $previously_completed = $row["completed"];
      $completed = 1;
      mysql_free_result($check_result);
    }
    if ($completed) {
      $incident_query .= "visible=0, completed=1, ";  // Incident complete, drop off main screen
    }
    else {
      $incident_query .= "visible=1, ";  // Form Submission while Incident active, so put onto screen (important for first full save)
    }
    $incident_query .= "disposition='". MysqlClean($_POST,"disposition",80) . "' ";

    if ($authorized) {
      #$incident_query .= ", updated=NOW() WHERE incident_id=". MysqlClean($_POST,"incident_id",20);
      $incident_query .= ", updated=NOW() WHERE incident_id=$incident_id";
      mysql_query($incident_query) or die("In query: $incident_query<br>\nError: ".mysql_error());
    }

    $release_comment = "Released from Incident #$incident_id at ".date('H:m:s');

    if ($completed && !$previously_completed && isset($_POST["release_query"])) {
      $stackids = array();
      $stackunits = array();
      $query = "SELECT * FROM incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL";
      $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
      if (mysql_num_rows($result) > 0) {
        $i=1;
        while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
          $stackids[$i++] = $row["uid"];
          if (!$row["is_generic"]) $stackunits[$i++] = "'".$row["unit"]."'";
        }
        $uidsin = implode(",", $stackids);
        $unitsin = implode(",", $stackunits);
        mysql_free_result($result);
        
        if ($unitsin != "") {
          $query="UPDATE units SET status='In Service', status_comment='$release_comment', update_ts=NOW() WHERE unit IN ($unitsin)";
          mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());

          foreach ($stackunits as $message_unit) {
            $query="INSERT INTO messages (ts, unit, message) VALUES (NOW(), $message_unit, 'Status Change: In Service (was: Attached to Incident)')";
            mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
          }
        }
  
        if ($uidsin != "") {
          $query = "UPDATE incident_units SET cleared_time=NOW() WHERE uid IN ($uidsin)";
          mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
        }
      }
    }

    // In ADDITION to updating the header fields, if the note submit button was activated, also try to save a new note:
    if (isset($_POST["note_message"]) || isset($_POST["note_submit"])) {
      if (isset($_POST["note_message"]) && $_POST["note_message"] <> "") {
        $unit = MysqlClean($_POST,"note_unit",20);
        $message = MysqlClean($_POST,"note_message",255);

        if (isset($_SESSION['username']) && $_SESSION['username'] != '')
          $creator = $_SESSION['username'];
        else 
          $creator = "";

        $query = "INSERT INTO incident_notes (incident_id, ts, unit, message, creator) VALUES ($incident_id, NOW(), '$unit', '$message', '$creator')";
        mysql_query($query) or die ("Couldn't insert incident note: ".mysql_error());
      }
      $reload_not_close=1;
    }

    // Try on any of the optional ways the form could have been submitted:

    // If it was a unit attachment selection, try to save a new unit:
    if (isset($_POST["unit_submit"])) {
      if (isset($_POST["add_unit"]) && $_POST["add_unit"] != "") {
        $unit = MysqlClean($_POST, "add_unit", 20);
        // Double check that the unit hasn't changed status since the form's SELECT element was compiled.
        $query = "LOCK TABLES units WRITE, incident_units WRITE, messages WRITE";
        mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());

        $query = "SELECT * FROM units where unit='$unit'";
        $result = mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
        if (mysql_num_rows($result) != 1) {
                die("In query: $query<br>\nIncorrect number of rows returned (expecting 1): ".mysql_num_rows($result));
        }
        $unitrow = mysql_fetch_array($result, MYSQL_ASSOC);
        if ($unitrow["status"] != "In Service") {
          $query = "UNLOCK TABLES";
          mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
          ?>
          <html><head>
          <script language="Javascript">
  <!--
  alert ("Unit is not available - <?php print $unitrow["status"]?>")
  window.location = "edit-incident.php?incident_id=<?php print $incident_id?>";
  //-->
  </script>
          <?php
          die ("Unit is not available - ".$unitrow["status"]);
        }
        
        $query = "INSERT INTO incident_units (incident_id, unit, dispatch_time, is_primary, is_generic) VALUES ('$incident_id', '$unit', NOW(), 0";
        if ($unitrow["type"] != "Generic") {
          $query .= ",0)";
          $unitquery = "UPDATE units SET status='Attached to Incident', status_comment='Attached to Incident #$incident_id at ".
                 date('H:m:s') . "', update_ts=NOW() WHERE unit='$unit'";
          mysql_query($unitquery) or die ("In query: $unitquery<br>\nError: ".mysql_error());
          $messagequery="INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Status Change: Attached to Incident (was: In Service)')";
          mysql_query($messagequery) or die("In query: $messagequery<br>\nError: ".mysql_error());
        }
        else
          $query .= ",1)";
        mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());

        $query = "UNLOCK TABLES";
        mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
      }
      $reload_not_close=1;
    }
    
    // If it was a unit arrival submit button, note that:
    if (isset($update_arrived_unit_uid)) {
      $query = "LOCK TABLES incident_units WRITE";
      mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());

      $query = "UPDATE incident_units SET arrival_time=NOW() where uid='$update_arrived_unit_uid'";
      mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());

      $query = "UNLOCK TABLES";
      mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());

      $reload_not_close=1;
    }

    // If it was a unit release submit button, note that:
    if (isset($update_release_unit_uid)) {
      $query = "LOCK TABLES units WRITE, incident_units WRITE, messages WRITE";
      mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());

      $query = "UPDATE incident_units SET cleared_time=NOW() where uid='$update_release_unit_uid'";
      mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
      $query = "SELECT * FROM incident_units where uid='$update_release_unit_uid'";
      $result = mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
      if (mysql_num_rows($result) <> 1) die ("In query: $query<br>\nExpected 1 result row, got: ".mysql_num_rows($result));
      $row = mysql_fetch_array($result, MYSQL_ASSOC);
      $release_unit_name = $row["unit"];
      mysql_free_result($result);

      $query = "UPDATE units SET status='In Service', status_comment='$release_comment' where unit='$release_unit_name'";
      mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());

      $query="INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$release_unit_name', 'Status Change: In Service (was: Attached to Incident)')";
      mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());

      $query = "UNLOCK TABLES";
      mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());

      $reload_not_close=1;
    }

    // If one of the sub-submit functions was not used, consider it a main submit, and go away.
    // TODO: This is NOT the cleanest way to do this. Need to explicitly Close Window.
    if (isset($_POST["save_incident"])) {
      print "<SCRIPT LANGUAGE=\"JavaScript\"> window.opener.location.reload(); self.close()</SCRIPT>";
    }
    elseif (isset($reload_not_close) && $reload_not_close) {
      header("Location: edit-incident.php?incident_id=$incident_id");
    }
    else
      print "<SCRIPT LANGUAGE=\"JavaScript\"> window.opener.location.reload(); self.close()</SCRIPT>";
  }

  elseif (isset($_GET["incident_id"])) {
    if ($_GET["incident_id"] <> "new") {
      $incident_id = MysqlClean($_GET,"incident_id",20);
    }
    else {
      // editing a new incident: create the placeholder first in the database to assign the incident ID number
      $query = "LOCK TABLES incidents WRITE";
      mysql_query($query) or die ("Database could not lock table - is another incident being created right now?<br>Error was: " . mysql_error());

      $query = "INSERT INTO incidents (ts_opened, visible) VALUES (NOW(), 0)";
      mysql_query($query) or die ("Could not create new incident row: ". mysql_error());
      if (mysql_affected_rows() != 1) 
        die("Critical error: ".mysql_affected_rows()." is a bad number of rows when inserting new incident.");
      $query = "SELECT LAST_INSERT_ID()";
      $result = mysql_query($query) or die ("Could not select new incident row: ". mysql_error());
      $row = mysql_fetch_array($result, MYSQL_NUM);
      $incident_id = $row[0];
      mysql_free_result($result);

      $query = "UNLOCK TABLES";
      mysql_query($query) or die ("Could not unlock tables: ". mysql_error());
      header("Location: edit-incident.php?incident_id=$incident_id");
    }
  }
  else {
    die("Internal error: Must specify incident ID in URL request.");
  }

  // ASSERT: the only way to get to this point is to have a numerical GET 
  // value for the incident_id key which is NOT equal to the "new" magic value.
  // Meaning, a user has requested this script to load and display an incident.
  // So now we get it out of the database:
  if (!isset($incident_id)) die ("Critical error: incident_id wasn't set at this point.");
  $query = "SELECT * FROM incidents WHERE incident_id=$incident_id";
  $result = mysql_query($query) or die("select query failed : " . mysql_error());
  if (mysql_num_rows($result) != 1) {
    die("Critical error: ".mysql_num_rows($result)." is a bad number of rows when looking for incident_id $incident_id");
  }
  $row = mysql_fetch_object($result);
?>

<HTML>
<HEAD>
  <TITLE>Dispatch :: Edit Incident #<?php print $incident_id?></TITLE>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA="screen, print">
  <META http-equiv="Content-Script-Type" content="text/javascript">

  <?php include('include-clock.php') ?>

  <script language="JavaScript">

    function stampTimestamp()
    {
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
    function stampFulltime()
    {
      var stampTime = new Date()
      var year = stampTime.getFullYear()
      var month = stampTime.getMonth()+1
      month=((month < 10) ? "0" : "") + month
      var day = stampTime.getDate()
      day=((day < 10) ? "0" : "") + day
      var mytime = year + "-" + month + "-" + day + " " + stampTimestamp()
      return (mytime)
    }


    function stampCompletedTime()
    {
      if (document.myform.disposition.value != "") {
        document.myform.ts_complete.value = stampFulltime();
        document.myform.dts_complete.value = stampTimestamp();
	<?php if (!$row->completed) {
	echo " document.myform.release_query.disabled = 0;\n";
	echo " document.myform.release_query.checked = 1;\n";
	echo " document.myform.release_query.value = 1;\n";
	}
	?>
      }
    }

    function handleDisposition()
    {
      document.myform.dts_complete.disabled = false;
	    stampCompletedTime();
    }

    function unitDispatched()
    {
      //document.myform.dts_dispatch.disabled = true;
      document.myform.dts_arrival.disabled = false;
      document.myform.ts_dispatch.value = stampFulltime();
      document.myform.dts_dispatch.value = stampTimestamp();
      document.myform.ts_arrival.value = "";
      document.myform.dts_arrival.value = "";
    }

    function stampDispatchedTime()
    {
      // TODO: Why is this state checking flaky, and why doesn't unitDispatched() automatically fill this time in??
      if (document.myform.ts_dispatch.value == "") {
        document.myform.ts_dispatch.value = stampFulltime();
        document.myform.dts_dispatch.value = stampTimestamp();
      }
    }

    function stampArrivedTime()
    {
      if (document.myform.ts_dispatch.value != "") {
        document.myform.ts_arrival.value = stampFulltime();
        document.myform.dts_arrival.value = stampTimestamp();
      }
    }

    function addUnit()
    {
      if (document.myform.add_unit != "") {
        var objTable=document.getElementById("unit_table");
        try {
          var newNode = objTable.children(objTable.children.length-1).cloneNode(true);
          with (newNode.children(0).children(0).children(0)) outerHTML=outerHTML.replace(/unit_([0-9]{1,3})/i,function (p1,p2){return "unit_"+(p2*1+1)})
          objTable.appendChild(newNode);
        }
        catch(e) {
          var str = document.myform.add_unit[document.myform.add_unit.selectedIndex].value + '<input type="hidden" name="new_unit_name" value="'+document.myform.add_unit.value+'"><input type="hidden" name="new_unit_dispatch_time" value="'+stampFulltime()+'">';
          var tbody = objTable.getElementsByTagName("TBODY")[0];
          var row = document.createElement("TR");
          var td1 = document.createElement("TD");
          td1.innerHTML=str;
          td1.setAttribute("class", "text");
          /*td1.setAttribute("id", "unit_table_unit_*/
          row.appendChild(td1);
          tbody.appendChild(row);
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
<table width="100%"><tr><td><font color="white" size="+1"><b>Incident #<?php print $incident_id?></b> </font>
<?php 
  if ($row->visible == 0) {
    if ($row->completed) {
      print "&nbsp; &nbsp; <font color=\"#FF0000\"><b>&nbsp; &nbsp;[Completed Incident]</b></font>";
    }
  }
?>
</td>
           <td align=right><input type="text" readonly disabled STYLE="color: black; background-color: white" name="displayClock" size="6"></td>
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
    <td align=right class="label">Details
      <input type="hidden" name="incident_id" value="<?php print $incident_id?>"> </td>
      <input type="hidden" name="updated" value="<?php print $row->updated?>"> </td>

    <td align=left class="text"><input type="text" tabindex="1" size="50" maxlength="80" name="call_details" 
        value="<?php print $row->call_details ?>">

    <td width="30" align=right class="label">Call&nbsp;Type</td>
    <td width="50" align=left class="text">
      <select name="call_type" accesskey="t" tabindex="5">
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
     </td>

    <td width="100" align=right class="label">Received</td>
    <td align=left class="text">
       <input type="hidden" name="ts_opened" value="<?php print $row->ts_opened ?>">
       <input type="text" class="time" size=6 readonly disabled style="color: black" name="dts_opened" value="<?php print date("H:i:s", strtotime($row->ts_opened)) ?>">
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label">Location</td>
    <td align=left colspan=3 class="text"><input tabindex="2" type="text" size="50" maxlength="80" name="location" 
        value="<?php print $row->location  ?>">

    <td width="100" align=right class="label">Dispatched</td>
    <td align=left class="text">
       <input type="hidden" name="ts_dispatch" value="<?php print $row->ts_dispatch  ?>">
       <input type="text" class="time" size=6 readonly disabled style="color: black" 
               onclick="stampDispatchedTime()" name="dts_dispatch" 
               value="<?php if ($row->ts_dispatch) print dls_hmstime($row->ts_dispatch) ?>">
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label">Reporting&nbsp;Party</td>
    <td align=left class="text"><input type="text" tabindex="3" size="50" maxlength="80" name="reporting_pty" 
        value="<?php print $row->reporting_pty  ?>">

    <td class="label"></td><td align=left class="label"><!--a href="">Other Units...</a--></td>

    <td width="100" align=right class="label">Unit&nbsp;On&nbsp;Scene</td>
    <td align=left class="text">
       <input type="hidden" name="ts_arrival" value="<?php print $row->ts_arrival  ?>">
       <input type="text" class="time" size=6 readonly disabled style="color: black" 
               tabindex="96" onkeypress="stampArrivedTime()" onclick="stampArrivedTime()" name="dts_arrival" 
               value="<?php if ($row->ts_arrival) print dls_hmstime($row->ts_arrival) ?>">
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label">Contact&nbsp;At</td>
    <td align=left class="text"><input type="text" tabindex="4" size="50" maxlength="80" name="contact_at" 
        value="<?php print $row->contact_at  ?>">

    <td align=right class="label">Disposition</td>
    <td align=left class="text"><select name="disposition" onchange="handleDisposition()" tabindex="98">
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

    <td width="100" align=right class="label">Completed</td>
    <td align=left class="text">
       <input type="hidden" name="ts_complete" value="<?php print $row->ts_complete  ?>">
       <input type="text" class="time" size=6 readonly <?php if (!$row->disposition || !strcmp($row->disposition, "")) print "disabled"?> 
              tabindex="99" onkeypress="stampCompletedTime()" onclick="stampCompletedTime()" name="dts_complete" 
              value="<?php if ($row->ts_complete) print dls_hmstime($row->ts_complete) ?>">
</tr>

<!-- ****************************************** -->
<tr>
   <td colspan="3" class="label" align=left></td>
   <?php 
   if (!$row->completed) {
     print "<td class=\"label\" colspan=3 valign=top> <p name=\"release_label\">Release Assigned Units On Incident Completion";
     print "<input type=\"checkbox\" disabled tabindex=\"100\" name=\"release_query\" value=\"0\"></td>\n";
   }
   ?>
</tr>

<!-- ****************************************** -->

<tr>
   <td class="label" colspan="6" align="middle">
   <input type="submit" tabindex="50" name="save_incident" value="Save">
<?php 
  if (!$row->visible && !$row->completed)
    echo "<input type=\"submit\" name=\"incident_abort\" tabindex=\"51\" value=\"Abort Incident\">\n";
  else
    echo "<input type=\"submit\" name=\"cancel_changes\" tabindex=\"51\" value=\"Cancel\">\n";
?>
</td>
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
       <td class="label" align=left><b>Incident Notes</b></td>
    </tr>

    <tr><td></td></tr>
    <tr><td></td></tr>

    <tr><td class="label">Add note:
          <select name="note_unit" tabindex="61"> 
<?php
    $query="SELECT unit FROM units ORDER BY unit ASC";
    $formresult = mysql_query($query) or die("In query: $query  <br>\nError: " . mysql_error());

    echo "<option selected value=\"\"></option>\n";
    while ($line = mysql_fetch_array($formresult, MYSQL_ASSOC)) {
       echo "<option value=\"". $line["unit"] ."\">". $line["unit"] ."</option>\n";
    }
    mysql_free_result($formresult);
?> 
         </select>
       </td>
       <td>
         <input type="text" name="note_message"  tabindex="62" size=40 maxlength=250>
         <input type="submit" name="note_submit" tabindex="63" value="Save Note"> 
       <!-- How do we get away without this?  JavaScript OnChange? -->
       </td>
    </tr>
    
    <!-- submit this, will also change the top.  have to onchange() them all to set a global flag, then here, save all if necc? -->
    <!-- one form to rule them all, one form to mind them.  one bool to choose which one, and in the tables bind them. -->
    <tr><td colspan="2">
        <iframe border=0 frameborder=0 name="notes" src="incident-notes.php?incident_id=<?php print $incident_id?>" 
            width=600 height=300 marginheight=0 marginwidth=0 scrolling="yes"></iframe>
    </td></tr>
  </table> <!-- layout table for incident notes -->
  </td>
</tr>
</table> <!-- outer color table for incident notes -->

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
    <td colspan=4 width=100% class="label">Attach&nbsp;additional&nbsp;unit:&nbsp;<select name="add_unit"> 
<?php
   $unitquery = "SELECT unit FROM units WHERE status <> 'Attached to Incident' OR type='Generic' ORDER BY unit";
   $unitresult = mysql_query($unitquery) or die ("In query: $unitquery<br>\nError: ".mysql_error());
   echo "<option selected value=\"\"></option>\n";
   while ($unitrow = mysql_fetch_array($unitresult,MYSQL_ASSOC)) {
     echo "<option value=\"" . $unitrow["unit"]."\">". $unitrow["unit"] . "</option>\n";
   }
   mysql_free_result($unitresult);
?>
</select> <input type="submit" name="unit_submit" value="Attach"></td>
      </tr><tr></tr>
      </table>

  <table width=100% cellspacing=1 cellpadding=0>
      <tr bgcolor="darkgray"> 
        <td width=100% class="ihsmall">Unit Name</td>
        <td class="ihsmall">Dispatched</td>
        <td class="ihsmall">On&nbsp;Scene</td>
        <td class="ihsmall">Released</td>
      </tr>
      <tr><td>
  <?php 
  // checkbox columns:  arrived, cleared
     $newquery = "SELECT * from incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL ORDER BY unit";
     $myresult = mysql_query($newquery) or die ("In query: $newquery<br>\nError: ".mysql_error());
     if (!mysql_num_rows($myresult)) {
             print "<tr><td class=\"message\"><font color=\"gray\" size=\"-1\">No units attached</font></td>\n";
             print "<td class=message><center>-</center>\n";
             print "<td class=message><center>-</center>\n";
             print "<td class=message><center>-</center>\n";
             print "</tr>";
     }
     while ($line = mysql_fetch_array($myresult, MYSQL_ASSOC)) {
       $safe_unit = str_replace(" ", "_", $line["unit"]);
        
       print "<tr>\n";
       print "<td class=\"text\" align=left>".$line["unit"]."</td>\n";
       print "<td class=\"text\" align=right><input type=\"text\" class=\"time\" size=\"6\" readonly disabled name=\"unit_dispatch_".$safe_unit."\" value=\"". dls_hmstime($line["dispatch_time"]) ."\"></td>";

       if (isset($line["arrival_time"]) && $line["arrival_time"] != "") {
          print "<td class=\"text\" align=right><input type=\"text\" class=\"time\" readonly disabled size=\"6\"".
                "name=\"darrived_unit_". $line["unit"]."\" value=\"". dls_hmstime($line["arrival_time"]) ."\"> </td>";
       }
       else {
          print "<td class=\"text\" align=right><input type=\"submit\" name=\"arrived_unit_". $line["uid"]."\" value=\"On Scene\" style=\"font-size: 10\"> </td>";
       }

       print "<td class=\"text\" align=right><input type=\"submit\" name=\"release_unit_". $line["uid"]."\" value=\"Release\" style=\"font-size: 10\"> </td>";
     }
  ?>

</td></tr> </table>
</td></tr> </table>
</td></tr> </table> <!-- outer page table -->

</form>
</body>
</html>

<?php mysql_free_result($result) ?>
<?php mysql_close($link) ?>
