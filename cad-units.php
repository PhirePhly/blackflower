<?php
  $subsys="units";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');

  $td= "\t<td class=\"message\">"; 

  if (isset($_POST["addunit"])) {
    if (strpos($_POST["addunit"], "'")) {
            die("An apostrophe is an invalid character for use in a unit name.");
            // TODO: handle error condition better
            }
    $query = "INSERT INTO units (unit) VALUES (UPPER('".MysqlClean($_POST,"addunit",20)."'))";
    mysql_query($query) or die ("couldn't insert unit: ".mysql_error());
    header('Location: cad-units.php');
  }

  $query = "SELECT role, color_html FROM unitcolors";
  $result = mysql_query($query) or die ("In query: $query\nError: ".mysql_error());
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $rolecolor[$line["role"]] = $line["color_html"];
  }

?> 

<HTML>
<HEAD>
  <META HTTP-EQUIV=Refresh CONTENT="15; URL=<?php 
    print $_SERVER["PHP_SELF"]; 
    if (isset($_GET["search"])) 
      print "?search=".MysqlClean($_GET,"search",20); 
    if (isset($_GET["hour"])) 
      print "&hour=".MysqlClean($_GET,"hour",2); ?>"> 
  <!-- TODO: is this used?  i think hour/search are old. -->
  <TITLE>Dispatch :: Unit Listing</TITLE>
  <?php include('include-clock.php')?>
  <SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
<!--
function popup(url,name)
{
	var newwindow=window.open(url,name,'height=500,width=700,scrollbars');
	if (window.focus) {newwindow.focus()}
	return false;
}
-->
</SCRIPT>

  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA="screen, print">
</HEAD>
<BODY vlink=blue link=blue alink=cyan onload="displayClockStart()" onunload="displayClockStop()">
<?php include('include-title.php') ?>
<?php include('include-footer.php') ?>
<p>
<form name="myform" action="cad-units.php" method="post">
<table width=98%>
<tr>
  <td align=left><input tabindex="2" type="submit" value="Add New Unit" onClick="return popup('unit.php?new-unit','unit-new')"></td>
  <td width=100%></td>
  <td align=right> <input type="text" name="displayClock" size="8"></td>
</tr>
</table>
<p>
<?php
  $types = array("Unit", "Individual", "Generic", "");
  foreach ($types as $type) {
    if ($type=="") {
             $wheretype=" WHERE type IS NULL";
             $title="<b>Other</b> class units";
     }
    
    else { 
            $wheretype = "WHERE type='$type'";
            $title="<b>$type</b> class units";
    }

    $unitquery = "SELECT * from units $wheretype ORDER BY unit ASC ";
    $unitresult = mysql_query($unitquery) or die("unit Query failed : " . mysql_error());

    $unitarray = array();
    $unitnames = array();
    if (mysql_num_rows($unitresult) > 0) {
      echo $title;
      while ($urow = mysql_fetch_array($unitresult, MYSQL_ASSOC)) {
        $unitarray[$urow["unit"]] = $urow;
        array_push($unitnames, $urow["unit"]);
      }
      natsort($unitnames);
/////////////////////////////////////////////////////
?>
<table width=98%><tr><td bgcolor="#aaaaaa">
  <table  width=100% cellpadding=2 cellspacing=2> 
  <tr>
    <td width="100" class="th">Unit Name</td>
    <td width="80" class="th">Branch/Role</td>
    <td width="120" class="th">Status</td>
    <td width="550" class="th">Last Message</td>
  </tr>
<?php
/////////////////////////////////////////////////////

      foreach ($unitnames as $u_name) {
        $unitrow = $unitarray[$u_name];
        $u_name_html = str_replace(" ", "&nbsp;", $u_name);
        if ($unitrow["status"] == "Off Duty"  || $unitrow["status"] == "Out of Service") {
          $u_name_html = "<font color=\"gray\">$u_name_html</font>";
        }
        elseif ( ((isset($_COOKIE["units_color"]) && $_COOKIE["units_color"] == "yes") ||
                 !isset($_COOKIE["units_color"]))
                 &&
                 isset($rolecolor[$unitrow["role"]])) {
          $u_name_html = "<font color=\"".$rolecolor[$unitrow["role"]]."\">$u_name_html</font>";
        }
        $u_status_tm = dls_utime($unitrow["update_ts"]);
        $u_status_html = str_replace(" ", "&nbsp;", $unitrow["status"]);

        // TODO: replace as of MySQL 4.1 with a subquery (SELECT... WHERE oid=select(max(oid)...).
        $oidquery = "SELECT oid, unit, ts, message FROM messages WHERE unit = '". $unitrow["unit"] ."' AND deleted=0 ORDER BY oid DESC LIMIT 1";
        $oidresult = mysql_query($oidquery) or die("In query: $oidquery\nError: " . mysql_error());
        if ($line = mysql_fetch_array($oidresult, MYSQL_ASSOC)) {
          $u_time = dls_utime($line["ts"]);
          $u_message = $line["message"];
        }
        else {
          $u_time = "";
          $u_message = "<font color=\"gray\">No messages logged</font>";
        }
        mysql_free_result($oidresult);
    
        echo "\n\n<tr>\n";
        echo $td, "<a href=\"unit.php?unit=", $u_name, "\" onClick=\"return popup('unit.php?unit=".$unitrow["unit"]."')\">", $u_name_html,"</a></td>\n";
        echo $td, $unitrow["role"], "</td>\n";
        if ($u_status_tm != "") 
          echo $td, "$u_status_html&nbsp;($u_status_tm)</td>\n";
        else echo $td, "</td>\n";
        echo $td, "$u_message&nbsp;";
        if ($u_time != "") echo "($u_time)";
        echo "</td>\n</tr>\n";
      
      }
      print "</table>\n</table>\n<p>\n";
    }  
    mysql_free_result($unitresult);
  }
/////////////////////////////////////////////////////////
?>


</body>
</html>

<?php mysql_close($link); ?>
