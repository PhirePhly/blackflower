<?php
  $subsys="incidents";
  require_once('db-open.php');
  require('local-dls.php');
  require_once('session.inc');

  $saved=0;
  $deleted=0;

  if (isset($_POST["note_id"])) {
    $note_id = MysqlClean($_POST,"note_id",20);
    $incident_id = MysqlClean($_POST,"incident_id",20);
    if (isset($_POST["undelete"])) {
      $query = "UPDATE incident_notes SET deleted=0 where note_id=$note_id";
      mysql_query($query) or die("update delete query failed: ".mysql_error());
      $deleted=0;
    }
    elseif (isset($_POST["delete"])) {
      $query = "UPDATE incident_notes SET deleted=1 where note_id=$note_id";
      mysql_query($query) or die("update delete query failed: ".mysql_error());
      $deleted=1;
    }
    elseif (isset($_POST["save"])) {
      $unit = MysqlClean($_POST,"unit",20);
      $message = MysqlClean($_POST,"message",255);
      $query = "UPDATE incident_notes SET unit='$unit', message='$message' WHERE note_id=$note_id";
      mysql_query($query) or die("update query failed: ".mysql_error());
      $saved=1;
      header("Location: incident-notes.php?incident_id=$incident_id");
    }
  }
  elseif (isset($_GET["note_id"]))
    $note_id = MysqlClean($_GET,"note_id",20);
  else 
    die("Improper usage: GET[note_id] must be specified.");

  $query = "SELECT * FROM incident_notes WHERE note_id=$note_id";
  $result = mysql_query($query) or die("select query failed : " . mysql_error());
  if (mysql_num_rows($result) != 1) {
    die("Critical error: ".mysql_num_rows($result)." is a bad number of rows when looking for note_id=$note_id");
  }
  $messagerow = mysql_fetch_array($result, MYSQL_ASSOC);
  $ts = $messagerow["ts"];
  $unit = $messagerow["unit"];
  $message = $messagerow["message"];
  $incident_id = $messagerow["incident_id"];
?>

<html>
<head>
  <title>Dispatch :: Edit Incident Note</title>
</head>
<body>
<font face="tahoma,ariel,sans">

<b>EDIT INCIDENT NOTE</b><p>

  <form name="myform" action="edit-incident-note.php" method="post">
  <table>
  <tr><td bgcolor="#dddddd">Timestamp</td><td bgcolor="#dddddd">Unit</td><td colspan=2 bgcolor="#dddddd">Message</td></tr>
  <tr><td bgcolor="#cccccc"><?php print dls_utime($ts)?> <td bgcolor="#cccccc">

<?php
   if ($deleted) {
     print "<select disabled name=\"unit\">\n";
   }
   else {
     print "<select name=\"unit\">\n";
   }
   $query = "SELECT unit FROM units";
   $result = mysql_query($query) or die("Query failed : " . mysql_error());
  
   $selected=0;
   while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      if (!strcmp($line["unit"], $unit)) {
        echo "<option selected value=\"". $line["unit"] ."\">". $line["unit"] ."\n";
	$selected=1;
      }
      else
        echo "<option value=\"". $line["unit"] ."\">". $line["unit"] ."\n";
   }
   if (!$selected)
      echo "<option selected value=\"\"></option>";
   print "</select>\n";
?>

   </td>
   <td colspan=2 bgcolor="#cccccc">
     <input <?php if ($deleted) print "disabled"?> type="text" name="message" size=100 maxlength=250
      value="<?php print MysqlUnClean($message)?>">
     <input type="hidden" name="note_id" value="<?php print $note_id?>">
     <input type="hidden" name="incident_id" value="<?php print $incident_id?>">
   </td>
   </tr>
   <tr><td></td><td></td><td>
     <input <?php if ($deleted) print "disabled"?> type="submit" name="save" value="Save Changes">
     <input <?php if ($deleted) print "disabled"?> type="reset" value="Clear Changes"><?php if ($saved) print "&nbsp; &nbsp;Changes saved." ?>
   </td>
     <td align=right>
     <input <?php if ($deleted) print "disabled"?> type="submit" name="delete" value="Delete This Entry">  
   <?php 
   if ($deleted) {
       echo "     <input type=\"submit\" name=\"undelete\" value=\"Undelete This Entry\">\n";
       echo "   </td></tr><tr><td colspan=2><td colspan=2><font color=\"red\"> <b>This is your last chance to save this entry -- If you want to save it, Undelete it NOW!";
   }
   ?>
   </td></tr>
   <tr><td colspan=2></td>
       <td><a href="incident-notes.php?incident_id=<?php print $incident_id?>">Return to incident notes</a></td>
   </tr>
   </table>
 </form>

</body>
</html>

<?php mysql_free_result($result) ?>
<?php mysql_close($link) ?>
