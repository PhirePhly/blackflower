<?php
  $subsys="incidents";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  // Scroll incidents per page
  $scrollipp = 15;

  // Begin filter GET/POST parsing

  // If apply_filters was posted...
  if (isset($_POST['apply_filters'])) {

    if ($_POST['date'] != "") {
      $filterdate = $_POST['date'];
    }

    if ($_POST['calltype'] != "") {
      $filtercalltype = $_POST['calltype'];
    }

    if (isset($_POST['scroll'])) {
      $filterscroll = "yes";
    }
    else {
      $filterscroll = "no";
    }
  }

  // 'owever... if the remove filters button was posted, reset all filters
  elseif (isset($_POST['remove_filters'])) {
    unset ($filterdate, $filtercalltype, $filterscroll, $start);
    $filterscroll = "yes";
  }

  // Process GETs
  else {
    if ($_GET['date'] != "") {
      $filterdate = $_GET['date'];
    }

    if ($_GET['calltype'] != "") {
      $filtercalltype = $_GET['calltype'];
    }

    if ($_GET['scroll'] == "yes") {
      $filterscroll = "yes";
    }
    elseif($_GET['scroll'] == "no") {
      $filterscroll = "no";
    }

    if ($_GET['start'] != "") {
      $start = $_GET['start'];
    }
  }

  if (isset($_POST["incidents_hide_units_oos"])) {
    if ($_POST["incidents_hide_units_oos"] == "Hide Out of Service" &&
        (!isset($_COOKIE["incidents_hide_units_oos"]) || $_COOKIE["incidents_hide_units_oos"] == "no")) {
      setcookie("incidents_hide_units_oos", "yes");
    }
    elseif ($_POST["incidents_hide_units_oos"] == "Show All Units" &&
            (!isset($_COOKIE["incidents_hide_units_oos"]) || $_COOKIE["incidents_hide_units_oos"] == "yes")) {
      setcookie("incidents_hide_units_oos", "no");
    }
    header("Location: http://".$_SERVER['HTTP_HOST'].$_SERVER["PHP_SELF"]."?".
           "date=$filterdate&calltype=$filtercalltype&scroll=$filterscroll&start=$start");
    exit;
  }

  header_html("Dispatch :: Incidents","",
              $_SERVER['PHP_SELF']."?"."date=$filterdate&calltype=$filtercalltype&scroll=$filterscroll&start=$start");
?>
<body vlink="blue" link="blue" alink="cyan">

<form name="myform" 
 action="incidents-frame.php?<?php echo "date=$filterdate&calltype=$filtercalltype&scroll=$filterscroll&start=$start";?>"
 method="post" style="margin: 0px;" target="incidents">

<!-- START Display Incidents -->
<table width="100%">
<tr><td bgcolor="#aaaaaa">
<?php
  if (isset($filterdate) || isset($filtercalltype)) {
    print "<b class=\"text\" style=\"color: #dd0000;\">Filters Applied</b><br />\n";
  }
?>
  <table width="100%" cellpadding="1" cellspacing="1">
  <tr>
    <td class="th" width="20">Call No.</td>
    <td class="th">Incident Details</td>
    <td class="th">Call Type</td>
    <td class="th" width="50">Call&nbsp;Time</td>
    <td class="th" width="50">Dispatch</td>
    <td class="th" width="50">Arrival</td>
    <td class="th" width="50">Complete</td>
    <td class="th">Location</td>
    <td class="th">Unit(s) Assigned</td>
  </tr>
