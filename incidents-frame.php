<?php
  $subsys="incidents";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  header_html("Dispatch :: Incidents","",$_SERVER['PHP_SELF']);
?>
<body vlink="blue" link="blue" alink="cyan">

<!-- START Display Incidents -->
<table width="100%">
<tr><td bgcolor="#aaaaaa">
  <table width="100%" cellpadding="1" cellspacing="1">
  <tr>
    <td class="th" width="20">No.</td>
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
  if (isset($_COOKIE["incidents_open_only"]) && $_COOKIE["incidents_open_only"]=="no")
    $query = "SELECT * FROM incidents ORDER BY incident_id DESC";
  else
    $query = "SELECT * FROM incidents WHERE visible=1 ORDER BY incident_id DESC";
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
      $href = "<a href='edit-incident.php?incident_id=$incident_id' "
            . "onClick=\"return popup('edit-incident.php?incident_id=$incident_id','incident-$incident_id',600,1000)\">";

      // First Column "Number"
      echo $td, $quality, $href, $incident_id, "</span></a>";
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

     echo "<ul>\n  <li>\n";
     $query = "SELECT * from units ORDER BY unit";
     $result = mysql_query($query) or die ("In query: $query<br>\nError: ".mysql_error());
     $maxdisplayrows = 0;
     $unitnames = array();
     $unitarray = array();
     while ($unitrow = mysql_fetch_array($result, MYSQL_ASSOC)) {
       array_push($unitnames, $unitrow["unit"]);
       $unitarray[$unitrow["unit"]] = $unitrow;
     }
     natsort($unitnames);
     foreach ($unitnames as $u_name) {
       $unitrow = $unitarray[$u_name];

       $u_name_html = str_replace(" ", "&nbsp;", $u_name);
       $u_status_html = str_replace(" ", "&nbsp;", $unitrow["status"]);

       if ($unitrow["status"] == "Off Duty"  ||
           $unitrow["status"] == "Out of Service" ||
           $unitrow["status"] == "Off Playa") {
         $u_name_html = "<span style='color: gray;'>$u_name_html</span>";
       }
       elseif (((isset($_COOKIE["units_color"]) && $_COOKIE["units_color"] == "yes")
             || !isset($_COOKIE["units_color"]))
             &&  isset($rolecolor[$unitrow["role"]])) {
         $u_name_html = "<span style='color: ".$rolecolor[$unitrow["role"]].";'>$u_name_html</span>";
       }

       if ($unitrow["status"] == "Attached to Incident") {
         $u_name_html = "<span style='background-color: yellow; color:black'>$u_name_html</span>";
       }
       $display = "<td class=\"message\">"
                . "<a href=\"edit-unit.php?unit=".$u_name."\""
                . " onClick=\"return popup('edit-unit.php?unit=".$unitrow["unit"]."','unit-".str_replace(" ", "&nbsp;", $unitrow["unit"])."',500,700)\">"
                . $u_name_html."</a></td><td class=\"message\">$u_status_html</td>";
       $type = $unitrow["type"];
       if (isset($units[$type]))
         $units[$type][sizeof($units[$type])+1] = $display;
       else
         $units[$type][1] = $display;

       if (sizeof($units[$type]) > $maxdisplayrows)
         $maxdisplayrows = sizeof($units[$type]);
     }
     mysql_free_result($result);

     $types = array("Unit", "Individual", "Generic", "");
     echo "  <table>\n  <tr><td><table cellpadding=\"1\" cellspacing=\"1\">\n    <tr>";
     foreach ($types as $type) {
       if (isset($units[$type])) {
         if ($type == "")
           $type = "Other";
         echo "<td class=\"text\" valign=\"top\" style='padding-right: 25px;'><b>$type</b> Class Units</td>";
       }
     }
     echo "    </tr>\n    <tr>\n";
     foreach ($types as $type) {
       if (isset($units[$type])) {
         echo "      <td valign=\"top\" style='padding-right: 25px;'>\n";
         echo "      <table cellpadding=\"1\" cellspacing=\"1\" bgcolor=\"#aaaaaa\">\n";
         for ($i=1; $i <= $maxdisplayrows; $i++) {
           if (isset($units[$type][$i]))
             echo "        <tr>",$units[$type][$i],"</tr>\n";
           else
             echo "        <tr><td style='font-size: 12px; background-color: #bbbbbb' colspan=\"2\">&nbsp;</td></tr>\n";
         }
         echo "      </table></td>\n";
       }
     } ?>
    </tr></table></td>
  </tr></table>
  </li>
</ul>
<?
   }

   mysql_close($link);
 ?>

</body>
</html>


