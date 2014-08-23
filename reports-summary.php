<?php
require_once('db-open.php');
include('local-dls.php');
include('functions.php');
require_once('session.inc');

$subsys="reports";

if (!CheckAuthByLevel('reports', $_SESSION["access_level"])) {
  header_html('Dispatch :: Access Restricted');
  include('include-title.php');
  print "Access level too low to access Reports page.";
  exit;
}


if (isset($_GET["startdate"]) && isset($_GET["enddate"])) {
  $startdate = MysqlClean($_GET,"startdate",20);
  $enddate = MysqlClean($_GET,"enddate",20);
  $showalldates = $_GET["show-alldates"];
  syslog(LOG_INFO, $_SESSION['username'] . " generated summary report");
  ?>
  <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
  <html>
  <head>
    <title>Dispatch :: Reports</title>
    <meta http-equiv="content-language" content="en" />
    <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
    <link rel="StyleSheet"
          href="style.css"
          type="text/css"
          media="screen, print" />
    <link rel="shortcut icon"
          href="favicon.ico"
          type="image/x-icon" />
  </head>
  <body vlink="blue" link="blue" alink="cyan">
  
    <div class="h1">Summary Report</div>
    <font size="-1">
  <ul>
      <div class="text">
    Start date: <?php print $startdate ?> <br>
    End date: <?php print $enddate ?> <br>
    Report written at: <?php print NOW ?> <br>
    </div>
  </ul><p></font>

  <table><tr><td bgcolor="#aaaaaa">
  <table cellspacing="1">
  <tr><td class="text" bgcolor="#aaaaaa"> Call Type </td>

  <?php

  $types = array();
  $typesquery = MysqlQuery("SELECT call_type FROM incident_types WHERE call_type != 'TRAINING'");
  while ($call_type = mysql_fetch_object($typesquery)) {
    if ($call_type->call_type == '' || $call_type->call_type == 'NULL') {
      $call_type->call_type = 'undefined';
    }
    array_push($types, $call_type->call_type);
  }
  mysql_free_result($typesquery);
  natsort($types);

  $dates = array();
  $datesquery = MysqlQuery("SELECT DISTINCT DATE_FORMAT(ts_opened, '%Y-%m-%d') as canddate FROM incidents WHERE ts_opened >= '$startdate' AND ts_opened <= '$enddate 23:59:59'");
  $lastdate = '';
  while ($date = mysql_fetch_object($datesquery)) {
    $nextdate = date('Y-m-d', strtotime($lastdate)+86400);
    while ($showalldates &&
           $lastdate != '' && 
           $date->canddate != $nextdate &&
           strtotime($date->canddate) <= strtotime($enddate) # may be unnecessary?
           ) {
      print "<td class=\"text\">" . date('n/j', strtotime($nextdate)) . "</td>\n";
      array_push($dates, $nextdate);
      $lastdate = $nextdate;
      $nextdate = date('Y-m-d', strtotime($lastdate)+86400);
    }
    print "<td class=\"th\">" . date('n/j', strtotime($date->canddate)) . "</td>\n";
    array_push($dates, $date->canddate);
    $lastdate = $date->canddate;
  }
  # TODO: interpolate absent dates, depending on GET toggle
  mysql_free_result($datesquery);
  print "<td class=\"text\">Totals:</td></tr>\n";

  $datesumcount = array();
  foreach ($dates as $date) {
    $datesumcount[$date]=0;
  }

  foreach ($types as $call_type) {
    print "<tr bgcolor=\"white\"><td class=\"text\"><b>$call_type</b></td>\n";
    $typesumcount=0;
    foreach ($dates as $date) {
      if ($call_type == 'undefined') {
        $countsquery = MysqlQuery("SELECT count(*) AS cnum FROM incidents WHERE (call_type='' OR call_type IS NULL) AND DATE_FORMAT(ts_opened, '%Y-%m-%d') = '$date'");
      }
      else {
        $countsquery = MysqlQuery("SELECT count(*) AS cnum FROM incidents WHERE call_type='$call_type' AND DATE_FORMAT(ts_opened, '%Y-%m-%d') = '$date'");
      }
      $count = mysql_fetch_object($countsquery);
      print '<td class="text" style="text-align: center">' . $count->cnum. "</td>\n";
      mysql_free_result($countsquery);
      $datesumcount[$date] += $count->cnum;
      $typesumcount += $count->cnum;
    }
    print "<td class=\"text\" style=\"text-align: center; background-color:#cccccc\">" . $typesumcount . "</td>\n";
    print "</tr>\n\n";
  }
  print "<tr></tr><tr><td class=\"text\">Totals:</td>";
  foreach ($dates as $date) {
    print "<td class=\"text\" style=\"text-align: center; background-color:#cccccc\">" .$datesumcount[$date] . "</td>\n";
  }

  mysql_close($link);
  print "</table>\n";
  print "</td></tr></table>\n";
} 

else {
  mysql_close($link);
  die("Date(s) not set.");
}

?>
