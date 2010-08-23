<?php
  $subsys="incidents";

  require_once('db-open.php');
  require('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');


  if (isset($_POST["incident_abort"])) {
    header('Location: incidents.php');
    exit;
  }
  elseif (isset($_POST["goto_incident"])) {
    $incident_id = (int) MysqlClean($_POST,'goto_incident', 20);
    header("Location: edit-incident.php?incident_id=$incident_id");
    exit;
  }
  elseif (isset($_POST["new_incident"])) {
    $call_details = MysqlClean($_POST, 'call_details', 80);
    $location = MysqlClean($_POST, 'location', 80);
    $call_type = MysqlClean($_POST, 'call_type', 40);
   
    MysqlQuery("LOCK TABLES incidents WRITE");
    // if this fails ... is another incident being created right now?

    MysqlQuery("INSERT INTO incidents (call_details, location, call_type, ts_opened, visible, updated) VALUES ('$call_details', '$location', '$call_type', NOW(), 1, NOW())");
    if (mysql_affected_rows() != 1)
      die("Critical error: When inserting incident, mysql_affected_rows(".mysql_affected_rows().") != 1.");
    $findlastIDquery = "SELECT LAST_INSERT_ID()";
    $findlastIDresult = MysqlQuery($findlastIDquery) or die ("Could not select new incident row LAST_INSERT_ID(): ". mysql_error());
    $newIDrow = mysql_fetch_array($findlastIDresult, MYSQL_NUM);
    $incident_id = $newIDrow[0];
    mysql_free_result($findlastIDresult);
    MysqlQuery("UPDATE incidents SET call_number='" .CallNumber($incident_id) . "' WHERE incident_id=$incident_id ");
    MysqlQuery("UNLOCK TABLES");
    syslog(LOG_INFO, $_SESSION['username'] . " created call [" . CallNumber($incident_id). "] (incident $incident_id)");
    print "
      <html>
      <head>
      <script language=JavaScript>
      function popup(url, name, height, width)
{
  var myWindow = window.open(url,name,'width='+width+',height='+height+',scrollbars')
  // FLAG: TODO: modal=yes is a partially ineffective hack, i'd prefer to have truly modeless persistent windows, but Javascript SUCKS.
  if (myWindow.focus) {
    myWindow.focus()
  }
  return false;
  }
</script>

      </head>

      <body bgcolor=\"#99cc99\">
      New incident has been created (Call #<b>". CallNumber($incident_id)."</b>).
      <p>
      Next steps:
      <li> Request the caller's (reporting party's) ID/name and the following info.
      ";

  if ($call_type == 'INJURY' || $call_type == 'ILLNESS') {
    print "<ul><li>Illness/Injury call: <b> Ask questions of the caller to obtain the SEND Protocol information.</ul>";
  }
  elseif ($call_type == 'FIRE') {
    print "<ul><li>Fire call: <b> Request information about fire size and proximity of exposures.</ul>";
  }
  elseif ($call_type == 'LAW ENFORCEMENT') {
    print "<ul><li> Law Enforcement call: <b>Request information about presence or absence of weapons, etc.</ul>";
  }

  print "
      <li> <b>IF THIS IS AN EMERGENCY CALL, REMIND THE CALLER TO STAY ON SCENE AND ON THIS CHANNEL UNTIL HELP ARRIVES.
      </b>
    <p>
      Continue to the incident screen to enter additional details.
      <form action=\"new-incident.php\" method=\"post\">
      <input type=hidden name=goto_incident value=\"$incident_id\">
      <button type=\"submit\" accesskey=\"g\" Value=\"Go To New Incident\"><u>G</u>o To New Incident</button>
      <button onClick=\"self.close()\" type=\"submit\" accesskey=\"C\" Value=\"Close\"><u>C</u>lose</button>
      </center>
      </body>
      </html>
";
      //<button onClick=\"return popup('edit-incident.php?incident_id=$incident_id','incident-new',width=1000,height=600,scrollbars)\">Go To New Incident</button>
    //header("Location: edit-incident.php?incident_id=$incident_id");
    //print "<a href=\"javascript:return popup('edit-incident.php?incident_id=$incident_id','incident-new',600,1000); self.close();\">Click here to continue to incident</a>";
    exit;
  }

?>

<body bgcolor="#cccc99" onload="document.myform.call_details.focus();" onBlur="self.focus()">
<font face="tahoma,ariel,sans">
<form name="myform" action="new-incident.php" autocomplete="off" method="post">
<input type="hidden" name="new_incident_form">

<?php
  //<!-- <td align=right>
  //<input type="text" name="displayClock" readonly disabled STYLE="color: black; background-color: white" size="6">
  //</td> -->  <!-- todo 1.8: possibly add countdown timer, new incidents should be entered in 30/60 seconds? --> 
?>

<table border cellspacing=0 cellpadding=0  width="100%">
<tr><td bgcolor="#cccc99" class="text" width=100% height="100%">
<table cellpadding=3 cellspacing=0 width="100%" height="100%">
<!-- ****************************************** -->

<tr valign=top>
<td width=100% colspan=3 bgcolor="#993333" class="text">
  <font color="white" size="+1">
  &nbsp;<b> New Incident</b></font>
</td>
</tr>
<tr><td></td></tr>
<tr><td></td></tr>

<tr>
    <td align=right class="label"><b><u>W</u>HAT IS THE PROBLEM?</b>
    </td>

    <td align=left class="text">
    <label for="call_details" accesskey="w">
    <input type="text" name="call_details" id="call_details" tabindex="1" size="50" maxlength="80">
    </lable>
    </td>

    <td style="font-size: 12px; color:gray"> Ask caller for the general description of incident </td>
</tr>
<tr><td></td></tr>
<tr><td></td></tr>

<!-- ****************************************** -->
<tr>
    <td align=right class="label"><b>W<u>H</u>ERE ARE YOU?  (Where is the problem?)</b></td>
    <td align=left class="text">
    <label for="location" accesskey="h">
    <input type="text" name="location" id="location" tabindex="2" size="50" maxlength="80">
    </label>
    </td>

    <td style="font-size: 12px; color:gray"> Ask caller for the location of incident </td>
</tr>
<tr><td></td></tr>
<tr><td></td></tr>
<!-- ****************************************** -->

<tr valign=top>
    <td width="30" align=right class="label"><b><u>T</u>ype&nbsp;Of&nbsp;Call:</b><br><span style="font-size:12px; color:gray">(Dispatcher's choice)</span></td>
    <td width="50" align=left class="text">
      <Label for="call_type" accesskey="t">
      <select name="call_type" id="call_type" tabindex="5" size=12 onChange="handleIncidentType()" onKeyUp="handleIncidentType()">
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

</tr>

<tr><td colspan=4><br></td></tr>

<tr>
   <td class="label">&nbsp;</td>
   <td class="label" align="middle">
   <button type="submit" name="new_incident" tabindex="41" accesskey="S"><u>S</u>ave New Incident</button>
   <button type="submit" name="incident_abort" tabindex="43" accesskey="C"><u>C</u>ancel New Incident</button>
   </td>

</tr>

</table>
</td></tr></table>

</form>
</body>
</html>
