<?php
  $subsys="incidents";
  require_once('db-open.php');
  require('local-dls.php');
  require_once('session.inc');

  $saved=0;
  $deleted=0;

  if (!isset($ALLOW_EDIT_INCIDENT_NOTES) || !$ALLOW_EDIT_INCIDENT_NOTES) {
     print "Incident note editing is turned off.";
     exit;
  }
  if (isset($_POST["note_id"])) {
    $note_id = MysqlClean($_POST,"note_id",20);
    $incident_id = MysqlClean($_POST,"incident_id",20);
    if (isset($_POST["undelete"])) {
      $query = "UPDATE incident_notes SET deleted=0 where note_id=$note_id";
      mysqli_query($link, $query) or die("update delete query failed: ".mysqli_error($link));
      $deleted=0;
    }
    elseif (isset($_POST["delete"])) {
      $query = "UPDATE incident_notes SET deleted=1 where note_id=$note_id";
      mysqli_query($link, $query) or die("update delete query failed: ".mysqli_error($link));
      $deleted=1;
    }
    elseif (isset($_POST["save"])) {
      $unit = MysqlClean($_POST,"unit",20);
      $message = MysqlClean($_POST,"message",255);
      $query = "UPDATE incident_notes SET unit='$unit', message='$message' WHERE note_id=$note_id";
      mysqli_query($link, $query) or die("update query failed: ".mysqli_error($link));
      $saved=1;
      header("Location: incident-notes.php?incident_id=$incident_id");
    }
  }
  elseif (isset($_GET["note_id"]))
    $note_id = MysqlClean($_GET,"note_id",20);
  else 
    die("Improper usage: GET[note_id] must be specified.");

  $query = "SELECT * FROM incident_notes WHERE note_id=$note_id";
  $result = mysqli_query($link, $query) or die("select query failed : " . mysqli_error($link));
  if (mysqli_num_rows($result) != 1) {
    die("Critical error: ".mysqli_num_rows($result)." is a bad number of rows when looking for note_id=$note_id");
  }
  $messagerow = mysqli_fetch_array($result, MYSQLI_ASSOC);
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
   $result = mysqli_query($link, $query) or die("Query failed : " . mysqli_error($link));
   $unitnames = array();
   while ($line = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
     array_push($unitnames, $line["unit"]);
   }
   natsort($unitnames);
  
   $foundselected=0;
   foreach ($unitnames as $oneunit) {
      echo "<option ";
      if (!strcmp($oneunit, $unit)) {
        echo " selected ";
	      $foundselected=1;
      }
      echo " value=\"$oneunit\">$oneunit</option>\n";
   }
   if (!$foundselected)
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

<?php mysqli_free_result($result) ?>
<?php mysqli_close($link) ?>
