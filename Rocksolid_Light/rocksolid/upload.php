<?php
include "config.inc.php";
include "newsportal.php";

if(isset($_POST['username'])) {
  $name = $_POST['username'];
// Save name in cookie
  if ($setcookies==true) {
    setcookie("cookie_name",stripslashes($name),time()+(3600*24*90));
  }
} else {
  if ($setcookies) {
    if ((isset($_COOKIE["cookie_name"])) && (!isset($name))) {
      $name=$_COOKIE["cookie_name"];
    } else {
      $name = '';
    }
  }
}

include "head.inc";

if(isset($_FILES)) {
// Check auth here
    if(isset($_POST['key']) && password_verify($CONFIG['thissitekey'].$_POST['username'], $_POST['key'])) {
      if(check_bbs_auth($_POST['username'], $_POST['password'])) {
	$userdir = '/var/spool/rslight/upload/'.strtolower($_POST[username]);
	$upload_to = $userdir.'/'.$_FILES[photo][name];
	if(is_file($upload_to)) {
	  echo $_FILES[photo][name].' already exists in your folder';
	} else {
	  if(!is_dir($userdir)) {
	    mkdir($userdir);
	  }	
	  $success = move_uploaded_file($_FILES[photo][tmp_name], $upload_to);
	  if ($success) {
  	    echo 'Saved '.$_FILES[photo][name].' to your files folder';
	  } else {
  	    echo 'There was an error saving '.$_FILES[photo][name];
	  }
	}
      } else {
	echo 'Authentication Failed';
      }
    echo '<br /><br />';
    }
}

echo '<table border="0" align="center" cellpadding="0" cellspacing="1">';
echo '<form name="form1" method="post" action="upload.php" enctype="multipart/form-data">';
echo '<tr><td><strong>Please Login to Upload<br />(max size=2MB)</strong></td></tr>';
echo '<tr><td>Username:</td><td><input name="username" type="text" id="username" value="'.$name.'"></td></tr>';
echo '<tr><td>Password:</td><td><input name="password" type="password" id="password"></td></tr>';
echo '<td><input name="command" type="hidden" id="command" value="Upload" readonly="readonly"></td>';
echo '<input type="hidden" name="key" value="'.password_hash($CONFIG['thissitekey'].$name, PASSWORD_DEFAULT).'">';
echo '<tr><td><input type="file" name="photo" id="fileSelect" value="fileSelect" accept="image/*,audio/*,text/*,application/*"></td>
';
echo '<td>&nbsp;</td>';
echo '<td><input type="submit" name="Submit" value="Upload"></td>';
echo '</tr>';
echo '</form>';
echo '<tr><td><a href="files.php">Go to Files</a></td></tr>';
echo '</table>';
echo '</body></html>';
?>