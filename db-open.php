<?php
  if (!@include('cad.conf')) {
    print "Critical error: CAD configuration file is missing or unreadable.  Contact your CAD system administrator.";
    exit;
  }

  // Use mysqli for PHP 7.4+ / 8.x compatibility
  $link = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) or die("Could not connect : " . mysqli_connect_error());

  function MysqlQuery ($sqlquery) {
    global $link;
    $return = mysqli_query($link, $sqlquery) or die("CRITICAL ERROR\nIn query: $sqlquery<br>\nError: ".mysqli_error($link));
    return $return;
  }


  function MysqlGrabData ($sqlquery) {
    global $link;
    $return = MysqlQuery($sqlquery);
    $num_rows = mysqli_num_rows($return);
    if ($num_rows != 1) {
      print "Internal error, expected 1 row (got $num_rows) in query [$sqlquery]";
      syslog(LOG_CRIT, "MysqlGrabData: Internal error - saw $num_rows rows for [$sqlquery]");
    }
    $rval = mysqli_fetch_array($return, MYSQLI_NUM);
    mysqli_free_result($return);
    return $rval[0];
  }

  function MysqlClean ($array, $index, $maxlength) {
    global $link;
    if (isset($array["{$index}"])) {
      $input = substr($array["{$index}"], 0, $maxlength);
      // get_magic_quotes_gpc() removed in PHP 7.4, no longer needed
      $input = mysqli_real_escape_string($link, $input);
      return ($input);
    }
    return NULL;
  }

  function MysqlUnClean ($input) {
    $input = htmlentities($input);
    return ($input);
  }

  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
  header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
  header("Cache-Control: no-store, no-cache, must-revalidate");
  header("Cache-Control: post-check=0, pre-check=0", false);
  header("Pragma: no-cache");
?>
