<?php
  $subsys="unit";

  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');
  require_once('functions.php');

  if (isset($_POST['unit'])) {
      $unit = strtoupper(MysqlClean($_POST,'unit',20));
      if (isset($_POST['status'])) $status = MysqlClean($_POST,'status',30); else $status = "";
      if (isset($_POST['status_comment'])) $status_comment = MysqlClean($_POST,'status_comment',255); else $status_comment= "";
      if (isset($_POST['type'])) $type = MysqlClean($_POST,'type',20); else $type= "Unit";
      if (isset($_POST['assignment'])) $assignment = MysqlClean($_POST,'assignment',20); else $assignment= "";
      if (isset($_POST['role'])) $role = MysqlClean($_POST,'role',20); else $role= "Other";
      if (isset($_POST['personnel'])) $personnel = MysqlClean($_POST,'personnel',100); else $personnel= "";
  }

  if (isset($_POST['saveunit'])) {
    if (isset($_POST['new-unit-entered'])) {
      $unit = strtoupper(MysqlClean($_POST,'unit',20));
      $pattern = "/[\\/[\]'!@#$\^%&*()+=,;:{}|<>~`?\"]/";
      $replacement = "";
      if (preg_match($pattern, $unit)) {
        die('Bad characters in name: '.$unit. "\n  Only letters, numbers, space, dash or underscore are valid characters.\n  Use your browser Back feature to resolve the problem and try again.");
      }
      // update status
      if ($personnel != "")
        $query = "INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Unit created - personnel: $personnel')";
      else
        $query = "INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Unit created.')";
      mysql_query($query) or die("In query: $query\nError: " . mysql_error());

      // update units
      // TODO: sanity check $unit input characters here?
      $query = "INSERT INTO units (unit, status, status_comment, type, role, personnel, update_ts, assignment) VALUES ('$unit', '$status', '$status_comment', '$type', '$role', '$personnel', NOW(), '$assignment')";
      mysql_query($query) or die("In query: $query\nError: " . mysql_error());
    }

    else {
      // update status and personnel notes
      if ($_POST['status'] <> $_POST['previous_status']) {
        $query = "INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Status change: $status";
        if ($_POST['previous_status'] <> "") {
              $query .= " (was: ".MysqlClean($_POST, 'previous_status', 200).")";
        }
        $query .= "')";
        mysql_query($query) or die("In query: $query\nError: " . mysql_error());

        if ($_POST['previous_status'] == 'Attached to Incident') {
          $query = "UPDATE incident_units SET cleared_time=NOW() WHERE unit='$unit' AND cleared_time IS NULL";
          mysql_query($query) or die("In query: $query\nError: " . mysql_error());
        }
      }
      if ($_POST['personnel'] <> $_POST['previous_personnel']) {
        $query = "INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Personnel change logged: $personnel')";
        mysql_query($query) or die("In query: $query\nError: " . mysql_error());
      }

      // update units
      if ($_POST['status'] <> $_POST['previous_status'])
         $fragment = "status='$status', update_ts=NOW(),";
      else $fragment="";
      $query = "UPDATE units SET $fragment status_comment='$status_comment', type='$type', role='$role', personnel='$personnel', assignment='$assignment' WHERE unit='$unit'";
      mysql_query($query) or die("In query: $query\nError: " . mysql_error());
    }


    print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
    die("(Error: JavaScript not enabled or not present) Action completed. Close this window to continue.");
  }

  elseif (isset($_POST["deleteunit"])) {
    if (isset($_POST['deleteforsure'])) {
      $query = "DELETE FROM units WHERE unit='".MysqlClean($_POST,"unit",20)."'";
      mysql_query($query) or die("In query: $query<br>\nError: " . mysql_error());
      $query = "INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Unit deleted.')";
      mysql_query($query) or die("In query: $query<br>\nError: " . mysql_error());
      print "<SCRIPT LANGUAGE=\"JavaScript\">if (window.opener){window.opener.location.reload()} self.close()</SCRIPT>";
      die("(Error: JavaScript not enabled or not present) Action completed. Close this window to continue.");
    }
    else {
      $_GET['unit'] = MysqlClean($_POST,'unit',20);
      $unit = $_GET['unit'];
      $newunit = 0;
      $unitquery = "SELECT * from units where unit = '$unit'";
      $unitresult = mysql_query($unitquery) or die("unit query failed:" . mysql_error());
      $unitline = mysql_fetch_array($unitresult, MYSQL_ASSOC) or die ("unit not found in table");
	    mysql_free_result($unitresult);
    }
  }
  elseif (isset($_GET["new-unit"])) {
    $unitline["unit"] = "";
    $unit = "";
    $newunit = 1;
    $unitline["status"] = "(new unit)";
    $unitline["role"] = "Other";
    $unitline["type"] = "Unit";
    $unitline["assignment"] = "";
    $unitline["status_comment"] = "";
    $unitline["personnel"] = "";
    $unitline["update_ts"] = "";
  }
  elseif (isset($_GET["unit"])) {
    $unit = MysqlClean($_GET,"unit",20);
    $newunit = 0;
    $unitquery = "SELECT * from units where unit = '$unit'";
    $unitresult = mysql_query($unitquery) or die("unit query failed:" . mysql_error());
    $unitline = mysql_fetch_array($unitresult, MYSQL_ASSOC) or die ("unit not found in table");
	  mysql_free_result($unitresult);
  }
  elseif (isset($_POST['add_pageout'])) {
    $newval = MysqlClean($_POST, 'newpageout', 20);
    MysqlQuery("INSERT INTO unit_incident_paging (unit, to_pager_id) VALUES ('$unit', $newval)");
    header("Location: edit-unit.php?unit=".$_POST['unit']);
    exit;
  }
  else {
    $action = 0;
    foreach (array_keys($_POST) as $postkey) {
      if (preg_match("/delete_pageout_(\d+)/", $postkey, $matches)) {
        MysqlQuery("DELETE FROM unit_incident_paging WHERE row_id=" . $matches[1] );
        if (mysql_affected_rows() != 1) {
          syslog(LOG_WARNING, "Error while deleting row_id " . $matches[1] . " from unit_incident_paging");
        }
        $action = 1;
        header("Location: edit-unit.php?unit=".$_POST['unit']);
        exit;
      }
    }
    // If no row was found in the pattern match case
    if (!$action) {
      die ('Unknown options to edit-unit.php page load.');
    }
  }

  header_html('Dispatch :: Unit Details');
