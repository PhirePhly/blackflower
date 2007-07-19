<?php
  $subsys="cad";
  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  // Prepare page GET/POST input

  // Filter by date
  if ($_GET['date'] != "") {
    $filterdate = $_GET['date'];
  }
  elseif ($_POST['date'] != "") {
    $filterdate = $_POST['date'];
  }

  // Filter by hour
  if ($_GET['hour'] != "") {
    $filterhour = $_GET['hour'];
  }
  elseif ($_POST['hour'] != "") {
    $filterhour = $_POST['hour'];
  }

  // Filter by unit
  if ($_GET['unit'] != "") {
    $filterunit = $_GET['unit'];
  }
  elseif ($_POST['unit'] != "") {
    $filterunit = $_POST['unit'];
  }

  // Messages per page
  if ($_GET['mpp'] != "") {
    $filtermpp = $_GET['mpp'];
  }
  elseif ($_POST['mpp'] != "") {
    $filtermpp = $_POST['mpp'];
  }

  // Start message
  if ($_GET['start'] != "") {
    $start = $_GET['start'];
  }

  // 'owever... if the remove filters button was posted, reset all filters
  if (isset($_POST['remove_filters']))
    unset ($filterdate, $filterhour, $filterunit, $filtermpp);

  // Set default MPP
  if (!isset($filtermpp)) $filtermpp = 10;

  // Prepare refreshURI
  $refreshURI = $_SERVER["PHP_SELF"]."?".
  "date=$filterdate&hour=$filterhour&unit=$filterunit&mpp=$filtermpp&start=$start";

  header_html("Dispatch :: Log Viewer","  <base target=\"_parent\">",$refreshURI);
?>

<body vlink="blue" link="blue" alink="cyan">
<form name="myform" action="cad-log-frame.php" method="post" style="margin: 0px;" target="log">

<span class="text"><b style="font-size: 10pt;">Log Viewer :: Viewing messages</b>
&nbsp; Use selections below to filter for specific log messages.
</span>

<!-- Begin Filter Form Outer Table -->
<table width="100%" style="margin-bottom: 8px;">
<tr>
<td bgcolor="#aaaaaa">

  <!-- Begin Filter Form Inner Table -->
  <table width="100%" cellspacing="1" cellpadding="2">
  <tr class="message">

  <td class="text">Date:&nbsp;</td>
  <td class="text">
  <select name="date" id="date" tabindex="101">
  <option value=""></option>
<?php
  $datesquery = "SELECT DISTINCT CAST(ts AS DATE) AS tsdate FROM messages ORDER BY ts DESC";
  $datesresult = MysqlQuery($datesquery);
  $dates = array();
  while ($line = mysql_fetch_array($datesresult, MYSQL_ASSOC)) {
    array_push($dates, $line["tsdate"]);
  }
  foreach ($dates as $date) {
    echo "<option value=\"$date\"";
    if (isset($filterdate) && $filterdate == $date) echo " SELECTED";
    echo ">$date</option>\n";
  }
  mysql_free_result($datesresult);
?>
  </select>
  </td>

  <td class="text">Hour:&nbsp;</td>
  <td class="text">
  <select name="hour" id="hour" tabindex="102">
  <option value=""></option>
<?php
  for ($i = 0; $i < 24; $i++) {
    echo "<option value=\"$i\"";
    if (isset($filterhour) && $filterhour == $i) echo " SELECTED";
    echo ">";
    if ($i < 10) echo "0";
    print "$i:00</option>\n";
  }
?>
  </select>
  </td>

  <td class="text">Unit:&nbsp;</td>
  <td class="text">
  <select name="unit" id="unit" tabindex="103">
  <option value=""></option>
<?php
  $unitquery = "SELECT unit FROM units";
  $unitresult = MysqlQuery($unitquery);
  $unitnames = array();
  while ($line = mysql_fetch_array($unitresult, MYSQL_ASSOC)) {
    array_push($unitnames, $line["unit"]);
  }
  natsort($unitnames);
  foreach ($unitnames as $unitname) {
    echo "<option value=\"$unitname\"";
    if (isset($filterunit) && $filterunit == $unitname) echo " SELECTED";
    echo ">$unitname</option>\n";
  }
  mysql_free_result($unitresult);
?>
  </select>
  </td>

  <td class="text" nowrap>Messages Per Page:&nbsp;</td>
  <td class="text">
  <select name="mpp" id="mpp" tabindex="104">
  <option value="0">All</option>
<?php
  $mpp = array(10, 25, 100);
  $mppdefault = 10;
  foreach ($mpp as $pp) {
    echo "<option value=\"$pp\"";
    if (isset($filtermpp) && $filtermpp == $pp) echo " SELECTED";
    echo ">$pp";
    if (isset($mppdefault) && $mppdefault == $pp) echo " (Default)";
    echo "</option>\n";
  }
