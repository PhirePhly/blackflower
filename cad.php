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

<!-- Begin Add Message Form -->
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
   if ($USE_MESSAGE_TYPE) {
     if (!isset($_COOKIE['cad_show_message_type']) || $_COOKIE['cad_show_message_type'] == 'yes') {
       print '<td class="text" nowrap>Message type:&nbsp;</td>';
     }
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
    if ($USE_MESSAGE_TYPE) {
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

</form>
<!-- End Add Message Form -->

<!-- Begin Filter Form -->
<form name="filter" action="cad-log-frame.php" method="post" style="margin: 0px;" target="log">

<span class="text"><b style="font-size: 10pt;">Log Viewer :: Viewing messages</b>
&nbsp; Use selections below to filter for specific log messages.
</span>

<!-- Begin Filter Form Outer Table -->
<table width="100%" style="margin-bottom: 8px;">
<tr>
<td bgcolor="#aaaaaa">

  <!-- Begin Filter Form Inner Table -->
  <table width="100%" cellspacing="1" cellpadding="2">
  <tr class="message">

  <td class="text">Date:&nbsp;</td>
  <td class="text">
  <select name="date" id="date" tabindex="101">
  <option value=""></option>
<?php
  $datesquery = "SELECT DISTINCT CAST(ts AS DATE) AS tsdate FROM messages ORDER BY ts DESC";
  $datesresult = MysqlQuery($datesquery);
  $dates = array();
  while ($line = mysql_fetch_array($datesresult, MYSQL_ASSOC)) {
    array_push($dates, $line["tsdate"]);
  }
  foreach ($dates as $date) {
    echo "<option value=\"$date\">$date</option>\n";
  }
  mysql_free_result($datesresult);
?>
  </select>
  </td>

  <td class="text">Hour:&nbsp;</td>
  <td class="text">
  <select name="hour" id="hour" tabindex="102">
  <option value=""></option>
<?php
  for ($i = 0; $i < 24; $i++) {
    echo "<option value=\"$i\">";
    if ($i < 10) echo "0";
    print "$i:00</option>\n";
  }
?>
  </select>
  </td>

  <td class="text">Unit:&nbsp;</td>
  <td class="text">
  <select name="funit" id="funit" tabindex="103">
  <option value=""></option>
<?php
  $unitquery = "SELECT unit FROM units";
  $unitresult = MysqlQuery($unitquery);
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
  </td>

  <td class="text" nowrap>Messages Per Page:&nbsp;</td>
  <td class="text">
  <select name="mpp" id="mpp" tabindex="104">
<?php
  $mpp = array(10, 25, 100);

  // This should probably be abstracted to a configurable setting at some point...
  $mppdefault = 10;

  $filtermppindex = 0;
  $filtermppcount = 0;

  echo "<option value=\"0\">All</option>\n";
  $filtermppcount++;

  foreach ($mpp as $pp) {
    echo "<option value=\"$pp\"";
    if (isset($mppdefault) && $mppdefault == $pp) {
      echo " SELECTED";
    }
    echo ">$pp";
    if (isset($mppdefault) && $mppdefault == $pp) {
      echo " (Default)";
      $filtermppindex = $filtermppcount;
    }
    echo "</option>\n";
    $filtermppcount++;
  }
?>
  </select>
<?php
  echo "<SCRIPT LANGUAGE=\"JavaScript\">var filtermppindex = $filtermppindex;</SCRIPT>\n";
?>

  </td>

  <td class="text" nowrap align="right" width="100%">
  <button type="submit" name="apply_filters" id="apply_filters" value="apply_filters">Filter</button>
  <button type="submit" name="remove_filters" id="remove_filter" value="remove_filters"
   onClick="document.getElementById('date').options[0].selected=true;
            document.getElementById('hour').options[0].selected=true;
            document.getElementById('funit').options[0].selected=true;
            document.getElementById('mpp').options[filtermppindex].selected=true;"
   >Reset</button>
  </td>

  </tr>
  </table>
  <!-- End Filter Form Inner Table -->

</td>
</tr>
</table>
<!-- End Filter Form Outer Table -->

</form>
<!-- End Filter Form -->

<iframe name="log" src="cad-log-frame.php"
        width=<?=trim($_COOKIE['width'])-30; ?>
        height=<?=trim($_COOKIE['height'])-250; ?>
        marginheight="0" marginwidth="0" frameborder="0"> </iframe>
<?php
   mysql_close($link);
?>
</body>
</html>
