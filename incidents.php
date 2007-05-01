<?php
  $subsys="incidents";

  require_once('session.inc');
  require_once('functions.php');

  header_html("Dispatch :: Incidents","  <script src=\"js/clock.js\" type=\"text/javascript\"></script>");
?>
<body vlink="blue" link="blue" alink="cyan"
      onload="displayClockStart()"
      onunload="displayClockStop()"
      onresize="resizeMe()">
<? include('include-title.php'); ?>
<form name="myform" action="incidents.php" style="margin: 0px">
<table width="98%">
<tr><td></td> </tr>
<tr>
  <td align="left" class=text width=5%><b>Incidents </b></td>
  <td align="left">
    <button type="submit" value="Create New Incident" title="Create New Incident - ALT-N" accesskey="n"
     onClick="return popup('edit-incident.php?incident_id=new','incident-new',600,1000)">Create <U>N</U>ew Incident</button>
  </td>
  <td></td>
  <td align="right">
    <input type="text" name="displayClock" size="8" />
  </td>
</tr>
<tr><td></td> </tr>

<tr><td colspan=4>
<iframe border style="padding: 0px" name="incidents" src="incidents-frame.php"
        width="<?=trim($_COOKIE['width']) - 30; ?>"
        height="<?=trim($_COOKIE['height']) - 140; ?>"
        marginheight="0" marginwidth="0" frameborder="0"> </iframe>
</td> </tr></table>
</form>
</body>
</html>
