<?php
  $subsys="reports";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  header_html("Dispatch :: Reports");
?>
<body vlink="blue" link="blue" alink="cyan">
<?php
  include('include-title.php');
  if (!CheckAuthByLevel('reports', $_SESSION["access_level"])) {
    print "Access level too low to access Reports page.";
    exit;
  }

  // Initialize date arrays for choosers, unit array for unit chooser
  $incidents_dates = array();
  $incidents_types = array();
  $units_dates = array();
  $units = array();
  $message_types = array();
  $unit_filter_sets = array();

  $query = "SELECT DATE_FORMAT(ts_opened,'%Y-%m-%d') as ts_date FROM incidents GROUP BY ts_date DESC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($incidents_dates, $line->ts_date);
  }
  mysql_free_result($result);

  $query = "SELECT DATE_FORMAT(ts, '%Y-%m-%d') as ts_date FROM messages GROUP BY ts_date DESC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($units_dates, $line->ts_date);
  }
  mysql_free_result($result);
  foreach ($incidents_dates as $idate) {
    if (!in_array($idate, $units_dates)) {
      array_push($units_dates, $idate);
    }
  }
  sort($units_dates);

  $query = "SELECT unit FROM units ORDER BY unit ASC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($units, $line->unit);
  }
  natsort($units);
  mysql_free_result($result);

  $query = "SELECT call_type FROM incident_types ORDER BY call_type ASC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($incidents_types, $line->call_type);
  }
  mysql_free_result($result);

  $query = "SELECT message_type FROM message_types ORDER BY message_type ASC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($message_types, $line->message_type);
  }
  mysql_free_result($result);

  $query = "SELECT DISTINCT filter_set_name FROM unit_filter_sets ORDER BY filter_set_name ASC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($unit_filter_sets, $line->filter_set_name);
  }
  mysql_free_result($result);
  ?>

  
