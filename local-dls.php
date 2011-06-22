<?php
  if (!@include('cad.conf')) {
    print "Critical error: CAD configuration file is missing or unreadable.  Contact your CAD system administrator.";
    exit;
  }

  // Check that this file is not loaded directly.
  if (basename( __FILE__ ) == basename($_SERVER["PHP_SELF"])) exit();

  if (version_compare(PHP_VERSION, '5.1.0', '>=')) {
    if (isset($DEFAULT_TIMEZONE)) {
      date_default_timezone_set($DEFAULT_TIMEZONE);
    } 
    else {
      date_default_timezone_set('America/Los_Angeles');
    }
  }
  define("THIS_HOUR", date("H"));
  define("THIS_DATE", date("Y-m-d"));
  define("NOW", date("Y-m-d H:i:s"));
  define("THIS_WEEK", date("Y-W"));
  define("THIS_DOW",  date("w"));
  define("THIS_PAGETS", time());

  define("TS_HOUR",3600);
  define("TS_DAY",86400);
  define("TS_WEEK",604800);


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

  function dls_utime ($datetime, $year=TRUE, $seconds=FALSE) {
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
        if ((int)THIS_PAGETS - $tval < TS_WEEK) {
        //($varweek == THIS_WEEK || ($varweek == THIS_WEEK-1 && $vardow > THIS_DOW)) {
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
      if ($old)
        $u_time = "<span class=\"lolite\">$u_time</span>";
      return $u_time;
    }
    else {
      return "";
    }
  }

  // For timestamping in the edit_incident window ONLY -- we don't want huge amounts of detail there.
  function dls_hmtime ($tm_var) {
    if ($tm_var && $tm_var <> "0000-00-00 00:00:00")
      return date("H:i", strtotime($tm_var));
    else return "";
  }

  function dls_dhmtime ($tm_var) {
    $dayname = '';
    if ($tm_var && $tm_var <> "0000-00-00 00:00:00" &&
       (date("D", strtotime($tm_var)) != date("D", time())))
      $dayname =  date("D", strtotime($tm_var)) . ' ';
    return $dayname . dls_hmtime ($tm_var);
  }

  function dls_mdhmtime ($tm_var) {
    if ($tm_var && $tm_var <> "0000-00-00 00:00:00")
      return date("m/d H:i", strtotime($tm_var));
    else return "";
  }

?>
