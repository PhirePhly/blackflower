<?php
  $subsys="bulletins";

  require_once('db-open.php');
  require_once('session.inc');
  require_once('functions.php');
  require_once('local-dls.php');

// Non-UI Methods
  

  if (isset($_POST["clear_bulletin"])) {
    header('Location: bulletins.php');
    exit;
  }
  elseif (isset($_POST["save_bulletin"])) {
    $insert_not_update=0;
    if ($_SESSION["access_level"] < 5) {
      print "Access level (". $_SESSION["access_level"] . ") too low to edit/create bulletin.\n";
      exit;
    }
    $bulletin_id = MysqlClean($_POST, "bulletin_id", 160);
    if ($bulletin_id == "new") {
      $insert_not_update = 1;
    }
    else {
      $bulletin_id = (int)$bulletin_id;
    }

    $bulletin_subject = MysqlClean($_POST, "bulletin_subject", 160);
    $bulletin_text = MysqlClean($_POST, "bulletin_text", 65536);
    $access_level = (int)MysqlClean($_POST, "access_level", 10);
    $closed = 0;
    if (isset($_POST["closed"])) {
      $closed = 1;
    }
    $orig_closed = MysqlClean($_POST, "orig_closed", 10);

    $whoami = (int)$_SESSION["id"];
    if ($insert_not_update) {
      MysqlQuery("INSERT INTO bulletins (bulletin_subject, bulletin_text, updated, updated_by, access_level, closed) VALUES ('$bulletin_subject', '$bulletin_text', NOW(), $whoami, $access_level, $closed)");
      if (mysql_affected_rows() == 1) {
        $bulletin_id = mysql_insert_id();
        MysqlQuery("INSERT INTO bulletin_history (bulletin_id, action, updated, updated_by) VALUES ($bulletin_id, 'Created', NOW(), $whoami)");
        # TODO: error check?
      }
      else {
        # TODO: error report
      }
    }
    else {
      $action = 'Edited';
      if ($closed == 1 && $orig_closed == "open") {
        $action = "Closed";
      }
      elseif ($closed == 0 && $orig_closed == "closed") {
        $action = "Reopened";
      }
      MysqlQuery("UPDATE bulletins SET bulletin_subject='$bulletin_subject', bulletin_text='$bulletin_text', access_level=$access_level, closed=$closed, updated=NOW(), updated_by=$whoami WHERE bulletin_id=$bulletin_id");
      if (mysql_affected_rows() == 1) {
        MysqlQuery("INSERT INTO bulletin_history (bulletin_id, action, updated, updated_by) VALUES ($bulletin_id, '$action', NOW(), $whoami)");
      }
      else {
        # TODO: Error report
      }
    }
    header("Location: bulletins.php?bulletin_id=$bulletin_id");
  }

// Below here is user interface

  if (isset($_GET["meta"])) {
    if ($_GET["meta"] == "1") 
      $_SESSION["bulletins_show_metadata"] = 1;
    else
      $_SESSION["bulletins_show_metadata"] = 0;
    
    header('Location: bulletins.php');
    exit;
  }
  if (isset($_GET["closed"])) {
    if ($_GET["closed"] == "1")
      $_SESSION["bulletins_show_closed"] = 1;
    else
      $_SESSION["bulletins_show_closed"] = 0;
    header('Location: bulletins.php');
    exit;
  }

  header_html("Dispatch :: Bulletins");
?>
<body vlink="blue" link="blue" alink="cyan" onresize="resizeMe()">
<?php include('include-title.php'); ?><p />



