<?php

// Check that this file is not loaded directly.
if (basename(__FILE__)==basename($_SERVER["PHP_SELF"])) exit();

function header_html($title,$extras="",$refreshURI="") {
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta http-equiv="content-language" content="en">
  <meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
<?if ($refreshURI != "") {
    echo "  <meta http-equiv=\"refresh\" content=\"15; URL=".$refreshURI."\"\n";
  }
?>
  <title><?=$title?></title>
  <link rel="stylesheet"
        href="style.css"
        type="text/css"
        media="screen,print">
  <link rel="shortcut icon"
        href="/cad/favicon.ico"
        type="image/x-icon">
  <script src="js/utils.js" type="text/javascript"></script>
<?if ($extras != "") {
    echo $extras."\n";
  }
  echo "</head>\n";
}



?>