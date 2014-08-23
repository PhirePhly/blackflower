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
<?php include('include-title.php'); ?>
<form name="myform" action="units.php" style="margin: 0px; margin-bottom: 2px;">
<table width="98%">
<tr>
  <td align="left" class="text"><b>Units</b></td>
<?php 
  if (CheckAuthByLevel('create_units', $_SESSION['access_level'])) {
?>
  <td align="left" width="100%">
  <button type="submit" value="Add New Unit" title="Add New Unit - ALT-N" accesskey="n"
   onClick="return popup('edit-unit.php?new-unit','unit-new',500,700)" class="newbutton"
   >Add <u>N</u>ew Unit</button>
  </td>
<?php
  }
?>
  <td align="right"><input type="text" name="displayClock" size="8" /></td>
</tr>
</table>
</form>

<iframe name="units" src="unit-frame.php"
        width="<?php print trim($_COOKIE['width']) - 30;?>"
        height="<?php print trim($_COOKIE['height']) - 175;?>"
        marginheight="0" marginwidth="0" frameborder="0"></iframe>

<?php 
  if (CheckAuthByLevel('create_units', $_SESSION['access_level'])) {
?>
<button type="submit" value="Add New Unit" title="Add New User - ALT-N" accesskey="n"
   onClick="return popup('edit-unit.php?new-unit','unit-new',500,700)" class="newbutton"
   >Add <u>N</u>ew Unit</button>
<?php
  }
?>
</body>
</html>
