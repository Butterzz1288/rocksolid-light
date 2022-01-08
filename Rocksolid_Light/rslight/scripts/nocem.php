<?php

  include "config.inc.php";
  include ("$file_newsportal");

  if(!isset($CONFIG['enable_nocem']) || $CONFIG['enable_nocem'] != true) {
    exit;
  }

  $lockfile = $lockdir . '/rslight-spoolnews.lock';
  $pid = file_get_contents($lockfile);
  if (posix_getsid($pid) === false || !is_file($lockfile)) {
    print "Starting nocem...\n";
    file_put_contents($lockfile, getmypid()); // create lockfile
  } else {
    print "nocem currently running\n";
    exit;
  }

  putenv("GNUPGHOME=".$config_dir.".gnupg");
  $webserver_group=$CONFIG['webserver_user'];
  $logfile=$logdir.'/nocem.log';
  @mkdir($spooldir."/nocem/processed",0755,'recursive');
  @mkdir($spooldir."/nocem/failed",0755,'recursive');

  $nocem_path=$spooldir."/nocem/";
  $messages=scandir($nocem_path);
  $begin="@@BEGIN NCM BODY";
  $end="@@END NCM BODY";

  foreach($messages as $message) {
    $nocem_file=$nocem_path.$message;
    if(!is_file($nocem_file)) {
      continue;
    }
    $signed_text=file_get_contents($nocem_file);
    if(verify_signature($signed_text) == 1) {
      file_put_contents($logfile, "\n".format_log_date()." ".$config_name." Bad signature  in: ".$message, FILE_APPEND);
      echo "Bad signature in: ".$message."\r\n";
      rename($nocem_file, $nocem_path."failed/".$message);
      continue; 
    } else {
      file_put_contents($logfile, "\n".format_log_date()." ".$config_name." Good signature in: ".$message, FILE_APPEND);
      echo "Good signature in: ".$message."\r\n";
    }
    $nocem_list=file($nocem_file, FILE_IGNORE_NEW_LINES);
    $start=0;
    foreach($nocem_list as $nocem_line) {
      if(strpos($nocem_line, $begin) !== false) {
        $start=1;
        continue;
      } 
      if(strpos($nocem_line, $end) !== false) {
	break;
      }
      if((isset($nocem_line[0]) && $nocem_line[0] == '<') && $start == 1) {
        $found = explode(' ', $nocem_line);
	$msgid = $found[0];
	foreach($found as $found_group) {
	  delete_message($msgid, $found_group);
	}
      }
    }  
  rename($nocem_file, $nocem_path."processed/".$message);
  }
  unlink($lockfile);
  exit;

function verify_signature($signed_text) {
  $plaintext = "";
  $res = gnupg_init();
  $info = gnupg_verify($res,$signed_text,false,$plaintext);

  if($info[0]['status'] == 0 && $info[0]['summary'] == 0) {
    return 0;
  } else {
    return 1;
  }
}

function delete_message($messageid, $group) {
  global $logfile,$config_dir,$spooldir, $CONFIG, $webserver_group;

/* Find section */
    $menulist = file($config_dir."menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($menulist as $menu) {
      if($menu[0] == '#') {
        continue;
      }
      $menuitem=explode(':', $menu);
      $glfp=fopen($config_dir.$menuitem[0]."/groups.txt", 'r');
      $section="";
      while($gl=fgets($glfp)) {
        $group_name = preg_split("/( |\t)/", $gl, 2);
        if(stripos(trim($group_name[0]), $group) !== false) {
          $config_name=$menuitem[0];
	  file_put_contents($logfile, "\n".format_log_date()." ".$config_name." FOUND: ".$messageid." IN: ".$config_name.'/'.$group, FILE_APPEND);
          break 2;
        }
      }
    }
 if($config_name) {
  $database = $spooldir.'/articles-overview.db3';
  $dbh = rslight_db_open($database);
  $query = $dbh->prepare('DELETE FROM overview WHERE msgid=:messageid');
  $query->execute(['messageid' => $messageid]);
  $dbh = null; 
 }
  if($CONFIG['article_database'] == '1') {
    $database = $spooldir.'/'.$group.'-articles.db3';
    $articles_dbh = article_db_open($database);
    $articles_query = $articles_dbh->prepare('DELETE FROM articles WHERE msgid=:messageid');
    $articles_query->execute(['messageid' => $messageid]);
    $articles_dbh = null;
  }
  $this_overview=$spooldir.'/'.$group.'-overview';
  if(false === (is_file($this_overview))) {
    return;
  }
  $out_overview=$this_overview.'.new'; 
  $overviewfp=fopen($this_overview, 'r');
  $out_overviewfp=fopen($out_overview, 'w'); 
  while($line=fgets($overviewfp)) {
    $break=explode("\t", $line);
    if($break[4] == $messageid) {
      echo "DELETING: ".$messageid." IN: ".$group." #".$break[0]."\r\n";
      file_put_contents($logfile, "\n".format_log_date()." ".$config_name." DELETING: ".$messageid." IN: ".$group." #".$break[0], FILE_APPEND);
      $grouppath = preg_replace('/\./', '/', $group);
      unlink($spooldir.'/articles/'.$grouppath.'/'.$break[0]);
      continue; 
    } else {
      fputs($out_overviewfp, $line);
    }
  }
  fclose($overviewfp);
  fclose($out_overviewfp); 
  rename($out_overview, $this_overview);
  chown($this_overview, $CONFIG['webserver_user']);
  chgrp($this_overview, $webserver_group);
  delete_message_from_overboard($config_name, $group, $messageid);
  return;
}

function delete_message_from_overboard($config_name, $group, $messageid) {
  GLOBAL $spooldir;
  $delkey = hash('crc32', serialize($messageid)); 
  $cachefile=$spooldir."/".$config_name."-overboard.dat";
  if(is_file($cachefile)) {
    $cached_overboard = unserialize(file_get_contents($cachefile));
    if(isset($cached_overboard[$delkey])) {
      unset($cached_overboard[$delkey]);
      $stats = stat($cachefile);
      file_put_contents($cachefile, serialize($cached_overboard));
      touch($cachefile, $stats[9]);
    }
  }
  $cachefile=$spooldir."/".$group."-overboard.dat";
  if(is_file($cachefile)) {
    $cached_overboard = unserialize(file_get_contents($cachefile));
    if(isset($cached_overboard[$delkey])) {
      unset($cached_overboard[$delkey]);
      $stats = stat($cachefile);
      file_put_contents($cachefile, serialize($cached_overboard));
      touch($cachefile, $stats[9]);
    }
  }
}
?>