/*-------------------------------------------------------------------------*/?>
<body vlink="blue" link="blue" alink="cyan">

<font face="tahoma,ariel,sans">
<form name="myform" action="edit-unit.php" method="post">

<? if (!$newunit)
     print " <b>Unit: $unit</b>";
   else
     print " <b>Creating a New Unit</b>";
?>
  <p />
  <table width="660">
  <tr>
  <td colspan="3" bgcolor="#aaaaaa" width="580">
     <table cellpadding="2" cellspacing="0" width="100%">
     <tr>

  <?php
  if ($newunit) {
    print "<td class=\"message\"><b>Unit name</b></td>\n";
    print "<td colspan=\"5\" class=\"message\"><input type=\"text\" name=\"unit\"><input type=\"hidden\" name=\"new-unit-entered\"></td>\n";
    print "</tr>\n<tr>\n";
    print "</tr>\n<tr>\n";
  }
  else {
    print "<input type=\"hidden\" name=\"unit\" value=\"".$unit."\" />";
  }
  ?>

       <td class="message" width="80" STYLE="width: 80px"><u>S</u>tatus</td>
       <td class="message">
         <label for="status" accesskey="s">
         <select name="status" id="status">
    <?php /*--------------------------------------------------------------------------------------*/

       $statusset=0;
       $statusquery = "SELECT * from status_options";
       $statusresult = mysql_query($statusquery) or die ("status query failed:" . mysql_error());
       while ($line = mysql_fetch_array($statusresult, MYSQL_ASSOC)) {
         echo "        <option ";
         if (!strcmp($line["status"], $unitline["status"])) {
	         $statusset=1;
	         echo "selected ";
	       }
	       echo "value=\"". MysqlUnClean($line["status"])."\">". $line["status"]."</option>\n";
       }
       if (!$statusset) {
         echo "        <option selected value=\"\">\n";
       }
	     mysql_free_result($statusresult);
   /*----------------------------------------------------------------------*/?>
          </status>
          </label>
<input type="hidden" name="previous_status" value="<?=MysqlUnClean($unitline["status"]);?>" />
</td>

