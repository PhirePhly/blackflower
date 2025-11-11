<?php
        
  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');

  $subsys='cad';
  SessionErrorIfReadonly();
  $deleted=0;

  if (isset($_POST["oid"])) {
    $oid = MysqlClean($_POST,"oid",20);
    if (isset($_POST["undelete"])) {
      $query = "UPDATE messages SET deleted=0 where oid=$oid";
      mysqli_query($link, $query) or die("update delete query failed: ".mysqli_error($link));
      $deleted=0;
    }
    elseif (isset($_POST["delete"])) {
      $query = "UPDATE messages SET deleted=1 where oid=$oid";
      mysqli_query($link, $query) or die("update delete query failed: ".mysqli_error($link));
      $deleted=1;
    }
    elseif (isset($_POST["save"])) {
      $unit = MysqlClean($_POST,"unit",20);
      $message = MysqlClean($_POST,"message",255);
      $message_type = MysqlClean($_POST,"message_type",20);
      $query = "UPDATE messages SET unit='$unit', message='$message', message_type='$message_type' WHERE oid=$oid";
      mysqli_query($link, $query) or die("update query failed: ".mysqli_error($link));
    }

    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    die("(Error: JavaScript not enabled or not present) Action completed. Close this window to continue.");
  }
  if (!isset($_GET["oid"]))
    die("Improper usage: GET[oid] must be specified.");

  $oid = MysqlClean($_GET,"oid",20);
  $query = "SELECT * FROM messages WHERE oid=$oid";
  $result = mysqli_query($link, $query) or die("select query failed : " . mysqli_error($link));
  if (mysqli_num_rows($result) != 1) {
    die("Critical error: ".mysqli_num_rows($result)." is a bad number of rows when looking for oid=$oid");
  }
  $messagerow = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $ts = $messagerow["ts"];
  $unit = $messagerow["unit"];
  $message = $messagerow["message"];
  $message_type = $messagerow["message_type"];
?>

<html>
<head>
  <title>Dispatch :: Edit Message</title>
</head>
<body>
<font face="tahoma,ariel,sans">

<b>EDIT MESSAGE</b>
<p>

  <form name="myform" action="edit-message.php" method="post">
  <table>
  <tr><td bgcolor="#dddddd">Timestamp</td><td bgcolor="#dddddd">Unit</td>
  <?php
    if ($USE_MESSAGE_TYPE) {
      if (isset($_COOKIE['cad_show_message_type']) && $_COOKIE['cad_show_message_type'] == 'yes') 
        print "<td bgcolor=\"#dddddd\">Type</td>";
    }
  ?>
  <td colspan=2 bgcolor="#dddddd">Message</td></tr>

  <tr>
    <td bgcolor="#cccccc"><?php print dls_utime($ts)?> 
    <td bgcolor="#cccccc">

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
   foreach ($unitnames as $unitname) {
      echo "<option ";
      if (!strcmp($unitname, $unit)) echo " selected ";
      echo " value=\"$unitname\">$unitname</option>\n";
   }
   print "</select>\n";
?>
<?php
   if ($USE_MESSAGE_TYPE) {
     if (isset($_COOKIE['cad_show_message_type']) && $_COOKIE['cad_show_message_type'] == 'yes') {
       print "</td><td>";
       if ($deleted) 
         print "<select disabled name=\"message_type\">\n";
       else 
         print "<select name=\"message_type\">\n";
     
       $query = "SELECT message_type FROM message_types";
       $result = mysqli_query($link, $query) or die("Query failed : " . mysqli_error($link));
  
       while ($line = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
          if (!strcmp($line["message_type"], $message_type)) 
            echo "<option selected value=\"". $line["message_type"] ."\">". $line["message_type"] ."\n";
          else
            echo "<option value=\"". $line["message_type"] ."\">". $line["message_type"] ."\n";
       }
       print "</select>\n";
     }
   }
   else 
     print "<input type=hidden name=\"message_type\" value=\"$message_type\">\n";
?>
   </td>

   <td colspan=2 bgcolor="#cccccc">
     <input <?php if ($deleted) print "disabled"?> type="text" name="message" size=90 maxlength=250
      value="<?php print MysqlUnClean($message)?>">
     <input type="hidden" name="oid" value="<?php print $oid?>">
   </td>
   </tr>
   <tr><td colspan=2></td>
<?php
  if ($USE_MESSAGE_TYPE) {
    if (isset($_COOKIE['cad_show_message_type']) && $_COOKIE['cad_show_message_type'] == 'yes') print "<td></td>";
  }
?>
   <td>
     <input <?php if ($deleted) print "disabled"?> type="submit" name="save" value="Save">
     <input <?php if ($deleted) print "disabled"?> type="submit" name="cancel" value="Cancel">
   </td>
     <td align=right>
     <input <?php if ($deleted) print "disabled"?> type="submit" name="delete" value="Delete This Message">  
   <?php 
   if ($deleted) {
       echo "     <input type=\"submit\" name=\"undelete\" value=\"Undelete This Entry\">\n";
       echo "   </td></tr><tr><td colspan=2><td colspan=2><font color=\"red\"> <b>This is your last chance to save this entry -- If you want to save it, Undelete it NOW!";
   }
   ?>
   </td></tr>
   </table>
 </form>

</body>
</html>

<?php mysqli_free_result($result) ?>
<?php mysqli_close($link) ?>