?>
  </select>
  </td>

  <td class="text" nowrap align="right" width="100%">
  <button type="submit" name="apply_filters" id="apply_filter" value="apply_filters">Apply Filters</button>
  <button type="submit" name="remove_filters" id="apply_filter" value="remove_filters">Remove Filters</button>
  </td>

  </tr>
  </table>
  <!-- End Filter Form Inner Table -->

</td>
</tr>
</table>
<!-- End Filter Form Outer Table -->

</form>


<!-- Begin Log Messages Outer Table -->
<table width="100%">
<tr>
<td bgcolor="#aaaaaa">

  <!-- Begin Log Messages Inner Table -->
  <table width="100%" cellpadding="1" cellspacing="1">
  <tr>
<?php 
   if (!isset($_COOKIE['cad_show_creator']) || $_COOKIE['cad_show_creator'] == 'yes') {
     print '      <td bgcolor="#cccccc" class="text" nowrap><font color="gray">Logged By</font></td>'; 
   } 
   print " <td bgcolor=\"#cccccc\" class=\"text\"><b>Time</b></td>\n";
   print " <td bgcolor=\"#cccccc\" class=\"text\"><b>Unit</b></td>\n";
   if (!isset($_COOKIE['cad_show_message_type']) || $_COOKIE['cad_show_message_type'] == 'yes') {
     print '      <td bgcolor="#cccccc" class="text"><b>Type</b></td>'; 
   } 
   print "    <td width=\"100%\" bgcolor=\"#cccccc\" class=\"text\"><b>Message</b></td>\n";
?>
  </tr>

<?php
  // Prepare unit query
  $unitquery = "SELECT unit,status,role FROM units";
  $unitresult = MysqlQuery($unitquery);
  while ($unitrow = mysql_fetch_array($unitresult, MYSQL_ASSOC)) {
    $unitExists[$unitrow["unit"]] = 1;
    $unitstatus[$unitrow["unit"]] = $unitrow["status"];
    $unitrole[$unitrow["unit"]] = $unitrow["role"];
  }
  mysql_free_result($unitresult);

  // Prepare unit color query
  $unitcolorquery = "SELECT role, color_html FROM unitcolors";
  $unitcolorresult = MysqlQuery($unitcolorquery);
  while ($line = mysql_fetch_array($unitcolorresult, MYSQL_ASSOC)) {
    $unitrolecolor[$line["role"]] = $line["color_html"];
  }
  mysql_free_result($unitcolorresult);

  // Prepare main query
  // Start query string
  $logquery = "SELECT * FROM messages WHERE deleted=0";

  // Prepare date/hour filter
  if (isset($filterhour)) {
    if ($filterhour < 9) {
      $filterhourpadded = "0$filterhour";
    }
    else {
      $filterhourpadded = $filterhour;
    }
  }
  if (isset($filterdate) && isset($filterhour)) {
    $logquery .= " AND DATE_FORMAT(ts, '%Y-%m-%d %H') = '$filterdate $filterhourpadded'";
  }
  elseif (isset($filterdate)) {
    $logquery .= " AND DATE_FORMAT(ts, '%Y-%m-%d') = '$filterdate'";
  }
  elseif (isset($filterhour)) {
    $logquery .= " AND DATE_FORMAT(ts, '%H') = '$filterhourpadded'";
  }

  // Prepare unit filter
  if (isset($filterunit)) {
    $logquery .= " AND unit = '$filterunit'";
  }

  // Prepare order direction
  $logquery .= " ORDER BY oid DESC";

  // Prepare count of how many total results this query would return
  $howmanyquery = $logquery;
  $howmanyquery = str_replace("SELECT *", "SELECT COUNT(*) AS howmany", $howmanyquery);

  // Prepare start and limit
  if (isset($filtermpp) && $filtermpp > 0) {
    if (isset($start) && $start > 0) {
      $logquery .= " LIMIT $start, $filtermpp";
    }
    else {
      $logquery .= " LIMIT $filtermpp";
    }
  }

  // Issue Query
  $logresult = MysqlQuery($logquery);

  $td = "  <td class=\"message\">";

  // MAIN DISPLAY TABLE
  if (mysql_num_rows($logresult) > 0) {
    for ($i=1; $line = mysql_fetch_array($logresult, MYSQL_ASSOC); $i++) {
       ((int)THIS_PAGETS - date("U", strtotime($line["ts"]))) < 300 ? $quality="<b>" : $quality="";

      echo "<tr>\n";
      if (!isset($_COOKIE['cad_show_creator']) || $_COOKIE['cad_show_creator'] == 'yes') {
        if (isset($line["creator"]) && $line["creator"] != "NULL" && $line["creator"] != "") {
          echo $td, "<font color=\"gray\">", $line["creator"], "</font></td>\n";
        } else {
          echo $td, "</td>\n";
        }
      }

      echo $td, $quality, dls_utime($line["ts"]), "</td>\n";
      if (isset($unitExists[$line["unit"]])) {
        echo $td,
             $quality,
             "<a href=\"edit-unit.php?unit=",$line["unit"],"\" onClick=\"return popup('edit-unit.php?unit=".$line["unit"]."','unit',500,700)\" TARGET=\"_blank\">";
        echo "<!-- DEBUG UNITSTATUS ", $line["unit"], " ", $unitstatus[$line["unit"]], " -->";
        if ($unitstatus[$line["unit"]] == "Off Duty"  ||
            $unitstatus[$line["unit"]] == "Out of Service" ||
            $unitstatus[$line["unit"]] == "Off Playa") {
          echo "<span style='color: gray;'>", dls_ustr($line["unit"]), "</span>";
        }
        elseif ( ((isset($_COOKIE["units_color"]) && $_COOKIE["units_color"] == "yes") ||
                 !isset($_COOKIE["units_color"]))
                 &&
                 isset($unitrolecolor[$unitrole[$line["unit"]]])) {
          echo "<span style='color: ", $unitrolecolor[$unitrole[$line["unit"]]], ";'>", dls_ustr($line["unit"]), "</span>";
        }
        else {
          echo dls_ustr($line["unit"]);
        }
        echo "</a></td>\n";
      }
      else {
        echo $td, $quality, dls_ustr($line["unit"]), "</td>\n";
      }

      if (!isset($_COOKIE['cad_show_message_type']) || $_COOKIE['cad_show_message_type'] == 'yes') {
        if (isset($line["message_type"]) && $line["message_type"] != "NULL" && $line["message_type"] != "") {
          echo $td, "<font color=\"gray\">", $line["message_type"], "</font></td>\n";
        } else {
          echo $td, "</td>\n";
        }
      }

      echo $td, "\n    <table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">\n";
      echo "    <tr><td align=left class=\"message\">", $quality, $line["message"], "</td>\n";
      echo "        <td align=right><a href=\"edit-message.php?oid=".$line["oid"]."\" onClick=\"return popup('edit-message.php?oid=".$line["oid"]."', 'edit-message', 200, 900)\" TARGET=\"_blank\"><font color=\"gray\" size=\"-2\">[edit]</font></a></td>\n";
      echo "    </tr>\n    </table>\n  </td>\n";
      echo "</tr>\n\n";
    }
  }
  else {
    echo "<tr>\n";
    echo "<td class=\"message\" colspan=5>",
         "<font color=\"gray\" size=\"-1\">No logged messages based on current filters</font>";
    echo "</tr>\n";
  }