<div style="float: left; width: 400px;">

  <div class="rbtn">
  <form style="margin: 0px" name="myform" method="GET" action="reports-summary.php">
  <font class="h2"><u>Incidents Summary Report</u></font><br>

  <table>
  <tr> <td class="text" align=left>Start Date </td>
       <td align=left><SELECT name="startdate">

  <?php
  sort($incidents_dates);
  foreach($incidents_dates as $idate) {
    print "<OPTION ";
    #if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td><td> </td></tr>
  <tr> <td class="text" align=left>End Date </td>
       <td align=left><SELECT name="enddate">

  <?php
  sort($incidents_dates);
  foreach($incidents_dates as $idate) { 
    // This is a poor way of doing it, assumes there are incidents each day and that operational period was continuous through today.  
    // Also not sure of this code.
    // TODO: refactor to only exclude last idate if idate==today.  
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td><td> </td></tr>

  <tr> <td  class="text">Show empty dates?
       <td> <input type="checkbox" name="show-alldates" checked /></tr>
  
  <tr><td></td></tr>
  <tr> <td><input class="btn" type="submit" name="summary_report" value="Get Report" /></td></tr>
  </table>
  </form>
</div>

  <!-- ---------------------------------------------------------------------------------->

  <div class="rbtn">
  <form style="margin: 0px" name="myform" method="GET" action="reports-incidents.php">
  <div class="h2"><u>Incident Details Report</u></div>
  
  <table>
  <tr> <td title="Get report for a specific incident/call number" class="Text" align=left>For Incident # : </td>
       <td title="Get report for a specific incident/call number" align=left><INPUT type=text name="call_number">
  
   <input class="btn" type="submit" name="incidents_report" value="Get Report PDF" /></td></tr>
  </table>
  </form>

  <table>
  <tr> <td colspan=2 align=center> <hr> </td></tr>

  <form style="margin: 0px" name="myform" method="GET" action="reports-incidents.php">
  <tr> <td class="text" align=left>Start Date </td>
       <td align=left><SELECT name="startdate">

  <?php
  sort($incidents_dates);
  foreach($incidents_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td><td> </td></tr>
  <tr> <td class="text" align=left>End Date </td>
       <td align=left><SELECT name="enddate">

  <?php
  sort($incidents_dates);
  foreach($incidents_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>


  </select></td><td> </td></tr>
  <tr><td></td></tr>

  <tr> <td class="text" align=left>Incident Type </td>
       <td align=left><SELECT name="selected-type">
  <?php
  print "<option selected value=\"\">All types</option>\n";
  foreach($incidents_types as $itype) {
    print "<option value=\"".$itype."\">".$itype."</option>\n";
  }
?>
  </select></td><td> </td></tr>
  <tr><td></td></tr>

  <tr> <td class="text">All Incidents For Date</td>
       <td align="left"> <input type="checkbox" name="mode" checked disabled value="report-by-date" /></td></tr>
  <tr> <td  class="text">Exclude TRAINING calls?</td>
       <td> <input type="checkbox" checked name="hidetraining" value="1" /></tr>
  <tr> <td  class="text">New page for each incident?</td>
       <td> <input type="checkbox" checked name="always-pagebreak" value="1" /></tr>

  <tr> <td></td></tr>
  <tr> <td><input class="btn" type="submit" name="incidents_report" value="Get Report PDF" /></td></tr>
  </table>
  </form>
</div>

  <!-- ---------------------------------------------------------------------------------->

  <div class="rbtn">
<form style="margin: 0px" name="myform" method="GET" action="reports-messages.php">
<font class="h2"><u>Message Report</u></font><br>
<table>
  <tr>
    <td class="text" align="left">Message Type</td>
    <td align="left">
      <SELECT name="message_type">
      <OPTION value="All Messages">All Messages</option>
  <?php
  foreach($message_types as $message_type) {
    print "<option value=\"".$message_type."\">".$message_type."</option>\n";
  }
  ?>
  </select></td> <td> </td></tr>

  <tr>
    <td class="text" align="left">Date</td>
    <td align="left"><select name="selected-date">
  <?php
  foreach($units_dates as $idate) {
    print "<option ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td>
    <td></td>
  </tr>
  <tr> <td></td> </tr>
  <tr> <td><input class="btn" type="submit" name="messages_report" value="Get Report PDF" /></td> </tr>

</table>
</form>
</div>

</div> <!-- outer -->
<div style="float: left; width: 20px;">
&nbsp;
</div> <!-- outer -->
<div style="float: left; width: 400px;">
  <div class="rbtn">
<form style="margin: 0px" name="myform" method="GET" action="reports-responsetimes.php">
<font class="h2"><u>Response Times Report</u></font><br>
  <table>
  <tr> <td class="text" align=left>Start Date </td>
       <td align=left><SELECT name="startdate">

  <?php
  sort($incidents_dates); // TODO: these sorts are all massively repetitive and unnecessary, right??
  foreach($incidents_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td><td> </td></tr>
  <tr> <td class="text" align=left>End Date </td>
       <td align=left><SELECT name="enddate">

  <?php
  sort($incidents_dates); // TODO: these sorts are all massively repetitive and unnecessary, right??
  foreach($incidents_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>
  </select></td><td> </td></tr>
  <tr><td></td></tr>

  <tr> <td class="text" align=left>Filter by Unit Set? </td>
       <td align=left><SELECT name="filterset">
  <?php
  print "<option selected value=\"\">All units</option>\n";
  foreach($unit_filter_sets as $setname) {
    print "<option value=\"".$setname."\">".$setname."</option>\n";
  }
?> 
  </select></td><td> </td></tr>
  <tr> <td class="text" align=left>Filter by incident type? </td>
       <td align=left><SELECT name="incidenttypes">
  <?php
  print "<option selected value=\"filter\">Select specific types</option>\n";
  print "<option value=\"all\">All types (except TRAINING)</option>\n";

?>
  </select></td><td> </td></tr>
  <tr><td></td></tr>
  <tr> <td><INPUT class="btn" type="submit" name="units_report" value="Get Report PDF"></td></tr>

  </table>

</form>
</div>

  <!-- ---------------------------------------------------------------------------------->

  <div class="rbtn">
  <form style="margin: 0px" name="myform" method="GET" action="reports-units.php">
  <font class="h2"><u>Unit Details Report</u></font><br>
  <table>
  <tr> <td class="text" align="left">Unit</td>
       <td align="left"><select name="unit">
  <?php
  foreach($units as $unit) {
    print "<OPTION value=\"".$unit."\">".$unit."</option>\n";
  }
  ?>
  </select></td><td> </td></tr>


  <tr> <td class="text" align=left>Start Date </td>
       <td align=left><SELECT name="startdate">

  <?php
  sort($units_dates); // TODO: these sorts are all massively repetitive and unnecessary, right??
  foreach($units_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  <tr> <td class="text" align=left>End Date </td>
       <td align=left><SELECT name="enddate">

  <?php
  foreach($units_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td><td> </td></tr>
  </select></td><td> </td></tr>
  <tr><td></td></tr>
  <tr> <td><INPUT class="btn" type="submit" name="units_report" value="Get Report PDF"></td></tr>

  </table>
  </form>
</div>

  <!-- ---------------------------------------------------------------------------------->

  <div class="rbtn">
  <form style="margin: 0px" name="myform" method="GET" action="reports-utilization.php">
  <font class="h2"><u>Unit Utilization Report</u></font><br>
  <table>

  <tr> <td class="text" align=left>Start Date </td>
       <td align=left><SELECT name="startdate">

  <?php
  sort($incidents_dates); // TODO: these sorts are all massively repetitive and unnecessary, right??
  foreach($incidents_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td><td> </td></tr>
  <tr> <td class="text" align=left>End Date </td>
       <td align=left><SELECT name="enddate">

  <?php
  sort($incidents_dates); // TODO: these sorts are all massively repetitive and unnecessary, right??
  foreach($incidents_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>
  </select></td><td> </td></tr>
  <tr><td></td></tr>

  <tr> <td class="text" align=left>Filter by Unit Set? </td>
       <td align=left><SELECT name="filterset">
  <?php
  print "<option selected value=\"\">All units</option>\n";
  foreach($unit_filter_sets as $setname) {
    print "<option value=\"".$setname."\">".$setname."</option>\n";
  }
?> 
  </select></td><td> </td></tr>
  <tr> <td class="text" align=left>Filter by incident type? </td>
       <td align=left><SELECT name="incidenttypes">
  <?php
  print "<option value=\"filter\">Select specific types</option>\n";
  print "<option selected value=\"all\">All types (except TRAINING)</option>\n";

?>
  </select></td><td> </td></tr>
  <tr><td></td></tr>
  <tr> <td><INPUT class="btn" type="submit" name="units_report" value="Get Report PDF"></td></tr>

  </table>
  </form>
</div>

  <!-- ---------------------------------------------------------------------------------->

</body>
</html>

