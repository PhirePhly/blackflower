<font face="tahoma,ariel,sans">
<?php
  if (!isset($_COOKIE['width']) OR !isset($_COOKIE['height'])) {
  ?>
  <script language='javascript'>
  <!--
    document.cookie="width="+ window.innerWidth;
    document.cookie="height="+ window.innerHeight;
    window.location.reload();
    -->
  </script>
  <?php
  } 
  else 
  {
  ?>
  <script language='javascript'>
  <!--
    if (window.innerWidth != <?php print htmlentities($_COOKIE['width'])?> || window.innerHeight != <?php print htmlentities($_COOKIE['height'])?>) {
      document.cookie="width="+ window.innerWidth;
      document.cookie="height="+ window.innerHeight;
      window.location.reload();
    }
    -->
  </script>
  <?php
  }

?>
<table cellspacing=0 cellpadding=0 width=100% bgcolor=lightgray >
<tr>
  <td colspan=6 bgcolor=lightgray>
  <?php
    #if (file_exists("Logos/title.png") )
      #print "<img src=\"Logos/title.png\" height=40 width=310>";
    #else
      print "<font size=\"+3\">Black Flower CAD</font>";
  ?>
  </td>
  <td colspan=3 align=right bgcolor=lightgray>
  <?php
    if (file_exists("Logos/title2.png") )
      print "<img src=\"Logos/title2.png\" height=40 width=250>";
  ?>
  </td>
  <td rowspan=2 valign=top width=72 bgcolor="lightgray">
  <?php
    if (file_exists("Logos/logo.png") ) {
      print "<img src=\"Images/hdr1.png\" height=3 width=85><br>";
      print "<img src=\"Logos/logo.png\" height=59 width=85><br>";
      print "<img src=\"Images/hdr1.png\" height=3 width=85>";
    }
  ?>
  </td>
          <td bgcolor=lightgray width=20><font color="#dddddd" size="-2">
          <?php print htmlentities($_COOKIE['width'])." x ".htmlentities($_COOKIE['height'])?></td>
</tr>

<tr valign=bottom>
   
  <td align=left valign=bottom width=10 bgcolor="lightgray"><img src="Images/hdr1.png" height=3 width=10></td>
  <td align=left valign=bottom width=1 bgcolor="lightgray"><img src="Images/hdr2.png" height=3 width=1></td>
  <td align=left  valign=bottom border=0 bgcolor=lightgray>
<?php
  if ($subsys == "incidents")    print "<img src=\"Images/menu-inc-sel.gif\">";
  else                           print "<a href=\"incidents.php\"><img src=\"Images/menu-inc.gif\" border=0></a>";
  ?><img src="Images/hdr2.png" height=3 width=1><?php
  if ($subsys == "units")        print "<img src=\"Images/menu-unit-sel.gif\">";
  else                           print "<a href=\"cad-units.php\"><img src=\"Images/menu-unit.gif\" border=0></a>";
  ?><img src="Images/hdr2.png" height=3 width=1><?php
  if ($subsys == "cad")          print "<img src=\"Images/menu-log-sel.gif\">";
  else                           print "<a href=\"cad.php\"><img src=\"Images/menu-log.gif\" border=0></a>";

  ?>
  <td align=left valign=bottom width=1 bgcolor="lightgray"><img src="Images/hdr2.png" height=3 width=1></td>
  <td align=left valign=bottom width=100% bgcolor="lightgray"><img src="Images/hdr1.png" height=3 width=100%></td>
  <td align=left valign=bottom width=1 bgcolor="lightgray"><img src="Images/hdr2.png" height=3 width=1></td>
  <td align=right>
  <?php

  if ($subsys == "config")       print "<img src=\"Images/menu-set-sel.gif\">";
  else                           print "<a href=\"config.php\"><img src=\"Images/menu-set.gif\" border=0></a>";
  ?><img src="Images/hdr2.png" height=3 width=1><?php
  if ($subsys == "reports")      print "<img src=\"Images/menu-rep-sel.gif\">";
  else                           print "<a href=\"reports.php\"><img src=\"Images/menu-rep.gif\" border=0></a>";
  ?><img src="Images/hdr2.png" height=3 width=1><?php
  if ($subsys == "help")         print "<img src=\"Images/menu-help-sel.gif\">";
  else                           print "<a href=\"help.php\"><img src=\"Images/menu-help.gif\" border=0></a>";
?>
  </td>
  <td align=left valign=bottom width=1 bgcolor="lightgray"><img src="Images/hdr2.png" height=3 width=1></td>
  <td align=left valign=bottom width=20 bgcolor="lightgray"><img src="Images/hdr1.png" height=3 width=20></td>
  <td align=left valign=bottom width=20 bgcolor="lightgray"><img src="Images/hdr1.png" height=3 width=20></td>
</tr>
</table>
