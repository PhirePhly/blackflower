<?php
require_once('db-open.php');
include('local-dls.php');
require_once('session.inc');

$subsys="reports";


  if ($_SESSION["access_level"] < 5) {
    header_html('Dispatch :: Access Restricted');
    include('include-title.php');
    print "Access level too low to access Reports page.";
    exit;
  }

// End subclass definition
// Begin main program

if (isset($_GET["message_type"]) && isset($_GET["selected-date"])) {
  $message_type = MysqlClean($_GET,"message_type",20);
  $optional_type_clause = "";
  if ($message_type != "All Messages") {
    $optional_type_clause = "message_type = '$message_type' AND";
  }
  $date = MysqlClean($_GET,"selected-date",20);

  syslog(LOG_INFO, $_SESSION['username'] . " generated messages report");
  $query = "SELECT * FROM messages WHERE $optional_type_clause DATE_FORMAT(ts, '%Y-%m-%d') LIKE '$date%'";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());

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

  <div class="h1">Messages Report</div>
  <font size="-1">
    <div class="text"><ul>Message type: <?php print $message_type;?><br>
    Selected date: <?php print $date ?><br>
    Report written at: <?php print NOW ?> <br>
    </ul><p></font>

  <table><tr><td bgcolor="#aaaaaa">
  <table cellspacing="1">
  <tr>
    <td class="th">Type</td>
    <td class="th">Time Logged</td>
    <td class="th">From</td>
    <td class="th">Message</td>
    <td class="th"><font size="-2">Logged By</font></td></tr>
<?
  while ($line = mysql_fetch_object($result)) {
    print "<tr bgcolor=\"white\"><td class=\"text\">".$line->message_type."</td>\n";
    print "<td class=\"text\">" .date('Y-m-d',strtotime($line->ts))."&nbsp;".date('H:i:s',strtotime($line->ts))."</td\n";
    print "<td class=\"text\">" .$line->unit."</td>\n";
    print "<td class=\"text\">" . $line->message."</td>\n";
    print "<td class=\"text\">" . $line->creator."</td>\n";
    print "</tr>\n\n";
  }
  mysql_free_result($result);

  mysql_close($link);
?>
</table>
</td></tr></table>
<?
} else {
  mysql_close($link);
  die("Unit selection not set.");
}
?>
