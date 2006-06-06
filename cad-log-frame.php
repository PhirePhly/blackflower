<?php
  $subsys="cad";
  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');

  define("MODE_DEFAULT", "last25");
  define("MODE_ALL", "all");
  define("MODE_TIMERANGE", "hourly");

  if (isset($_COOKIE["cadmode"]))
    $mode = MysqlClean($_COOKIE, "cadmode", 10);
  else
    $mode = MODE_DEFAULT;
   
  if ($mode == MODE_TIMERANGE)  {
    if (isset($_GET["date"])) { $searchdate = MysqlClean($_GET, "date", 20); }
    else $searchdate = THIS_DATE;

    if (isset($_GET["hour"])) {
      $searchhour = MysqlClean($_GET, "hour", 2);
      if ($searchhour == "24") $searchhour = "00";
      elseif ($searchhour == "now") $searchhour = THIS_HOUR;
      else { 
       $searchhour == (int)$searchhour;
       if ($searchhour < 10) $searchhour = "0". $searchhour; // this may still be buggy after change from ts to datetime etc
      }
    }
    else $searchhour = THIS_HOUR;
  }
?> 

<HTML>
<HEAD>
  <META HTTP-EQUIV=Refresh CONTENT="15; URL=<?php 
      print $_SERVER["PHP_SELF"]; 
      if (isset($_GET["search"])) 
        print "?search=". MysqlClean($_GET,"search", 10); 
      if (isset($_GET["hour"])) 
        print "&hour=". MysqlClean($_GET,"hour", 2); 
      if (isset($_GET["date"])) 
        print "&date=". MysqlClean($_GET,"date", 10); ?>"> 
  <TITLE>Dispatch :: Log Viewer</TITLE>
  <BASE target="_parent">
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA="screen, print">
  <SCRIPT  LANGUAGE="JavaScript" TYPE="text/javascript">
<!--
function popup(url,name, height, width)
{
	var newwindow=window.open(url,name,'height='+height+', width='+width);
	if (window.focus) {newwindow.focus()}
	return false;
}
-->
</SCRIPT>
</HEAD>

