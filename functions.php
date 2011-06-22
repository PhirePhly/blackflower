<?php
  if (!@include('cad.conf')) {
    print "Critical error: CAD configuration file is missing or unreadable.  Contact your CAD system administrator.";
    exit;
  }

// Check that this file is not loaded directly.
if (basename(__FILE__)==basename($_SERVER["PHP_SELF"])) exit();

function header_html($title,$extras="",$refreshURI="") {
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta http-equiv="content-language" content="en">
  <meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
<?php if ($refreshURI != "") {
    echo "  <meta http-equiv=\"refresh\" content=\"15; URL=".$refreshURI."\">\n";
  }
?>
  <title><?php print $title ?></title>
  <link rel="stylesheet"
        href="style.css"
        type="text/css"
        media="screen,print">
  <link rel="shortcut icon"
        href="/cad/favicon.ico"
        type="image/x-icon">
  <script src="js/utils.js" type="text/javascript"></script>
<?php if ($extras != "") {
    echo $extras."\n";
  }
  echo "</head>\n";
}

function CallNumber($incident_id) {
  global $CALLNUM_MODE;
  global $CALLNUM_PREFIX_FORMAT;
  global $CALLNUM_ID_FORMAT;
  global $CALLNUM_BASEINDEX;
  if (isset($CALLNUM_MODE)) {
    if ($CALLNUM_MODE == 'dateprefix') {
      return date($CALLNUM_PREFIX_FORMAT) . sprintf($CALLNUM_ID_FORMAT, $incident_id);
    }
    elseif ($CALLNUM_MODE == 'baseindex') {
      return (int)$CALLNUM_BASEINDEX + (int)$incident_id;
    }
    else {
      # TODO: log an error here -- but syslogging isn't opened yet.  
      return $incident_id;
    }
  }
  else {
    return $incident_id;
  }
}


?>
