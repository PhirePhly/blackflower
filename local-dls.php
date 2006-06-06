<?php
  define("THIS_HOUR", date("H"));
  define("THIS_DATE", date("Y-m-d"));
  define("NOW", date("Y-m-d H:i:s"));
  define("THIS_WEEK", date("Y-W"));
  define("THIS_DOW",  date("w"));
  define("THIS_PAGETS", time());
  
  function dls_ustr ($str_in) {
         return str_replace(" ", "&nbsp;", $str_in);
  }

  // dls_utime ($datetime, $seconds = FALSE)
  //
  // This function takes in a MySQL DATETIME and returns the equivalent displayable
  // string in "universal CAD display time format" -- 
  //
  // If from same day as current, "HH:MM"
  // If from a previous day less than seven days ago, "<gray>Day HH:MM</gray>"
  // If from earlier than that, "<gray>YYYY-mm-dd HH:MM</gray>"
  // If $seconds is passed in as TRUE, the seconds value of the DATETIME is
  //  appended to the universal time display format.

  function dls_utime ($datetime, $seconds=FALSE) {
    if ($datetime && $datetime <> "0000-00-00 00:00:00") {
      $old=0;
      $tval = strtotime($datetime);
      if ($seconds) $u_fmt = "H:i:s";
      else $u_fmt = "H:i";

      $vardate = date("Y-m-d", $tval);
      //print "\n<!-- comparing ".THIS_DATE." and $vardate -->\n";
      if ($vardate <> THIS_DATE) {
        $varweek = date("Y-W", $tval);
        $vardow = date("w", $tval);
        //print "<!-- comparing weeks ".THIS_WEEK." and variable $varweek -- comparing dow ".THIS_DOW." and variable $vardow -->\n";
        if ($varweek == THIS_WEEK || ($varweek == THIS_WEEK-1 && $vardow > THIS_DOW)) {
          $u_fmt = "D $u_fmt";
        }
        else {
          $u_fmt = "Y-m-d $u_fmt";
        }
        $u_time = date($u_fmt, $tval);
        $old=1;
      }
      else {
        $u_time = date($u_fmt, $tval);
      }
      $u_time = dls_ustr($u_time);
      if($old) $u_time = "<font color=\"gray\">$u_time</font>";
      return $u_time;
    }
    else {
      return "";
    }
  }

  // For timestamping in the edit_incident window ONLY -- we don't want huge amounts of detail there.
  function dls_hmstime ($tm_var) {
    if ($tm_var && $tm_var <> "0000-00-00 00:00:00")
      return date("H:i:s", strtotime($tm_var));
    else return "";
  }

?>