<td class="message" width="50">T<u>y</u>pe</td>
<td class="message">
  <label for="type" accesskey="y">
  <select name="type" id="type" width="100" STYLE="width: 100px">
<? /*----------------------------------------------------------------------*/
       $avail_types = array('Unit', 'Individual', 'Generic');
       if (array_search($unitline["type"], $avail_types) === FALSE) {
         print "<option selected value=\"\">(none)</option>\n";
       }
       foreach ($avail_types as $type) {
         print "<option ";
         if ($unitline["type"] == $type)
           print "selected ";
         print "value=\"$type\">$type</option>\n";
       }
   /*----------------------------------------------------------------------*/?>

  </select>
  </label>
  </td>

<td class="message" width="50"><u>A</u>ssignment</td>
<td class="message" width=150 style="width: 150px">
  <label for="assignment" accesskey="a">
  <select name="assignment" id="assignment" width="150" STYLE="width:150px">
<? /*----------------------------------------------------------------------*/
       $avail_asses = MysqlQuery('SELECT * FROM unit_assignments');
       
       print "<option value=\"\">(none)</option>\n";
       while ($avail_assignment = mysql_fetch_object ($avail_asses)) {
         print "<option ";
         if ($unitline["assignment"] == $avail_assignment->assignment)
           print "selected ";

         print "value=\"".$avail_assignment->assignment."\">".
               $avail_assignment->description."</option>\n";
       }
   /*----------------------------------------------------------------------*/?>
   </select>

</td>
</tr>

<tr>
 <td class="message" align="right">Updated:</td>
 <td class="message" align="right" width="150"><?=dls_utime($unitline["update_ts"])?></td>
 <td class="message" align="right">B<u>r</u>anch</td>
 <td class="message" align="right" colspan=3>
    <label for="role" accesskey="role">
    <select name="role" id="role" width="100" STYLE="width: 100px">

       <?php /*--------------------------------------------------------------------------------------*/
         $avail_roles = array('Fire', 'Medical', 'Comm', 'MHB', 'Admin', 'Other');
         if (array_search($unitline["role"], $avail_roles) === FALSE) {
		         print "<option selected value=\"\">(not set)</option>\n";
         }
         foreach ($avail_roles as $role) {
           print "<option ";
           if ($unitline["role"] == $role) print "selected ";
           print "value=\"$role\">$role</option>\n";
         }
	  /*--------------------------------------------------------------------------------------------*/ ?>
     </select>
     </label>
   </td>
   </tr>
   <tr>
     <td class="message"  width="90">C<u>o</u>mment</td>
     <td class="message" colspan="5">
     <label for="status_comment" accesskey="o">
     <input name="status_comment" id="status_comment" type="text" maxlength="250" size="80"
      value="<?php print MysqlUnClean($unitline["status_comment"])?>" />
     </label>
     </td>
   </tr>

   <tr>
     <td class="message"  width="90"><u>P</u>ersonnel</td>
     <td class="message" colspan="5">
         <label for="personnel" accesskey="p">
         <input name="personnel" id="personnel" type="text" maxlength="250" size="80"
          value="<?=MysqlUnClean($unitline["personnel"]);?>" />
         </label>
         <input type="hidden" name="previous_personnel" value="<?=$unitline["personnel"];?>" />
     </td>
   </tr>
<?
  if ($unitline["type"] == "Generic")
    print "<tr>\n<td class=\"message\" colspan=\"6\"><b>Note: As a generic unit, multiple instances of this unit may be simultaneously assigned to separate incidents.</b></td></tr>"
?>
   </table>
   </td>
 </tr>
 <tr><td></td></tr>
 <tr><td></td>
     <td class="label"><input type="submit" name="saveunit" value="Save" />
     <input type="reset" value="Cancel" onClick='if (window.opener){window.opener.location.reload()} self.close()' />
     <NOSCRIPT><B>Warning</B>: Javascript disabled. Close popup to cancel changes.</NOSCRIPT>
     </td>
