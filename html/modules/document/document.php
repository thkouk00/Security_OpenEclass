<?php
/*========================================================================
*   Open eClass 2.3
*   E-learning and Course Management System
* ========================================================================
*  Copyright(c) 2003-2010  Greek Universities Network - GUnet
*  A full copyright notice can be read in "/info/copyright.txt".
*
*  Developers Group:	Costas Tsibanis <k.tsibanis@noc.uoa.gr>
*			Yannis Exidaridis <jexi@noc.uoa.gr>
*			Alexandros Diamantidis <adia@noc.uoa.gr>
*			Tilemachos Raptis <traptis@noc.uoa.gr>
*
*  For a full list of contributors, see "credits.txt".
*
*  Open eClass is an open platform distributed in the hope that it will
*  be useful (without any warranty), under the terms of the GNU (General
*  Public License) as published by the Free Software Foundation.
*  The full license can be read in "/info/license/license_gpl.txt".
*
*  Contact address: 	GUnet Asynchronous eLearning Group,
*  			Network Operations Center, University of Athens,
*  			Panepistimiopolis Ilissia, 15784, Athens, Greece
*  			eMail: info@openeclass.org
* =========================================================================*/


/*===========================================================================
document.php
 * @version $Id: document.php,v 1.86 2009-07-24 13:42:05 jexi Exp $
@last update: 20-12-2006 by Evelthon Prodromou
@authors list: Agorastos Sakis <th_agorastos@hotmail.com>
*/

$require_current_course = TRUE;
$guest_allowed = true;

include '../../include/baseTheme.php';
include '../../include/lib/forcedownload.php';
include "../../include/lib/fileDisplayLib.inc.php";
include "../../include/lib/fileManageLib.inc.php";
include "../../include/lib/fileUploadLib.inc.php";

/**** The following is added for statistics purposes ***/
include('../../include/action.php');
$action = new action();
$action->record('MODULE_ID_DOCS');
/**************************************/

$tool_content = "";
$nameTools = $langDoc;
$dbTable = "document";

$require_help = TRUE;
$helpTopic = 'Doc';

// check for quotas
mysql_select_db($mysqlMainDb);
$currentCourseID = xss_sql_filter($currentCourseID); // MY CODE
$d = mysql_fetch_row(mysql_query("SELECT doc_quota FROM cours WHERE code='$currentCourseID'"));
$diskQuotaDocument = $d[0];
mysql_select_db($currentCourseID);

$basedir = $webDir . 'courses/' . $currentCourseID . '/document';

// -------------------------
// download action2
// --------------------------
if (@$action2=="download")
{
	$real_file = $basedir . $id;
    $id = xss_sql_filter($id); // MY CODE
	if (strpos($real_file, '/../') === FALSE) {
		//fortwma tou pragmatikou onomatos tou arxeiou pou vrisketai apothikevmeno sth vash
		$result = mysql_query ("SELECT filename FROM document WHERE path LIKE '%$id%'");
		$row = mysql_fetch_array($result);
		if (!empty($row['filename']))
		{
			$id = $row['filename'];
		}
		send_file_to_client($real_file, my_basename($id));
		exit;
	} else {
		header("Refresh: ${urlServer}modules/document/document.php");
	}
}


if($is_adminOfCourse)  {
	if (@$uncompress == 1)
		include("../../include/pclzip/pclzip.lib.php");
}

// file manager basic variables definition
$diskUsed = dir_total_space($basedir);
$local_head = '
<script type="text/javascript">
function confirmation (name)
{
    if (confirm("'.$langConfirmDelete.'" + name))
        {return true;}
    else
        {return false;}
}
</script>
';


