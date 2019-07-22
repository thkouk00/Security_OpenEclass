<?php
include '../../include/baseTheme.php';
global $mysqlMainDb;

$result = db_query("SELECT user_id,username,password FROM eclass.user", $mysqlMainDb);
while ($r = mysql_fetch_array($result)) {
	echo $r['user_id'];
	echo " , ";
	echo $r['username'];
	echo " , ";
	echo $r['password'];
	echo "<br>";
}
echo "<br>";
echo "<br>";

$result = db_query("UPDATE eclass.user SET user_id=40 where user_id=51");

?>