<table cellspacing=0 cellpadding=0>
<tr valign=top>
<td style="width:500px">
<span class=text><b>Available Bulletins</b></span><p>
<!-- ; border-right: 1px dotted grey"> -->
<?php
  if (isset($_GET["bulletin_id"])) {
    $bulletin_id = (int) $_GET["bulletin_id"];
    MysqlQuery("UPDATE bulletin_views SET last_read=NOW() WHERE bulletin_id=$bulletin_id AND user_id=".$_SESSION["id"]);
    if (mysql_affected_rows() == 0) {
      MysqlQuery("INSERT INTO bulletin_views (bulletin_id, user_id, last_read) VALUES ($bulletin_id, ". $_SESSION["id"] . ", NOW())");
      if (mysql_affected_rows() == 0) {
        syslog(LOG_WARNING, "Could not update or insert bulletin_views for id [$bulletin_id], user [". $_SESSION["id"]."]");
      }
    }
    elseif (mysql_affected_rows() > 1) {
      syslog(LOG_WARNING, "Updated too many (" . mysql_affected_rows() . ") bulletin_views for id [$bulletin_id], user [". $_SESSION["id"]."]");
    }
  }

  $closed = 0;
  if (isset($_SESSION["bulletins_show_closed"]) && $_SESSION["bulletins_show_closed"] == 1) {
    $closed = 1;
  }
  $ViewedAt = array ();
  $viewquery = MysqlQuery("SELECT * FROM bulletin_views WHERE user_id=".$_SESSION['id']);
  while ($view = mysql_fetch_object($viewquery)) {
    $ViewedAt[$view->bulletin_id] = $view->last_read;
  }
  $bulletin_query = MysqlQuery("SELECT b.*, u.username, u.id FROM bulletins b LEFT OUTER JOIN users u ON b.updated_by=u.id WHERE " . ($closed ? "" : " b.closed=0 AND ") . " b.access_level <= ". $_SESSION["access_level"] . " ORDER BY b.closed ASC, b.updated DESC ");
  if (mysql_num_rows($bulletin_query) == 0) {
    print "<li><b>No bulletins entered</b>\n";
  }
  else {
    while ($bulletin = mysql_fetch_object($bulletin_query)) {
      $bullstatus="";
      $style = "font-family: tahoma, sans; font-size: 10pt; font-weight: bold; ";
      $divstyle = "";

      if ($bulletin->closed) {
        $bullstatus = " <font color=gray size=-1>CLOSED</font>" ;
        $style .= "color: gray; ";
      }
      elseif (!isset($ViewedAt[$bulletin->bulletin_id])) {
        $bullstatus = "<span class=\"text\" style=\"font-size: 10px; color: red; font-weight: bold;\">NEW " . dls_utime($bulletin->updated) . "</span>";
        $style .= "font-weight:bold; ";
      }
      elseif ($ViewedAt[$bulletin->bulletin_id] < $bulletin->updated) {
        $bullstatus = "<span class=\"text\" style=\"font-size: 10px; color: red; \">UPDATED ". dls_utime($bulletin->updated, FALSE, FALSE) . "</span>";
        $style .= "font-weight:bold; ";
      }

      if (isset($_GET["bulletin_id"]) && ($_GET["bulletin_id"] ==  $bulletin->bulletin_id)) {
        $divstyle .= "border-left: 3px solid blue; border-top: 1px dotted blue; border-bottom: 1px dotted blue; padding: 2px; ";
        if (!$bulletin->closed)
          $style .= "color: blue; ";
      }
      else {
        //$divstyle .= " margin-right: 5px;";
        if (!$bulletin->closed)
          $style .= "color: darkblue; ";
      }
      print " <div style=\"margin-right: 5px; $divstyle; $style\">
        <a style=\"$style\" href=\"bulletins.php?bulletin_id=" . $bulletin->bulletin_id . "\">" . $bulletin->bulletin_subject ."</a> 
        </span> $bullstatus ";
        if (isset($_GET["bulletin_id"]) && ($_GET["bulletin_id"] ==  $bulletin->bulletin_id)) {
          //print "<span style=\"float: right; width: 30px; \"><a href=\"bulletins.php\" title=\"Exit View\"> <img src=\"Images/paper-control-24-ns.png\"></a> </span>\n";
        }

      if ((isset($_GET["bulletin_id"]) && $_GET["bulletin_id"] == $bulletin->bulletin_id ) ||
          (isset($_SESSION["bulletins_show_metadata"]) &&  $_SESSION["bulletins_show_metadata"] == 1)) {
        print "<br><span style=\"color: gray; font:11px tahoma,sans; background-color: white; \">&nbsp; &nbsp; Updated: " . dls_utime($bulletin->updated) . " by " . $bulletin->username . "</span>\n";

        if ($_SESSION["access_level"] > 1 && $bulletin->access_level > 1) {
          print "<br><span style=\"color: gray; font: 11px tahoma,sans; background-color: white; \">&nbsp; &nbsp; Access Level <b>".$bulletin->access_level."</b> Required To View</span>\n";
        }
      }
      print "</div>";
    }
  }

  // right hand pane:
  //
  print "<td style=\"padding: 5px; \">&nbsp;</td>\n";
  print "<td style=\"width: 500px; \">\n";
 
  /////////////////////////////////////////////////////////////////////////////////
  
  if (isset($_GET["bulletin_id"])) {
    $bulletin_id = MysqlClean($_GET,"bulletin_id",20);
    $bulletin_query = MysqlQuery("SELECT b.*, u.username from bulletins b LEFT OUTER JOIN users u ON b.updated_by=u.id where b.bulletin_id=$bulletin_id ");
    if (mysql_affected_rows() != 1) {
      print "Error - expected 1 row, but got " . mysql_affected_rows() . " rows for bulletin $bulletin_id ";
      syslog(LOG_WARNING, "Error - expected 1 row, but got " . mysql_affected_rows() . " rows for bulletin $bulletin_id ");
      exit;
    }
    $bulletin = mysql_fetch_object($bulletin_query);

    print "<div style=\"font: bold 10pt monospace;\"><u>" . $bulletin->bulletin_subject . "</u></div>";
    print "<div style=\"font: 10pt monospace; border: 1px dotted gray; margin: 5px; padding-left: 10px; padding-right: 10px; background-color: #ffffcc\"><pre>". 
          $bulletin->bulletin_text . "</pre></div>\n";
  }

  /////////////////////////////////////////////////////////////////////////////////
  elseif (isset($_GET["edit_bulletin"])) {
    if ($_SESSION["access_level"] < 5) {
      print "Access level (". $_SESSION["access_level"] . ") too low to edit/create bulletin.\n";
      exit;
    }

    $bulletin_subject = "";
    $bulletin_text = "";
    $access_level = 1;
    $closed = 0;
    $bid_request = MysqlClean($_GET, "edit_bulletin", 20);
    if ($bid_request != "new") {
      $bulletin_load = MysqlQuery("SELECT * FROM bulletins WHERE bulletin_id=$bid_request");
      if (mysql_num_rows($bulletin_load) != 1) {
        print "ERROR loading bulletin for edit - returned " . $mysql_num_rows($bulletin_load) . " rows (expected 1).";
        syslog (LOG_WARNING, "ERROR loading bulletin for edit - returned " . $mysql_num_rows($bulletin_load) . " rows (expected 1).");
        exit;
      }
      else {
        $bulletin = mysql_fetch_object($bulletin_load);
        $bulletin_subject = $bulletin->bulletin_subject;
        $bulletin_text = $bulletin->bulletin_text;
        $access_level = $bulletin->access_level;
        $closed = $bulletin->closed;
      }
    }
?>
    Editing Bulletin <?php print $bid_request ?><p>
    <form action=bulletins.php method=post>
    <input name=bulletin_id type=hidden value="<?php print $bid_request ?>">

    
    Subject: <input name=bulletin_subject type=text size=80 maxlength=155 value="<?php print $bulletin_subject ?>"><br/>
    Access Level required to view: 
    <select name=access_level>
    <?php 
    // Horrible.  magic numbers.
      for ($i = 1; $i <= $_SESSION["access_level"]; $i++) {
        print "<option value=$i";
        if ($i == $access_level) { print " selected"; }
        print ">$i";
        if ($i == 1)     { print " (Normal Users)"; }
        elseif ($i == 5) { print " (Supervisors Only)"; }
        elseif ($i == 9) { print " (Asst./Dep./Chiefs Only)"; }
        elseif ($i == 10) { print " (System Admins Only)"; }
        print "</option>\n";
      }
    ?>

    </select> <br/>
    Close this bulletin? <input name=closed type=checkbox <?php print $closed ? " checked " : "" ?>><br/>
    <input name=orig_closed type=hidden value="<?php print $closed ? "closed" : "open"?>">
    Bulletin text: <br/>
    <textarea wrap=hard name=bulletin_text rows=10 cols=60 wrap="hard"><?php print $bulletin_text ?></textarea><br/>
    <input type=submit name=save_bulletin value="Save Changes"> <input type=submit name=clear_bulletin value="Cancel">

    </form>

<?php

  }
  print "<br></td> \n";  //end GUI block: right hand view/edit pane
  //print "<tr><td colspan=3 style=\"border-bottom: 1px dotted grey; font-size: 3px; margin-bottom: 15px;\">&nbsp; </td></tr>\n";
  //print "<tr><td colspan=3 style=\"font-size: 3px;\">&nbsp; </td></tr>\n";
  
  print "<tr><Td colspan=3><br>\n";

  if (isset($_SESSION["bulletins_show_closed"]) && $_SESSION["bulletins_show_closed"]==1) {
    print "<a class=\"button\" href=\"bulletins.php?closed=0\">Hide Closed Bulletins</a>\n";
  }
  else {
    print "<a class=\"button\" href=\"bulletins.php?closed=1\">Show Closed Bulletins</a>\n";
  }

  if (isset($_SESSION["bulletins_show_metadata"]) && $_SESSION["bulletins_show_metadata"]==1) {
    print "<a class=\"button\" href=\"bulletins.php?meta=0\">Hide Timestamps</a>\n";
  }
  else {
    print "<a class=\"button\" href=\"bulletins.php?meta=1\">Show Timestamps</a>\n";
  }
  if ($_SESSION["access_level"] >= 5) {
    print "<a class=\"button\" href=\"bulletins.php?edit_bulletin=new\">Add New Bulletin</a>\n";
  }

  if (isset($_GET["bulletin_id"]) && $_SESSION["access_level"] >= 5) {
    print "<a class=\"button\" href=\"bulletins.php?edit_bulletin=$bulletin_id\">Edit This Bulletin</a>\n";
  }

  if ($_SESSION["access_level"] >= 10) {
    print "<a class=\"button\" href=\"bulletins_import.php\">Database Import</a>\n";
  }

  print "</td></tr></table>\n";

  /////////////////////////////////////////////////////////////////////////////////

  mysql_close($link);
?>
</body>
</html>
