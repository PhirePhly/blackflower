<?php
  $subsys="main";

  include('local-dls.php');
  require_once('session.inc');

  if (isset($_GET["frame"]) && $_GET["frame"] == "1") {
          print "<BASE target=_parent>\n";
          print "<p><div style=\"font-family: Tahoma\"><p>";
          print "<b>Introductory Menu</b><p>\n";
          print "<font size=-1>Please select from one of the following links, or select a tab above:</font><p>\n";
          print "<p></div><ul>\n";

    print "<div style=\"font-family: Tahoma; font-size: 18\"><a href=incidents.php>Incidents</a></div><p>\n";
    print "<div style=\"font-family: Tahoma; font-size: 18\"><a href=cad-units.php>Units</a></div><p>\n";
    print "<div style=\"font-family: Tahoma; font-size: 18\"><a href=cad.php>Log Messages</a></div><p>\n";
    print "<div style=\"font-family: Tahoma; font-size: 18\"><a href=config.php>Settings</a></div><p>\n";
    print "<div style=\"font-family: Tahoma; font-size: 18\"><a href=reports.php>Reports</a></div><p>\n";
    print "<div style=\"font-family: Tahoma; font-size: 18\"><a href=help.php>Help</a></div><p>\n";
    print "</ul>";
    exit;
  }
?>
<html>
<head>
  <TITLE>Dispatch :: Main Menu</TITLE>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA="screen, print">
</head>
<body vlink=blue link=blue alink=cyan>
<?php include('include-title.php') ?>
  
<iframe name="menu" src="index.php?frame=1" 
        width=<?php print htmlentities($_COOKIE['width']) - 30 ?> 
        height=<?php print htmlentities($_COOKIE['height']) - 125 ?> 
        marginheight=0 marginwidth=0 frameborder=0> </iframe> <?php include('include-footer.php') ?>

</body>

