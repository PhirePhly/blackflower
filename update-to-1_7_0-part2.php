<?php
  require('db-open.php');
  require ('session.inc');
  if (!CheckAuthByLevel('admin_upgrades', $_SESSION['access_level'])) {
    print "ERROR - Administrator access required for this feature.";
    exit;
  }

  print "<b>Update CAD paging integration to 3.0</b>\n";
  print "<pre>";
  print "You must run the following commands:";
  print "GRANT SELECT ON paging.people TO 'dbuser'@'localhost' IDENTIFIED BY '...';\n";
  print "GRANT SELECT ON paging.pager_assignments TO 'dbuser'@'localhost' IDENTIFIED BY '...';\n";
  print "FLUSH PRIVILEGES;\n";
  print "</pre>";
  print "<hr>\n";

  if(isset($_GET["update_uip"])) {
    $uipquery = MysqlQuery(" SELECT row_id,unit,to_pager_id,person_id FROM unit_incident_paging ui JOIN paging.pager_assignments pa ON pa.pager_id=ui.to_pager_id; ");
    while ($uiprow = mysql_fetch_object($uipquery)) {
      print "Converting unit ".$uiprow->unit." from pager $uiprow->to_pager_id to person $uiprow->person_id ...<br>";
      MysqlQuery("UPDATE $db.unit_incident_paging SET to_person_id=$uiprow->person_id WHERE row_id=$uiprow->row_id");
    }
    print "Completed converting unit_incident_paging.<p>";
  }
  
  $pagers = MysqlQuery("SELECT COUNT(*) as cnt FROM $db.unit_incident_paging");
  while ($pager = mysql_fetch_object($pagers)) {
    print "<p>Pager integrations (total): ".$pager->cnt."<br>";
  }
  $people = MysqlQuery("SELECT COUNT(*) as cnt FROM $db.unit_incident_paging WHERE to_person_id != 0");
  while ($person = mysql_fetch_object($people)) {
    print "<p>Pager integrations (updated): ".$person->cnt."<br>";
  }
  print "<hr>";
  print "Click here to <a href=\"update-to-1_7_0-part2.php?update_uip=1\"><font color=blue>Update Unit Incident Paging</font></a><br>";

?>

