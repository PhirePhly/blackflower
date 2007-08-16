<?php
  $subsys="bulletins";

  require_once('db-open.php');
  require_once('session.inc');
  require_once('functions.php');
  require_once('local-dls.php');

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
    header('Location: bulletins.php');
  }



  header_html("Dispatch :: Bulletins");
?>
<body vlink="blue" link="blue" alink="cyan" onresize="resizeMe()">
<? include('include-title.php'); ?><p />
<?php 
  if (isset($_GET["bulletin_id"])) {
    $bulletin_id = MysqlClean($_GET,"bulletin_id",20);
    print "<div class=\"text\"> Bulletin " . $bulletin_id . "</div> ";
    $bulletin_query = MysqlQuery("SELECT b.*, u.username from bulletins b, users u where b.bulletin_id=$bulletin_id and b.updated_by=u.id");
    # show it
    if (mysql_affected_rows() != 1) {
      print "Error - expected 1 row, but got " . mysql_affected_rows() . " rows for bulletin $bulletin_id ";
      syslog(LOG_WARNING, "Error - expected 1 row, but got " . mysql_affected_rows() . " rows for bulletin $bulletin_id ");
      exit;
    }
    $bulletin = mysql_fetch_object($bulletin_query);
    print "<table class=\"bulletin-info\" style=\"border: 1px solid gray\"><tr><td>\n";
    print "<span class=\"text\">Subject: <b>" . $bulletin->bulletin_subject . "</b></span><br>\n";
    if ($_SESSION["access_level"] > 1) {
      print "<span class=\"text\">Required Access Level To View: <b>" . $bulletin->access_level . "</b></span><br>\n";
    }
    if ($bulletin->closed) {
      print "<span class=\"bulletin-info\"><b>This Bulletin is Closed.</b></span><br>\n";
    }
    print "<span class=\"text\">Updated: " . dls_utime($bulletin->updated) . " by " . $bulletin->username . " (". $bulletin->updated.")</span><br>\n";
    print "</td></tr></table>\n";
    print "<table><tr><td>\n";
    print "</td></tr></table>\n";
    print "<table class=text style=\"background-color: #ffffcc; border: 1px solid black\"><tr><td>\n";
    print "<div class=\"text\"><pre>" . $bulletin->bulletin_text . "</pre></div>\n";
    print "</td></tr></table>\n";

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

    print "<p>\n";
    if ($_SESSION["access_level"] >= 5) {
      print "<a href=\"bulletins.php?edit_bulletin=$bulletin_id\">Edit Bulletin</a><p>\n";
    }

    print "<span class=button><a href=bulletins.php>Return to List</a></span>\n";
    print "</body></html>\n ";
    exit;
  }

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
    Editing Bulletin <?=$bid_request?><p>
    <form action=bulletins.php method=post>
    <input name=bulletin_id type=hidden value="<?=$bid_request?>">

    <table>
    <tr> <td> Subject: </td><td><input name=bulletin_subject type=text size=80 maxlength=155 value="<?=$bulletin_subject?>"></td></tr>
    <tr> <td> Access Level required to view: </td><td>
    <select name=access_level>
    <?php 
      for ($i = 1; $i <= $_SESSION["access_level"]; $i++) {
        print "<option value=$i";
        if ($i == $access_level) {
          print " selected";
        }
        print ">$i";
        if ($i == 1) {
          print " (Normal Users)";
        }
        elseif ($i == 5) {
          print " (Supervisors Only)";
        }
        elseif ($i == 9) {
          print " (Asst./Dep./Chiefs Only)";
        }
        elseif ($i == 10) {
          print " (System Admins Only)";
        }
        print "</option>\n";
      }
      ?>

    </select> </td></tr>
    <tr> <td> Close this bulletin? </td><td><input name=closed type=checkbox <?=$closed? " checked " : ""?>></td></tr>
    <input name=orig_closed type=hidden value="<?=$closed? "closed" : "open"?>">
    <tr> <td> Bulletin: </td><td><textarea name=bulletin_text rows=10 cols=60 wrap="hard"><?=$bulletin_text?></textarea></td></tr>
    <tr> <td> <input type=submit name=save_bulletin value="Save Changes"> <input type=submit name=clear_bulletin value="Cancel"> </td></tr>
    </table>
    </form>
