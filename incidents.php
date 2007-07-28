<?php
  $subsys="incidents";

  require_once('session.inc');
  require_once('functions.php');

  if (isset($_POST["incidents_open_only"])) {
    if ($_POST["incidents_open_only"] == "Hide Closed" &&
        (!isset($_COOKIE["incidents_open_only"]) || $_COOKIE["incidents_open_only"] == "no")) {
      setcookie("incidents_open_only", "yes");
    }
    elseif ($_POST["incidents_open_only"] == "Show All" &&
            (!isset($_COOKIE["incidents_open_only"]) || $_COOKIE["incidents_open_only"] == "yes")) {
      setcookie("incidents_open_only", "no");
    }
    header("Location: http://".$_SERVER['HTTP_HOST'].$_SERVER["PHP_SELF"]);
    exit;
  }

  header_html("Dispatch :: Incidents","  <script src=\"js/clock.js\" type=\"text/javascript\"></script>");
?>
<body vlink="blue" link="blue" alink="cyan"
      onload="displayClockStart()"
      onunload="displayClockStop()"
      onresize="resizeMe()">
<? include('include-title.php'); ?>
<table width="98%">
<tr>
  <td align="left" class="text"><b>Incidents</b></td>
  <td align="left" width="100%">
    <form name="createform" action="incidents.php" method="post" style="margin: 0px;">
    <button type="submit" value="Create New Incident" title="Create New Incident - ALT-N" accesskey="n"
     onClick="return popup('edit-incident.php?incident_id=new','incident-new',600,1000)" class="newbutton"
     >Create <U>N</U>ew Incident</button>
    </form>
  </td>
<?php
  if ($_COOKIE["incidents_open_only"] == "no") {
    print "<td nowrap>\n";

    print "<form name=\"filter\" action=\"incidents-frame.php\" method=\"post\" style=\"border: 0px;\" target=\"incidents\">\n";
    print "<table border=0 cellpadding=0 cellspacing=0>\n";
    print "<tr>\n";

    print "<td nowrap class=\"text\">Date:\n";
    print "<select name=\"date\" id=\"date\" tabindex=\"101\">\n";
    print "<option value=\"\"></option>\n";
    $datesquery = "SELECT DISTINCT CAST(ts_opened AS DATE) AS tsdate FROM incidents ORDER BY ts_opened DESC";
    $datesresult = MysqlQuery($datesquery);
    $dates = array();
    while ($line = mysql_fetch_array($datesresult, MYSQL_ASSOC)) {
      array_push($dates, $line["tsdate"]);
    }
    foreach ($dates as $date) {
      echo "<option value=\"$date\">$date</option>\n";
    }
    mysql_free_result($datesresult);
    print "</select>\n";
    print "</td>\n";

    print "<td nowrap class=\"text\">Type:\n";
    print "<select name=\"calltype\" id=\"calltype\" tabindex=\"102\">\n";
    print "<option value=\"\"></option>\n";
    $calltypequery = "SELECT call_type FROM incident_types";
    $calltyperesult = MysqlQuery($calltypequery);
    $calltypes = array();
    while ($line = mysql_fetch_array($calltyperesult, MYSQL_ASSOC)) {
      array_push($calltypes, $line["call_type"]);
    }
    foreach ($calltypes as $calltype) {
      echo "<option value=\"$calltype\">$calltype</option>\n";
    }
    mysql_free_result($calltyperesult);
    print "</select>\n";
    print "</td>\n";

    print "<td nowrap class=\"text\">Scroll:<input type=\"checkbox\" name=\"scroll\" id=\"scroll\" checked/></td>\n";

    print "<td nowrap>\n";
    print "<button type=\"submit\" name=\"apply_filters\" id=\"apply_filters\" value=\"apply_filters\">Filter</button>\n";
    print "<button type=\"submit\" name=\"remove_filters\" id=\"remove_filter\" value=\"remove_filters\"\n";
    print " onClick=\"document.getElementById('date').options[0].selected=true;\n";
    print "           document.getElementById('calltype').options[0].selected=true;\n";
    print "           document.getElementById('scroll').checked=true;\"\n";
    print " >Reset</button>\n";
    print "</td>\n";

    print "</tr>\n";
    print "</table>\n";
    print "</form>\n";
    print "</td>\n";

    print "<td>&nbsp;&nbsp;</td>\n";
  }
?>
  <td>
  <form name="modeform" action="incidents.php" method="post" style="margin: 0px;">
<?php
  if (!isset($_COOKIE["incidents_open_only"]) || $_COOKIE["incidents_open_only"] == "yes") {
    print "<button type=\"submit\" name=\"incidents_open_only\" id=\"incidents_open_only\" ";
    print "value=\"Show All\" title=\"Show All Incients\">Show All</button>\n";
  }
  else {
    print "<button type=\"submit\" name=\"incidents_open_only\" id=\"incidents_open_only\" ";
    print "value=\"Hide Closed\" title=\"Hide Closed Incients\">Hide Closed</button>\n";
  }
?>
  </form>
  </td>
  <td>&nbsp;&nbsp;</td>
  <td align="right">
  <form name="myform" action="incidents.php" method="post" style="margin: 0px;">
  <input type="text" name="displayClock" size="8" />
  </form>
  </td>
</tr>
</table>
</form>

<iframe name="incidents" src="incidents-frame.php<?php if ($_COOKIE["incidents_open_only"] == "no") echo "?scroll=yes"; ?>"
        width="<?=trim($_COOKIE['width']) - 30; ?>"
        height="<?=trim($_COOKIE['height']) - 140; ?>"
        marginheight="0" marginwidth="0" frameborder="0"></iframe>

</body>
</html>
