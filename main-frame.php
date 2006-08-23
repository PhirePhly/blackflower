<?php

/**
 *
 *
 * @version $Id$
 * @copyright 2006
 */

$subsys="main";

include('local-dls.php');
require_once('session.inc');
require_once('functions.php');

header_html("Dispatch :: Main Menu");
?>

<body vlink='blue' link='blue' alink='cyan'>

<BASE target='_parent'>
<p><div style='font-family: Tahoma'><p>
<b>Introductory Menu</b><p>
<font size=-1>Please select from one of the following links, or select a tab above:</font><p>
<p></div><ul>

<div style='font-family: Tahoma; font-size: 18'><a href='incidents.php'>Incidents</a></div><p>
<div style='font-family: Tahoma; font-size: 18'><a href='units.php'>Units</a></div><p>
<div style='font-family: Tahoma; font-size: 18'><a href='cad.php'>Log Messages</a></div><p>
<div style='font-family: Tahoma; font-size: 18'><a href='config.php'>Settings</a></div><p>
<div style='font-family: Tahoma; font-size: 18'><a href='reports.php'>Reports</a></div><p>
<div style='font-family: Tahoma; font-size: 18'><a href='help.php'>Help</a></div><p>
</ul>
</body>
</html>
