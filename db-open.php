<?php
  $link = mysql_connect("localhost", "cad", "cad-password") or die("Could not connect : " . mysql_error());
  mysql_select_db("cad") or die("Could not select database");

  function MysqlQuery ($sqlquery) {
    $return = mysql_query($sqlquery) or die("CRITICAL ERROR\nIn query: $sqlquery<br>\nError: ".mysql_error());
    return $return;
  }

  function MysqlClean ($array, $index, $maxlength) {
    global $link;
    if (isset($array["{$index}"])) {
      $input = substr($array["{$index}"], 0, $maxlength);
      $input = mysql_real_escape_string($input, $link);
      $input = htmlentities($input);
      return ($input);
    }
    return NULL;
  }

  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
  header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
  header("Cache-Control: no-store, no-cache, must-revalidate");
  header("Cache-Control: post-check=0, pre-check=0", false);
  header("Pragma: no-cache");
?>
