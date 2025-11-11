<?php
  $subsys="cad";
  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  // Prepare page GET/POST input

  $filterdate = '';
  $filterhour = '';
  $filterunit = '';
  $filtermpp = '';
  $start = '';
  // If apply was POSTed...
  if (isset($_POST['apply_filters'])) {
    if ($_POST['date'] != "") {
      $filterdate = MysqlClean($_POST, 'date', 20);
    }

    if ($_POST['hour'] != "") {
      $filterhour = MysqlClean($_POST, 'hour', 5);
    }

    if ($_POST['funit'] != "") {
      $filterunit = MysqlClean($_POST, 'funit', 100);
    }

    if ($_POST['mpp'] != "") {
      $filtermpp = MysqlClean($_POST,'mpp',10);
    }
  }

  // 'owever... if the remove filters button was posted, reset all filters (redundant now that we're setting before assigning to, rev 185)
  elseif (isset($_POST['remove_filters'])) {
    $filterdate = '';
    $filterhour = '';
    $filterunit = '';
    $filtermpp = '';
  }

  // Otherwise, process GETs
  else {
    if (isset ($_GET['date']) && $_GET['date'] != "") {
      $filterdate = MysqlClean($_GET,'date',20);
    }

    if (isset ($_GET['hour']) && $_GET['hour'] != "") {
      $filterhour = MysqlClean($_GET,'hour',5);
    }

    if (isset ($_GET['unit']) && $_GET['unit'] != "") {
      $filterunit = MysqlClean($_GET,'unit',100);
    }

    if (isset ($_GET['mpp']) && $_GET['mpp'] != "") {
      $filtermpp = MysqlClean($_GET,'mpp',10);
    }

    if (isset ($_GET['start']) && $_GET['start'] != "") {
      $start = MysqlClean($_GET,'start',10);
    }
  }

  // Set default MPP
  if (!isset($filtermpp)) $filtermpp = 10;

  // Prepare refreshURI
  $refreshURI = $_SERVER["PHP_SELF"]."?".
  "date=$filterdate&hour=$filterhour&unit=$filterunit&mpp=$filtermpp&start=$start";

  header_html("Dispatch :: Log Viewer","  <base target=\"_parent\">",$refreshURI);
?>

<body vlink="blue" link="blue" alink="cyan">

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
   if ($USE_MESSAGE_TYPE) {
     if (!isset($_COOKIE['cad_show_message_type']) || $_COOKIE['cad_show_message_type'] == 'yes') {
       print '      <td bgcolor="#cccccc" class="text"><b>Type</b></td>'; 
     } 
   }
   print "    <td width=\"100%\" bgcolor=\"#cccccc\" class=\"text\"><b>Message</b></td>\n";
?>
  </tr>

<?php
  // Prepare unit query
  $unitquery = "SELECT unit,status,role FROM units";
  $unitresult = MysqlQuery($unitquery);
  while ($unitrow = mysqli_fetch_array($unitresult, MYSQLI_ASSOC)) {
    $unitExists[$unitrow["unit"]] = 1;
    $unitstatus[$unitrow["unit"]] = $unitrow["status"];
    $unitrole[$unitrow["unit"]] = $unitrow["role"];
  }
  mysqli_free_result($unitresult);

  // Prepare unit color query
  $unitcolorquery = "SELECT role, color_html FROM unit_roles";
  $unitcolorresult = MysqlQuery($unitcolorquery);
  while ($line = mysqli_fetch_array($unitcolorresult, MYSQLI_ASSOC)) {
    $unitrolecolor[$line["role"]] = $line["color_html"];
  }
  mysqli_free_result($unitcolorresult);

  // Prepare main query
  // Start query string
  $logquery = "SELECT * FROM messages WHERE deleted=0";

  // Prepare date/hour filter
  if (!empty($filterhour)) {
    if ($filterhour < 9) {
      $filterhourpadded = "0$filterhour";
    }
    else {
      $filterhourpadded = $filterhour;
    }
  }
  if (!empty($filterdate) && !empty($filterhour)) {
    $logquery .= " AND DATE_FORMAT(ts, '%Y-%m-%d %H') = '$filterdate $filterhourpadded'";
  }
  elseif (!empty($filterdate)) {
    $logquery .= " AND DATE_FORMAT(ts, '%Y-%m-%d') = '$filterdate'";
  }
  elseif (!empty($filterhour)) {
    $logquery .= " AND DATE_FORMAT(ts, '%H') = '$filterhourpadded'";
  }

  // Prepare unit filter
  if (!empty($filterunit)) {
    $logquery .= " AND unit = '$filterunit'";
  }

  // Prepare order direction
  $logquery .= " ORDER BY oid DESC";

  // Prepare count of how many total results this query would return
  $howmanyquery = $logquery;
  $howmanyquery = str_replace("SELECT *", "SELECT COUNT(*) AS howmany", $howmanyquery);

  // Prepare start and limit
  if (!empty($filtermpp)) {
    if (!empty($start)) {
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
  if (mysqli_num_rows($logresult) > 0) {
    for ($i=1; $line = mysqli_fetch_array($logresult, MYSQLI_ASSOC); $i++) {
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

      if ($USE_MESSAGE_TYPE) {
        if (!isset($_COOKIE['cad_show_message_type']) || $_COOKIE['cad_show_message_type'] == 'yes') {
          if (isset($line["message_type"]) && $line["message_type"] != "NULL" && $line["message_type"] != "") {
            echo $td, "<font color=\"gray\">", $line["message_type"], "</font></td>\n";
          } else {
            echo $td, "</td>\n";
          }
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
  $howmanyline = mysqli_fetch_array($howmanyresult);
  $howmany = $howmanyline['howmany'];

  // Print page back / page forward links
  echo "<center class=\"text\" style=\"margin-top: 8px;\">";

  $prevpage = $start - $filtermpp;
  if ($filtermpp > 0 && $start >= $filtermpp) {
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
  echo "Current filter will display $howmany message";
  if ($howmany != 1) echo "s";
  echo " on $pages page";
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

  mysqli_free_result($howmanyresult);
?>

<?php
  mysqli_free_result($logresult);
  mysqli_close($link);
 ?>
</body>
</html>
