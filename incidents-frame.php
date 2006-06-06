<?php
  $subsys="incidents";
  
  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');

?> 

<HTML>
<HEAD>
  <META HTTP-EQUIV=Refresh CONTENT="15; URL=<?php print $_SERVER["PHP_SELF"] ?>"> 
  <TITLE>Dispatch :: Incidents</title>
  <BASE target="_parent">
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA=screen>
<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
<!--

function popup(url,name,height,width)
{
  var myWindow = window.open(url,name,'width='+width+',height='+height+',scrollbars')
  // FLAG: TODO: modal=yes is a partially ineffective hack, i'd prefer to have truly modeless persistent windows, but Javascript SUCKS.
  if (myWindow.focus) {myWindow.focus()}
	return false;
}

// -->
</SCRIPT>

</HEAD>
<BODY vlink=blue link=blue alink=cyan>
<font face="tahoma,ariel,sans">

<table width="100%">
<tr><td bgcolor="#aaaaaa">

  <table width="100%" cellpadding=1 cellspacing=1> 
  <tr>
    <td class="th" width=20>No.</td>
    <td class="th">Incident Details</td>
    <td class="th">Call Type</td>
    <td class="th" width=50>Call&nbsp;Time</td>
    <td class="th" width=50>Dispatch</td>
    <td class="th" width=50>Arrival</td>
    <td class="th" width=50>Complete</td>
    <td class="th">Location</td>
    <td class="th">Unit(s) Assigned</td>
  </tr>

 <?php
   // auxiliary query for incident_units: dynamically load them into array that the main display frame will reference:
   $query = "SELECT uid,incident_id,unit FROM incident_units WHERE cleared_time IS NULL ORDER BY incident_id,uid";
   $result = mysql_query ($query) or die ("In query: $query<br>\nError: ". mysql_error());
   while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
           $incident_id = $line["incident_id"];
           if (isset($unitcount[$incident_id])) 
              $unitcount[$incident_id]++;
           else
              $unitcount[$incident_id]=1;
           $unit[$incident_id][$unitcount[$incident_id]] = $line["unit"];
   }
   mysql_free_result($result);


   // PREPARE MAIN QUERY
   if (isset($_COOKIE["incidents_open_only"]) && $_COOKIE["incidents_open_only"]=="no")
     $query = "SELECT * FROM incidents ORDER BY incident_id DESC";
   else
     $query = "SELECT * FROM incidents WHERE visible=1 ORDER BY incident_id DESC";
   $result = mysql_query($query) or die("Query failed : " . mysql_error()."<p>query was:<br>".$query);

   $td = "<td class=\"message\">"; 

   // MAIN DISPLAY TABLE
   if (mysql_num_rows($result)) {
     while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
       $incident_id = $line["incident_id"];
       if ($line["completed"])
         $quality = "<font color=\"#666666\">";
       elseif (isset($line["ts"]) && $line["ts"] <> "" && ((int)THIS_PAGETS - date("U", strtotime($line["ts"]))) < 300) 
         $quality="<b>";
       else 
         $quality="";

       echo "\t<tr>\n";
       $href = "<a href=\"edit-incident.php?incident_id=$incident_id\" onClick=\"return popup('edit-incident.php?incident_id=$incident_id','incident-$incident_id', 600, 1000)\">";

       echo $td, $quality, $href, $incident_id;
       if ($line["completed"])
	     {
         echo "&nbsp;&nbsp;<font size=\"-2\">[completed]</font>";
         $quality = "<font color=\"#666666\"><i>";
	     }
       echo "</td>";

       echo $td, $quality, "&nbsp;", $href, str_replace(" ", "&nbsp;", $line["call_details"]), "</a></td>";

       echo $td, $quality, str_replace(" ", "&nbsp;", $line["call_type"]), "</td>";
       echo $td, $quality, dls_utime($line["ts_opened"]), "</td>";
       if (!$line["completed"] && (!$line["ts_dispatch"] || !strcmp($line["ts_dispatch"], "0000-00-00 00:00:00")))
         echo $td, $quality, "<font color=\"darkred\"><blink>Undispatched</blink></font></td>";
       else
         echo $td, $quality, dls_utime($line["ts_dispatch"]), "</td>";
       echo $td, $quality, dls_utime($line["ts_arrival"]), "</td>";
       echo $td, $quality, dls_utime($line["ts_complete"]), "</td>";
       echo $td, $quality, str_replace(" ", "&nbsp;", $line["location"]), "</td>\n";
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
         
         echo $td, $quality, str_replace(" ", "&nbsp;", $display), "</td>\n";
       }
       else
         echo $td, $quality, "none&nbsp;assigned</td>\n";

       echo "</tr>\n";
     }
   }

   else {
      echo "\t<tr>\n";
      echo $td, "<center>-</center>";
      echo $td, "<center>-</center>";
      echo $td, "<font color=\"gray\" size=\"-1\">No active incidents.</font>";
      echo $td, "<center>-</center>";
      echo $td, "<center>-</center>";
      echo $td, "<center>-</center>";
      echo $td, "<center>-</center>";
      echo $td, "<center>-</center>";
      echo $td, "<center>-</center>";
      echo "\t</tr>\n";
   }

   echo "</table></table>\n";
   mysql_free_result($result);

   // Unit display section:
   if ((isset($_COOKIE["incidents_show_units"]) && $_COOKIE["incidents_show_units"] == "yes")
    || !isset($_COOKIE["incidents_show_units"])) {
     $query = "SELECT role, color_html FROM unitcolors";
     $result = mysql_query($query) or die ("In query: $query\nError: ".mysql_error());
     while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
       $rolecolor[$line["role"]] = $line["color_html"];
     }
     mysql_free_result($result);

     echo "<ul>";
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
       
       if ($unitrow["status"] == "Off Duty"  || $unitrow["status"] == "Out of Service") {
         $u_name_html = "<font color=\"gray\">$u_name_html</font>";
       }
       elseif ( ((isset($_COOKIE["units_color"]) && $_COOKIE["units_color"] == "yes")
                 || !isset($_COOKIE["units_color"]))
               && isset($rolecolor[$unitrow["role"]])) {
         $u_name_html = "<font color=\"".$rolecolor[$unitrow["role"]]."\">$u_name_html</font>";
       }

       if ($unitrow["status"] == "Attached to Incident") {
         $u_name_html = "<span style=\"background-color: yellow; color:black\">$u_name_html</span>";
       }
       $display = "<td class=\"message\"><a href=\"unit.php?unit=". $u_name. "\" onClick=\"return popup('unit.php?unit=".$unitrow["unit"]."','unit-".str_replace(" ", "&nbsp;", $unitrow["unit"])."',500,700)\">". $u_name_html."</a></td><td class=\"message\">$u_status_html</td>";
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
     echo "<table><tr><td><table cellpadding=1 cellspacing=1 ><tr>";
     foreach ($types as $type) {
       if (isset($units[$type])) {
         if ($type == "") $type = "Other";
         echo "<td class=\"text\" valign=top><b>$type</b> class units</td>\n";
       }
     }
     echo "</tr><tr>";
     foreach ($types as $type) {
       if (isset($units[$type])) {
         echo "<td valign=top><table cellpadding=1 cellspacing=1 bgcolor=#aaaaaa>";
         for ($i=1; $i <= $maxdisplayrows; $i++) {
                 if (isset($units[$type][$i]))
                         echo "<tr>",$units[$type][$i],"</tr>\n";
                 else
                         echo "<tr><td style=\"font-size: 12px; background-color:#bbbbbb\" colspan=2>&nbsp;</td></tr>\n";
         }
       }
       echo "</table></td>\n";
     }
     echo "</tr></table></td></tr></table></ul>\n";
   }

   mysql_close($link);
 ?>

</body>
</html>


