<?php
  $subsys="config";
  require_once('db-open.php');
  require_once('session.inc');

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
    $comment = MysqlClean($_POST, "comment", 80);
    $ts = date("Ymd_His");
    $query = "LOCK TABLES archive_master WRITE, messages WRITE, units WRITE, incidents WRITE, incident_notes WRITE";
    mysql_query($query) or die("locking Query failed : " . mysql_error());
  
  /* Note revision in master archive table */
    $query = "INSERT INTO archive_master VALUES ('$ts', NOW(), '$comment')";
    mysql_query($query) or die("archive master  Query failed : " . mysql_error());
    if (mysql_affected_rows() != 1) die("Error registering this checkpoint in archive_master table!!");
  
  /* Make backup copies of all relevant tables and data */
  
  /* The *easy* syntax here is CREATE TABLE .. LIKE ..   but that's only in MySQL 4.1.  Currently using 4.0.x. */
  
    $query = "CREATE TABLE cadarchives.messages_$ts (oid int not null auto_increment primary key, ts datetime not null, unit varchar(20), message varchar(255) not null, deleted bool not null default 0, creator varchar(20), message_type varchar(20))";
       mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());

    $query = "CREATE TABLE cadarchives.units_$ts (unit varchar(20) not null primary key, status varchar(30), status_comment varchar(255), update_ts datetime, 	role	set('Fire', 'Medical', 'Comm', 'MHB', 'Admin', 'Other'), type	set('Unit', 'Individual', 'Generic'), personnel varchar(100))";
       mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());

    $query = "CREATE TABLE cadarchives.incidents_$ts (incident_id int not null auto_increment primary key, call_type varchar(40), call_details varchar(80), ts_opened datetime not null, ts_dispatch datetime, ts_arrival datetime, ts_complete datetime, location varchar(80), location_num varchar(15), reporting_pty varchar(80), contact_at varchar(80), disposition varchar(80), visible bool not null default 0, primary_unit varchar(20), completed bool not null default 0, updated datetime not null)";
       mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());

    $query = "CREATE TABLE cadarchives.incident_notes_$ts (note_id int not null auto_increment primary key, incident_id int not null, ts datetime not null, unit varchar(20), message varchar(255) not null, deleted bool not null default 0, creator varchar(20))";
       mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());

    $query = "CREATE TABLE cadarchives.incident_units_$ts (uid int not null auto_increment primary key, incident_id int not null, unit varchar(20) not null, dispatch_time datetime, arrival_time datetime, cleared_time datetime, is_primary bool, is_generic bool)";
       mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());

       /* ------------------ */

    $query = "LOCK TABLES archive_master WRITE, messages WRITE, units WRITE, incidents WRITE, incident_notes WRITE, incident_units WRITE, cadarchives.units_$ts WRITE, cadarchives.messages_$ts WRITE, cadarchives.incidents_$ts WRITE, cadarchives.incident_notes_$ts WRITE, cadarchives.incident_units_$ts WRITE";
    mysql_query($query) or die("locking Query failed : " . mysql_error());

    $query = "INSERT INTO cadarchives.messages_$ts SELECT * from messages";
    mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());
    $query = "INSERT INTO cadarchives.units_$ts SELECT * from units";
    mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());
    $query = "INSERT INTO cadarchives.incidents_$ts SELECT * from incidents";
    mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());
    $query = "INSERT INTO cadarchives.incident_notes_$ts SELECT * from incident_notes";
    mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());
    $query = "INSERT INTO cadarchives.incident_units_$ts SELECT * from incident_units";
    mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());
  
  /* Clear relevant tables and data */
    $query = "DELETE FROM messages";
    mysql_query($query) or die("message clearing Query failed : " . mysql_error());
    $query = "DELETE FROM incident_notes";
    mysql_query($query) or die("incident_notes clearing Query failed : " . mysql_error());
    $query = "DELETE FROM incident_units";
    mysql_query($query) or die("incident_units clearing Query failed : " . mysql_error());
    $query = "DROP TABLE incidents";
    mysql_query($query) or die("incidents deleting Query failed : " . mysql_error());
    $query = "CREATE TABLE incidents (incident_id int not null auto_increment primary key, call_type varchar(40), call_details varchar(80), ts_opened datetime not null, ts_dispatch datetime, ts_arrival datetime, ts_complete datetime, location varchar(80), location_num varchar(15), reporting_pty varchar(80), contact_at varchar(80), disposition varchar(80), visible bool not null default 0, primary_unit varchar(20), completed bool not null default 0, updated datetime not null)";
    mysql_query($query) or die("Query failed ($query) -- error was: " . mysql_error());
    $query = "UPDATE units SET status=NULL, update_ts=NULL, status_comment=NULL";
    mysql_query($query) or die("status resetting Query failed : " . mysql_error());
  
  /* Finish */
    $query = "UNLOCK TABLES";
    mysql_query($query) or die("unlocking Query failed : " . mysql_error());
    header("Location: config.php");
  }

?> 

<html>
<head>
  <title>Dispatch :: Configuration</title>
</head>
<body vlink=blue link=blue alink=cyan>
<?php include('include-title.php') ?>
<?php include('include-footer.php') ?>

<p>
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
<a href="config.php">Abort and return to Configuration page</a><br>
</body>
</html>


