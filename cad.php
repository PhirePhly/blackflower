<?php
  $subsys="cad";

  require_once('db-open.php');
  require_once('session.inc');
  require_once('functions.php');

  if (isset($_POST['unit']))  {
    $post_pend="";
    $unit = MysqlClean($_POST, 'unit', 20);

    if (isset($_POST['message']))
      $message = MysqlClean($_POST, 'message', 255);
    else
      die("Internal error: POST message value missing.");

    if (isset($_SESSION['username']) && $_SESSION['username'] != '') {
      $creator = $_SESSION['username'];
    }
    else {
      $creator = '';
    }

    if (isset($_POST['message_type']))
      $message_type = MysqlClean($_POST, 'message_type', 20);
    else
      $message_type = "";

    $query = "INSERT INTO messages (ts,unit,message,creator,message_type) VALUES (NOW(), UPPER('$unit'), '$message', '$creator', '$message_type')";
    mysql_query($query) or die("Query failed : " . mysql_error());
    mysql_close($link);

    if (isset($_POST["hour"]))
      $post_pend .= "&hour=". MysqlClean($_POST, "hour", 20);
    # TODO: is this code still used?  there should also be a 'date' in post_pend.

    header("Location: cad.php$post_pend");
  }

  header_html("Dispatch :: Log Viewer","  <script src=\"js/clock.js\" type=\"text/javascript\"></script>");
?>
<body vlink="blue" link="blue" alink="cyan" onload="displayClockStart()" onunload="displayClockStop()" onresize="resizeMe()">
<? include('include-title.php'); ?>
<form name="myform" action="cad.php" method="post" style="margin: 0px;">

<b class="text">Log Viewer :: Add a Message:</b><br>

<table width="100%" style="margin-bottom: 8px;">
<tr>
<td bgcolor="#aaaaaa">

<table width="100%" cellspacing="1" cellpadding="2">
<tr valign="bottom" style='font-size: 8pt;' class='message'>
   <td class="text" nowrap>Message time:</td>
   <td class="text" nowrap><u>S</u>elect unit from list:&nbsp;</td>
 <?php
   if (!isset($_COOKIE['cad_show_message_type']) || $_COOKIE['cad_show_message_type'] == 'yes') {
     print '<td class="text" nowrap>Message type:&nbsp;</td>';
   }
 ?>
   <td class="text">Enter <u>m</u>essage</td>
   <td nowrap rowspan="2">
     <input type="submit" value="" style="visibility: hidden; width: 0px; height: 0px;"><br>
     &nbsp;&crarr;&nbsp;
     <input type="reset" value="Clear" tabindex="5">
   </td>
</tr>
<tr class="message">
   <td><input type="text" name="displayClock" size="8" /></td>
   <td>
     <label for="unit" accesskey="s">
     <select name="unit" id="unit" tabindex="3" style="width:150px">
     <option selected value=""></option>
<?php
      $unitquery = "SELECT unit FROM units";
      $unitresult = mysql_query($unitquery) or die("In query: $unitquery<br />\nError: " . mysql_error());
      $unitnames = array();
      while ($line = mysql_fetch_array($unitresult, MYSQL_ASSOC)) {
        array_push($unitnames, $line["unit"]);
      }
      natsort($unitnames);
      foreach ($unitnames as $unitname) {
         echo "<option value=\"$unitname\">$unitname</option>\n";
      }
      mysql_free_result($unitresult);
 ?>
     </select>
     </label>
   </td>

 <?php
      if (!isset($_COOKIE['cad_show_message_type']) || $_COOKIE['cad_show_message_type'] == 'yes') {
        print '<td>';
        print '<select tabindex="4" style="width:150px" name="message_type">';
        print '<option selected value=""></option>';
        $typequery = "SELECT message_type FROM message_types ORDER BY message_type ASC";
        $typeresult = mysql_query($typequery) or die("In query: $typequery<br />\nError: " . mysql_error());
        while ($line = mysql_fetch_array($typeresult, MYSQL_ASSOC)) {
           print "<option value=\"". $line["message_type"] ."\">". $line["message_type"] ."</option>\n";
        }
        mysql_free_result($typeresult);
        print "</select>\n</td>\n";
      }
 ?>

   <td>
   <label for="message" accesskey="m">
   <input type="text" name="message" id="message" tabindex="4" size="70" maxlength="250" />
   </label>
   </td>
</tr>
</table>

</td>
</tr>
</table>

<iframe name="log" src="cad-log-frame.php"
        width=<?=trim($_COOKIE['width'])-30; ?>
        height=<?=trim($_COOKIE['height'])-180; ?>
        marginheight="0" marginwidth="0" frameborder="0"> </iframe>
<?php
   mysql_close($link);
?>
</form>
</body>
</html>