// Actions to do before extracting file from zip archive
// Create database entries and set extracted file path to
// a new safe filename
function process_extracted_file($p_event, &$p_header) {

        global $file_comment, $file_category, $file_creator, $file_date, $file_subject,
               $file_title, $file_description, $file_author, $file_language,
               $file_copyrighted, $uploadPath, $realFileSize, $basedir;

        $realFileSize += $p_header['size'];
        $stored_filename = $p_header['stored_filename'];
        if (invalid_utf8($stored_filename)) {
                $stored_filename = cp737_to_utf8($stored_filename);
        }
        $path_components = explode('/', $stored_filename);
        $filename = array_pop($path_components);
        $file_date = date("Y\-m\-d G\:i\:s", $p_header['mtime']);
        $path = make_path($uploadPath, $path_components);
        if ($p_header['folder']) {
                // Directory has been created by make_path(),
                // no need to do anything else
                return 0;
        } else {
                $format = get_file_extension($filename);
                $path .= '/' . xss_sql_filter(safe_filename($format)); // MY CODE

                /* MY CODE BEGIN */
                $filename = xss_sql_filter($filename);
                $file_comment = xss_sql_filter($file_comment);
                $file_category = xss_sql_filter($file_category);
                $file_title = xss_sql_filter($file_title);
                $file_creator = xss_sql_filter($file_creator);
                $file_date = xss_sql_filter($file_date);
                $file_subject = xss_sql_filter($file_subject);
                $file_description = xss_sql_filter($file_description);
                $file_author = xss_sql_filter($file_author);
                $file_language = xss_sql_filter($file_language);
                $file_copyrighted = xss_sql_filter($file_copyrighted);
                $format = xss_sql_filter($format);
                /* END */
                db_query("INSERT INTO document SET
                                 path = '$path',
                                 filename = " . justQuote($filename) .",
                                 visibility = 'v',
                                 comment = " . justQuote($file_comment) . ",
                                 category = " . justQuote($file_category) . ",
                                 title = " . justQuote($file_title) . ",
                                 creator = " . justQuote($file_creator) . ",
                                 date = " . justQuote($file_date) . ",
                                 date_modified = " . justQuote($file_date) . ",
                                 subject = " . justQuote($file_subject) . ",
                                 description = " . justQuote($file_description) . ",
                                 author = " . justQuote($file_author) . ",
                                 format = '$format',
                                 language = " . justQuote($file_language) . ",
                                 copyrighted = " . justQuote($file_copyrighted));
                // File will be extracted with new encoded filename
                $p_header['filename'] = $basedir . $path;
                return 1;
        }
}


// Create a path with directory names given in array $path_components
// under base path $path, inserting the appropriate entries in
// document table.
// Returns the full encoded path created.
function make_path($path, $path_components)
{
        global $basedir, $nom, $prenom, $path_already_exists;

        $path_already_exists = true;
        $depth = 1 + substr_count($path, '/');
        /* MY CODE BEGINS */
        $depth = intval($depth);
        $path = xss_sql_filter($path);
        $nom = xss_sql_filter($nom);
        $prenom = xss_sql_filter($prenom);
        /* END */
        foreach ($path_components as $component) {
                $component = xss_sql_filter($component);
                $q = db_query("SELECT path, visibility, format,
                                (LENGTH(path) - LENGTH(REPLACE(path, '/', ''))) AS depth
                                FROM document WHERE filename = " . justQuote($component) .
                                " AND path LIKE '$path%' HAVING depth = $depth");
                if (mysql_num_rows($q) > 0) {
                        // Path component already exists in database
                        $r = mysql_fetch_array($q);
                        $path = $r['path'];
                        $depth++;
                } else {
                        // Path component must be created
                        $path .= '/' . safe_filename();
                        mkdir($basedir . $path, 0775);
                        db_query("INSERT INTO document SET

    				  path='$path',
                                  filename=" . justQuote($component) . ",
    				  visibility='v',
                                  creator=" . justQuote($prenom." ".$nom) . ",
                                  date=NOW(),
                                  date_modified=NOW(),
                                  format='.dir'");
                        $path_already_exists = false;
                }
        }
        return $path;
}

/*** clean information submited by the user from antislash ***/
stripSubmitValue($_POST);
stripSubmitValue($_GET);
/*****************************************************************************/

if($is_adminOfCourse)
{       // teacher only

	/*********************************************************************
	UPLOAD FILE
	//ousiastika dhmiourgei ena safe_fileName xrhsimopoiwntas ta DATETIME wste na mhn dhmiourgeitai
	//provlhma sto filesystem apo to onoma tou arxeiou. Parola afta to palio filename pernaei apo
	//'filtrarisma' wste na apofefxthoun 'epikyndynoi' xarakthres.
	//gia pardeigma me $fileName = "test.jpg" sto filesystem grafetai arxeio
	$safe_fileName = "20060301121510sdjklhsd.jpg"
	***********************************************************************/

	$dialogBox = '';
	if (is_uploaded_file(@$userFile)) {
		// check for disk quotas
		$diskUsed = dir_total_space($basedir);
		if ($diskUsed + @$_FILES['userFile']['size'] > $diskQuotaDocument) {
			$dialogBox .= "<p class='caution_small'>$langNoSpace</p>";
		} else {
                        // check for dangerous extensions and file types
                        if (preg_match('/\.(ade|adp|bas|bat|chm|cmd|com|cpl|crt|exe|hlp|hta|' .
                        'inf|ins|isp|jse|lnk|mdb|mde|msc|msi|msp|mst|pcd|pif|reg|scr|sct|shs|' .
                        'shb|url|vbe|vbs|wsc|wsf|wsh)$/', $_FILES['userFile']['name'])) {
                                $dialogBox .= "$langUnwantedFiletype: {$_FILES['userFile']['name']}";
                        }
                        /*** Unzipping stage ***/
                        elseif (isset($uncompress) and $uncompress == 1
                                and preg_match('/\.zip$/i', $_FILES['userFile']['name'])) {
                                $zipFile = new pclZip($userFile);
                                $realFileSize = 0;
                                $zipFile->extract(PCLZIP_CB_PRE_EXTRACT, 'process_extracted_file');
                                if ($diskUsed + $realFileSize > $diskQuotaDocument) {
                                        $dialogBox .= $langNoSpace;
                                } else {
                                        $dialogBox .= "<p class='success_small'>$langDownloadAndZipEnd</p><br />";
                                }
                        }
                        else
                        {
                        $fileName = trim ($_FILES['userFile']['name']);
                        //elegxos ean to "path" tou arxeiou pros upload vrisketai hdh se eggrafh ston pinaka documents
                        //(aftos einai ousiastika o elegxos if_exists dedomenou tou oti to onoma tou arxeiou sto filesystem einai monadiko)
                        $result = mysql_query ("SELECT filename FROM document WHERE filename LIKE '%$uploadPath/$fileName%'");
                        $row = mysql_fetch_array($result);
                                if (!empty($row['filename']))
                                {
                                        //to arxeio yparxei hdh se eggrafh ston pinaka document ths vashs
                                        $dialogBox .= "<b>$langFileExists !</b>";
                                } else //to arxeio den vrethike sth vash ara mporoume na proxwrhsoume me to upload
                                {
                                        /**** Check for no desired characters ***/
                                        $fileName = replace_dangerous_char($fileName);
                                        /*** Try to add an extension to files witout extension ***/
                                        $fileName = add_ext_on_mime($fileName);
                                        /*** Handle PHP files ***/
                                        $fileName = php2phps($fileName);
                                        //ypologismos onomatos arxeiou me date + time.
                                        //to onoma afto tha xrhsimopoiei sto filesystem & tha apothikevetai ston pinaka documents
                                        $safe_fileName = safe_filename(get_file_extension($fileName));
                                        //prosthiki eggrafhs kai metadedomenwn gia to eggrafo sth vash
                                        if ($uploadPath == ".")
                                                $uploadPath2 = "/".$safe_fileName;
                                        else
                                                $uploadPath2 = $uploadPath."/".$safe_fileName;
                                        //san file format vres to extension tou arxeiou
                                        $file_format = get_file_extension($fileName);
                                        //san date you arxeiou xrhsimopoihse thn shmerinh hm/nia
                                        $file_date = xss_sql_filter(date("Y\-m\-d G\:i\:s")); // MY CODE

                                        /* MY CODE BEGINS */
                                        $fileName = xss_sql_filter($fileName);
                                        $file_comment = xss_sql_filter($file_comment);
                                        $file_category = xss_sql_filter($file_category);
                                        $file_title = xss_sql_filter($file_title);
                                        $file_creator = xss_sql_filter($file_creator);
                                        $file_date = xss_sql_filter($file_date);
                                        $file_subject = xss_sql_filter($file_subject);
                                        $file_description = xss_sql_filter($file_description);
                                        $file_author = xss_sql_filter($file_author);
                                        $file_format = xss_sql_filter($file_format);
                                        $file_language = xss_sql_filter($file_language);
                                        $file_copyrighted = xss_sql_filter($file_copyrighted);

                                        /* END */
                                        $query = "INSERT INTO ".$dbTable." SET
                                        path	=	".justQuote($uploadPath2).",
                                        filename =	".justQuote($fileName).",
                                        visibility =	'v',
                                        comment	=	".justQuote($file_comment).",
                                        category =	".justQuote($file_category).",
                                        title =	".justQuote($file_title).",
                                        creator	=	".justQuote($file_creator).",
                                        date	= ".justQuote($file_date).",
                                        date_modified	=	".justQuote($file_date).",
                                        subject	=	".justQuote($file_subject).",
                                        description =	".justQuote($file_description).",
                                        author	=	".justQuote($file_author).",
                                        format	=	".justQuote($file_format).",
                                        language =	".justQuote($file_language).",
                                        copyrighted	=	".justQuote($file_copyrighted);

                                        db_query($query, $currentCourseID);

                                        /*** Copy the file to the desired destination ***/
                                        copy ($userFile, $basedir.$uploadPath."/".$safe_fileName);
                                        @$dialogBox .= "<p class='success_small'>$langDownloadEnd</p><br />";
                                } // end else tou if(!empty($row['filename']))
                        }
                }
	} // end if is_uploaded_file

	/**************************************
	MOVE FILE OR DIRECTORY
	**************************************/
	/*-------------------------------------
	MOVE FILE OR DIRECTORY : STEP 2
	--------------------------------------*/
	if (isset($moveTo))
	{
		//elegxos ean source kai destintation einai to idio
		if($basedir . $source != $basedir . $moveTo || $basedir . $source != $basedir . $moveTo)
		{
			if (move($basedir . $source, $basedir . $moveTo)) {
				update_db_info('document', 'update', $source, $moveTo.'/'.my_basename($source));
				$dialogBox = "<p class='success_small'>$langDirMv</p><br />";
			}
			else
			{
				$dialogBox = "<p class='caution_small'>$langImpossible</p><br />";
				/*** return to step 1 ***/
				$move = $source;
				unset ($moveTo);
			}
		}
	}

	/*-------------------------------------
	MOVE FILE OR DIRECTORY : STEP 1
	--------------------------------------*/
	if (isset($move))
	{
        $move = xss_sql_filter($move); // MY CODE
		//h $move periexei to onoma tou arxeiou. anazhthsh onomatos arxeiou sth vash
		$result = mysql_query("SELECT * FROM $dbTable WHERE path=" . justQuote($move));
		$res = mysql_fetch_array($result);
		$moveFileNameAlias = $res['filename'];
		@$dialogBox .= form_dir_list_exclude($dbTable, "source", $move, "moveTo", $basedir, $move);
	}

	/**************************************
	DELETE FILE OR DIRECTORY
	**************************************/
	if (isset($delete)) {
                update_db_info("document", "delete", $delete);
		if (my_delete($basedir . $delete)) {
			$dialogBox = "<p class='success_small'>$langDocDeleted</p><br />";
		}
	}

	/*****************************************
	RENAME
	******************************************/
	// step 2
	//nea methodos metonomasias arxeiwn kanontas update sthn eggrafh pou yparxei sth vash
	if (isset($renameTo2)) {
        $renameTo2 = xss_sql_filter(canonicalize_whitespace($renameTo2)); // MY CODE
		$query =  "UPDATE $dbTable SET filename=" .
                        justQuote($renameTo2) .
                        " WHERE path='$sourceFile'";
		db_query($query);
		$dialogBox = "<p class='caution_small'>$langElRen</p><br />";
	}

	//	rename
	if (isset($rename))
	{
        $rename = xss_sql_filter($rename); // MY CODE
		//elegxos gia to ean yparxei hdh eggrafh sth vash
		$result = mysql_query("SELECT * FROM $dbTable WHERE path='$rename'");
		$res = mysql_fetch_array($result);
		//yparxei eggrafh sth vash gia to arxeio opote xrhsimopoihse thn nea methodo metonomasias (ginetai sto STEP 2)
		$fileName = $res["filename"];
		@$dialogBox .= "<form>\n";
		$dialogBox .= "<input type='hidden' name='sourceFile' value='$rename' />
        	<table class='FormData' width='99%'><tbody><tr>
          	<th class='left' width='200'>$langRename:</th>
          	<td class='left'>$langRename ".htmlspecialchars($fileName)." $langIn: <input type='text' name='renameTo2' value='$fileName' class='FormData_InputText' size='50' /></td>
          	<td class='left' width='1'><input type='submit' value='$langRename' /></td>
        	</tr></tbody></table></form><br />";
	}

	// create directory
	// step 2: create the new directory
	if (isset($newDirPath)) {
                $newDirName = canonicalize_whitespace($newDirName);
                if (!empty($newDirName)) {
                        make_path($newDirPath, array($newDirName));
                        // $path_already_exists: global variable set by make_path()
                        if ($path_already_exists) {
                                $dialogBox = "<p class='caution_small'>$langFileExists</p>";
                        } else {
                                $dialogBox = "<p class='success_small'>$langDirCr</p>";
                        }
                }
	}

	// step 1: display a field to enter the new dir name
	if (isset($createDir))
	{
		$dialogBox .= "<form>\n";
		$dialogBox .= "<input type='hidden' name='newDirPath' value='$createDir' />\n";
		$dialogBox .= "<table class='FormData' width='99%'>
        	<tbody><tr><th class='left' width='200'>$langNameDir:</th>
          	<td class='left' width='1'><input type='text' name='newDirName' class='FormData_InputText' /></td>
          	<td class='left'><input type='submit' value='$langCreateDir' /></td>
  		</tr></tbody></table></form><br />";
	}

	//	add/update/remove comment
	//	h $commentPath periexei to path tou arxeiou gia to opoio tha epikyrothoun ta metadata
	if (isset($edit_metadata))
	{
        $commentPath = xss_sql_filter($commentPath); // MY CODE
		//elegxos ean yparxei eggrafh sth vash gia to arxeio
		$result = mysql_query ("SELECT * FROM $dbTable WHERE path=" . justQuote($commentPath));
		$res = mysql_fetch_array($result);
		if(!empty($res))
		{
			//elegxos ean o xrhsths epelekse diaforetikh glwssa h' tipota (option -> "")
			if (empty($file_language)) $file_language = $file_oldLanguage; // MY CODE below
			$query =  "UPDATE ".$dbTable." SET
    				comment=\"".xss_sql_filter($file_comment)."\",
				category=\"".xss_sql_filter($file_category)."\",
  	 			title=\"".xss_sql_filter($file_title)."\",
				date_modified=\"".date("Y\-m\-d G\:i\:s")."\",
    				subject=\"".xss_sql_filter($file_subject)."\",
    				description=\"".xss_sql_filter($file_description)."\",
    				author=\"".xss_sql_filter($file_author)."\",
    				language=\"".xss_sql_filter($file_language)."\",
    				copyrighted=\"".xss_sql_filter($file_copyrighted)."\"
    				  WHERE path=\"".xss_sql_filter($commentPath)."\"";
		} else
		//den yparxei eggrafh sth vash gia to sygkekrimeno arxeio opote dhmiourghse thn eggrafh
		{
			if (empty($file_language)) $file_language = xss_sql_filter($file_oldLanguage); // MY CODE
			if (empty($file_filename)) $file_filename = xss_sql_filter($fileName); // MY CODE
			$file_format = get_file_extension($file_filename);
			$query =  "INSERT INTO ".$dbTable." SET
    			path=\"".$commentPath."\",
    			filename=\"".$file_filename."\",
    			visibility=\"v\",
				comment=\"".xss_sql_filter($file_comment)."\",
				category=\"".xss_sql_filter($file_category)."\",
				title=\"".xss_sql_filter($file_title)."\",
				creator=\"".xss_sql_filter($prenom)." ".xss_sql_filter($nom)."\",
				date=\"".date("Y\-m\-d G\:i\:s")."\",
				date_modified=\"".date("Y\-m\-d G\:i\:s")."\",
				subject=\"".xss_sql_filter($file_subject)."\",
				description=\"".xss_sql_filter($file_description)."\",
				author=\"".xss_sql_filter($file_author)."\",
				format=\"".xss_sql_filter($file_format)."\",
				language=\"".xss_sql_filter($file_language)."\",
				copyrighted=\"".xss_sql_filter($file_copyrighted)."\"";
		}
		mysql_query($query);
	}

	//emfanish ths formas gia tropopoihsh comment
	//edw tha valoume kai ta epipleon pedia gia ta metadedomena
	if (isset($comment))
	{
		$oldComment='';
        $comment = xss_sql_filter($comment); // MY CODE
		/*** Retrieve the old comment and metadata ***/
		$query = "SELECT * FROM $dbTable WHERE path LIKE '%".$comment."%'";
		$result = mysql_query ($query);
		$row = mysql_fetch_array($result);
		$oldFilename = $row['filename'];
		$oldComment = $row['comment'];
		$oldCategory = $row['category'];
		$oldTitle = $row['title'];
		$oldCreator = $row['creator'];
		$oldDate = $row['date'];
		$oldSubject = $row['subject'];
		$oldDescription = $row['description'];
		$oldAuthor = $row['author'];
		$oldLanguage = $row['language'];
		$oldCopyrighted = $row['copyrighted'];

		//filsystem compability: ean gia to arxeio den yparxoun dedomena sto pedio filename
		//(ara to arxeio den exei safe_filename (=alfarithmitiko onoma)) xrhsimopoihse to
		//$fileName gia thn provolh tou onomatos arxeiou
		$fileName = my_basename($comment);
		if (empty($oldFilename)) $oldFilename = $fileName;
		$dialogBox .="	<form method=\"post\" action=\"$_SERVER[PHP_SELF]?edit_metadata\">
        		<input type='hidden' name='commentPath' value='$comment' />
        		<input type='hidden' size='80' name='file_filename' value='$oldFilename' />
        		<table  class='FormData' width=\"99%\">
        		<tbody><tr><th>&nbsp;</th>
        		<td><b>$langAddComment: </b>".htmlspecialchars($oldFilename)."</td>
        		</tr><tr>
        		<th class='left'>$langComment:</th>
        		<td><input type='text' size='60' name='file_comment' value='$oldComment' class='FormData_InputText' /></td>
        		</tr><tr>
        		<th class='left'>$langTitle:</th>
        		<td><input type='text' size='60' name='file_title' value='$oldTitle' class='FormData_InputText' /></td>
        		</tr>
        		<tr><th class='left'>$langCategory:</th><td>";
		//ektypwsh tou combobox gia thn epilogh kathgorias tou eggrafou
		$dialogBox .= "<select name='file_category' class='auth_input'>
			<option"; if($oldCategory=="0") $dialogBox .= " selected=\"selected\""; $dialogBox .= " value=\"0\">$langCategoryOther<br>";
		$dialogBox .= "	<option";
		if($oldCategory=="1") $dialogBox .= " selected=\"selected\""; $dialogBox .= " value=\"1\">$langCategoryExcercise<br>
		<option"; if($oldCategory=="1") $dialogBox .= " selected=\"selected\""; $dialogBox .= " value=\"2\">$langCategoryLecture<br>
		<option"; if($oldCategory=="2") $dialogBox .= " selected=\"selected\""; $dialogBox .= " value=\"3\">$langCategoryEssay<br>
		<option"; if($oldCategory=="3") $dialogBox .= " selected=\"selected\""; $dialogBox .= " value=\"4\">$langCategoryDescription<br>
		<option"; if($oldCategory=="4") $dialogBox .= " selected=\"selected\""; $dialogBox .= " value=\"5\">$langCategoryExample<br>
		<option"; if($oldCategory=="5") $dialogBox .= " selected=\"selected\""; $dialogBox .= " value=\"6\">$langCategoryTheory<br>
		</select></td></tr>";
		$dialogBox .= "<input type='hidden' size='80' name='file_creator' value='$oldCreator' />
    			<input type='hidden' size='80' name='file_date' value='$oldDate' />
    			<tr><th class='left'>$langSubject : </th><td>
			<input type='text' size='60' name='file_subject' value='$oldSubject' class='FormData_InputText' />
			</td></tr><tr><th class='left'>$langDescription : </th><td>
    			<input type='text' size='60' name='file_description' value='$oldDescription' class='FormData_InputText' /></td></tr>
    			<tr><th class='left'>$langAuthor : </th><td>
    			<input type='text' size='60' name='file_author' value='$oldAuthor' class='FormData_InputText' />
    			</td></tr>";

		$dialogBox .= "<tr><th class='left'>$langCopyrighted : </th>
			<td><input name='file_copyrighted' type='radio' value='0' ";
		if ($oldCopyrighted=="0" || empty($oldCopyrighted)) $dialogBox .= " checked='checked' "; $dialogBox .= " /> $langCopyrightedUnknown <input name='file_copyrighted' type='radio' value='2' "; if ($oldCopyrighted=="2") $dialogBox .= " checked='checked' "; $dialogBox .= " /> $langCopyrightedFree <input name='file_copyrighted' type='radio' value='1' ";

		if ($oldCopyrighted=="1") $dialogBox .= " checked='checked' "; $dialogBox .= "/> $langCopyrightedNotFree
    		</td></tr>
    		<input type='hidden' size='80' name='file_oldLanguage' value='$oldLanguage' />";
		//ektypwsh tou combox gia epilogh glwssas
		$dialogBox .= "	<tr><th class='left'>$langLanguage :</th>
    			<td><select name='file_language' class='auth_input'>
			</option><option value='en'>$langEnglish
			</option><option value='fr'>$langFrench
			</option><option value='de'>$langGerman
			</option><option value='el' selected>$langGreek
			</option><option value='it'>$langItalian
			</option><option value='es'>$langSpanish
			</option>
			</select></td></tr>
			<tr><th>&nbsp;</th>
			<td><input type='submit' value='$langOkComment' />&nbsp;&nbsp;&nbsp;$langNotRequired</td>
			</tr></tbody></table></form><br>";
	}

	// Visibility commands
	if (isset($mkVisibl) || isset($mkInvisibl))
	{
		$visibilityPath = @$mkVisibl.@$mkInvisibl; // At least one of these variables are empty

		// analoga me poia metavlhth exei timh ($mkVisibl h' $mkInvisibl) vale antistoixh
		//timh sthn $newVisibilityStatus gia na graftei sth vash
		if (isset($mkVisibl))
			$newVisibilityStatus = "v";
		else
			$newVisibilityStatus = "i";
        $visibilityPath = xss_sql_filter($visibilityPath); // MY CODE
		// enallagh ths timhs sto pedio visibility tou pinaka document
		mysql_query ("UPDATE $dbTable SET visibility='".$newVisibilityStatus."' WHERE path = '$visibilityPath'");
		$dialogBox = "<p class='success_small'>$langViMod</p><br />";
	}
} // teacher only

// Common for teachers and students
// define current directory
if (isset($openDir)  || isset($moveTo) || isset($createDir) || isset($newDirPath) || isset($uploadPath) ) // $newDirPath is from createDir command (step 2) and $uploadPath from upload command
{
	$curDirPath = @$openDir . @$createDir . @$moveTo . @$newDirPath . @$uploadPath;
}
elseif (isset($delete) || isset($move) || isset($rename) || isset($sourceFile) || isset($comment) || isset($commentPath) || isset($mkVisibl) || isset($mkInvisibl)) //$sourceFile is from rename command (step 2)
{
	$curDirPath = dirname(@$delete . @$move . @$rename . @$sourceFile . @$comment . @$commentPath . @$mkVisibl . @$mkInvisibl);
}
else
{
	$curDirPath="";
}

if ($curDirPath == '/') {
        $curDirPath = '';
}
$curDirName = my_basename($curDirPath);
$parentDir = dirname($curDirPath);

if (strpos($curDirName, '/../') !== false or
    !is_dir(realpath($basedir . $curDirPath))) {
	$tool_content .=  $langInvalidDir;
        draw($tool_content, 2, 'document');
        exit;
}

$order = 'ORDER BY filename';
$sort = 'name';
$reverse = false;
if (isset($_GET['sort'])) {
        if ($_GET['sort'] == 'type') {
                $order = 'ORDER BY format';
                $sort = 'type';
        } elseif ($_GET['sort'] == 'date') {
                $order = 'ORDER BY date_modified';
                $sort = 'date';
        }
}
if (isset($_GET['rev'])) {
        $order .= ' DESC';
        $reverse = true;
}

/*** Retrieve file info for current directory from database and disk ***/
$result = db_query("SELECT * FROM $dbTable
    	WHERE path LIKE '$curDirPath/%'
        AND path NOT LIKE '$curDirPath/%/%' $order");

$fileinfo = array();
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $fileinfo[] = array(
                'is_dir' => is_dir($basedir . $row['path']),
                'size' => filesize($basedir . $row['path']),
                'title' => $row['title'],
                'filename' => $row['filename'],
                'format' => $row['format'],
                'path' => $row['path'],
                'visible' => ($row['visibility'] == 'v'),
                'comment' => $row['comment'],
                'copyrighted' => $row['copyrighted'],
                'date' => strtotime($row['date_modified']));
}

// end of common to teachers and students

// ----------------------------------------------
// Display
// ----------------------------------------------

$dspCurDirName = htmlspecialchars($curDirName);
$cmdCurDirPath = rawurlencode($curDirPath);
$cmdParentDir  = rawurlencode($parentDir);

if($is_adminOfCourse) {
	/*----------------------------------------------------------------
	UPLOAD SECTION (ektypwnei th forma me ta stoixeia gia upload eggrafou + ola ta pedia
	gia ta metadata symfwna me Dublin Core)
	------------------------------------------------------------------*/
	$tool_content .= "\n  <div id='operations_container'>\n    <ul id='opslist'>";
	$tool_content .= "\n      <li><a href='upload.php?uploadPath=$curDirPath'>$langDownloadFile</a></li>";
	/*----------------------------------------
	Create new folder
	--------------------------------------*/
	$tool_content .= "\n      <li><a href='$_SERVER[PHP_SELF]?createDir=".$cmdCurDirPath."'>$langCreateDir</a></li>";
	$diskQuotaDocument = $diskQuotaDocument * 1024 / 1024;
	$tool_content .= "\n      <li><a href='showquota.php?diskQuotaDocument=$diskQuotaDocument&amp;diskUsed=$diskUsed'>$langQuotaBar</a></li>";
    $tool_content .= "\n    </ul>\n  </div>\n";

	// Dialog Box
	if (!empty($dialogBox))
	{
		$tool_content .=  $dialogBox . " ";
	}
}

// check if there are documents
if($is_adminOfCourse) {
	$sql = db_query("SELECT * FROM document");
} else {
	$sql = db_query("SELECT * FROM document WHERE visibility = 'v'");
}
if (mysql_num_rows($sql) == 0) {
	$tool_content .= "\n<p class='alert1'>$langNoDocuments</p>";
} else {

	// Current Directory Line
	$tool_content .= "\n<br /><div class=\"fileman\">";
	$tool_content .= "\n  <table width=\"99%\" align='left' class=\"Documents\">";
        $tool_content .= "\n  <tbody>";

        if ($is_adminOfCourse) {
                $cols = 4;
        } else {
                $cols = 3;
        }

	$tool_content .= "\n  <tr>";
        $tool_content .= "\n    <th height='18' colspan='$cols'><div align=\"left\">$langDirectory: ".make_clickable_path($dbTable, $curDirPath). "</div></th>";
        $tool_content .= "\n    <th><div align='right'>";

        // Link for sortable table headings
        function headlink($label, $this_sort)
        {
                global $sort, $reverse, $curDirPath;

                if (empty($curDirPath)) {
                        $path = '/';
                } else {
                        $path = $curDirPath;
                }
                if ($sort == $this_sort) {
                        $this_reverse = !$reverse;
                        $indicator = ' <img src="../../template/classic/img/arrow_' .
                                ($reverse? 'up': 'down') . '.gif" border="0"/>';
                } else {
                        $this_reverse = $reverse;
                        $indicator = '';
                }
                return '<a href=\'' . $_SERVER['PHP_SELF'] . '?openDir=' . $path .
                       '&amp;sort=' . $this_sort . ($this_reverse? '&amp;rev=1': '') .
                       '\'>' . $label . $indicator . '</a>';
        }

	/*** go to parent directory ***/
        if ($curDirName) // if the $curDirName is empty, we're in the root point and we can't go to a parent dir
        {
                $parentlink = $_SERVER['PHP_SELF'] . '?openDir=' . $cmdParentDir;
                $tool_content .=  "<a href='$parentlink'>$langUp</a> <a href='$parentlink'><img src='../../template/classic/img/parent.gif' height='20' width='20' /></a>";
        }
        $tool_content .= "</div></th>";
        $tool_content .= "\n  </tr>";
        $tool_content .= "\n  <tr>";
        $tool_content .= "\n    <td width='10%' class='DocHead'><div align='center'><b>" .
                         headlink($langType, 'type') . '</b></div></td>';
        $tool_content .= "\n    <td class='DocHead'><div align='left'><b>" .
                         headlink($langName, 'name') . '</b></div></td>';
        $tool_content .= "\n    <td width='15%' class='DocHead'><div align='center'><b>$langSize</b></div></td>";
        $tool_content .= "\n    <td width='15%' class='DocHead'><div align='center'><b>" .
                         headlink($langDate, 'date') . '</b></div></td>';
	if($is_adminOfCourse) {
		$tool_content .= "\n    <td width='20%' class='DocHead'><div align='center'><b>$langCommands</b></div></td>";
	}
	$tool_content .= "\n  </tr>";

        // -------------------------------------
        // Display directories first, then files
        // -------------------------------------
        foreach (array(true, false) as $is_dir) {
                foreach ($fileinfo as $entry) {
                        if (($entry['is_dir'] != $is_dir) or
                                        (!$is_adminOfCourse and !$entry['visible'])) {
                                continue;
                        }
                        $cmdDirName = $entry['path'];
                        if ($entry['visible']) {
                                $style = '';
                        } else {
                                $style = ' class="invisible"';
                        }
                        $copyright_icon = '';
                        if ($is_dir) {
                                $image = '../../template/classic/img/folder.gif';
                                $file_url = "$_SERVER[PHP_SELF]?openDir=$cmdDirName";
                                $link_extra = '';

                                $link_text = $entry['filename'];
                        } else {
                                $image = 'img/' . choose_image('.' . $entry['format']);
                                $file_url = file_url($cmdDirName, $entry['filename']);
                                $link_extra = " title='$langSave' target='_blank'";
                                if (empty($entry['title'])) {
                                        $link_text = $entry['filename'];
                                } else {
                                        $link_text = $entry['title'];
                                }
                                if ($entry['copyrighted']) {
                                        $link_text .= " <img src='./img/copyrighted.jpg' />";
                                }
                        }
                        $tool_content .= "\n  <tr$style>";
                        $tool_content .= "\n    <td width='1%' valign='top' style='padding-top: 7px;'><a href='$file_url'$style$link_extra><img src='$image' border='0' /></a></td>";
                        $tool_content .= "\n    <td><div align='left'><a href='$file_url'$style$link_extra>$link_text</a>";

                        /*** comments ***/
                        if (!empty($entry['comment'])) {
                                $tool_content .= "<br /><span class='comment'>" .
                                        nl2br(htmlspecialchars($entry['comment'])) .
                                        "</span>\n";
                        }
                        $tool_content .= "</div></td>\n";
                        if ($is_dir) {
                                // skip display of date and time for directories
                                $tool_content .= "<td>&nbsp;</td><td>&nbsp;</td>";
                        } else {
                                $size = format_file_size($entry['size']);
                                $date = format_date($entry['date']);
                                $tool_content .= "<td>$size</td><td>$date</td>";
                        }
                        if ($is_adminOfCourse) {
                                /*** delete command ***/
                                $tool_content .= "\n    <td><a href='$_SERVER[PHP_SELF]?delete=$cmdDirName' onClick=\"return confirmation('".addslashes($entry['filename'])."');\">";
                                $tool_content .= "<img src='../../template/classic/img/delete.gif' border='0' title='$langDelete' /></a>&nbsp;";
                                /*** copy command ***/
                                $tool_content .= "<a href='$_SERVER[PHP_SELF]?move=$cmdDirName'>";
                                $tool_content .= "<img src='../../template/classic/img/move_doc.gif' border='0' title='$langMove' /></a>&nbsp;";
                                /*** rename command ***/
                                $tool_content .=  "<a href='$_SERVER[PHP_SELF]?rename=$cmdDirName'>";
                                $tool_content .=  "<img src='../../template/classic/img/edit.gif' border='0' title='$langRename' /></a>&nbsp;";
                                /*** comment command ***/
                                $tool_content .= "<a href='$_SERVER[PHP_SELF]?comment=$cmdDirName'>";
                                $tool_content .= "<img src='../../template/classic/img/information.gif' border='0' title='$langComment' /></a>&nbsp;";
                                /*** visibility command ***/
                                if ($entry['visible']) {
                                        $tool_content .= "<a href='$_SERVER[PHP_SELF]?mkInvisibl=$cmdDirName'>";
                                        $tool_content .= "<img src='../../template/classic/img/visible.gif' border='0' title='$langVisible' /></a>";
                                } else {
                                        $tool_content .= "<a href='$_SERVER[PHP_SELF]?mkVisibl=$cmdDirName'>";
                                        $tool_content .= "<img src='../../template/classic/img/invisible.gif' border='0' title='$langVisible' /></a>";
                                }
                                $tool_content .= "</td>";
                                $tool_content .= "\n  </tr>";
                        }
                }
        }
        $tool_content .=  "\n  </tbody>";
        $tool_content .=  "\n  </table>";
        $tool_content .=  "\n</div>";
}
add_units_navigation(TRUE);
draw($tool_content, 2, "document", $local_head);
