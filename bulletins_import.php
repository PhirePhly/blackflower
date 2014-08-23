<?php
  $subsys="bulletins";

  require_once('db-open.php');
  require_once('session.inc');
  require_once('functions.php');
  require_once('local-dls.php');
  SessionErrorIfReadonly();

// Non-UI Methods
  

  if (isset($_POST["clear_bulletin"])) {
    header('Location: bulletins.php');
    exit;
  }

  elseif (isset($_POST["import_bulletins"])) {
    if (!CheckAuthByLevel('import_bulletins', $_SESSION["access_level"])) {
      print "Access level (". $_SESSION["access_level"] . ") too low to import bulletins.\n";
      exit;
    }
    $srcdbname = MysqlClean($_POST, "import_dbsource", 80);
    $srctablename = MysqlClean($_POST, "import_tablesource", 80);
    $import_ids = $_POST["import_id"];

    for ($i=0; $i<count($import_ids); $i++) {
      $import_ids[$i] = (int)$import_ids[$i];
      syslog(LOG_INFO, $_SESSION["username"] . " importing bulletin $import_ids[$i] from $srcdbname.$srctablename");
    }

    $sourcebulls = MysqlQuery("SELECT b.*,u.username FROM $srcdbname.$srctablename b LEFT OUTER JOIN users u ON b.updated_by=u.id WHERE b.bulletin_id IN (".join(",",$import_ids).")");
    while ($bulletin = mysql_fetch_object($sourcebulls)) {
      //print "fetched bulletin id $bulletin->bulletin_id subject $bulletin->bulletin_subject<br>\n";
      syslog(LOG_INFO, $_SESSION["username"] . " fetched bulletin id ". $bulletin->bulletin_id );
      if ($bulletin->username != "") 
        $updated_by = $bulletin->updated_by;
      else
        $updated_by = (int)$_SESSION["id"];
      $cleanme = array();
      $cleanme['bulletin_subject'] = $bulletin->bulletin_subject;
      $cleanme['bulletin_text']    = $bulletin->bulletin_text;
      $bulletin_subject = MysqlClean($cleanme, 'bulletin_subject', 160);
      $bulletin_text    = MysqlClean($cleanme, 'bulletin_text', 4096);
      MysqlQuery("INSERT INTO bulletins (bulletin_subject, bulletin_text, updated, updated_by, access_level, closed) VALUES ('$bulletin_subject', '$bulletin_text', '$bulletin->updated', $updated_by, $bulletin->access_level, $bulletin->closed)");
    }
    header("Location: bulletins.php");
    exit;
  }

  header_html("Dispatch :: Bulletins");
?>
<body vlink="blue" link="blue" alink="cyan" onresize="resizeMe()">
<?php include('include-title.php'); ?><p />