?>

  </table>
  <!-- End Log Messages Inner Table -->

</tr>
</table>
<!-- End Log Messages Outer Table -->

<?php

  // Issue Howmany Query
  $howmanyresult = MysqlQuery($howmanyquery);
  $howmanyline = mysql_fetch_array($howmanyresult);
  $howmany = $howmanyline['howmany'];

  // Print page back / page forward links
  echo "<center class=\"text\" style=\"margin-top: 8px;\">";

  $prevpage = $start - $filtermpp;
  if ($mpp > 0 && $start >= $filtermpp) {
    echo "<a href=\"".$_SERVER["PHP_SELF"].
         "?date=$filterdate&hour=$filterhour&unit=$filterunit&mpp=$filtermpp".
         "&start=$prevpage".
         "\" TARGET=\"log\">&lt;&lt;</a> | ";
  }
  else {
    echo "&lt;&lt; | ";
  }

  echo "<a href=\"".$_SERVER["PHP_SELF"].
       "?date=$filterdate&hour=$filterhour&unit=$filterunit&mpp=$filtermpp".
       "\" TARGET=\"log\">First Page</a> | ";

  if ($filtermpp > 0) {
    $pages = ceil($howmany / $filtermpp);
  }
  else {
    $pages = 1;
  }
  echo "Current filter will display $howmany messages on $pages page";
  if ($pages != 1) echo "s";
  echo " | ";

  $nextpage = $start + $filtermpp;
  if ($filtermpp > 0 && $nextpage < $howmany) {
    echo "<a href=\"".$_SERVER["PHP_SELF"].
         "?date=$filterdate&hour=$filterhour&unit=$filterunit&mpp=$filtermpp".
         "&start=$nextpage".
         "\" TARGET=\"log\">&gt;&gt;</a>";
  }
  else {
    echo "&gt;&gt;";
  }

  echo "</center>\n";
?>

<?php
  mysql_free_result($logresult);
  mysql_close($link);
 ?>
</body>
</html>
