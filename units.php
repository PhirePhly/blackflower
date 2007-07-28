<?php
  $subsys="units";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  header_html("Dispatch :: Unit Listing","  <script src=\"js/clock.js\" type=\"text/javascript\"></script>");
?>
<body vlink="blue" link="blue" alink="cyan"
      onload="displayClockStart()"
      onunload="displayClockStop()">
<? include('include-title.php'); ?>
<form name="myform" action="units.php" style="margin: 0px; margin-bottom: 2px;">
<table width="98%">
<tr>
  <td align="left" class="text"><b>Units</b></td>
  <td align="left" width="100%">
  <button type="submit" value="Add New Unit" title="Add New User - ALT-N" accesskey="n"
   onClick="return popup('edit-unit.php?new-unit','unit-new',500,700)" class="newbutton"
   >Add <u>N</u>ew Unit</button>
  </td>
  <td align="right"><input type="text" name="displayClock" size="8" /></td>
</tr>
</table>
</form>

<iframe name="units" src="unit-frame.php"
        width="<?=trim($_COOKIE['width']) - 30;?>"
        height="<?=trim($_COOKIE['height']) - 175;?>"
        marginheight="0" marginwidth="0" frameborder="0"></iframe>

</body>
</html>