<BODY vlink=blue link=blue alink=cyan>
<font face="tahoma,ariel,sans">

  <table width="100%">
  <tr>
     <td bgcolor="#aaaaaa">
     <table width="100%" cellpadding=0 cellspacing=1> 
     <tr>
 <?php if (isset($_COOKIE['cad_show_creator'])) { 
     print '      <td bgcolor="#cccccc" class="text"><font size=-2 color="gray">Logged By</font></td>'; } ?>
        <td bgcolor="#cccccc" class="text"><b>Time</b></td>
        <td bgcolor="#cccccc" class="text"><b>Unit</b></td>
 <?php if (isset($_COOKIE['cad_show_message_type']) && $_COOKIE['cad_show_message_type'] == 'yes') { 
     print '      <td bgcolor="#cccccc" class="text"><b>Type</b></td>'; } ?>
        <td width="100%" bgcolor="#cccccc" class="text"><b>Message</b></td>
     </tr>

 <?php
   // Prepare unit query
   $query = "SELECT unit FROM units";
   $unitresult = mysql_query($query) or die("unit query failed: ".mysql_error());
   while ($unitrow = mysql_fetch_array($unitresult, MYSQL_ASSOC)) {
     $unitExists[$unitrow["unit"]] = 1;
   }
   mysql_free_result($unitresult);

   // PREPARE MAIN QUERY
   $query = "SELECT * FROM messages WHERE deleted=0";
   if ($mode == MODE_TIMERANGE) {
     $query .= " AND DATE_FORMAT(ts, '%Y-%m-%d %H') = '$searchdate $searchhour'";
   }
   $query .= " ORDER BY oid DESC";
   $result = mysql_query($query) or die("Query failed : " . mysql_error());
   print "<!-- query was: $query -->\n";

   $td = "<td class=\"message\">"; 

   // MAIN DISPLAY TABLE
   if (mysql_num_rows($result) > 0) {
     for ($i=1; $line = mysql_fetch_array($result, MYSQL_ASSOC); $i++) {
        ((int)THIS_PAGETS - date("U", strtotime($line["ts"]))) < 300 ? $quality="<b>" : $quality="";

        echo "<tr>\n";
        if (isset($_COOKIE['cad_show_creator'])) {
          if (isset($line["creator"]) && $line["creator"] != "NULL" && $line["creator"] != "") {
            echo $td, "<font color=\"gray\">", $line["creator"], "</font></td>\n";
          }
          else { echo $td, "</td>\n"; }
        }

        echo $td, $quality, dls_utime($line["ts"]), "</td>\n";
        if (isset($unitExists[$line["unit"]]))
          echo $td, $quality, "<a href=\"unit.php?unit=",$line["unit"],"\" onClick=\"return popup('unit.php?unit=".$line["unit"]."', 'unit', 500, 700)\">", dls_ustr($line["unit"]), "</a></td>\n";
        else
          echo $td, $quality, dls_ustr($line["unit"]), "</td>\n";

        if (isset($_COOKIE['cad_show_message_type']) && $_COOKIE['cad_show_message_type'] == 'yes') {
          if (isset($line["message_type"]) && $line["message_type"] != "NULL" && $line["message_type"] != "") {
            echo $td, "<font color=\"gray\">", $line["message_type"], "</font></td>\n";
          }
          else { echo $td, "</td>\n"; }
        }

        echo $td, "<table width=100% cellspacing=0 cellpadding=0>\n";
        echo "  <tr><td align=left class=\"message\">", $quality, $line["message"], "</td>\n";
        echo "      <td align=right><a href=\"edit-message.php?oid=".$line["oid"]."\" onClick=\"return popup('edit-message.php?oid=".$line["oid"]."', 'edit-message', 200, 900)\"><font color=\"gray\" size=\"-2\">[edit]</font></a></td>\n";
        echo "  </tr></table></td>\n</tr>\n\n";
        if ($i > 25 && $mode == MODE_DEFAULT) break;
     }
   }
   else {
      echo "\t<tr>\n";
      echo $td, "<center>-</center>";
      echo $td, "<center>-</center>";
      echo $td, "<font color=\"gray\" size=\"-1\">No messages logged during this period</font>";
      echo "\t</tr>\n";
   }
   echo "</table></table>\n";

   echo "<table width=\"100%\"><tr>";

   if ($mode == MODE_TIMERANGE) {
     echo "<td class=\"text\"><table ><tr><td class=\"text\" width=50%>";
     $pieces=preg_split("/-/", $searchdate);
     if ($searchhour==0) { $phour = 23; $pdate = date("Y-m-d", mktime($searchhour, 0, 0, $pieces[1], $pieces[2]-1, $pieces[0])); }
     elseif ($searchhour == 1) { $phour = 24; $pdate = $searchdate; }
     else { $phour = $searchhour-1; $pdate = $searchdate; }

     if ($searchhour==23) { $nhour = 24; $ndate = date("Y-m-d", mktime($searchhour, 0, 0, $pieces[1], $pieces[2]+1, $pieces[0])); }
     else { $nhour = $searchhour+1; $ndate = $searchdate; }

     echo "<td class=\"text\"><a href=\"cad-log-frame.php?search=hour&hour=$phour&date=$pdate\" target=\"_self\">&lt;&lt;</a>&nbsp;</td>\n";
     printf("<td class=\"text\"><table border><tr><td class=\"text\"><b>%s<br>%02d:00-%02d:59</b></td></tr></table></td>\n",$searchdate, $searchhour, $searchhour);
     echo "<td class=\"text\">&nbsp;<a href=\"cad-log-frame.php?search=hour&hour=$nhour&date=$ndate\" target=\"_self\">&gt;&gt;</a></td>\n";
     echo "<td class=\"text\" width=50%></td></tr></table></td>";
     echo "</tr>\n</table>\n";
  }

   mysql_free_result($result);
   mysql_close($link);
 ?></body></html>
