<?php
  $subsys = "admin";

  require_once('session.inc');
  require_once('functions.php');

  header_html("Dispatch :: System Admin")
?>
<body vlink="blue" link="blue" alink="cyan">
<? include('include-title.php'); ?>
<?php
if ($_SESSION['access_level'] >= 10) {
 ?>
  <table>
  <tr><td></td></tr>
  <tr><td><b>System Administration</b></td></tr>
  <tr>
  <td align="left" width="400">

<table width="350" style="border: 3px ridge blue; padding: 5px; background-color: #dddddd">
  <tr><td><a href="config-users.php">Edit Users</a></td></tr>
  <tr><td><a href="config-cleardb.php">Archive and Clear Database</a></td></tr>
  <tr><td><font color="gray">Manage Database Archives</font>  (Not developed yet)</td></tr>
</table>
</td>

<?php
}
else {
  print "Access level too low to access System Administration features.";
}
?>

</tr>
</table>

</body>
</html>
