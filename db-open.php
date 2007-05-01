<?php
  require('cad.conf');

  $link = mysql_connect($DB_HOST, $DB_USER, $DB_PASS) or die("Could not connect : " . mysql_error());
  mysql_select_db($DB_NAME) or die("Could not select database");

  function MysqlQuery ($sqlquery) {
    global $link;
    $return = mysql_query($sqlquery, $link) or die("CRITICAL ERROR\nIn query: $sqlquery<br>\nError: ".mysql_error());
    return $return;
  }

  function MysqlClean ($array, $index, $maxlength) {
    global $link;
    if (isset($array["{$index}"])) {
      $input = substr($array["{$index}"], 0, $maxlength);
      if (get_magic_quotes_gpc()) {
        $input = stripslashes($input);
        $input = mysql_real_escape_string($input, $link);
      }
      else {
        $input = mysql_real_escape_string($input, $link);
      }
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
