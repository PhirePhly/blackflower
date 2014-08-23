<?php
  $subsys="units";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  $td= "    <td class=\"message\">";

  if (isset($_POST["addunit"])) {
    if (!CheckAuthByLevel('admin_general', $_SESSION['access_level'])) {
      print "Access level too low to create units.";
      exit;
    }

    if (strpos($_POST["addunit"], "'")) {
      die("An apostrophe is an invalid character for use in a unit name.");
      // TODO: handle error condition better
    }
    $query = "INSERT INTO units (unit) VALUES (UPPER('".MysqlClean($_POST,"addunit",20)."'))";
    mysql_query($query) or die ("couldn't insert unit: ".mysql_error());
    header('Location: units.php');
  }

  $query = "SELECT role, color_html FROM unit_roles";
  $result = mysql_query($query) or die ("In query: $query\nError: ".mysql_error());
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $rolecolor[$line["role"]] = $line["color_html"];
  }

  header_html("Dispatch :: Unit Listing");

?>
<body vlink="blue" link="blue" alink="cyan"
      onload="displayClockStart()"
      onunload="displayClockStop()">
<form name="myform" action="unit-frame.php" method="post">
<?php
  $types = array("Unit", "Individual", "Generic", "");
  foreach ($types as $type) {
    if ($type == "") {
      $wheretype = " WHERE type IS NULL";
      $title = "&nbsp;&nbsp;<b>Other</b> Class Units";
    } else {
      $wheretype = "WHERE type='$type'";
      $title = "&nbsp;&nbsp;<b>$type</b> Class Units";
    }

    $unitquery = "SELECT * from units u LEFT OUTER JOIN unit_assignments a ON u.assignment=a.assignment $wheretype ORDER BY unit ASC ";
    $unitresult = mysql_query($unitquery) or die("unit Query failed : ".mysql_error());

    $unitarray = array();
    $unitnames = array();
    if (mysql_num_rows($unitresult) > 0) {
      echo "<span class=\"text\">".$title."</span>";
      while ($urow = mysql_fetch_array($unitresult, MYSQL_ASSOC)) {
        $unitarray[$urow["unit"]] = $urow;
        array_push($unitnames, $urow["unit"]);
      }
      natsort($unitnames);

//---------------------------------------------------------------------------
?>

<table width="98%"><tr><td bgcolor="#aaaaaa">
  <table width="100%" cellpadding="1" cellspacing="1">
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
        if ($unitrow["status"] == "Off Duty"  ||
            $unitrow["status"] == "Out of Service" ||
            $unitrow["status"] == "Off Playa") {
          $u_name_html = "<span style='color: gray;'>$u_name_html</span>";
        }
        elseif ( ((isset($_COOKIE["units_color"]) && $_COOKIE["units_color"] == "yes") ||
                 !isset($_COOKIE["units_color"]))
                 &&
                 isset($rolecolor[$unitrow["role"]])) {
          $u_name_html = "<span style='color: ".$rolecolor[$unitrow["role"]].";'>$u_name_html</span>";
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
          $u_message = "<span style='color: gray;'>No messages logged</span>";
        }
        mysql_free_result($oidresult);

        $icon = "";
        if (isset($unitrow["assignment"])) {
          $icon = "<span class=" . $unitrow["display_class"] . " title=\"" .
                  $unitrow["description"] . "\">" . $unitrow["assignment"] .
                  "</span>";
        }

        echo "\n  <tr>\n";
        echo $td, "<a href=\"edit-unit.php?unit=", $u_name, "\" onClick=\"return popup('edit-unit.php?unit=".$unitrow["unit"]."','unit(edit)',500,700)\" TARGET=\"_blank\">", $u_name_html,"</a>&nbsp;&nbsp;$icon</td>\n";


        echo $td, $unitrow["role"], "</td>\n";
        if ($u_status_tm != "")
          echo $td, "$u_status_html&nbsp;($u_status_tm)</td>\n";
        else echo $td, "</td>\n";
        echo $td, "$u_message&nbsp;";
        if ($u_time != "") echo "($u_time)";
        echo "</td>\n  </tr>\n";

      }
      print "  </table>\n</table>\n<p />\n";
    }
    mysql_free_result($unitresult);
  }
/////////////////////////////////////////////////////////
?>
</body>
</html>
<?php mysql_close($link); ?>
