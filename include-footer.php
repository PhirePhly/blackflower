<?php
  include('VERSION');
  require_once('session.inc');

  print "<div style=\"text-align: right; vertical-align: text-bottom; width: 100%; font-family: tahoma, ariel, sans; font-size: 12; color: #666666\">\n";
  print "Black Flower CAD&nbsp;v$OC_VERSION&nbsp;$OC_LEVEL&nbsp;$OC_RELEASE_DATE\n";

  if ($_SESSION['username'] != "") {
    print "&nbsp;[Logged in as <b>".$_SESSION['username']."</b> ";
    if ($_SESSION['name'] != "") {
      print "(".$_SESSION['name'].")";
    }
    print " | <a href=\"index.php?logout\">Log out</a>]";
  }
  else {
    print "<font color=red><blink>Not logged in</blink></font>";
  }

  print "</div>";
?>
