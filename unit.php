<?php
  $subsys="unit";
  
  require_once('db-open.php');
  include('local-dls.php');
  require_once('session.inc');

  if (isset($_POST['unit'])) {
      $unit = strtoupper(MysqlClean($_POST,'unit',20));
      if (isset($_POST['status'])) $status = MysqlClean($_POST,'status',30); else $status = "";
      if (isset($_POST['status_comment'])) $status_comment = MysqlClean($_POST,'status_comment',255); else $status_comment= "";
      if (isset($_POST['type'])) $type = MysqlClean($_POST,'type',20); else $type= "Unit";
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
      $query = "INSERT INTO units (unit, status, status_comment, type, role, personnel, update_ts) VALUES ('$unit', '$status', '$status_comment', '$type', '$role', '$personnel', NOW())";
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
      $query = "UPDATE units SET $fragment status_comment='$status_comment', type='$type', role='$role', personnel='$personnel' WHERE unit='$unit'";
      mysql_query($query) or die("In query: $query\nError: " . mysql_error());
    }
    print "<SCRIPT LANGUAGE=\"JavaScript\"> window.opener.location.reload(); self.close()</SCRIPT>";
  }

  elseif (isset($_POST["deleteunit"])) {
    if (isset($_POST['deleteforsure'])) {
      $query = "DELETE FROM units WHERE unit='".MysqlClean($_POST,"unit",20)."'";
      mysql_query($query) or die("In query: $query<br>\nError: " . mysql_error());
      $query = "INSERT INTO messages (ts, unit, message) VALUES (NOW(), '$unit', 'Unit deleted.')";
      mysql_query($query) or die("In query: $query<br>\nError: " . mysql_error());
      print "<SCRIPT LANGUAGE=\"JavaScript\"> window.opener.location.reload(); self.close()</SCRIPT>";
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
  else
    die ('Unknown options to unit.php page load.');

/*--------------------------------------------------------------------------------------------*/?> 

<HTML>
<HEAD>
  <TITLE>Dispatch :: Unit Details</TITLE>
  <LINK REL=StyleSheet HREF="style.css" TYPE="text/css" MEDIA=screen>
  
</HEAD>
<BODY vlink=blue link=blue alink=cyan>

<FONT face="tahoma,ariel,sans">
<FORM name="myform" action="unit.php" method="post">

<?php if (!$newunit) print " <b>Unit: $unit </b>"; else print " <b>Creating a New Unit</b>";?>

  <p>
  <table width=660>
  <tr>
  <td width=20>&nbsp;</td>
  <td colspan=2 bgcolor="#aaaaaa" width=580>
     <table cellpadding=2 cellspacing=0 width=100%> 
     <tr>

  <?php 
  if ($newunit) {
    print "<td class=\"message\"><b>Unit name</b></td>\n";
    print "<td colspan=\"3\" class=\"message\"><input type=\"text\" name=\"unit\"><input type=\"hidden\" name=\"new-unit-entered\"></td>\n";
    print "</tr>\n<tr>\n";
    print "</tr>\n<tr>\n";
  }
  else {
    print "<input type=\"hidden\" name=\"unit\" value=\"".$unit."\">";
  }
  ?>

       <td class="message" width=150 STYLE="width: 150px">Status</td> 
       <td class="message"> 
         <select name="status" accesskey="s">
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
	       echo "value=\"". $line["status"]."\">". $line["status"]."</option>\n";
       }
       if (!$statusset) {
         echo "        <option selected value=\"none\">\n";
       }
       print "</select>\n";
	     mysql_free_result($statusresult);
    /*--------------------------------------------------------------------------------------------*/ ?>

<input type=hidden name="previous_status" value="<?php print $unitline["status"] ?>">
</td>

<td class="message" width=50>Type</td>
 <td class="message" > <select width="100" STYLE="width: 100px" name="type" accesskey="t">

     <?php /*--------------------------------------------------------------------------------------*/
       $avail_types = array('Unit', 'Individual', 'Generic');
       if (array_search($unitline["type"], $avail_types) === FALSE) {
		       print "<option selected value=\"\"></option>\n";
       }
       foreach ($avail_types as $type) {
         print "<option ";
         if ($unitline["type"] == $type) print "selected ";
         print "value=\"$type\">$type</option>\n";
       }
    /*--------------------------------------------------------------------------------------------*/ ?>

 </select>
</tr>
<tr>
 <td class="message" align=right>Updated:</td> 
 <td class="message" align=right width=150><?php print dls_utime($unitline["update_ts"])?> </td>
             
 <td class="message" align=right>Branch</td>
 <td class="message" align=right> <select width="100" STYLE="width: 100px" name="role" accesskey="r">

       <?php /*--------------------------------------------------------------------------------------*/
         $avail_roles = array('Fire', 'Medical', 'Comm', 'MHB', 'Admin', 'Other');
         if (array_search($unitline["role"], $avail_roles) === FALSE) {
		         print "<option selected value=\"\"></option>\n";
         }
         foreach ($avail_roles as $role) {
           print "<option ";
           if ($unitline["role"] == $role) print "selected ";
           print "value=\"$role\">$role</option>\n";
         }
		  /*--------------------------------------------------------------------------------------------*/ ?>
		</select>
		</td></tr>

   <tr>
     <td class="message"  width=90>Comment</td> 
     <td class="message" colspan="5"> <input type="text" accesskey="c" maxlength="250" size="80" name="status_comment" value="<?php print $unitline["status_comment"]?>"> </td>
   </tr>

   <tr>
     <td class="message"  width=90>Personnel</td> 
     <td class="message" colspan="5"> 
         <input type="text" accesskey="c" maxlength="250" size="80" name="personnel" value="<?php print $unitline["personnel"]?>"> 
         <input type="hidden" name="previous_personnel" value="<?php print $unitline["personnel"]?>">
     </td>
   </tr>
   <?php if ($unitline["type"] == "Generic") print "<tr>\n<td class=\"message\" colspan=\"6\"><b>Note: As a generic unit, multiple instances of this unit may be simultaneously assigned to separate incidents.</b></td></tr>" ?>

   
   </table>
   </td>
 </tr>
 <tr><td></td></tr>
 <tr><td></td>
     <td><input type="submit" name="saveunit" value="Save"> <input type="reset" value="Cancel" onClick="window.opener.location.reload(); self.close()"></td>
     <?php 
     if ($newunit) {
       print "</tr>\n";
    }
    else {
       print "<td align=right><input type=\"submit\" name=\"deleteunit\" value=\"Delete Unit\"></td>\n";
       print "</tr>\n";
       if (isset($_POST["deleteunit"])) {
          print "<tr><td colspan=2><td align=right><font size=\"-1\"><blink>Confirm</blink> delete unit? &nbsp; </font> <input type=\"checkbox\" name=\"deleteforsure\"></td></tr>";
       }
       else {
          print "<tr><td colspan=2><td align=right><font color=\"lightgray\" size=\"-1\"><i>Confirm delete unit? &nbsp; </i></font><input type=\"checkbox\" disabled name=\"deleteforsure\"></td></tr>"; 
       }
    }
  ?>
  </table>
  </form>

<?php if (!$newunit) { ?>
<b>Last 10 Messages</b><br>
  <table><tr><td width=20></td><td bgcolor="#aaaaaa">
  <table cellpadding=2 cellspacing=1> <tr>
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


