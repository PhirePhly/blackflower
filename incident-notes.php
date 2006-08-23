<?php
  #### How much of this file is still used?? Seems a lot of it has gone to edit-incident.php.

  $subsys="cad";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  define ('ACTION_NEW', 0);
  define ('ACTION_EDIT', 1);
  define ('ACTION_DELETE', 2);

  if (isset($_POST["incident_id"])) {
    $incident_id = MysqlClean($_POST,"incident_id",20);
    $unit = MysqlClean($_POST,"unit",20);
    $message = MysqlClean($_POST,"message",255);
    $action = MysqlClean($_POST,"action",20);

    if (isset($_SESSION["username"]) && $_SESSION["username"] != "")
      $creator = $_SESSION["username"];
    else
      $creator = "-";

    if ($action == ACTION_NEW) {
      $query = "INSERT INTO incident_notes (incident_id, ts, unit, message, creator) VALUES ($incident_id, NOW(), '$unit', '$message', '$creator')";
      mysql_query($query) or die ("Error with query: ".mysql_error());
      header("Location: edit-incident.php?incident_id=$incident_id");
    }
  }
  if (isset($_GET["incident_id"])) {
    $incident_id = $_GET["incident_id"];
  }
  else
    die ("No incident ID specified in URI.");

  $resultURI = $_SERVER["PHP_SELF"];
  if (isset($_GET["incident_id"]))
    $resultURI .= "?incident_id=" . MysqlClean($_GET,"incident_id",20);
  header_html("Dispatch :: Incident Viewer","",$resultURI)
?>
<body vlink="blue" link="blue" alink="cyan">
  <table width="100%">
  <tr>
     <td bgcolor="#aaaaaa">
     <table width="100%" cellpadding="0" cellspacing="1">
     <tr bgcolor="darkgray">
<?
  if (isset($_COOKIE['incidents_show_creator'])) {
    print "      <td class=\"ihsmall\"><font size=\"-2\" color=\"gray\">Logged By</font></td>";
  } ?>
        <td class="ihsmall">Time</td>
        <td class="ihsmall">Unit</td>
        <td width="100%" class="ihsmall">Note</td>
     </tr>

<?php
  $query = "SELECT * FROM incident_notes WHERE incident_id=$incident_id AND deleted=0 ORDER BY note_id DESC";
  $result = mysql_query($query) or die("Query failed : " . mysql_error());

  if (mysql_num_rows($result)) {
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      ((int)THIS_PAGETS - date("U", strtotime($line["ts"]))) < 300 ? $quality="<b>" : $quality="";
      echo "<tr>\n";
      if (isset($_COOKIE['incidents_show_creator'])) {
        if (isset($line["creator"] ) && $line["creator"] != "NULL" && $line["creator"] != "")
          echo "<td class=\"message\"><font color=\"gray\">", $line["creator"], "</font></td>";
        else
          echo "<td class=\"message\"></td>";
      }
      echo "<td class=\"message\">", $quality, dls_hmstime($line["ts"]), "</td>";
      echo "<td class=\"message\">", $quality, dls_ustr($line["unit"]), "</td>";
      echo "<td class=\"message\">";
      echo "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\"><tr><td align=\"left\" class=\"message\">\n";
      echo $quality, MysqlUnClean($line["message"]), "</td>\n";
      echo "<td align=\"right\" class=\"smalltext\"><a href=\"edit-incident-note.php?note_id=".$line["note_id"]."\" target=\"_self\"><font color=gray>[edit]</font></a></td>\n";
      echo "</tr></table></td></tr>\n";
    }
  } else {
    echo "<tr>\n";
    echo "<td class=\"message\"><center>-</center>";    // creator
    echo "<td class=\"message\"><center>-</center>";    // time stamp
    echo "<td class=\"message\"><center>-</center>";    // unit string
    echo "<td class=\"message\"><font color=\"gray\" size=\"-1\">No notes entered</font></td>";
    echo "</tr>\n";
  }
  echo "</table></table>\n";

  mysql_free_result($result);
  mysql_close($link);
?>

</body>
</html>