<table cellspacing=0 cellpadding=0>
<tr valign=top>
<td style="width:300px">
<!-- ; border-right: 1px dotted grey"> -->
<span class="text"><b>Current Bulletins</b></span><p>
<?php

  $bulletin_query = MysqlQuery("SELECT b.*, u.username, u.id FROM bulletins b LEFT OUTER JOIN users u ON b.updated_by=u.id ");
  if (mysql_num_rows($bulletin_query) == 0) {
    print "<li><b>No bulletins entered</b>\n";
  }
  else {
    while ($bulletin = mysql_fetch_object($bulletin_query)) {
      $bullstatus="";
      $style = "font-family: tahoma, sans; font-size: 10pt; font-weight: bold; ";

      if ($bulletin->closed) {
        $bullstatus = " <font color=gray size=-1>CLOSED</font>" ;
        $style .= "color: gray; ";
      }
      else
        $style .= "color: darkblue; ";

      print " <div style=\"margin-right: 5px; $style\">" . $bulletin->bulletin_id. ". $bulletin->bulletin_subject  $bullstatus ";
      print "<br><span style=\"color: gray; font:11px tahoma,sans; background-color: white; \">&nbsp; &nbsp; Updated: " . dls_utime($bulletin->updated) . " by " . $bulletin->username . "</span>\n";
      print "</div>";
    }
  }

  print "</td>\n";
  // right hand pane:
  //
  print "<td style=\"padding: 5px; \">&nbsp;</td>\n";
  print "<td \">\n";
 
  /////////////////////////////////////////////////////////////////////////////////
  
  if (!isset($_POST["dbsource"])) {
    if (!CheckAuthByLevel('import_bulletins', $_SESSION["access_level"])) {
      print "Access level (". $_SESSION["access_level"] . ") too low to import bulletins.\n";
      exit;
    }
    print "<form action=bulletins_import.php method=post>\n";
    print "<div class=\"text\"><b>Select Database Source</b></div>\n";

    print "<div style=\"font: 10pt monospace; border: 1px dotted gray; margin: 5px; padding-left: 10px; padding-right: 10px; background-color: #ffffcc\">";
    $dbnames = MysqlQuery("SELECT table_schema,table_name FROM information_schema.tables WHERE table_name LIKE 'bulletins%' AND table_schema != '$DB_NAME'");
    while ($db = mysql_fetch_object($dbnames)) {
      print "<div style=\"font: bold 10pt monospace;\"><input type=\"radio\" name=\"dbsource\" value=\"$db->table_schema.$db->table_name\"> $db->table_schema.$db->table_name </div>";
    }

    print "</div>\n";
    print "<p>\n";
    print "<input type=submit name=select_bulletins value=\"Select Bulletins to Import\"> \n";
    print "<input type=submit name=clear_bulletin value=\"Cancel - Don't Import\">\n";
    print "<p>\n";
    print "<div class=\"text\" style=\"color: grey\">A database must have the 'bulletins' table in order to be shown in this list.</div>\n";
    print "<div class=\"text\" style=\"color: grey\">If desired source database <b>dbname</b> is not shown, execute SQL such as:</div>\n";
    print "<div class=\"text\" style=\"color: grey\">&nbsp; <code> GRANT ALL ON &lt;<b>dbname</b>&gt;.* TO '$DB_USER'@'&lt;<b>clienthostname</b>&gt;';</code></div>\n";
    print "<div class=\"text\" style=\"color: grey\">Or maybe (be cautious of security implications):</div>\n";
    print "<div class=\"text\" style=\"color: grey\">&nbsp; <code> UPDATE mysql.user SET show_db_priv='Y' WHERE user='$DB_USER';</code></div>\n";
    print "</form>";
  }

  /////////////////////////////////////////////////////////////////////////////////
  elseif (isset($_POST["select_bulletins"])) {
    if (!CheckAuthByLevel('import_bulletins',$_SESSION["access_level"])) {
      print "Access level (". $_SESSION["access_level"] . ") too low to import bulletins.\n";
      exit;
    }
    print "<form action=bulletins_import.php method=post>\n";

    $dbname = MysqlClean($_POST, "dbsource", 80);
    $dbary = explode(".", $dbname);
    print "<!-- field 0 -- database name is ".$dbary[0]."-->";
    print "<!-- field 1 -- table name is ".$dbary[1]."-->";

    $dbverify = MysqlQuery("SELECT table_name,table_schema FROM information_schema.tables WHERE table_name = '$dbary[1]' AND table_schema = '$dbary[0]'");
    if (mysql_num_rows($dbverify) != 1) {
      print "Error - expected one row for dbname, got ". mysql_num_rows($dbverify);
      exit;
    }
    else {
      $verifyrow = mysql_fetch_object($dbverify);
      $dbname = $verifyrow->table_schema;
      $tablename = $verifyrow->table_name;
    }

    print "<div class=\"text\"><b>Select Bulletins To Import From <code>$dbname.$tablename</code></b></div>\n";
    print "<div class=\"text\">Any bulletins marked with asterisk were written by a nonexistant user, your username will be used instead.</div>\n";
    print "<div class=\"text\">Any bulletins listed in gray text are closed.</div>\n";
    print "<input type=hidden name=\"import_dbsource\" value=\"$dbname\">\n";
    print "<input type=hidden name=\"import_tablesource\" value=\"$tablename\">\n";

    print "<div style=\"font: 10pt monospace; border: 1px dotted gray; margin: 5px; padding-left: 10px; padding-right: 10px; background-color: #ffffcc\">";
    $bulletins = MysqlQuery("SELECT b.*, u.username FROM $dbname.$tablename b LEFT OUTER JOIN users u on b.updated_by=u.id"); 
    while ($bulletin = mysql_fetch_object($bulletins)) {
      if ($bulletin->closed) 
        $style = "color:grey; ";
      else 
        $style = "color:darkblue; font-weight: bold; ";

      print "<div style=\"font: 10pt tahoma,sans; border-bottom: 1px dotted #cccc99; \">".
            "  <span style=\"color: gray;  margin-right:50px;font: 8pt tahoma,sans\">". dls_utime($bulletin->updated) . "".
            "  <input title=\"".htmlentities($bulletin->bulletin_text)."\" type=\"checkbox\" name=\"import_id[]\" value=\"$bulletin->bulletin_id\">" .
            "  <span style=\"$style\" title=\"".htmlentities($bulletin->bulletin_text)."\"> $bulletin->bulletin_subject </span></span>";
      if ($bulletin->username=="") {
        print "<span style=\"float: right; width=50px\">* </span>";
      }
      else {
        print "<span style=\"float: right; width=50px\">$bulletin->username </span>";
      }
      print "</div>\n";
    }

    print "</div>\n";
    print "<p>\n";
    print "<input type=submit name=import_bulletins value=\"Import Selected Bulletins\"> \n";
    print "<input type=submit name=clear_bulletin value=\"Cancel - Don't Import\">\n";
    print "<p>\n";
  }

  print "<br></td> \n";  //end GUI block: right hand view/edit pane
  print "</tr></table>\n";

  /////////////////////////////////////////////////////////////////////////////////

  mysql_close($link);
?>
</body>
</html>