<?php

    exit;
  }

?>

<span class=text><b>Bulletins</b></span><p>
<table cellspacing=1 cellpadding=0 style="padding-bottom: 0">
<tr class=text style="text-align: center"><td>Subject</td><td class=text style="text-align: center">Last Updated</td><td class=text>Status</td>
<?php
  $closed = 0;
  if (isset($_GET["closed"])) {
    $closed = 1;
  }
  $ViewedAt = array ();
  $viewquery = MysqlQuery("SELECT * FROM bulletin_views WHERE user_id=".$_SESSION['id']);
  while ($view = mysql_fetch_object($viewquery)) {
    $ViewedAt[$view->bulletin_id] = $view->last_read;
  }
  $bulletin_query = MysqlQuery("SELECT * FROM bulletins b LEFT OUTER JOIN users u ON b.updated_by=u.id WHERE " . ($closed ? "" : " b.closed=0 AND ") . " b.access_level <= ". $_SESSION["access_level"] . " ORDER BY b.updated DESC");
  if (mysql_num_rows($bulletin_query) == 0) {
    print "<tr>\n";
    print "<td class=bulletin>No bulletins entered  </b>\n";
    print "<td class=bulletin>-</td>\n";
    print "<td class=\"bulletin-info\">-</td>\n";
    print "</tr>\n\n";
  }
  else {
    while ($bulletin = mysql_fetch_object($bulletin_query)) {
      $bulledit = "";
      $bulllink = "";
      $bullstatus="";
      $boldtitle=0;
      
      print "<tr>\n";
      #print "<td class=info>Bulletin " . $bulletin->bulletin_id . "</td>\n";
      $closed = $bulletin->closed;
      $bulllink = "<a ";
      if ($closed) {
        $bulllink  .= "style=\"color: gray\" ";
        $bulledit = "<font color=gray>";
      }
      $bulllink .= "href=\"bulletins.php?bulletin_id=" . $bulletin->bulletin_id . "\">" . $bulletin->bulletin_subject ."</a>";
      $bulledit .= "Edited " . (isset($bulletin->username) ? " by " . $bulletin->username . "," : "") . " " . dls_utime($bulletin->updated);
      if (!isset($ViewedAt[$bulletin->bulletin_id])) {
        $bullstatus = "<span style=\"font-size: 10px; background-color: black; color: yellow; font-weight: bold; border: 1px solid red\">NEW</span>";
        $boldtitle=1;
      }
      elseif ($ViewedAt[$bulletin->bulletin_id] < $bulletin->updated) {
        $bullstatus = "<span style=\"font-size: 10px; background-color: yellow; color: black; border: 1px solid black\">UPDATED</span>";
        $boldtitle = 1;
      }
  
      if ($closed) {
        $bullstatus .= " <font color=gray size=-1>CLOSED</font>" ;
        $bulledit .= "</font>";
      }

      print "<td class=bulletin>" . ($boldtitle? "<b>":"").$bulllink. ($boldtitle?"</b>":""). "\n";
      print "<td class=bulletin>$bulledit</td>\n";
      print "<td class=\"bulletin-info\">$bullstatus</td>\n";
      print "</tr>\n\n";
    }
  }
  
  print "</table>\n";
  print "<p>\n";

  if (isset($_GET["closed"])) {
    print "<a class=text href=\"bulletins.php\">View Only Open Bulletins (Default)</a><br>\n";
  }
  else {
    print "<a class=text href=\"bulletins.php?closed\">View All Bulletins (Including Closed)</a><br>\n";
  }

  if ($_SESSION["access_level"] >= 5) {
    print "<a class=text href=\"bulletins.php?edit_bulletin=new\">Add New Bulletin</a><br>\n";
  }
  mysql_close($link);
?>
</body>
</html>
