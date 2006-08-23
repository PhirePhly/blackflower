<?php

/**
 *
 *
 * @version $Id$
 * @copyright 2006
 */

include('local-dls.php');
require_once('session.inc');
require_once('functions.php');

header_html("Dispatch :: Main Menu");
echo "<body vlink='blue' link='blue' alink='cyan'>\n";
$subsys='main';
include('include-title.php');
?>

<iframe name="menu" src="main-frame.php"
        width="<?=trim($_COOKIE['width']) - 60 ?>"
        height="<?=trim($_COOKIE['height']) - 125 ?>"
        marginheight="0" marginwidth="30" frameborder="0"> </iframe>
</body>
</html>

