<?php
require_once('db-open.php');
include('local-dls.php');
require_once('session.inc');

$subsys="reports";

// End subclass definition
// Begin main program

if (isset($_GET["message_type"]) && isset($_GET["selected-date"])) {
  $message_type = MysqlClean($_GET,"message_type",20);
  $optional_type_clause = "";
  if ($message_type != "All Messages") {
    $optional_type_clause = "message_type = '$message_type' AND";
  }
  $date = MysqlClean($_GET,"selected-date",20);
  
  $query = "SELECT * FROM messages WHERE $optional_type_clause DATE_FORMAT(ts, '%Y-%m-%d') LIKE '$date%'";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());

  ?>

<HTML>
<HEAD>
  <TITLE>Dispatch :: Reports</TITLE>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA="screen, print">
</HEAD>

<body vlink=blue link=blue alink=cyan>

  <div class="h1">Messages Report</div>
  <font size=-1>
    <div class="text"><ul>Message type: <?php print $message_type;?><br>
    Selected date: <?php print $date ?></ul><p></font>
  <table><tr><td bgcolor=#aaaaaa>
  <TABLE cellspacing=1>
  <tr>
    <td class="th">Type</td>
    <td class="th">Time Logged</td>
    <td class="th">From</td>
    <td class="th">Message</td>
    <td class="th"><font size=-2>Logged By</font></td></tr>
  <?php
  while ($line = mysql_fetch_object($result)) {
    print "<tr bgcolor=white><td class=text>".$line->message_type."</td>\n";
    print "<td class=text>" .date('Y-m-d',strtotime($line->ts))."&nbsp;".date('H:i:s',strtotime($line->ts))."</td\n";
    print "<td class=text>" .$line->unit."</td>\n";
    print "<td class=text>" . $line->message."</td>\n";
    print "<td class=text>" . $line->creator."</td>\n";
    print "</tr>\n\n";
  }
  mysql_free_result($result);

  mysql_close($link);

  ?>

</TABLE>
</td></tr></table>

  <?php
}
else 
{
  mysql_close($link);
  die("Unit selection not set.");
}

?>
