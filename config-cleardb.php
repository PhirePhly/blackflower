<?php
  $subsys="admin";
  require_once('db-open.php');
  require_once('session.inc');
  require_once('functions.php');

  $td = "<td bgcolor=#cccccc>";

  if ($_SESSION['access_level'] < 10) {
    syslog(LOG_WARNING, "Database clearing attempted without permissions by user ". $_SESSION['username'] ." level ". $_SESSION['access_level']);
    echo "Access level insufficient for this operation.<br>\n";
    echo "User: " . $_SESSION['username'] . "<br>\n";
    echo "Level: " . $_SESSION['access_level'] . "<br>\n";
    exit;
  }
  elseif (isset($_POST["cleardb"]) && $_POST["cleardb"] == 3) {

  /* Define timestamp and get a lock on tables */
    MysqlQuery("LOCK TABLES deployment_history READ, archive_master WRITE");
    $comment = MysqlClean($_POST, "comment", 80);
    $ts = date("Ymd_His");
    sleep(1);
    syslog(LOG_WARNING, "Database was archive/cleared to archive tag [$ts] by user ". $_SESSION['username'] ." level ". $_SESSION['access_level']);

    $dbver = 'NULL';
    $codever = 'NULL';
    $dephist = MysqlQuery("SELECT * FROM deployment_history ORDER BY idx DESC LIMIT 1");
    if (mysql_num_rows($dephist)) {
      $schemaver = mysql_fetch_object($dephist);
      $dbver = "'" . $schemaver->database_version . "'";
      $codever = "'" . $schemaver->requires_code_ver . "'";
    }

  /* Note revision in master archive table */
    MysqlQuery("INSERT INTO archive_master VALUES ('$ts', NOW(), '$comment', $dbver, $codever)");
    if (mysql_affected_rows() != 1) die("Error registering archive checkpoint [$ts] in archive_master table");

  /* Make backup copies of all relevant tables and data */

    MysqlQuery("CREATE TABLE cadarchives.messages_$ts LIKE messages ");
    MysqlQuery("CREATE TABLE cadarchives.incidents_$ts LIKE incidents ");
    MysqlQuery("CREATE TABLE cadarchives.incident_notes_$ts LIKE incident_notes ");
    MysqlQuery("CREATE TABLE cadarchives.incident_units_$ts LIKE incident_units ");
    MysqlQuery("CREATE TABLE cadarchives.bulletins_$ts LIKE bulletins ");
    MysqlQuery("CREATE TABLE cadarchives.bulletin_views_$ts LIKE bulletin_views ");
    MysqlQuery("CREATE TABLE cadarchives.bulletin_history_$ts LIKE bulletin_history ");
    MysqlQuery("CREATE TABLE cadarchives.units_$ts LIKE units ");
    MysqlQuery("CREATE TABLE cadarchives.unit_incident_paging_$ts LIKE unit_incident_paging ");
    MysqlQuery("CREATE TABLE cadarchives.deployment_history_$ts LIKE deployment_history ");

    MysqlQuery("LOCK TABLES messages WRITE, incidents WRITE, incident_notes WRITE, 
                  incident_units WRITE, bulletins WRITE, bulletin_views WRITE,
                  bulletin_history WRITE, units WRITE, unit_incident_paging WRITE,
                  cadarchives.messages_$ts WRITE, cadarchives.incidents_$ts WRITE, cadarchives.incident_notes_$ts WRITE, 
                  cadarchives.incident_units_$ts WRITE, cadarchives.bulletins_$ts WRITE, cadarchives.bulletin_views_$ts WRITE,
                  cadarchives.bulletin_history_$ts WRITE, cadarchives.units_$ts WRITE, cadarchives.unit_incident_paging_$ts WRITE,
                  cadarchives.deployment_history_$ts WRITE, deployment_history WRITE,
                  archive_master WRITE");

    MysqlQuery("INSERT INTO  cadarchives.messages_$ts SELECT * FROM messages");
    MysqlQuery("INSERT INTO  cadarchives.incidents_$ts SELECT * FROM incidents");
    MysqlQuery("INSERT INTO  cadarchives.incident_notes_$ts SELECT * FROM incident_notes");
    MysqlQuery("INSERT INTO  cadarchives.incident_units_$ts SELECT * FROM incident_units");
    MysqlQuery("INSERT INTO  cadarchives.bulletins_$ts SELECT * FROM bulletins");
    MysqlQuery("INSERT INTO  cadarchives.bulletin_views_$ts SELECT * FROM bulletin_views");
    MysqlQuery("INSERT INTO  cadarchives.bulletin_history_$ts SELECT * FROM bulletin_history");
    MysqlQuery("INSERT INTO  cadarchives.units_$ts SELECT * FROM units");
    MysqlQuery("INSERT INTO  cadarchives.unit_incident_paging_$ts SELECT * FROM unit_incident_paging");
    MysqlQuery("INSERT INTO  cadarchives.deployment_history_$ts SELECT * FROM deployment_history");

  /* Clear relevant tables and data */
    MysqlQuery("DELETE FROM messages");
    MysqlQuery("DELETE FROM incident_notes");
    MysqlQuery("DELETE FROM incident_units");
    MysqlQuery("DELETE FROM incidents");
    MysqlQuery("DELETE FROM bulletins");
    MysqlQuery("DELETE FROM bulletin_views");
    MysqlQuery("DELETE FROM bulletin_history");
    MysqlQuery("UPDATE units SET status=NULL, update_ts=NULL, status_comment=NULL, personnel_ts=NULL, location_ts=NULL, notes_ts=NULL");

  /* Finish */
    MysqlQuery("UNLOCK TABLES");
    sleep(1);
    header("Location: admin.php");
    exit;
  }

  header_html("Dispatch :: Configuration");
?>
<body vlink="blue" link="blue" alink="cyan">
<? include('include-title.php'); ?>
<center><b>CLEARING THE DATABASE</b></center>

<p>
<center><blink><font color=red><b>WARNING</b></font></blink></center>
<p>
<table width="100%">
<tr>
<td width="25%">
</td>
<td width="50%">
<center>
<font color=red>
Clearing the database will DELETE ALL LOG MESSAGES AND UNIT STATUS ENTRIES.
Do not do this unless you are really, <i>really</i>, <b>REALLY</b>
sure this is what you want to do!<p>
(Note: Unit definitions will not be deleted, you must manually delete them.)
</font>
</center>
</td>
<td width="25%">
</td>
</tr>
</table>
<p>
  <form name="myform" action="config-cleardb.php" method="post">
<table>
  <tr>
  <?php
    if (!isset($_POST["cleardb"]) || $_POST["cleardb"] == 0) {
      echo $td, "Are you SURE you want to do this?</td>\n";
      echo $td, "<input type=\"checkbox\" name=\"cleardb\" value=\"1\"> </td>\n";
      echo "<td><input type=\"submit\" value=\"Yes!\"> </td>";
    }
    elseif (isset($_POST["cleardb"]) && $_POST["cleardb"] == 1) {
      echo $td, "Are you SURE you want to do this?</td>\n";
      echo $td, "<input type=\"checkbox\" name=\"cleardb\" value=\"1\" disabled checked> </td>\n</tr>\n<tr>\n";
      echo $td, "Are you REALLY SURE you want to do this?</td>\n";
      echo $td, "<input type=\"checkbox\" name=\"cleardb\" value=\"2\"> </td>";
      echo "<td><input type=\"submit\" value=\"Yes!\"> </td>";
      echo "</tr><tr>\n";
    }
    elseif (isset($_POST["cleardb"]) && $_POST["cleardb"] == 2) {
      echo $td, "Are you SURE you want to do this?</td>\n";
      echo $td, "<input type=\"checkbox\" name=\"cleardb\" value=\"1\" disabled checked> </td>\n</tr>\n<tr>\n";
      echo $td, "Are you REALLY SURE you want to do this?</td>\n";
      echo $td, "<input type=\"checkbox\" name=\"cleardb\" value=\"2\" disabled checked> </td>\n</tr>\n<tr>\n";
      echo "<td bgcolor=#cccccc colspan=2> <input type=\"hidden\" name=\"cleardb\" value=\"3\"> \n";
      echo "Comment: <input type=\"text\" maxlength=\"80\" size=\"40\" name=\"comment\"></td>";
      echo "<td><input type=\"submit\" value=\"Archive and Clear Database\"> </td>";
    }
  ?>
  </tr>
  </table>

  </form>
</ul>
<p>
<hr>
<a href="admin.php">Abort and return to Admin page</a><br>
</body>
</html>


