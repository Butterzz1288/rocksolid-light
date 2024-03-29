<?php
  
include "config.inc.php";
include "head.inc";
include ($config_dir.'/admin.inc.php');

// Accept new config
if(($_POST['configure'] == "Save Configuration") && ($_POST['configkey'] == $admin['key'])) {
  $configfile=$config_dir.'rslight.inc.php';
  $return = "<?php\n";
  $return.="return [\n";
  foreach($_POST as $key => $value) {
    if(($key !== 'configure') && ($key !== 'configkey')) {
      $value = preg_replace('/\'/', '\\\'', $value);
      $return.='  \''.$key.'\' => \''.trim($value).'\''.",\n";
    }
  }
  $return = rtrim(rtrim($return),',');
  $return.="\n];\n";
  $return.='?>';
  rename($configfile, $configfile.'.bak');
  file_put_contents($configfile, $return);
  echo '<center>';
  echo 'New Configuration settings saved in '.$configfile.'<br />';
  echo '<a href="'.$CONFIG['default_content'].'">Home</a>';
  echo '</center>';
  $CONFIG = $_POST;
  exit(0);
}

if ((isset($_POST["password"]) && ($_POST["password"]==$admin["password"])) && ($_POST['configkey'] == $admin['key'])) { 
  include($config_dir.'/scripts/setup.inc.php');
  exit(0);
} else{ 
//Show the wrong password notice
  if($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo '<center>';
    echo '<h2>Password Incorrect</h2>';
    echo '<a href="'.$_SERVER['PHP_SELF'].'">Retry</a>&nbsp;<a href="'.$CONFIG['default_content'].'">Home</a>';
    echo '</center>';
    exit(0);
  }
  echo '<p align="left">';
  echo '<form id ="myForm" method="post"><p align="left">';
  echo 'Enter password to access configuration: ';
  echo '<input type="hidden" name="configkey" value="'.$admin['key'].'">';
  echo '<input name="password" type="password" size="25" maxlength="20"><input value="Submit" type="submit"></p>';
  echo '</form>';
 } 
?>
