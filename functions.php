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

function CheckAuthByLevel ($requested_privilege, $access_level) {

  global $ACCESS_LEVEL_ADMIN_GENERAL;
  global $ACCESS_LEVEL_ADMIN_UPGRADES;
  global $ACCESS_LEVEL_ADMIN_CLEARDB;
  global $ACCESS_LEVEL_EDIT_USERS;
  global $ACCESS_LEVEL_EDITCHANNELS;
  global $ACCESS_LEVEL_EDIT_CHANNELS;
  global $ACCESS_LEVEL_EDIT_STAGING_LOCATIONS;
  global $ACCESS_LEVEL_EDIT_REPORT_FILTERS;
  global $ACCESS_LEVEL_CREATE_BULLETINS;
  global $ACCESS_LEVEL_IMPORT_BULLETINS;
  global $ACCESS_LEVEL_REVIEW_INCIDENTS;
  global $ACCESS_LEVEL_ACCESS_REPORTS;
  global $ACCESS_LEVEL_CREATE_UNITS;
  global $ACCESS_LEVEL_DELETE_UNITS;
  global $ACCESS_LEVEL_CREATE_UNITPAGING;
  global $ACCESS_LEVEL_DELETE_UNITPAGING;

  if (!isset( $ACCESS_LEVEL_ADMIN_GENERAL)) { $ACCESS_LEVEL_ADMIN_GENERAL = 10; }
  if (!isset( $ACCESS_LEVEL_ADMIN_UPGRADES)) { $ACCESS_LEVEL_ADMIN_UPGRADES = 10; }
  if (!isset( $ACCESS_LEVEL_ADMIN_CLEARDB)) { $ACCESS_LEVEL_ADMIN_CLEARDB = 10; }
  if (!isset( $ACCESS_LEVEL_EDIT_USERS)) { $ACCESS_LEVEL_EDIT_USERS = 10; }
  if (!isset( $ACCESS_LEVEL_EDITCHANNELS)) { $ACCESS_LEVEL_EDITCHANNELS = 5; }
  if (!isset( $ACCESS_LEVEL_EDIT_CHANNELS)) { $ACCESS_LEVEL_EDIT_CHANNELS = $ACCESS_LEVEL_EDITCHANNELS; } // Provide backwards compatibility with old (1.9) config variable name
  if (!isset( $ACCESS_LEVEL_EDIT_STAGING_LOCATIONS)) { $ACCESS_LEVEL_EDIT_STAGING_LOCATIONS = 1; }
  if (!isset( $ACCESS_LEVEL_EDIT_REPORT_FILTERS)) { $ACCESS_LEVEL_EDIT_REPORT_FILTERS = 10; }
  if (!isset( $ACCESS_LEVEL_CREATE_BULLETINS)) { $ACCESS_LEVEL_CREATE_BULLETINS = 5; }
  if (!isset( $ACCESS_LEVEL_IMPORT_BULLETINS)) { $ACCESS_LEVEL_IMPORT_BULLETINS = 5; }
  if (!isset( $ACCESS_LEVEL_REVIEW_INCIDENTS)) { $ACCESS_LEVEL_REVIEW_INCIDENTS = 5; }
  if (!isset( $ACCESS_LEVEL_ACCESS_REPORTS)) { $ACCESS_LEVEL_ACCESS_REPORTS = 5; }
  if (!isset( $ACCESS_LEVEL_CREATE_UNITS)) { $ACCESS_LEVEL_CREATE_UNITS = 5; }
  if (!isset( $ACCESS_LEVEL_DELETE_UNITS)) { $ACCESS_LEVEL_DELETE_UNITS = 5; }
  if (!isset( $ACCESS_LEVEL_CREATE_UNITPAGING)) { $ACCESS_LEVEL_CREATE_UNITPAGING = 5; }
  if (!isset( $ACCESS_LEVEL_DELETE_UNITPAGING)) { $ACCESS_LEVEL_DELETE_UNITPAGING = 5; }

  $LEVELS = array();
  $LEVELS["admin_general"] = (int) $ACCESS_LEVEL_ADMIN_GENERAL;
  $LEVELS["admin_upgrades"] = (int) $ACCESS_LEVEL_ADMIN_UPGRADES;
  $LEVELS["admin_cleardb"] = (int) $ACCESS_LEVEL_ADMIN_CLEARDB;
  $LEVELS["edit_users"] = (int) $ACCESS_LEVEL_EDIT_USERS;
  $LEVELS["edit_channels"] = (int) $ACCESS_LEVEL_EDIT_CHANNELS;
  $LEVELS["edit_staging_locations"] = (int) $ACCESS_LEVEL_EDIT_STAGING_LOCATIONS;
  $LEVELS["edit_report_filters"] = (int) $ACCESS_LEVEL_EDIT_REPORT_FILTERS;
  $LEVELS["create_bulletins"] = (int) $ACCESS_LEVEL_CREATE_BULLETINS;
  $LEVELS["import_bulletins"] = (int) $ACCESS_LEVEL_IMPORT_BULLETINS;
  $LEVELS["review_incidents"] = (int) $ACCESS_LEVEL_REVIEW_INCIDENTS;
  $LEVELS["reports"] = (int) $ACCESS_LEVEL_ACCESS_REPORTS;
  $LEVELS["create_units"] = (int) $ACCESS_LEVEL_CREATE_UNITS;
  $LEVELS["delete_units"] = (int) $ACCESS_LEVEL_DELETE_UNITS;
  $LEVELS["create_unitpaging"] = (int) $ACCESS_LEVEL_CREATE_UNITPAGING;
  $LEVELS["delete_unitpaging"] = (int) $ACCESS_LEVEL_DELETE_UNITPAGING;

  // TODO: load & overwrite from database any that exist there.

  // Fail safe: make sure privilege is set to a valid level.
  if (! array_key_exists($requested_privilege, $LEVELS)) {
    // TODO: log an error
    return false;
  }
  elseif ((int)$LEVELS[$requested_privilege] == 0) {  // ????
    return false;
  }
  else {
    return ((int)$access_level >= (int)$LEVELS[$requested_privilege]);
  }

}


?>
