<?php
  $subsys="incidents";
  
  require_once('session.inc');

?> 

<HTML>
<HEAD>
<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
<!--
function popup(url,name)
{
	var newwindow=window.open(url,name,'height=600,width=1000');
	if (window.focus) {newwindow.focus()}
	return false;
}

function resizeMe()
{
  document.cookie="width="+ window.innerWidth;
  document.cookie="height="+ window.innerHeight;
  window.location.reload();
  return false;
} 
// -->
</SCRIPT>
  <title>Dispatch :: Incidents</title>
  <?php include('include-clock.php') ?>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA=screen>
</HEAD>
<BODY vlink=blue link=blue alink=cyan onload="displayClockStart()" onunload="displayClockStop()" onresize="resizeMe()">
<?php include('include-title.php') ?>

<p>
<table width=98%>
<tr>
  <td align=left> <input type=submit value="Create New Incident" onClick="return popup('edit-incident.php?incident_id=new','incident-new')"> </td>
  <td></td>
  <td align=right> <form name="myform"><input type="text" name="displayClock" size="8"></form></td>
</tr>
</table>

<iframe name="incidents" src="incidents-frame.php" 
        width=<?php print htmlentities($_COOKIE['width']) - 30 ?> 
        height=<?php print htmlentities($_COOKIE['height']) - 175 ?> 
        marginheight=0 marginwidth=0 frameborder=0> </iframe> <?php include('include-footer.php') ?></BODY>
</HTML>
