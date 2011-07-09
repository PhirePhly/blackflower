<?php
/* edit-incident.php - 
 * This is executed in a "popup" window. Required GET syntax:
 * edit-incident.php?incident_id=N
 */

  // Initialize subsystem
  $subsys="incidents";

  // Required Files
  require_once('db-open.php');
  require('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  $incident_id = '';
  if (isset($_GET["incident_id"])) {
    // Is this an existing incident?
    if ($_GET["incident_id"] <> "new") {
      $incident_id = (int) MysqlClean($_GET,"incident_id",20);
    }
    else {
      // Ok. We have a new incident (using $AVOID_NEWINCIDENT_DIALOG). 
      // Create the placeholder first in the database to assign the incident ID number.
      MysqlQuery("LOCK TABLES incidents WRITE");
      // if this fails ... is another incident being created right now?  TODO: error handling?

      MysqlQuery("INSERT INTO incidents (ts_opened, visible, updated) VALUES (NOW(), 0, NOW())");
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
   * value for an existing incident_id.
   */
  $incident_id = (int) $incident_id;  // paranoia 1.8.0
  if (!isset($incident_id)) die ("Critical error: assertion failed, incident_id undefined.");
  if ($incident_id < 0) die ("Critical error: incident_id was not a numerical value.");

  $lock_msg="";
  $lock_obtained=0;
  if (isset($USE_INCIDENT_LOCKING) && $USE_INCIDENT_LOCKING) {
    MysqlQuery("LOCK TABLES incident_locks LOW_PRIORITY WRITE, users READ");
    MysqlQuery("DELETE FROM incident_locks WHERE user_id=".(int)$_SESSION['id']." AND session_id='".session_id()."' AND takeover_timestamp IS NOT NULL"); // This may be overly aggressive, but is needed to clean up the POST/alert logic in the short term.
    $incident_lock = MysqlQuery ("SELECT incident_locks.*, users.username FROM incident_locks LEFT OUTER JOIN users on incident_locks.user_id = users.id WHERE incident_id=$incident_id AND takeover_timestamp IS NULL");
    $incident_previously_locked = mysql_num_rows($incident_lock);
    $lock_info = mysql_fetch_object($incident_lock);

    if ($incident_previously_locked == 0) {
      MysqlQuery("INSERT INTO incident_locks (incident_id, user_id, timestamp, ipaddr, session_id) VALUES ($incident_id, ".$_SESSION['id'].", NOW(), '".$_SERVER['REMOTE_ADDR']."', '".session_id()."')");
      if (mysql_affected_rows() == 1) {
        $lock_obtained=1;
        $lock_msg = "Locked for writing.";
      }
      else {
        $lock_msg = "Critical error: Unable to lock incident, contact the system administrator.  mysql_affected_rows = " . mysql_affected_rows();
      }
    }

    elseif ($incident_previously_locked > 1) {
      $lock_msg = "Critical error: expected 0 or 1 locks, incident $incident_id has $incident_previously_locked.";
    }

    elseif ($lock_info->user_id == $_SESSION['id'] && $lock_info->ipaddr == $_SERVER['REMOTE_ADDR'] && $lock_info->session_id == session_id() && $lock_info->takeover_timestamp == '') {
      $lock_obtained=1;
      $lock_msg = "Locked for writing...";
    } 
    
    elseif ($lock_info->takeover_timestamp != '') { // duplicate logic right now wrt post, future safety net for meta refresh
      $takeover_lock_user = MysqlGrabData ("SELECT username FROM incident_locks LEFT OUTER JOIN users on incident_locks.takeover_by_user_id = users.id WHERE lock_id=".$lock_info->lock_id);
      $lock_msg = "<u>" . $takeover_lock_user ."</u> has taken over editing from you";
      $lock_msg2 = "(since ".dls_utime($lock_info->takeover_timestamp) . ", from ".$lock_info->takeover_ipaddr.")";
      MysqlQuery("DELETE FROM incident_locks WHERE lock_id = ". $lock_info->lock_id);
    }
    else {
      if ($lock_info->user_id == $_SESSION['id']) 
        $lock_msg = "You are already editing this incident (in another window?)";
      else 
        $lock_msg = "These fields are read-only while <u>".  $lock_info->username ."</u> is editing the incident";

      $lock_msg2 = "(since ".dls_utime($lock_info->timestamp) . ", from ".$lock_info->ipaddr.")";
    }

    MysqlQuery("UNLOCK TABLES");
  }

  $incidentdataresult = MysqlQuery("SELECT * FROM incidents WHERE incident_id=$incident_id");
  if (mysql_num_rows($incidentdataresult) != 1) {
    die("Critical error: ".mysql_num_rows($incidentdataresult).
        " is a bad number of rows when looking for incident_id $incident_id");
  }
  $row = mysql_fetch_object($incidentdataresult);



  header_html("Dispatch :: Call #" .$row -> call_number,
              "  <script src=\"js/clock.js\" type=\"text/javascript\"></script>\n" .
              "  <script src=\"js/edit-incident.js\" type=\"text/javascript\"></script>\n");
?>

<body onload="displayClockStart()" onunload="displayClockStop()" onBlur="self.focus()" style="margin: 2px;">
<!--<font face="tahoma,ariel,sans">-->
<form name="myform" action="edit-incident-post.php" method="post" style="width: 970px; margin: 0px; padding: 0px">
<table cellspacing=2 cellpadding=0 width="970" style="padding: 0; margin: 0">
<tr>
<?php 

  print "<td colspan=2 bgcolor=\"" .  ($row->completed ?  "#660000" : "darkblue") ."\" class=\"text\">\n";
  print "<table width=\"100%\"><tr><td>\n";

  print "<font color=\"white\" size=\"+1\">\n";
  print "<span title=\"$lock_msg\"> Call #<b>" . $row->call_number . "</b></font>";
  if ($_SESSION['access_level'] >= 10 || $row->call_number == '') {
    print "<font size=\"-1\" color=\"lightgray\"> &nbsp; (Incident $incident_id)</font>";
  }
  if ($row->visible == 0) {
    if ($row->completed) {
      print "&nbsp; &nbsp; <font color=\"#FF0000\"><b>&nbsp; &nbsp;This Incident Is Completed </b></font>";
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
<tr><td width=100%>
<table width=100%>
<!-- ****************************************** -->

<?php 

  function DisabledP($bitwise, $type = 'field', $style = '') {
    $styles = array(
      'field' =>  ' disabled style="disabled; color: black; background-color: #bbbbbb; ' . $style. '"',
      'button' => ' disabled style="disabled; ' . $style . '"');
    if ($bitwise) 
      return $styles[$type];
    else 
      return '';
  }

    $is_locked = 0;
    $is_complete = ($row->completed == 1);

    if (isset($USE_INCIDENT_LOCKING) && $USE_INCIDENT_LOCKING) {
      if ($lock_obtained) {
        print "<tr><Td><input type=hidden name=\"incident_row_locked\" value=\"unlock_on_action\"></td></tr>";
      } 
      else {
        $is_locked = 1;
        print "<tr valign=top style=\"background-color: 993300; color: white\"><td width=95% colspan=15 class=text style=\"white-space: nowrap; padding-bottom: 5px\"><div><b>$lock_msg</b> <font size=\"-2\">$lock_msg2</font> <b>You may only edit notes and units at this time.</b></div>\n";
        print "<div style=\"text-align:right; padding-top: 3px\">Or, if necessary:    \n";
   print "<button type=\"submit\" name=\"try_to_edit\" tabindex=\"01\" title=\"Checks for read-write editing abilities for this incident, which will be granted if the previous editor has completed their work and released the incident.\">Try To Edit (Refresh)</button>&nbsp;";
   print "<button type=\"submit\" name=\"takeover\" tabindex=\"02\" title=\"Forcibly takes over read-write editing mode for this incident, regardless of the previous editor's status.  USE WITH CARE (typically if a browser session has crashed, etc)\">Take Over Editing</button>";
        print "</div></td></tr>\n";
      }
    }
?>

<tr>
    <td align=right class="label">Deta<u>i</u>ls
      <input type="hidden" name="incident_id" value="<?php print $incident_id?>">
      <input type="hidden" name="updated" value="<?php print $row->updated?>">
    </td>

    <td align=left class="text">
    <label for="call_details" accesskey="i">
    <input type="text" name="call_details" id="call_details" tabindex="1" size="50" maxlength="80" 
      <?php print DisabledP($is_locked | $is_complete) ?> value="<?php print MysqlUnClean($row->call_details) ?>">
    </lable>
    </td>

    <td width="30" align=right class="label">Call&nbsp;T<u>y</u>pe</td>
    <td width="50" align=left class="text">
      <Label for="call_type" accesskey="y">
      <select name="call_type" id="call_type" tabindex="5" 
        <?php print DisabledP($is_locked | $is_complete) ?>
      onChange="handleIncidentType()" onKeyUp="handleIncidentType()">
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
       <input type="text" name="dts_opened" tabindex="121" class="time" size=8 readonly disabled style="color: black" 
              value="<?php print dls_dhmtime($row->ts_opened) ?>">
    </td>
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label"><u>L</u>ocation</td>
    <td align=left colspan=3 class="text">
    <label for="location" accesskey="l">
    <input type="text" name="location" id="location" tabindex="2" size="50" maxlength="80" 
      <?php print DisabledP($is_locked | $is_complete) ?>
     value="<?php print MysqlUnClean($row->location)  ?>">
    </label>
    </td>

    <td width="100" align=right class="label">Dispatched</td>
    <td align=left class="text">
       <input type="hidden" name="ts_dispatch" value="<?php print $row->ts_dispatch  ?>">
       <input type="text" name="dts_dispatch" tabindex="122" class="time" size=8 readonly disabled style="color: black"
              value="<?php if ($row->ts_dispatch) print dls_dhmtime($row->ts_dispatch) ?>">
    </td>
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label"><u>R</u>eporting&nbsp;Party</td>
    <td align=left class="text">
    <label for="reporting_pty" accesskey="r">
    <input type="text" name="reporting_pty" id="reporting_pty" tabindex="3" size="50" maxlength="80" 
      <?php print DisabledP($is_locked | $is_complete) ?>
     value="<?php print MysqlUnClean($row->reporting_pty)  ?>">
    </label>
    </td>

    <td class="label"></td><td align=left class="label"><!--a href="">Other Units...</a--></td>

    <td width="100" align=right class="label">Unit&nbsp;On&nbsp;Scene</td>
    <td align=left class="text">
       <input type="hidden" name="ts_arrival" value="<?php print $row->ts_arrival  ?>">
       <input type="text" name="dts_arrival" tabindex="123" class="time" size=8 readonly disabled style="color: black"
              value="<?php if ($row->ts_arrival) print dls_dhmtime($row->ts_arrival) ?>">
    </td>
</tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label"><u>C</u>ontact&nbsp;At</td>
    <td align=left class="text">
    <label for="contact_at" accesskey="c">
    <input type="text" name="contact_at" id="contact_at" tabindex="4" size="50" maxlength="80" 
      <?php print DisabledP($is_locked | $is_complete) ?>
     value="<?php print MysqlUnClean($row->contact_at)  ?>">
    </label>
    </td>

    <td align=right class="label">Dis<u>p</u>osition</td>
    <td align=left class="text">
    <label for="disposition" accesskey="p">
    <select name="disposition" id="disposition" tabindex="61" onChange="handleDisposition()" onKeyUp="handleDisposition()" 
      <?php print DisabledP($is_locked | $is_complete) ?> >
<?php
   $dispresult = MysqlQuery("SELECT disposition FROM incident_disposition_types");
   if (!$row->disposition || !strcmp($row->disposition, ''))
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
       <input type="text" name="dts_complete" tabindex="124" class="time" size=8 readonly
              <?php if (!$row->disposition || !strcmp($row->disposition, "")) print "disabled"?>
              value="<?php if ($row->ts_complete) print dls_dhmtime($row->ts_complete) ?>">
    </td>
</tr>

<!-- ****************************************** -->
<tr>
   <td colspan=2 class="label" align=left>&nbsp;
   <noscript><span style="background-color: #cc9999"><b>Warning</b>: Javascript is disabled. Close this incident popup to cancel changes.</span></noscript>
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

<?php 
   if (isset($USE_INCIDENT_LOCKING) && $USE_INCIDENT_LOCKING && !$lock_obtained) 
     $readonly_disabled = 1;
   else
     $readonly_disabled = 0;
?>
<tr>
   <td class="label" align="right" colspan=2>
   <button type="submit" name="save_incident" tabindex="41" accesskey="1" 
     <?php print DisabledP($readonly_disabled | $is_complete, 'button'); ?> ><u>1</u>  Save (Refresh)</button>
   <button type="submit" name="save_incident_closewin" tabindex="42" value="Save & Close" accesskey="2" 
     <?php print DisabledP($readonly_disabled | $is_complete, 'button'); ?> ><u>2</u>  Save & Close</button>
<?php
  if (!$row->visible && !$row->completed) {
    echo "<button type=\"submit\" name=\"incident_abort\" tabindex=\"43\" accesskey=\"3\"><u>3</u>  Abort Incident</button>\n";
    echo "</td>\n";
  }
  else {
    echo "<button type=\"submit\" name=\"cancel_changes\" tabindex=\"43\" accesskey=\"3\"><u>3</u>  Cancel</button>\n";
    if  ($row->completed) {
      echo "<button type=\"submit\" name=\"reopen_incident\" tabindex=\"44\" accesskey=\"4\"><u>4</u>  Reopen</button> ";
    }
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
<td valign=top width=450>
<table height=350 cellspacing=0 cellpadding=0 border> <!-- outer color table for incident notes -->
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
          <select name="note_unit" id="note_unit" tabindex="81" <?php print DisabledP($is_complete); ?> >
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
         <input type="text" name="note_message" id="note_message" tabindex="82" size=50 maxlength=250
     <?php print DisabledP($is_complete); ?> > &crarr;
       </td>
    </tr>

    <tr><td colspan="2">
        <iframe border=0 frameborder=0 name="notes" tabindex="-1"
         src="incident-notes.php?incident_id=<?php print $incident_id?>"
         width=400 height=274 marginheight=0 marginwidth=0 scrolling="auto"></iframe>
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
    <label for="attach_unit_select" accesskey="u">
    <select name="attach_unit_select" id="attach_unit_select" tabindex="101"
     <?php print DisabledP($is_complete); ?> >
<?php
   $attached_generics = array();
   $ag_query = MysqlQuery("
     SELECT u.unit 
     FROM units u 
     LEFT OUTER JOIN incident_units iu ON u.unit=iu.unit 
     WHERE type='Generic'  
       AND incident_id=$incident_id
       AND cleared_time IS NULL
    ");
   while ($ag_row = mysql_fetch_object($ag_query)) {
     array_push($attached_generics, $ag_row->unit);
   }

   if (count($attached_generics)) 
     $exclude_already_attached_generics = " AND unit NOT IN ('" . implode("','", $attached_generics) . "')";
   else
     $exclude_already_attached_generics = '';

   $unitresult = MysqlQuery("SELECT unit FROM units WHERE (status IN ('In Service', 'Available On Pager') and type != 'Generic') OR (type='Generic' $exclude_already_attached_generics )");

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
    <input type="submit" name="attach_unit" tabindex="102" value="Attach"
     <?php print DisabledP($is_complete); ?> >
    </td>
    </tr>
    <tr></tr>
  </table>

  <table width=100% cellspacing=1 cellpadding=0>
      <tr bgcolor="darkgray">
        <td width=100% class="ihsmall">Unit&nbsp;Name</td>
        <td class="ihsmall"><u>Dispatched</u></td>
        <td class="ihsmall"><u>On&nbsp;Scene</u></td>
        <td class="ihsmall"><u>Transporting</u></td>
        <td class="ihsmall"><u>At&nbsp;Destination</u></td>
        <td class="ihsmall"><u>Cleared</u></td>
      </tr>
      <tr><td>

  <?php
     // List units currently attached to this incident
     $attachedunitsresult = MysqlQuery(
       "SELECT * from incident_units WHERE incident_id=$incident_id AND cleared_time IS NULL ORDER BY dispatch_time DESC");

     if (!mysql_num_rows($attachedunitsresult)) {
             print "<tr><td class=\"messageold\" colspan=\"6\">No units attached</td></tr>";
     }
     while ($line = mysql_fetch_array($attachedunitsresult, MYSQL_ASSOC)) {
      ((int)THIS_PAGETS - date("U", strtotime($line["dispatch_time"]))) < 300 ? $quality="<b>" : $quality="";
       
       $safe_unit = str_replace(" ", "_", $line["unit"]);
       $html_unit = str_replace(" ", "&nbsp;", $line["unit"]);

       print "<tr>\n";
       print "<td class=\"message\" align=\"left\">$quality$html_unit</td>\n";
       print "<td class=\"message\" align=\"right\">$quality".dls_hmtime($line["dispatch_time"])."</td>";

       if (isset($line["arrival_time"]) && $line["arrival_time"] != "") {
         print "<td class=\"message\" align=\"right\">".dls_hmtime($line["arrival_time"])."</td>";
       }
       else {
         print "<td class=\"message\" align=\"right\">".
               "<input type=\"submit\" name=\"arrived_unit_".$line["uid"]."\" tabindex=\"-1\"".
               DisabledP($is_complete, 'field', 'font-size: 10') .
               " style=\"font-size: 10\" value=\"On Scene\">".
               "</td>";
       }

       if (isset($line["transport_time"]) && $line["transport_time"] != "") {
         print "<td class=\"message\" align=\"right\">".dls_hmtime($line["transport_time"])."</td>";
       }
       else {
         print "<td class=\"message\" align=\"right\">".
               "<input type=\"submit\" name=\"transpo_unit_".$line["uid"]."\" tabindex=\"-1\"".
               DisabledP($is_complete, 'field', 'font-size: 10') .
               " style=\"font-size: 10\" value=\"Transport\">".
               "</td>";
       }

       if (isset($line["transportdone_time"]) && $line["transportdone_time"] != "") {
         print "<td class=\"message\" align=\"right\">".dls_hmtime($line["transportdone_time"])."</td>";
       }
       else {
         print "<td class=\"message\" align=\"right\">".
               "<input type=\"submit\" name=\"transdn_unit_".$line["uid"]."\" tabindex=\"-1\"".
               DisabledP($is_complete, 'field', 'font-size: 10') .
               " style=\"font-size: 10\" value=\"At Destination\">".
               "</td>";
       }

       print "<td class=\"message\" align=right>".
             "<input type=\"submit\" name=\"release_unit_". $line["uid"]."\" tabindex=\"-1\"".
               DisabledP($is_complete, 'field', 'font-size: 10') .
             " style=\"font-size: 10\" value=\"Clear\">".
             "</td>";
     }
  ?>

      <tr>
        <td colspan=7 align=left valign=top class="label"><br><b>Units Previously Assigned</b></td>
      </tr>
      <tr bgcolor="darkgray">
        <td width=100% class="ihsmall">Unit&nbsp;Name</td>
        <td class="ihsmall"><u>Dispatched</u></td>
        <td class="ihsmall"><u>On&nbsp;Scene</u></td>
        <td class="ihsmall"><u>Transporting</u></td>
        <td class="ihsmall"><u>At&nbsp;Destination</u></td>
        <td class="ihsmall"><u>Cleared</u></td>
      </tr>

  <?php
     // List units previously attached to this incident
     $prevunitsresult = MysqlQuery(
       "SELECT * from incident_units WHERE incident_id=$incident_id AND cleared_time IS NOT NULL ORDER BY dispatch_time DESC");

     if (!mysql_num_rows($prevunitsresult)) {
             print "<tr><td class=\"messageold\" colspan=\"6\">No units attached previously</td></tr>";
     }
     while ($line = mysql_fetch_array($prevunitsresult, MYSQL_ASSOC)) {
       $safe_unit = str_replace(" ", "_", $line["unit"]);
       $html_unit = str_replace(" ", "&nbsp;", $line["unit"]);
       print "<tr>\n";
       print "<td class=\"messageold\" align=\"left\">$html_unit</td>\n";
       print "<td class=\"messageold\" align=\"right\">".dls_hmtime($line["dispatch_time"])."</td>";
       print "<td class=\"messageold\" align=\"right\">".dls_hmtime($line["arrival_time"])."</td>";
       print "<td class=\"messageold\" align=\"right\">".dls_hmtime($line["transport_time"])."</td>";
       print "<td class=\"messageold\" align=\"right\">".dls_hmtime($line["transportdone_time"])."</td>";
       print "<td class=\"messageold\" align=\"right\">".dls_hmtime($line["cleared_time"])."</td>";
     }
  ?>

</td></tr> </table>
</td></tr> </table>
</td></tr> </table></form></body></html><?php 
mysql_free_result($incidentdataresult);
mysql_close($link);
?>