<?
    if ($newunit) {
      print "</tr>\n";
    }
    else {
      print "<td align=\"right\"><input type=\"submit\" name=\"deleteunit\" value=\"Delete Unit\" /></td>\n";
      print "</tr>\n";
      print "<tr><td colspan=\"2\"><td align=\"right\" style=\"color: lightgray; font-size: 9pt; font-style: italic;\">";
      if (isset($_POST["deleteunit"])) {
         print "<span style=\"color: red; text-decoration: blink;\">Confirm</span> delete unit?&nbsp;<input type=\"checkbox\" name=\"deleteforsure\" /></td></tr>";
      } else {
         print "Confirm delete unit?&nbsp;<input type=\"checkbox\" disabled name=\"deleteforsure\" /></td></tr>";
      }
    }
?>
  </table>

<?php 
  if (isset($USE_PAGING_LINK) && $USE_PAGING_LINK) {

    $paginglink = mysql_connect($PAGINGHOST, $PAGINGUSER, $PAGINGPASS) or die("Could not connect : " . mysql_error());
    $options_query = mysql_query("SELECT * FROM $PAGINGDB.pagers ORDER BY name", $paginglink) or die ("Problem with query on $PAGINGDB.pagers");
    $Pagers = array();
    while ($pager_option = mysql_fetch_object($options_query)) {
      $Pagers[$pager_option->pager_id] = $pager_option->name;
    }
    $pageout_query = mysql_query("SELECT * FROM unit_incident_paging WHERE unit='$unit'", $paginglink);
    // TODO: set access level dynamically
    if (mysql_num_rows($pageout_query) || $_SESSION['access_level'] >= 5) {
      ?>

  <table width="300" valign=top>
  <tr valign=top>
    <td> <span class=header>Page-out&nbsp;on&nbsp;incident&nbsp;assignment&nbsp;of&nbsp;this&nbsp;unit:&nbsp;&nbsp;</span></td>
    <td width=50>&nbsp;</td>
    <td> <span class=header>Add&nbsp;page-out&nbsp;notification&nbsp;of:</span></td>
  </tr>
  <tr valign=top>
    <td bgcolor="#aaaaaa" width="280">
     <table cellpadding="2" cellspacing="1" width="100%">

     <?php
      while ($pageout_rcpt = mysql_fetch_object($pageout_query)) {
        // TODO: set access level dynamically
        if ($_SESSION['access_level'] >= 5) {
          print "<tr><td width=100% class=\"message\">" . $Pagers[$pageout_rcpt->to_pager_id] . "</td><td align=right class=message><input type=submit name=\"delete_pageout_". $pageout_rcpt->row_id . "\" value=\"Delete\"></td></tr>";
          unset($Pagers[$pageout_rcpt->to_pager_id]);
        }
        else {
          print "<tr><td width=100% class=\"message\">" . $Pagers[$pageout_rcpt->to_pager_id] . "</td></tr>";
        }
      }
      print "</table></td>\n";
    }
    if ($_SESSION['access_level'] >= 5) {
    ?>

</td><td> </td> <td>

     <table cellpadding="2" cellspacing="1" width="100%">
     <?php
      print "<tr><td class=message><SELECT name=newpageout>\n";
      foreach (array_keys($Pagers) as $pager) {
        print "<option value=$pager>" . $Pagers[$pager] . "</option>\n";
      }
      print "</SELECT></td><td class=message><input type=submit value=\"Add\" name=\"add_pageout\"></td></tr>\n";
    }
  }

?>
  </table>
  </td></tr>
  </table>
  </form>
  <p>


<?php if (!$newunit) { ?>
<b>Last 10 Messages</b><br />
  <table><tr><td width="20"></td><td bgcolor="#aaaaaa">
  <table cellpadding="2" cellspacing="1"> <tr>
    <td class="message">Time</td>
    <td class="message">Message</td>
  </tr>

  <?php

     $rowquery = "SELECT * FROM messages WHERE unit = '$unit' AND deleted=0 ORDER BY oid DESC LIMIT 10";
     $rowresult = mysql_query($rowquery) or die("row Query failed : " . mysql_error());

     while ($line = mysql_fetch_array($rowresult, MYSQL_ASSOC)) {
        echo "\t<tr>\n";
        $td = "<td class=\"message\">";

        echo $td, dls_utime($line["ts"]), "</td>";
        echo $td, $line["message"], "</td>";
        echo "\t</tr>\n";
     }
     mysql_free_result($rowresult);
     mysql_close($link);
   ?>

  </table>
  </table>
  <?php } ?>

</body>
</html>