<?php
  // auxiliary query for incident_units: dynamically load them into array that the main display frame will reference:
  $query = "SELECT uid,incident_id,unit FROM incident_units WHERE cleared_time IS NULL ORDER BY incident_id,uid";
  $result = mysql_query ($query) or die ("In query: $query<br />\nError: ". mysql_error());
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $incident_id = $line["incident_id"];
    if (isset($unitcount[$incident_id]))
      $unitcount[$incident_id]++;
    else
      $unitcount[$incident_id] = 1;
    $unit[$incident_id][$unitcount[$incident_id]] = $line["unit"];
  }
  mysql_free_result($result);

  // PREPARE MAIN QUERY
  if (isset($_COOKIE["incidents_open_only"]) && $_COOKIE["incidents_open_only"]=="no") {
    $query = "SELECT * FROM incidents";

    if (isset($filterdate) && isset($filtercalltype)) {
      $query .= " WHERE DATE_FORMAT(ts_opened, '%Y-%m-%d') = '$filterdate' AND call_type = '$filtercalltype'";
    }
    elseif (isset($filterdate)) {
      $query .= " WHERE DATE_FORMAT(ts_opened, '%Y-%m-%d') = '$filterdate'";
    }
    elseif (isset($filtercalltype)) {
      $query .= " WHERE call_type = '$filtercalltype'";
    }

    $query .= " ORDER BY incident_id DESC";

    // Prepare count of how many total results this query would return
    $howmanyquery = $query;
    $howmanyquery = str_replace("SELECT *", "SELECT COUNT(*) AS howmany", $howmanyquery);

    // Prepare start and limit
    if ($filterscroll == "yes") {
      if (isset($start) && $start > 0) {
        $query .= " LIMIT $start, $scrollipp";
      }
      else {
        $query .= " LIMIT $scrollipp";
      }
    }
  }
  else {
    $query = "SELECT * FROM incidents WHERE visible=1 ORDER BY incident_id DESC";
  }

  $result = mysql_query($query) or die("Query failed : " . mysql_error()."<p>query was:<br>".$query);

  $td = "    <td class=\"message\">";

  // ------------------------------------------------------------------------
  // Incident Display Table

  if (mysql_num_rows($result)) {

    // Loop through all the incidents that match the query
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      echo "  <tr>\n";

      $incident_id = $line["incident_id"];
      if ($line["completed"])
        $quality = "<span style='color: #666666;'>";
      elseif (isset($line["ts"]) && $line["ts"] <> "" && ((int)THIS_PAGETS - date("U", strtotime($line["ts"]))) < 300)
        $quality="<span style='font-weight: bold;'>";
      else
        $quality="<span style='font-weight: normal;'>";
      $href = "<a href='edit-incident.php?incident_id=$incident_id' " .
              "onClick=\"return popup('edit-incident.php?incident_id=$incident_id','incident-$incident_id',600,1000)\" ".
              "TARGET=\"_blank\">";

      // First Column "Number"
      if ($line["call_number"] != '') {
        echo $td, $quality, $href, $line["call_number"], "</span></a>";
      }
      else {
        echo $td, $quality, $href, 'legacy incident_id ', $incident_id, "</span></a>";  # bug 75 conversion
      }
      if ($line["completed"]) {
        echo "&nbsp;&nbsp;<span style='font-size: 8pt;'>[completed]</span>";
        $quality = "<span style='color: #666666; font-style: italic;'>";
      }
      echo "</td>\n";

      echo $td, $quality, "&nbsp;", $href, str_replace(" ", "&nbsp;", MysqlUnClean($line["call_details"])), "</span></a></td>\n";

      echo $td, $quality, str_replace(" ", "&nbsp;", $line["call_type"]), "</span></td>\n";
      echo $td, $quality, dls_utime($line["ts_opened"]), "</span></td>\n";
      if (!$line["completed"] && (!$line["ts_dispatch"] || !strcmp($line["ts_dispatch"], "0000-00-00 00:00:00")))
        echo $td, $quality, "</span><span style='color: darkred; text-decoration: blink;'>Undispatched</span></td>\n";
      else
        echo $td, $quality, dls_utime($line["ts_dispatch"]), "</span></td>\n";
      echo $td, $quality, dls_utime($line["ts_arrival"]), "</span></td>\n";
      echo $td, $quality, dls_utime($line["ts_complete"]), "</span></td>\n";
      echo $td, $quality, str_replace(" ", "&nbsp;", MysqlUnClean($line["location"])),"</span></td>\n";

      if (isset($unitcount[$incident_id])) {
         $count = $unitcount[$incident_id];

         if ($count == 1)
           $display = $unit[$incident_id][1];
         else {
           $display = implode (", ", $unit[$incident_id]);
           if (strlen($display) > 20) {
              $display = substr($display, 0, 15) . "... ($count units)";
           }
         }

         echo $td, $quality, str_replace(" ", "&nbsp;", $display), "</span></td>\n";
       }
       else
         echo $td, $quality, "none&nbsp;assigned</span></td>\n";

       echo "  </tr>\n";
     }
   } else {
      echo "  <tr>\n";
      echo $td, "<center>-</center></td>\n";
      echo $td, "<center>-</center></td>\n";
      echo $td, "<span style='color: #666666; font-size: 9pt;'>No active incidents.</span></td>\n";
      echo $td, "<center>-</center></td>\n";
      echo $td, "<center>-</center></td>\n";
      echo $td, "<center>-</center></td>\n";
      echo $td, "<center>-</center></td>\n";
      echo $td, "<center>-</center></td>\n";
      echo $td, "<center>-</center></td>\n";
      echo "  </tr>\n";
   }

   echo "  </table>\n</td></tr>\n</table>\n";

   // Are we going to scroll the results?
   if ($filterscroll == "yes") {
     // Issue Howmany Query
     $howmanyresult = MysqlQuery($howmanyquery);
     $howmanyline = mysql_fetch_array($howmanyresult);
     $howmany = $howmanyline['howmany'];

     // Print page back / page forward links
     echo "<center class=\"text\" style=\"margin-top: 8px;\">";

     $prevpage = $start - $scrollipp;
     if ($scrollipp > 0 && $start >= $scrollipp) {
       echo "<a href=\"".$_SERVER["PHP_SELF"].
            "?date=$filterdate&calltype=$filtercalltype&scroll=$filterscroll".
            "&start=$prevpage".
            "\" TARGET=\"incidents\">&lt;&lt;</a> | ";
     }
     else {
       echo "&lt;&lt; | ";
     }

     echo "<a href=\"".$_SERVER["PHP_SELF"].
          "?date=$filterdate&calltype=$filtercalltype&scroll=$filterscroll".
          "\" TARGET=\"incidents\">First Page</a> | ";

     if ($scrollipp > 0) {
       $pages = ceil($howmany / $scrollipp);
     }
     else {
       $pages = 1;
     }
     echo "Current filter will display $howmany incident";
     if ($howmany != 1) echo "s";
     echo " on $pages page";
     if ($pages != 1) echo "s";
     echo " | ";

     $nextpage = $start + $scrollipp;
     if ($scrollipp > 0 && $nextpage < $howmany) {
       echo "<a href=\"".$_SERVER["PHP_SELF"].
            "?date=$filterdate&calltype=$filtercalltype&scroll=$filterscroll".
            "&start=$nextpage".
            "\" TARGET=\"incidents\">&gt;&gt;</a>";
     }
     else {
       echo "&gt;&gt;";
     }

     echo "</center>\n";

     mysql_free_result($howmanyresult);
   }

   mysql_free_result($result);
   echo "<!-- END Display Incidents -->\n\n";

   // -----------------------------------------------------------------------
   // Unit display section:

   if ((isset($_COOKIE["incidents_show_units"]) && $_COOKIE["incidents_show_units"] == "yes")
    || !isset($_COOKIE["incidents_show_units"])) {
     $query = "SELECT role, color_html FROM unitcolors";

     $result = mysql_query($query) or die ("In query: $query\nError: ".mysql_error());
     while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
       $rolecolor[$line["role"]] = $line["color_html"];
     }
     mysql_free_result($result);

     if ($_COOKIE["incidents_hide_units_oos"] == "yes") {
       $query = "SELECT * FROM units u LEFT OUTER JOIN unit_assignments a on u.assignment=a.assignment".
                " WHERE status NOT IN ('Out Of Service', 'Off Comm', 'Off Duty', 'Off Playa')";
     }
     else {
       $query = "SELECT * FROM units u LEFT OUTER JOIN unit_assignments a on u.assignment=a.assignment";
     }
     $result = mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
     $unitnames = array();
     $unitarray = array();
     while ($unitrow = mysql_fetch_array($result, MYSQL_ASSOC)) {
       array_push($unitnames, $unitrow["unit"]);
       $unitarray[$unitrow["unit"]] = $unitrow;
     }
     natsort($unitnames);

     $columns = 1;
     $columnwidthpct = 25;
     if (count($unitnames) > 10) {
       $columns = $INCIDENT_UNIT_COLUMNS;
       if ($columns >= 3) {
         $columnwidthpct = (int)100/$columns;
       }
     }

     $displayunits = array();

     print "<div style=\"width: auto; margin: 0px; margin-top: 8px; margin-bottom: 8px;\">\n";
     print "<table width=\"100%\"><tr><td nowrap class=\"text\">\n";
     print "<b>Unit Availability</b> &nbsp; &nbsp; (Units shown in <b>Bold</b>, Generic Units shown in ".
           "<span style=\"border: 2px dotted gray; background-color: #cccccc\"><b>Dashed Bold</b></span>. ".
           " Icons indicate designated supervisory Assignment.)\n";

     print "</td><td class=\"text\" align=\"right\">\n";

     if ($_COOKIE["incidents_hide_units_oos"] == "yes") {
       print "<button type=\"submit\" name=\"incidents_hide_units_oos\" id=\"incidents_hide_units_oos\" ";
       print "value=\"Show All Units\" title=\"Show All Units\">Show All Units</button>\n";
     }
     else {
       print "<button type=\"submit\" name=\"incidents_hide_units_oos\" id=\"incidents_hide_units_oos\" ";
       print "value=\"Hide Out of Service\" title=\"Hide Out of Service Units\">Hide Out of Service</button>\n";
     }
     print "</td></tr></table>\n";
     print "</div>\n";

     print "<table width=\"100%\" border=0>\n";

     print "<tr>\n";
     print "<td valign=top width=\"$columnwidthpct%\" align=left>";
     print "<table cellpadding=\"1\" cellspacing=\"1\" bgcolor=\"#aaaaaa\" width=\"100%\">";

     $threshold = sizeof($unitnames) / $columns;
     $pos_counter = 0;
     foreach ($unitnames as $u_name) {
       $pos_counter++;
       $unitrow = $unitarray[$u_name];

       $u_name_html = str_replace(" ", "&nbsp;", $u_name);
       $u_status_html = str_replace(" ", "&nbsp;", $unitrow["status"]);

       if ($unitrow["status"] == "Off Duty"  ||
           $unitrow["status"] == "Out of Service" ||
           $unitrow["status"] == "Off Playa") {
         $u_name_html = "<span style='color: gray;'>$u_name_html</span>";
       }
       elseif ($unitrow["status"] == "Attached to Incident") {
         $u_status_html = "<span style=\"font-size:9px\">Attached&nbsp;to&nbsp;Incident</font>";
         $u_name_html = "<span style='background-color: yellow; color:black'>$u_name_html</span>";
       }
       elseif (((isset($_COOKIE["units_color"]) && $_COOKIE["units_color"] == "yes")
             || !isset($_COOKIE["units_color"]))
             &&  isset($rolecolor[$unitrow["role"]])) {
         $u_name_html_build = "<span style='";

         if ($unitrow["type"] == 'Unit') {
           $u_name_html_build .= "font-weight: Bold; ";
         }

         elseif ($unitrow["type"] == 'Generic') {
           $u_name_html_build .= " background-color: #bbbbbb; border: 2px dotted gray; padding-left: 1px; padding-right: 1px; font-weight: bold; ";
         }

         $u_name_html_build .= "color: ".$rolecolor[$unitrow["role"]].";'>$u_name_html</span>";
         $u_name_html = $u_name_html_build;
       }

       if ($unitrow["status"] == "Available on Pager") {
         $u_status_html = "<span style=\"font-size:9px\">Available&nbsp;on&nbsp;Pager</font>";
       }


       $icon = "";
       if (isset($unitrow["assignment"])) {
         $icon = "<span class=" . $unitrow["display_class"] . " title=\"" .
                 $unitrow["description"] . "\">" . $unitrow["assignment"] .
                 "</span>";
       }

       print "<tr><td class=\"message\">"
             . "<a href=\"edit-unit.php?unit=".$u_name."\""
             . " onClick=\"return popup('edit-unit.php?unit=".$unitrow["unit"]."','unit-".str_replace(" ", "&nbsp;", $unitrow["unit"])."',500,700)\" TARGET=\"_blank\">"
             . $u_name_html."</a>&nbsp;&nbsp;$icon</td><td class=\"message\">$u_status_html</td></tr>\n";

       if ($pos_counter >= $threshold) {
         print "</table></td>\n\n";
         print "<td valign=top width=\"$columnwidthpct%\" align=left>\n";
         print "<table cellpadding=\"1\" cellspacing=\"1\" bgcolor=\"#aaaaaa\" width=\"100%\">\n";
         $pos_counter = 0;
       }

     }
     mysql_free_result($result);
   }

   mysql_close($link);
 ?>
 </table></td><td></td><td></td></tr></table>

</form>
</body>
</html>
