<!-- START include-title.php -->

 <script type="text/javascript">
  <!--
<?php
  if (!isset($_COOKIE['width']) OR !isset($_COOKIE['height'])) { ?>
    resizeMe();
<?} else { ?>
  if (window.innerWidth != <?=trim($_COOKIE['width'])?> || window.innerHeight != <?=trim($_COOKIE['height'])?>) {
    resizeMe();
  }
<?} ?>
  -->
  </script>


<?php
  include('VERSION');
  require_once('session.inc');

  $new_bull = 0;
  $upd_bull = 0;
  if ($BULLETIN_TITLE_SCAN == 1) {
    # This is inefficient - but a LEFT OUTER JOIN is ineffectual unless we pre-populate on
    # bulletin add/delete and on user add.
    $LastRead = array();
    $bulletin_views = MysqlQuery('SELECT bulletin_id, last_read FROM bulletin_views WHERE user_id='. (int)$_SESSION['id'] );
    while ($view = mysql_fetch_object($bulletin_views)) {
      $LastRead[$view->bulletin_id] = $view->last_read;
    }
    $bulletins = MysqlQuery('SELECT bulletin_id, updated FROM bulletins WHERE access_level <= '.(int)$_SESSION['access_level'] .' AND closed=0');
    while ($bull = mysql_fetch_object($bulletins)) {
      if (!isset($LastRead[$bull->bulletin_id])) {
        $new_bull++;
      }
      elseif ($LastRead[$bull->bulletin_id] < $bull->updated) {
        $upd_bull++;
      }
    }
  }

?>
<!-- Display Header - custom to client -->
<table cellspacing="0" cellpadding="0" width="100%" >
<tr>
<td rowspan=5 valign="top" style="padding-right: 0.02cm"><img src="<?=$HEADER_LOGO?>" height="72" width="72" alt="" /></td>
<td class=headertext><?=$HEADER_TITLE?></td>

<td align=right class=headerinfo title="Release Date <?=$OC_RELEASE_DATE?>">
  Black Flower CAD v<?=$OC_VERSION?> <?=$OC_LEVEL?> 
<br>
<?if ($_SESSION['username'] != "") {
    print "&nbsp;Logged in as <b>".$_SESSION['username']."</b>";
    if ($_SESSION['name'] != "") {
      print " (".$_SESSION['name'].")";
    }
  }
  else {
    print "<span style='color: red; text-decoration: blink;'>Not logged in</span>\n";
  }
  print "<br>\n";

  if ($new_bull && $upd_bull) {
    print "<font color=red><b>You have $new_bull NEW and $upd_bull Updated bulletins to read</b></font>";
  }
  elseif ($new_bull) {
    print "<font color=red><b>You have $new_bull NEW bulletin" . ($new_bull>1?"s":"") . " to read</b></font>";
  }
  elseif ($upd_bull) {
    print "<font color=red><b>You have $upd_bull Updated bulletin"  . ($upd_bull>1?"s":"") ." to read</b></font>";
  }
?>
  </td>
</tr>

<tr> <td colspan="3" bgcolor="menubg"></td> </tr>
<tr> <td colspan=3 class="menubg" width=100%> </td> </tr>

<tr>
  <td colspan=3 class=menubg> 
<?php
  $CSS = array ( 
    "main" => "headermenu",
    "incidents" => "headermenu",
    "units" => "headermenu",
    "cad" => "headermenu",
    "bulletins" => "headermenu",
    "config" => "headermenuright",
    "reports" => "headermenuright",
    "admin" => "headermenuright",
    "help" => "headermenuright",
    "logout" => "headermenuright");
  $CSS[$subsys] .= "sel";

  print "<span class=\"". $CSS["incidents"] . "\"><a class=menua href=\"incidents.php\">Incidents</a></span>\n";
  print "<span class=\"". $CSS["units"]     . "\"><a class=menua href=\"units.php\">Units</a></span>\n";
  print "<span class=\"". $CSS["cad"]       . "\"><a class=menua href=\"cad.php\">Log Viewer</a></span>\n";
  print "<span class=\"". $CSS["bulletins"] . "\"><a class=menua href=\"bulletins.php\">Bulletins</a></span>\n";

  print "<span class=\"". $CSS["logout"]      . "\"><a class=menua href=\"main.php?logout\">Log Out</a></span>\n";
  print "<span class=\"". $CSS["help"]      . "\"><a class=menua href=\"help.php\">Help</a></span>\n";
  if ($_SESSION["access_level"] >= 9) {
    print "<span class=\"". $CSS["admin"]   . "\"><a class=menua href=\"admin.php\">System Admin</a></span>\n";
  }
  print "<span class=\"". $CSS["config"]    . "\"><a class=menua href=\"config.php\">Preferences</a></span>\n";
  if ($_SESSION["access_level"] >= 5) {
    print "<span class=\"". $CSS["reports"] . "\"><a class=menua href=\"reports.php\">Reports</a></span>\n";
  }
  print "<span class=headermenuright>&nbsp;</span>\n";

?>
</td></tr>
<tr valign="bottom"> <td colspan=3 class="menubg" width=100%> </td> </tr>
<tr valign="bottom"> <td colspan=3 style="background-color: white; padding: 0.0001cm" width=100%> </td> </tr>
<tr valign="bottom"> <td colspan=3 style="background-color: #666666; padding: 0.01cm" width=100%> </td> </tr>
<tr valign="bottom"> <td colspan=3 style="background-color: #999999; padding: 0.01cm" width=100%> </td> </tr>
</table>


<noscript>
<p />
<div align="left">
<table cellspacing="0" cellpadding="0" border="0" width="538" align="center">
  <tr><td colspan="6"><img src="Images/1.gif" width="1" height="15" alt="" /></td></tr>
  <tr><td colspan="6" bgcolor="#cc0000"><img src="Images/1.gif" alt="" /></td></tr>
  <tr>
    <td rowspan="4" bgcolor="#cc0000"><img src="Images/1.gif" alt="" /></td>
    <td rowspan="4" bgcolor="#ffffcc"><img src="Images/1.gif" width="5" height="1" alt="" /></td>
    <td bgcolor="#ffffcc" width="100%" colspan="2">&nbsp;</td>
    <td rowspan="4" bgcolor="#ffffcc"><img src='Images/1.gif' width="5" height="1" alt="" /></td>
    <td rowspan="4" bgcolor="#cc0000"><img src="Images/1.gif" alt="" /></td>
  </tr>
  <tr><td bgcolor="#ffffcc"><img src="Images/error.gif" width="23" height="23" border="0" alt="Error" /></td><td bgcolor="#ffffcc"><font face="ariel, helvetica" size="2" color="#cc0000"><b>JavaScript is not enabled on your browser</b></font></td></tr>
  <tr><td bgcolor="#ffffcc">&nbsp;</td>
    <td bgcolor="#ffffcc"><font face="ariel, helvetica" size="2">
    <b>This application requires JavaScript for full functionality.  Please configure your browser to enable JavaScript, and Reload this page.<br /></b>
    </font></td></tr>
  <tr><td bgcolor="#ffffcc" colspan="2">&nbsp;</td></tr>
  <tr><td colspan="6" bgcolor="#cc0000"><img src="Images/1.gif" alt="" /></td></tr>
  <tr><td colspan="6"><img src="Images/1.gif" width="1" height="15" alt=""></td></tr>
</table>
</div>
</noscript>
<!-- END include-title.php -->

