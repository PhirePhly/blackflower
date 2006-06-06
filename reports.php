<?php
  $subsys="reports";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
?>
<HTML>
<HEAD>
  <TITLE>Dispatch :: Reports</TITLE>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA="screen, print">
</head>
<body vlink=blue link=blue alink=cyan>
<?php include('include-title.php');?>


  <?php
  // Initialize date arrays for choosers, unit array for unit chooser
  $incidents_dates = array();
  $units_dates = array();
  $units = array();
  $message_types = array();

  $query = "SELECT DATE_FORMAT(ts_opened,'%Y-%m-%d') as ts_date FROM incidents GROUP BY ts_date DESC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($incidents_dates, $line->ts_date);
  }
  mysql_free_result($result);

  $query = "SELECT DATE_FORMAT(ts, '%Y-%m-%d') as ts FROM messages GROUP BY ts ASC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($units_dates, $line->ts);
  }
  mysql_free_result($result);
  foreach ($incidents_dates as $idate) {
    if (!in_array($idate, $units_dates)) {
      array_push($units_dates, $idate);
    }
  }
  rsort($units_dates);

  $query = "SELECT unit FROM units";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($units, $line->unit);
  }
  mysql_free_result($result);
    
  $query = "SELECT message_type FROM message_types";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    array_push($message_types, $line->message_type);
  }
  mysql_free_result($result);
  ?>

  
  <p>
  <form name="myform" method="GET" action="reports-incidents.php">
  <font class="h1"><u>Incidents Report</u></font><br>
  <ul>
  <table>
  <tr> <td class="text" align=left><b>Date</b> </td>
       <td align=left><SELECT name="selected-date">

  <?php
  foreach($incidents_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td><td> </td></tr>
  <tr><td></td></tr>

  <tr> <td class="text"><b>All Incidents For Date</b></td>
       <td align=left> <INPUT type="checkbox" name="mode" checked disabled value="report-by-date"></input></td></tr>
  <tr> <td  class="text"><b>New page for each incident?</td>
       <td> <INPUT type="checkbox" name="always-pagebreak" value="1"></tr>

  <tr> <td></td></tr>
  <tr> <td><INPUT type="submit" name="incidents_report" value="Get PDF Incidents Report"></td></tr>
  </table>
  </ul>
  </form>

  <!-- ---------------------------------------------------------------------------------->

  <form name="myform" method="GET" action="reports-units.php">
  <font class="h1"><u>Units Report</u></font><br>
  <ul>
  <table>
  <tr> <td class="text" align=left><b>Unit</b></td>
       <td align=left><SELECT name="unit">
  <?php
  foreach($units as $unit) {
    print "<OPTION value=\"".$unit."\">".$unit."</option>\n";
  }
  ?>
  </select></td><td> </td></tr>

  <tr> <td class="text" align=left><b>Date</b></td>
       <td align=left><SELECT name="selected-date">
  <?php
  foreach($units_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td><td> </td></tr>
  <tr><td></td></tr>
  <tr> <td><INPUT type="submit" name="units_report" value="Get PDF Units Report"></td></tr>

  </table>
  </ul>
  </form>

  <!-- ---------------------------------------------------------------------------------->

<form name="myform" method="GET" action="reports-messages.php">
<font class="h1"><u>Message Report</u></font><br>
<ul>
<table>
  <tr> 
    <td class="text" align=left><b>Message Type</b></td>
    <td align=left>
      <SELECT name="message_type">
      <OPTION value="All Messages">All Messages</option>
  <?php
  foreach($message_types as $message_type) {
    print "<OPTION value=\"".$message_type."\">".$message_type."</option>\n";
  }
  ?>
  </select></td> <td> </td></tr>

  <tr> 
    <td class="text" align=left><b>Date</b></td>
    <td align=left><SELECT name="selected-date">
  <?php
  foreach($units_dates as $idate) {
    print "<OPTION ";
    if (date('Y-m-d', time()-86400) == $idate) print "selected ";
    print "value=\"".$idate."\">".date('D', strtotime($idate)) . " ". $idate . "</option>\n";
  }
  ?>

  </select></td>
    <td></td>
  </tr>
  <tr> <td></td> </tr>
  <tr> <td><INPUT type="submit" name="messages_report" value="Get Messages Report"></td> </tr>

</table>
</ul>
</form>

  <!-- ---------------------------------------------------------------------------------->

</body>
</html>

<?php include('include-footer.php'); ?>
