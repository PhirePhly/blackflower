<?php

  $subsys="cad";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  $username = "(unknown)";
  if (isset($_SESSION['username']) && $_SESSION['username'] != '')
    $username = $_SESSION['username'];

  if (isset($_POST["channel_assign"])) {
    SessionErrorIfReadonly();
    $channel_to_toggle = (int) $_POST['channel_assign'];
    $incident_id = (int) $_POST['incident_id'];
    MysqlQuery ("LOCK TABLES channels WRITE, incident_notes WRITE");
    $chinfo = MysqlQuery ("SELECT channel_name,incident_id FROM channels WHERE channel_id=$channel_to_toggle"); 
    if (mysql_num_rows($chinfo)) { 
      $chrow = mysql_fetch_object($chinfo); // Trust in 1 row returned due to primary key integrity
      if ((int)$chrow->incident_id) {
        MysqlQuery ("UNLOCK TABLES");
        print "<html><body><SCRIPT LANGUAGE=\"JavaScript\">alert('That channel ($chrow->channel_name) was previously assigned to incident " . CallNumber($chrow->incident_id) . "'); window.location=\"incident-channels.php?incident_id=$incident_id\"; </SCRIPT></body></html>\n";
        exit;
      }
      MysqlQuery("INSERT INTO incident_notes (incident_id, ts, unit, message, creator) VALUES
                  ($incident_id, NOW(), '', 'Channel $chrow->channel_name assigned to incident.', '$username') ");
      MysqlQuery ("UPDATE channels SET incident_id=$incident_id WHERE channel_id=$channel_to_toggle");
    }
    MysqlQuery ("UNLOCK TABLES");
    header("Location: incident-channels.php?incident_id=$incident_id");
    exit;
  }

  elseif (isset($_POST["channel_unassign"])) {
    SessionErrorIfReadonly();
    $channel_to_toggle = (int) $_POST['channel_unassign'];
    $incident_id = (int) $_POST['incident_id'];
    MysqlQuery ("LOCK TABLES channels WRITE, incident_notes WRITE");
    $chinfo = MysqlQuery ("SELECT channel_name,incident_id FROM channels WHERE channel_id=$channel_to_toggle"); 
    if (mysql_num_rows($chinfo)) { 
      $chrow = mysql_fetch_object($chinfo); // Trust in 1 row returned due to primary key integrity
      if (!isset($chrow->incident_id) || !(int)$chrow->incident_id) {
        MysqlQuery ("UNLOCK TABLES");
        print "<html><body><SCRIPT LANGUAGE=\"JavaScript\">alert('That channel ($chrow->channel_name) was already unassigned, incident_id is empty [$chrow->incident_id].'); window.location=\"incident-channels.php?incident_id=$incident_id\"; </SCRIPT></body></html>\n";
        exit;
      }
      MysqlQuery("INSERT INTO incident_notes (incident_id, ts, unit, message, creator) VALUES
                  ($incident_id, NOW(), '', 'Channel $chrow->channel_name unassigned from incident.', '$username') ");
      MysqlQuery ("UPDATE channels SET incident_id=NULL WHERE channel_id=$channel_to_toggle");
    }
    MysqlQuery ("UNLOCK TABLES");
    header("Location: incident-channels.php?incident_id=$incident_id");
    exit;
  }

  elseif (isset($_GET["incident_id"])) {
    SessionErrorIfReadonly();
    $incident_id = (int) $_GET["incident_id"];
  }
  else
    die ("Invalid parameter set - No incident ID specified in URI.");


  $resultURI = $_SERVER["PHP_SELF"];
  if (isset($_GET["incident_id"]))
    $resultURI .= "?incident_id=" . MysqlClean($_GET,"incident_id",20);
  header_html("Dispatch :: Incident Viewer","",$resultURI);

?>
  <body vlink="blue" link="blue" alink="cyan">
  <form name="myform" action="incident-channels.php" method="post" style="width: 320px; margin: 0px; padding: 0px">
  <input type="hidden" name="incident_id" value="<?php print $incident_id?>">

<?php
  $channels = MysqlQuery("SELECT * FROM channels c WHERE available=1 ORDER BY precedence,channel_name");
  if (mysql_num_rows($channels)) {
    while ($channel = mysql_fetch_object($channels)) {
      $chclass='channel';
      $chtitle='This channel is available, click here to assign to this incident.';
      $chaction='assign';
      if ($channel->repeater) { $chclass .= ' b'; $chtitle.='  This is a repeated channel. '; }
      if ($channel->incident_id == $incident_id) { $chclass .= ' chasg'; $chtitle = "This channel is assigned to this incident; click here to release."; $chaction = 'unassign'; }
      elseif ($channel->incident_id) { $chclass .= ' chother'; $chtitle = "This channel is assigned to incident ". CallNumber($channel->incident_id) .".";}
      print "<button type=submit style=\"margin: 0px; padding: 0px;\" name=\"channel_$chaction\" value=\"$channel->channel_id\" title=\"$chtitle\"><span class=\"$chclass\" title=\"$chtitle\">$channel->channel_name</span></button>\n";
    }
    mysql_free_result($channels);
  }
  else {
    print "<span class=\"text\"><i> No channels configured. </i></span>";
  }

  print '</form></body>';
?>
