<?php
/*  rslight NNTP<->HTTP Gateway
 *  Download: https://news.novabbs.com/getrslight
 *
 *  Based on Newsportal by Florian Amrhein
 *
 *  E-Mail: retroguy@novabbs.com
 *  Web: https://news.novabbs.com
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

if(file_exists("lib/types.inc.php"))
  include "lib/types.inc.php";
if(file_exists("lib/thread.inc.php"))
  include "lib/thread.inc.php";
if(file_exists("lib/message.inc.php"))
  include "lib/message.inc.php";
if(file_exists("lib/post.inc.php"))
  include "lib/post.inc.php";

$CONFIG = include($config_file);

/*
 * opens the connection to the NNTP-Server
 *
 * $server: adress of the NNTP-Server
 * $port: port of the server
 */
function nntp_open($nserver=0,$nport=0) {
  global $text_error,$CONFIG;
  global $server,$port;
  // echo "<br>NNTP OPEN<br>";
  if(!isset($CONFIG['enable_nntp']) || $CONFIG['enable_nntp'] != true) {
    $CONFIG['server_auth_user'] = $CONFIG['remote_auth_user'];
    $CONFIG['server_auth_pass'] = $CONFIG['remote_auth_pass'];
  } 
  $authorize=((isset($CONFIG['server_auth_user'])) && (isset($CONFIG['server_auth_pass'])) &&
              ($CONFIG['server_auth_user'] != ""));
  if ($nserver==0) $nserver=$server;
  if ($nport==0) $nport=$port;
  $ns=@fsockopen($nserver,$nport);
  $weg=line_read($ns);  // kill the first line
  if (substr($weg,0,2) != "20") {
    echo "<p>".$text_error["error:"].$weg."</p>";
    fclose($ns);
    $ns=false;
  } else {
    if ($ns != false) {
      fputs($ns,"MODE reader\r\n");
      $weg=line_read($ns);  // and once more
      if ((substr($weg,0,2) != "20") && 
          ((!$authorize) || ((substr($weg,0,3) != "480") && ($authorize)))) {
        echo "<p>".$text_error["error:"].$weg."</p>";
        fclose($ns);
        $ns=false;
      }
    }
    if ((isset($CONFIG['server_auth_user'])) && (isset($CONFIG['server_auth_pass'])) &&
        ($CONFIG['server_auth_user'] != "")) {
      fputs($ns,"AUTHINFO USER ".$CONFIG['server_auth_user']."\r\n");
      $weg=line_read($ns);
      fputs($ns,"AUTHINFO PASS ".$CONFIG['server_auth_pass']."\r\n"); 
      $weg=line_read($ns);
/* Only check auth if reading and posting same server */
      if (substr($weg,0,3) != "281" && !(isset($post_server)) && ($post_server!="")) {
        echo "<p>".$text_error["error:"]."</p>";
        echo "<p>".$text_error["auth_error"]."</p>";
      }
    }
  }
  if ($ns==false) echo "<p>".$text_error["connection_failed"]."</p>";
  return $ns;
}

function nntp2_open($nserver=0,$nport=0) {
  global $text_error,$CONFIG;
  // echo "<br>NNTP OPEN<br>";
  $authorize=((isset($CONFIG['remote_auth_user'])) && (isset($CONFIG['remote_auth_pass'])) &&
              ($CONFIG['remote_auth_user'] != ""));
  if ($nserver==0) $nserver=$CONFIG['remote_server'];
  if ($nport==0) $nport=$CONFIG['remote_port'];
  if($CONFIG['remote_ssl']) {
    $ns=@fsockopen('ssl://'.$nserver.":".$nport);
  } else {
    if(isset($CONFIG['socks_host']) && $CONFIG['socks_host'] !== '') {
        $ns=fsocks4asockopen($CONFIG['socks_host'], $CONFIG['socks_port'], $nserver, $nport);
    } else {
        $ns=@fsockopen('tcp://'.$nserver.":".$nport);
    }
  }
//  $ns=@fsockopen($nserver,$nport);
  $weg=line_read($ns);  // kill the first line
  if (substr($weg,0,2) != "20") {
    echo "<p>".$text_error["error:"].$weg."</p>";
    fclose($ns);
    $ns=false;
  } else {
    if ($ns != false) {
      fputs($ns,"MODE reader\r\n");
      $weg=line_read($ns);  // and once more
      if ((substr($weg,0,2) != "20") &&
          ((!$authorize) || ((substr($weg,0,3) != "480") && ($authorize)))) {
        echo "<p>".$text_error["error:"].$weg."</p>";
        fclose($ns);
        $ns=false;
      }
    }
    if ((isset($CONFIG['remote_auth_user'])) && (isset($CONFIG['remote_auth_pass'])) &&
        ($CONFIG['remote_auth_user'] != "")) {
      fputs($ns,"AUTHINFO USER ".$CONFIG['remote_auth_user']."\r\n");
      $weg=line_read($ns);
      fputs($ns,"AUTHINFO PASS ".$CONFIG['remote_auth_pass']."\r\n");
      $weg=line_read($ns);
/* Only check auth if reading and posting same server */
      if (substr($weg,0,3) != "281" && !(isset($post_server)) && ($post_server!="")) {
        echo "<p>".$text_error["error:"]."</p>";
        echo "<p>".$text_error["auth_error"]."</p>";
      }
    }
  }
  if ($ns==false) echo "<p>".$text_error["connection_failed"]."</p>";
  return $ns;
}

function fsocks4asockopen($proxyHostname, $proxyPort, $targetHostname, $targetPort)
{
    $sock = fsockopen($proxyHostname, $proxyPort);
    if($sock === false)
        return false;
    fwrite($sock, pack("CCnCCCCC", 0x04, 0x01, $targetPort, 0x00, 0x00, 0x00, 0x01, 0x00).$targetHostname.pack("C", 0x00));
    $response = fread($sock, 16);
    $values = unpack("xnull/Cret/nport/Nip", $response);
    if($values["ret"] == 0x5a) return $sock;
    else
    {
        fclose($sock);
        return false;
    }
}

/*
 * Close a NNTP connection
 *
 * $ns: the handle of the connection
 */
function nntp_close(&$ns) {
  if ($ns != false) {
    fputs($ns,"QUIT\r\n");
    fclose($ns);
  }
}

/*
 * Validates an email adress
 *
 * $address: a string containing the email-address to be validated
 *
 * returns true if the address passes the tests, false otherwise.
 */
function validate_email($address)
{
  global $validate_email;
  $return=true;
  if (($validate_email >= 1) && ($return == true))
/* Need to clean up this regex to work properly with preg_match
    $return = (preg_match('^[-!#$%&\'*+\\./0-9=?A-Z^_A-z{|}~]+'.'@'.
               '[-!#$%&\'*+\\/0-9=?A-Z^_A-z{|}~]+\.'.
               '[-!#$%&\'*+\\./0-9=?A-Z^_A-z{|}~]+$',$address));
*/
    $return = 1;
  if (($validate_email >= 2) && ($return == true)) {
    $addressarray=address_decode($address,"garantiertungueltig");
    $return=checkdnsrr($addressarray[0]["host"],"MX");
    if (!$return) $return=checkdnsrr($addressarray[0]["host"],"A");
  }
  return($return);
}

/*
 * decodes a block of 7bit-data in uuencoded format to it's original
 * 8bit format.
 * The headerline containing filename and permissions doesn't have to
 * be included.
 * 
 * $data: The uuencoded data as a string
 *
 * returns the 8bit data as a string
 *
 * Note: this function is very slow and doesn't recognize incorrect code.
 */
function uudecode_line($line) {
  $data=substr($line,1);
  $length=ord($line[0])-32;
  $decoded="";
  for ($i=0; $i<(strlen($data)>>2); $i++) {
    $pack=substr($data,$i<<2,4);
    $upack="";
    $bitmaske=0;
    for ($o=0; $o<4; $o++) {
      $g=((ord($pack[3-$o])-32));
      if ($g==64) $g=0;
      $bitmaske=$bitmaske | ($g << (6*$o));
    }
    $schablone=255;
    for ($o=0; $o<3; $o++) {
      $c=($bitmaske & $schablone) >> ($o << 3);
      $schablone=($schablone << 8);
      $upack=chr($c).$upack;
    }
    $decoded.=$upack;
  }
  $decoded=substr($decoded,0,$length);
  return $decoded;
}

/*
 * decodes uuencoded Attachments.
 *
 * $data: the encoded data
 *
 * returns the decoded data
 */
function uudecode($data) {
  $d=explode("\n",$data);
  $u="";
  for ($i=0; $i<count($d)-1; $i++)
    $u.=uudecode_line($d[$i]);
  return $u;
}

/*
 * returns the mimetype of an filename
 *
 * $name: the complete filename of a file
 *
 * returns a string containing the mimetype
 */
function get_mimetype_by_filename($name) {
  $ending=strtolower(strrchr($name,"."));
  switch($ending) {
    case ".jpg":
    case ".jpeg":
      $type="image/jpeg";
      break;
    case ".gif":
      $type="image/gif";
      break;
    case ".png":
      $type="image/png";
      break;
    case ".bmp":
      $type="image/bmp";
      break;
    default:
      $type="text/plain";
  }
  return $type;
}

function get_mimetype_by_string($filedata) {
  if(function_exists('finfo_open')) {
    $f = finfo_open();
    return finfo_buffer($f, $filedata, FILEINFO_MIME_TYPE);
  } else {
    return false;
  }
}

/*
 * Test, if the access to a group is allowed. This is true, if $testgroup is
 * false or the groupname is in groups.txt
 *
 * $groupname: name of the group to be checked
 *
 * returns true, if access is allowed
 */
function testGroup($groupname) {
  global $CONFIG,$testgroup,$file_groups,$config_dir;
  $groupname=strtolower($groupname);
  if ($testgroup) {
    $gf=fopen($file_groups,"r");
    while (!feof($gf)) {
      $read=trim(line_read($gf));
      $read=preg_replace('/\t/', ' ', $read);
      $read=strtolower($read);
      $pos=strpos($read," ");
      if ($pos != false) {
        if (substr($read,0,$pos)==trim($groupname)) return true;
      } else {
        if ($read == trim($groupname)) return true;
      }
    }
    fclose($gf);
    if($groupname == $CONFIG['spamgroup']) {
	return true;
    } else {
/* Find section */
    $menulist = file($config_dir."menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($menulist as $menu) {
      if($menu[0] == '#') {
        continue;
      }
      $menuitem=explode(':', $menu);
      if($menuitem[1] == '0') {
	continue;
      }
      $glfp=fopen($config_dir.$menuitem[0]."/groups.txt", 'r');
      $section="";
      while($gl=fgets($glfp)) {
        $group_name = preg_split("/( |\t)/", $gl, 2);
        if(stripos(trim($groupname), trim($group_name[0])) !== false) {
	  fclose($glfp);
	  return true;
        }
      }
    }
    fclose($glfp);
    return false;
    }
  } else {
    return true;
  }
}

function get_section_by_group($groupname) {
    global $CONFIG, $config_dir;
    $menulist = file($config_dir."menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($menulist as $menu) {
      if($menu[0] == '#') {
        continue;
      }
      $menuitem=explode(':', $menu);
      if($menuitem[1] == '0') {
        continue;
      }
      $section = "";
      $glfp=fopen($config_dir.$menuitem[0]."/groups.txt", 'r');
      while($gl=fgets($glfp)) {
        $group_name = preg_split("/( |\t)/", $gl, 2);
        if(stripos(trim($groupname), trim($group_name[0])) !== false) {
          fclose($glfp);
	  $section=$menuitem[0];
	  fclose($glfp);
	  return $section;
        }
      }
    }
    fclose($glfp);
    return false;
}

function testGroups($newsgroups) {
  $groups=explode(",",$newsgroups);
  $count=count($groups);
  $return="";
  $o=0;
  for ($i=0; $i<$count; $i++) {
    if (testgroup($groups[$i]) &&
        (!function_exists("npreg_group_has_write_access") || 
         npreg_group_has_write_access($groups[$i]))) {
      if ($o>0) $return.=",";
      $o++;
      $return.=$groups[$i];
    }
  }
  return($return);
}

/*
 * read one line from the NNTP-server
 */
function line_read(&$ns) {
  if ($ns != false) {
    $t=str_replace("\n","",str_replace("\r","",fgets($ns,1200)));
    return $t;
  }
}

/*
 * Split an internet-address string into its parts. An address string could
 * be for example:
 * - user@host.domain (Realname)
 * - "Realname" <user@host.domain>
 * - user@host.domain
 *
 * The address will be split into user, host (incl. domain) and realname
 *
 * $adrstring: The string containing the address in internet format
 * $defaulthost: The name of the host which should be returned if the
 *               address-string doesn't contain a hostname.
 *
 * returns an hash containing the fields "mailbox", "host" and "personal"
 */
function address_decode($adrstring,$defaulthost) {
  $parsestring=trim($adrstring);
  $len=strlen($parsestring);
  $at_pos=strpos($parsestring,'@');     // find @
  $ka_pos=strpos($parsestring,"(");     // find (
  $kz_pos=strpos($parsestring,')');     // find )
  $ha_pos=strpos($parsestring,'<');     // find <
  $hz_pos=strpos($parsestring,'>');     // find >
  $space_pos=strpos($parsestring,')');  // find ' '
  $email="";
  $mailbox="";
  $host="";
  $personal="";
  if ($space_pos != false) {
    if (($ka_pos != false) && ($kz_pos != false)) {
      $personal=substr($parsestring,$ka_pos+1,$kz_pos-$ka_pos-1);
      $email=trim(substr($parsestring,0,$ka_pos-1));
    }
  } else {
    $email=$adrstring;
  }
  if (($ha_pos != false) && ($hz_pos != false)) {
    $email=trim(substr($parsestring,$ha_pos+1,$hz_pos-$ha_pos-1));
    $personal=substr($parsestring,0,$ha_pos-1);
  }
  if ($at_pos != false) {
    $mailbox=substr($email,0,strpos($email,'@'));
    $host=substr($email,strpos($email,'@')+1);
  } else {
    $mailbox=$email;
    $host=$defaulthost;
  }
  $personal=trim($personal);
  if (substr($personal,0,1) == '"') $personal=substr($personal,1);
  if (substr($personal,strlen($personal)-1,1) == '"')
    $personal=substr($personal,0,strlen($personal)-1);
  $result["mailbox"]=trim($mailbox);
  $result["host"]=trim($host);
  if ($personal!="") $result["personal"]=$personal;
  $complete[]=$result;
  return ($complete);
}

/*
 * Read the groupnames from groups.txt, and get additional informations
 * of the groups from the newsserver
 */
function groups_read($server,$port,$load=0) {
  global $gl_age,$file_groups,$spooldir,$config_name,$cache_index;
  // is there a cached version, and is it actual enough?
  $cachefile=$spooldir.'/'.$config_name.'-groups.dat';
  // if cache is new enough, don't recreate it
  clearstatcache(TRUE, $cachefile);
  if($load == 1 && file_exists($cachefile) && (filemtime($cachefile)+$cache_index>time())) {
    return;
  }
  if(file_exists($cachefile) && $load == 0) {
    // cached file exists and is new enough, so lets read it out.
    $file=fopen($cachefile,"r");
    $data="";
    while(!feof($file)) {
      $data.=fgets($file,1000);
    }
    fclose($file);
    $newsgroups=unserialize($data);
  } else {
    $ns=nntp_open($server,$port);
    if ($ns == false) return false;
    $gf=fopen($file_groups,"r");
    // if we want to mark groups with new articles with colors, wie will later
    // need the format of the overview
    $overviewformat=thread_overview_read($ns);
    while (!feof($gf)) {
      $gruppe=new newsgroupType;
      $tmp=trim(line_read($gf));
      $tmp=preg_replace('/\t/', ' ', $tmp);
      if(substr($tmp,0,1)==":") {
        $gruppe->text=substr($tmp,1);
        $newsgroups[]=$gruppe;  
      } elseif(strlen(trim($tmp))>0) {
        // is there a description in groups.txt?
	$pos=strpos($tmp," ");
        if ($pos != false) {
          // yes.
          $gruppe->name=substr($tmp,0,$pos);
          $desc=substr($tmp,$pos);
        } else {
          // no, get it from the newsserver.
          $gruppe->name=$tmp;
          fputs($ns,"XGTITLE $gruppe->name\r\n");
          $response=line_read($ns);
          if (strcmp(substr($response,0,3),"282") == 0) {
            $neu=line_read($ns);
            do {
              $response=$neu;
              if ($neu != ".") $neu=line_read($ns);
            } while ($neu != ".");
            $desc=strrchr($response,"\t");
            if (strcmp($response,".") == 0) {
              $desc="-";
            }
          } else {
            $desc="";
          }
          if (strcmp(substr($response,0,3),"500") == 0)
            $desc="-";
        }
        if (strcmp($desc,"") == 0) $desc="-";
        $gruppe->description=$desc;
        fputs($ns,"GROUP ".$gruppe->name."\r\n"); 
        $t=explode(" ",line_read($ns));
//RETRO
	if($t[0]=="211")
		$gruppe->count=$t[1];
	else {
		nntp_close($ns);
		$ns=nntp_open($server,$port);
	        if ($ns == false) return false;
		fputs($ns,"GROUP ".$gruppe->name."\r\n");
	        $t=explode(" ",line_read($ns));
		if($t[0]=="211")
                  $gruppe->count=$t[1];
	        else
		  continue;
	}
	// mark group with new articles with colors
        if($gl_age) {
          fputs($ns,'XOVER '.$t[3]."\r\n");
          $tmp=explode(" ",line_read($ns));
          if($tmp[0]=="224") {
            $tmp=line_read($ns);
            if($tmp!=".") {
              $head=thread_overview_interpret($tmp,$overviewformat,$gruppe->name);
              $tmp=line_read($ns);
              $gruppe->age=$head->date;
            }
          }
        }
        if ((strcmp(trim($gruppe->name),"") != 0) &&
            (substr($gruppe->name,0,1) != "#"))
          $newsgroups[]=$gruppe;
      }
    }
    fclose($gf);
    nntp_close($ns);
    // write the data to the cachefile
    $file=fopen($cachefile,"w");
    fputs($file,serialize($newsgroups));
    fclose($file);
  }
  if ($load == 0) {
    return $newsgroups;
  } else {
    return;
  }
}

function groups_show($gruppen) {
  global $gl_age,$frame,$spooldir,$CONFIG,$spoolnews;
  if ($gruppen == false) return;
  global $file_thread,$text_groups;
  $c = count($gruppen);
  $acttype="keins";
  echo '<table class="np_groups_table" cellspacing="0"><tr class="np_thread_head"><td width="45px" class="np_thread_head">';
  echo 'Latest</td><td style="text-align: center;">Newsgroup</td><td width="8%" class="np_thread_head">Messages</td><td width="20%" class="np_thread_head" >Last Message</td></tr>';
  for($i = 0 ; $i < $c ; $i++) {
    $g = $gruppen[$i];
    if(isset($g->text)) {
      if($acttype!="text") {
        $acttype="text";
      }
    } else {
      if($acttype!="group") {
        $acttype="group";
      }
/* Display group name and description */
      $lineclass="np_thread_line".(($i%2)+1);

      echo '<tr class="'.$lineclass.'"><td style="text-align: center;" class="'.$lineclass.'">';
      echo '<a href="overboard.php?thisgroup='._rawurlencode($g->name).'">'; 
      if (file_exists('../common/themes/'.$_SESSION['theme'].'/images/latest.png')) {
        $latest_image='../common/themes/'.$_SESSION['theme'].'/images/latest.png';
      } else {
        $latest_image='../common/images/latest.png';
      }
      echo '<img src="'.$latest_image.'">';
      echo '</a>';
      echo '</td>';

      echo '<td class="'.$lineclass.'">';
      echo '<span class="np_group_line_text">';
      echo '<a ';
        echo 'target="'.$frame['content'].'" ';
        echo 'href="'.$file_thread.'?group='._rawurlencode($g->name).'"><span class="np_group_line_text">'.group_display_name($g->name)."</span></a>\n";
	if($g->description!="-")
        echo '</span><br><p class="np_group_desc">'.$g->description.'</p>';

/* Display article count */
      echo '</td><td class="'.$lineclass.'">';
      if($gl_age)
        $datecolor=thread_format_date_color($g->age);
      echo '<small>';
      if($datecolor!="")
        echo '<font color="'.$datecolor.'">'.$g->count.'</font>';
      else
        echo $g->count;
      echo '</small>';

/* Display latest article info */
    echo '</td><td class="'.$lineclass.'"><div class="np_last_posted_date">';
    $filename = $spooldir."/".$g->name."-lastarticleinfo.dat";
    if($file=@fopen($filename,"r")) {
      $lastarticleinfo=unserialize(fread($file,filesize($filename)));
      fclose($file);
    } else {
      $lastarticleinfo->date = 0;
    }
// Handle newsportal errors in lastarticleinfo.dat
    if($lastarticleinfo->date == 0) {
      $database = $spooldir.'/articles-overview.db3';
      $table = 'overview';
      $articles_dbh = rslight_db_open($database);
      $articles_query = $articles_dbh->prepare('SELECT * FROM overview WHERE newsgroup=:group ORDER BY date DESC LIMIT 2');
      $articles_query->execute(['group' => $g->name]);
      $found = 0;
      while ($row = $articles_query->fetch()) {
        $found = 1;
        break;
      }
      $dbh = null;
      if($found) {
	$lastarticleinfo->date = $row['date'];
// Put this in a function already!
        $fromoutput = explode("<", html_entity_decode($row['name']));
// Just an email address?
        if(strlen($fromoutput[0]) < 2) {
          preg_match("/\<([^\)]*)\@/", html_entity_decode($row['name']), $fromaddress);
          $fromoutput[0] = $fromaddress[1];
        }
        if(strpos($fromoutput[0], "(")) {
          preg_match("/\(([^\)]*)\)/", html_entity_decode($row['name']), $fromaddress);
          $fromoutput[0] = $fromaddress[1];
        }
        if((isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true) && (strpos($fromoutput[0], '@') !== false)) {
          $lastarticleinfo->name = truncate_email($fromoutput[0]);
        } else {
          $lastarticleinfo->name = $fromoutput[0];
        }
      }
    }
    echo get_date_interval(date("D, j M Y H:i T",$lastarticleinfo->date));
    echo '<table><tr><td>';
    echo '<font class="np_last_posted_date">by: ';
    echo create_name_link(mb_decode_mimeheader($lastarticleinfo->name));             
    echo '</td></tr></table>';
    }
    echo "\n";
    flush();
  }
  echo "</td></div></table>\n";
}

/*
 * print the group names from an array to the webpage
 */
function groups_show_frames($gruppen) {
  global $gl_age,$frame,$spooldir;
  if ($gruppen == false) return;
  global $file_thread,$text_groups;
  $c = count($gruppen);
  echo '<div class="np_index_groupblock">';
  $acttype="keins";
  for($i = 0 ; $i < $c ; $i++) {
    $g = $gruppen[$i];
    if(isset($g->text)) {
      if($acttype!="text") {
        $acttype="text";
        if($i>0)
          echo '</div>';
        echo '<div class="np_index_grouphead">';
      }
      echo $g->text;
    } else {
      if($acttype!="group") {
        $acttype="group";
        if($i>0)
          echo '</div>';
        echo '<div class="np_index_groupblock">';
      }
      echo '<div class="np_index_group">';
      echo '<b><a ';
        echo 'target="'.$frame['content'].'" ';
	echo 'href="'.$file_thread.'?group='._rawurlencode($g->name).'">'.group_display_name($g->name)."</a></b>\n";
      if($gl_age)
        $datecolor=thread_format_date_color($g->age);
      echo '<small>(';
      if($datecolor!="")
        echo '<font color="'.$datecolor.'">'.$g->count.'</font>';
      else
        echo $g->count;
      echo ')</small>';
      if($g->description!="-")
        echo '<br><small>'.$g->description.'</small>';
      echo '</div>';
    }
    echo "\n";
    flush();
  }
  echo "</div></div>\n";
}

/*
 * gets a list of aviable articles in the group $groupname
 */
/*
function getArticleList(&$ns,$groupname) {
  fputs($ns,"LISTGROUP $groupname \r\n");
  $line=line_read($ns);
  $line=line_read($ns);
  while(strcmp($line,".") != 0) {
    $articleList[] = trim($line);
    $line=line_read($ns);
  }
  if (!isset($articleList)) $articleList="-";
  return $articleList;
}
*/

/*
 * Decode quoted-printable or base64 encoded headerlines
 *
 * $value: The to be decoded line
 *
 * returns the decoded line
 */
function headerDecode($value) {
	return mb_decode_mimeheader($value);
}

/*
 * calculates an Unix timestamp out of a Date-Header in an article
 *
 * $value: Value of the Date: header
 *
 * returns an Unix timestamp
 */
function getTimestamp($value) {
  global $CONFIG;

  return strtotime($value);
}

function parse_header($hdr,$number="") {
  for ($i=count($hdr)-1; $i>0; $i--)
    if (preg_match("/^(\x09|\x20)/",$hdr[$i]))
      $hdr[$i-1]=$hdr[$i-1]." ".ltrim($hdr[$i]);
  $header = new headerType;
  $header->isAnswer=false;
  for ($count=0;$count<count($hdr);$count++) {
    $variable=substr($hdr[$count],0,strpos($hdr[$count]," "));
    $value=trim(substr($hdr[$count],strpos($hdr[$count]," ")+1));
      switch (strtolower($variable)) {
        case "from:": 
          $fromline=address_decode(headerDecode($value),"nirgendwo");
          if (!isset($fromline[0]["host"])) $fromline[0]["host"]="";
          $header->from=$fromline[0]["mailbox"]."@".$fromline[0]["host"];
          $header->username=$fromline[0]["mailbox"];
          if (!isset($fromline[0]["personal"])) {
            $header->name="";
          } else {
            $header->name=$fromline[0]["personal"];
          }
          break;
        case "message-id:":
          $header->id=$value;
          break;
        case "subject:":
          $header->subject=headerDecode($value);
          break;
        case "newsgroups:":
          $header->newsgroups=$value;
          break;
        case "organization:":
          $header->organization=headerDecode($value);
          break;
        case "content-transfer-encoding:":
          $header->content_transfer_encoding=trim(strtolower($value));
          break; 
        case "content-type:":
          $header->content_type=array();
          $subheader=explode(";",$value);
          $header->content_type[0]=strtolower(trim($subheader[0]));
          for ($i=1; $i<count($subheader); $i++) {
            $gleichpos=strpos($subheader[$i],"=");
            if ($gleichpos) {
              $subvariable=trim(substr($subheader[$i],0,$gleichpos));
              $subvalue=trim(substr($subheader[$i],$gleichpos+1));
              if (($subvalue[0]=='"') &&
                  ($subvalue[strlen($subvalue)-1]=='"'))
                $subvalue=substr($subvalue,1,strlen($subvalue)-2);
              switch($subvariable) {
                case "charset":
                  $header->content_type_charset=array(strtolower($subvalue));
                  break;
                case "name":
                  $header->content_type_name=array($subvalue);
                  break;
                case "boundary":
                  $header->content_type_boundary=$subvalue;
                  break;
                case "format":
                  $header->content_type_format=array($subvalue);
              }
            }
          }
          break;
        case "references:":
          $ref=trim($value);
          while (strpos($ref,"> <") != false) {
            $header->references[]=substr($ref,0,strpos($ref," "));
            $ref=substr($ref,strpos($ref,"> <")+2);
          }
          $header->references[]=trim($ref);
          break;
        case "date:":
          $header->date=getTimestamp(trim($value));
          break;
        case "followup-to:":
          $header->followup=trim($value);
          break;
        case "x-newsreader:":
        case "x-mailer:":
	case "x-rslight-to:":
	  $header->rslight_to=trim($value);
	  break;
	case "x-rslight-site:":
	  $header->rslight_site=trim($value);
	  break;
        case "user-agent:":
          $header->user_agent=trim($value);
          break;
        case "x-face:": // not ready
//          echo "<p>-".base64_decode($value)."-</p>";
          break;
        case "x-no-archive:":
          $header->xnoarchive=strtolower(trim($value));
      }
  }
  if (!isset($header->content_type[0]))
    $header->content_type[0]="text/plain";
  if (!isset($header->content_transfer_encoding))
    $header->content_transfer_encoding="8bit";
  if ($number != "") $header->number=$number;
  return $header;
}

/*
 * convert the charset of a text
 */
function recode_charset($text,$source=false,$dest=false) {
  global $iconv_enable,$www_charset;
  if($dest==false)
    $dest=$www_charset;
  if(($iconv_enable) && ($source!=false)) {
    $return=iconv($source,
                 $dest."//TRANSLIT",$text);
    if($return!="")
      return $return;
    else
      return $text;
  } else {
    return $text;
  }
}

function decode_body($body,$encoding) {
  $bodyzeile="";
  switch ($encoding) {
    case "base64":
      $body=base64_decode($body);
      break;
    case "quoted-printable":
      $body=Quoted_printable_decode($body);
      $body=str_replace("=\n","",$body);
//    default:
//      $body=str_replace("\n..\n","\n.\n",$body);
  }

  return $body;
}

/*
 * makes URLs clickable
 *
 * $text: A text-line probably containing links.
 *
 * the function returns the text-line with HTML-Links to the links or
 * email-adresses.
 */
function html_parse($text) {
  global $frame_externallink;
  if ((isset($frame_externallink)) && ($frame_externallink != "")) { 
    $target=' TARGET="'.$frame_externallink.'" ';
  } else {
    $target=' ';
  }
  $ntext="";
  // split every line into it's words
  $words=explode(" ",$text);
  $n=count($words);
  $is_link = 0;
  for($i=0; $i<$n; $i++) {
    $word=$words[$i];
    if(preg_match('/(https?|ftp|news|gopher|telnet)\:\/\/[^\",]+/i', $word)) {
      $is_link = 1;
      $nlink = trim($word, '().,');
      $nlink = preg_replace('/(\&lt|\&gt|\&nbsp);/', '', $nlink);
      $nlink = preg_replace('/\[url=/', '', $nlink);
      $bbnlink = explode(']', $nlink);
      $nlink = $bbnlink[0];
      $nword = '<a '.$target.' href="'.$nlink.'">'.$nlink.'</a>';
      if(isset($bbnlink[1])) {
        $nword.=' '.$bbnlink[1];
      }
      if($nword!=$word && substr($nlink, strlen($nlink) - 3) != "://") { 
        $word=$nword;
      }
    }
    // add the spaces between the words
    if($i>0)
      $ntext.=" ";
    if($is_link) {
      $word = preg_replace('/\[\/url\]/', '', $word);
    }
    $ntext.=$word;
  }
  return($ntext);
}


/*
 * read the header of an article in plaintext into an array
 * $articleNumber can be the number of an article or its message-id.
 */
function readPlainHeader(&$ns,$group,$articleNumber) {
  fputs($ns,"GROUP $group\r\n");
  $line=line_read($ns);
  fputs($ns,"HEAD $articleNumber\r\n");
  $line=line_read($ns);
  if (substr($line,0,3) != "221") {
    echo $text_error["article_not_found"];
    $header=false;
  } else {
    $line=line_read($ns);
    $body="";
    while(strcmp(trim($line),".") != 0) {
      $body .= $line."\n";
      $line=line_read($ns);
    }
    return explode("\n",str_replace("\r\n","\n",$body));
  }
}

/*
 * cancel an article on the newsserver
 *
 * DO NOT USE THIS FUNCTION, IF YOU DON'T KNOW WHAT YOU ARE DOING!
 *
 * $ns: The handler of the NNTP-Connection
 * $group: The group of the article
 * $id: the Number of the article inside the group or the message-id
 */
function message_cancel($subject,$from,$newsgroups,$ref,$body,$id) {
  global $server,$port,$send_poster_host,$CONFIG,$text_error;
  global $www_charset;
  flush();
  $ns=nntp_open($server,$port);
  if ($ns != false) {
    fputs($ns,"POST\r\n");
    $weg=line_read($ns);
    fputs($ns,'Subject: '.quoted_printable_encode($subject)."\r\n");
    fputs($ns,'From: '.$from."\r\n");
    fputs($ns,'Newsgroups: '.$newsgroups."\r\n");
    fputs($ns,"Mime-Version: 1.0\r\n");
    fputs($ns,"Content-Type: text/plain; charset=".$www_charset."\r\n");
    fputs($ns,"Content-Transfer-Encoding: 8bit\r\n");
    if ($send_poster_host)
      fputs($ns,'X-HTTP-Posting-Host: '.gethostbyaddr(getenv("REMOTE_ADDR"))."\r\n");
    if ($ref!=false) fputs($ns,'References: '.$ref."\r\n");
    if (isset($CONFIG['organization']))
      fputs($ns,'Organization: '.quoted_printable_encode($CONFIG['organization'])."\r\n");
    fputs($ns,"Control: cancel ".$id."\r\n");
    $body=str_replace("\n.\r","\n..\r",$body);
    $body=str_replace("\r",'',$body);
    $b=explode("\n",$body);
    $body="";
    for ($i=0; $i<count($b); $i++) {
      if ((strpos(substr($b[$i],0,strpos($b[$i]," ")),">") != false ) | (strcmp(substr($b[$i],0,1),">") == 0)) {
        $body .= textwrap(stripSlashes($b[$i]),78,"\r\n")."\r\n";
      } else {
        $body .= textwrap(stripSlashes($b[$i]),74,"\r\n")."\r\n";
      }
    }
    fputs($ns,"\r\n".$body."\r\n.\r\n");
    $message=line_read($ns);
    nntp_close($ns);
  } else {
    $message=$text_error["post_failed"];
  }
  return $message;
}
function rslight_encrypt($data, $key) {
    $encryption_key = base64_decode($key);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function _rawurlencode($string) {
    $string = rawurlencode(str_replace('+','%2B',$string));
    return $string;
}

function _rawurldecode($string) {
    $string = rawurldecode(str_replace('%2B','+',$string));
    return $string;
}

function rslight_decrypt($data, $key) {
    $encryption_key = base64_decode($key);
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
}

function group_display_name($gname)
{
    global $config_dir;
    $namelist = file($config_dir."rename.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($namelist as $name) {
	if($name[0] == '#') {
	  continue;
	}
	$nameitem = explode(':', $name);
	if(!strcmp(trim($nameitem[0]),trim($gname))) {
	  return $nameitem[1];
	}
    }
    return $gname;
}
function check_bbs_auth($username, $password) {
  global $config_dir,$CONFIG;

  $workpath = $config_dir."users/";
  $username = trim(strtolower($username));
  $userFilename = $workpath.$username;
  $keyFilename = $config_dir."/userconfig/".$username;

// Create accounts for $anonymous and $CONFIG['server_auth_user'] if not exist  
  if($username == strtolower($CONFIG['anonusername'])) {
    if(filemtime($config_dir."rslight.inc.php") > filemtime($userFilename)) {
      if ($userFileHandle = @fopen($userFilename, 'w+'))
      {
        fwrite($userFileHandle, password_hash($CONFIG['anonuserpass'], PASSWORD_DEFAULT));
        fclose($userFileHandle);
      }
    }
  }
  if($username == strtolower($CONFIG['server_auth_user'])) { 
    if(filemtime($config_dir."rslight.inc.php") > filemtime($userFilename)) {
      if ($userFileHandle = @fopen($userFilename, 'w+'))
      {
        fwrite($userFileHandle, password_hash($CONFIG['server_auth_pass'], PASSWORD_DEFAULT));
        fclose($userFileHandle);
      }
    }
  }

  if(trim($username) == strtolower($CONFIG['anonusername']) && $CONFIG['anonuser'] != true) {
	return FALSE;
  }

  if ($userFileHandle = @fopen($userFilename, 'r'))
  {
        $userFileInfo = fread($userFileHandle, filesize($userFilename));
        fclose($userFileHandle);
        if (password_verify ( $password , $userFileInfo))
        {
                touch($userFilename);
                $ok = TRUE;
        } else {
                return FALSE;
        }
  } else {
        $ok = FALSE;
  }
  if ($ok)
  {
        return TRUE;
  } else {
	if(isset($CONFIG['auto_create']) && $CONFIG['auto_create'] == true) {
	  if ($userFileHandle = @fopen($userFilename, 'w+')) {
            fwrite($userFileHandle, password_hash($password, PASSWORD_DEFAULT));
            fclose($userFileHandle);
            chmod($userFilename, 0666);
          }
          $newkey = base64_encode(openssl_random_pseudo_bytes(44));
          if ($userFileHandle = @fopen($keyFilename, 'w+')) {
            fwrite($userFileHandle, 'encryptionkey:'.$newkey);
            fclose($userFileHandle);
            chmod($userFilename, 0666);
          } 
	  return TRUE;
	} else {
	  return FALSE;
	}
  }
}
function check_encryption_groups($request) {
  global $config_path;
  $groupsFilename = $config_path."encryption_ok.txt";
  if ($groupsFileHandle = @fopen($groupsFilename, 'r'))
  {
    while (!feof($groupsFileHandle))
    {
      $buffer = fgets($groupsFileHandle);
      $buffer = str_replace(array("\r", "\n"), '',$buffer);
        if(!strcmp($buffer, $request)) {
	  fclose($groupsFileHandle);
	  return TRUE;
	}
    }
    fclose($userFileHandle);
  } else {
    return FALSE;
  }
}

function set_user_config($username,$request,$newval) {
  global $config_dir;
  $userconfigpath = $config_dir."userconfig/";
  $username = strtolower($username);
  $userFilename = $userconfigpath.$username;
  $userData = file($userFilename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $userFileHandle = fopen($userFilename, 'w');

  $found=0;
  foreach($userData as $data) {
    if(strpos($data, $request.':') !== FALSE) {
      fputs($userFileHandle, $request.':'.$newval."\r\n");
      $found=1;
    } else {
      fputs($userFileHandle, $data."\r\n");
    }
  }
  if($found == 0) {
    fputs($userFileHandle, $request.':'.$newval."\r\n");
  }
  fclose($userFileHandle);
  return;  
}

function get_user_config($username,$request) {
  global $config_dir;
  $userconfigpath = $config_dir."userconfig/";
  $username = strtolower($username);
  $userFilename = $userconfigpath.$username;

  if ($userFileHandle = @fopen($userFilename, 'r'))
  {
    while (!feof($userFileHandle))
    {
      $buffer = fgets($userFileHandle);
      if(strpos($buffer, $request.':') !== FALSE) {
        $userdataline=$buffer;
        fclose($userFileHandle);
        $userdatafound = explode(':',$userdataline);
        return $userdatafound[1];
      }
    }
    fclose($userFileHandle);
    return FALSE;
  } else {
    return FALSE;
  }
}

function is_multibyte($s) {
  return mb_strlen($s,'utf-8') < strlen($s);
}

function check_spam($subject,$from,$newsgroups,$ref,$body,$msgid)
{
  global $msgid_generate,$msgid_fqdn,$spooldir;
  global $CONFIG;
  $spamfile = tempnam($spooldir, 'spam-');

  $tmpheader='From: '.$from."\r\n";
  if(strpos($from, $CONFIG['anonusername'])) {
    $tmpheader.="Anonymous: TRUE\r\n";
  }
  $tmpheader.='Message-ID: '.$msgid."\r\n";
  $tmpheader.='Subject: '.encode_subject($subject)."\r\n\r\n";
  if ($spamFileHandle = fopen($spamfile, 'w'))
  {
    fwrite($spamFileHandle, $tmpheader);
    fwrite($spamFileHandle, $body);
    $spamcommand = $CONFIG['spamc'].' -E < '.$spamfile;
    ob_start();
    $spamresult = passthru($spamcommand, $res);
    $spamresult = ob_get_contents();
    ob_end_clean();
	$spam_fail=1;
		foreach (explode(PHP_EOL, $spamresult) as $line) {
			$line = str_replace(array("\n\r", "\n", "\r"), '', $line);
			if(strpos($line, 'X-Spam-Checker-Version:') !== FALSE) {
				$spamcheckerversion = $line;
				$spam_fail=0;
			}
			if(strpos($line, 'X-Spam-Level:') !== FALSE) {
                $spamlevel = $line;
            }
		}
    }
    fclose($spamFileHandle);
    unlink($spamfile);
	return array(
		'res' => $res,
		'spamresult' => $spamresult,
		'spamcheckerversion' => $spamcheckerversion,
		'spamlevel' => $spamlevel,
		'spam_fail' => $spam_fail
	);
}

function format_log_date() {
    return date('M d H:i:s');
}

function create_name_link($name) {
    global $CONFIG; 
    $name = preg_replace('/\"/', '', $name);
    if(strpos($name, '...@') !== false && (isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true)) {
	$return = '<span class="visited">'.substr(htmlspecialchars($name),0,20).'</span>';
    } else {
    	$return = '<a href="search.php?command=search&searchpoint=Poster&terms='.$name.'"><span class="visited">'.substr(htmlspecialchars($name),0,20).'</span></a>';
    }
    return($return);
}
function truncate_email($address) {
    $before_at = explode('@', $address);
      $namelen = strlen($before_at[0]);
      if ($namelen > 3) {
        $endname = $namelen - 3;
        if($endname > 8)
          $endname = 8;
        if($endname < 3)
          $endname++;
        if($endname < 3)
          $endname++;
      } else {
        $endname = $namelen;
      }
      return substr($before_at[0], 0, $endname).'...'.substr($address, $namelen, strlen($address));
}

function get_date_interval($value) {
    $current = time();
    $datetime1 = date_create($value);
    $datetime2 = date_create("@$current");
    $interval = date_diff($datetime1, $datetime2);
    if(!$interval) {
	return '(date error)';
    }
    $years = $interval->format('%y')." Years ";
    $months = $interval->format('%m')." Months ";
    $days = $interval->format('%d')." Days ";
    $hours = $interval->format('%h')." Hours ";
    $minutes = $interval->format('%i')." Minutes ";
    if($interval->format('%y') == 1) {
        $years = $interval->format('%y')." Year ";
    }
    if($interval->format('%m') == 1) {
        $months = $interval->format('%m')." Month ";
    }
    if($interval->format('%d') == 1) {
        $days = $interval->format('%d')." Day ";
    }
    if($interval->format('%h') == 1) {
        $hours = $interval->format('%h')." Hour ";
    }
    if($interval->format('%i') == 1) {
        $minutes = $interval->format('%i')." Minute ";
    }
    if($interval->format('%y') == 0) {
        $years = '';
    }
    if($interval->format('%m') == 0) {
        $months = '';
    }
    if($interval->format('%d') == 0) {
        $days = '';
    }
    if($interval->format('%h') == 0) {
        $hours = '';
    }
    if($interval->format('%i') == 0) {
        $minutes = '';
    }
    if($years > 0) {
	$days = '';
        $hours = '';
	$minutes = '';
    }
    if($months > 0) {
	$hours = '';
        $minutes = '';
    }
    if($days > 0) {
	$minutes = '';
    }
    $variance = $interval->format($years.$months.$days.$hours.$minutes.' ago');
    if(strlen($variance) < 5) {
	$variance = " now";
    }
    return $variance;
}

function get_search_snippet($body, $content_type='') {
  $body = quoted_printable_decode($body);
  if($content_type !== '') {
    $mysnippet = recode_charset($body, $content_type, "utf8");
  } else {
    $mysnippet = $body;
  }
  if($bodyend=strrpos($mysnippet, "\n---\n")) {
        $mysnippet = substr($mysnippet, 0, $bodyend);
  } else {
        if($bodyend=strrpos($mysnippet, "\n-- ")) {
            $mysnippet = substr($mysnippet, 0, $bodyend);
        } else {
            if($bodyend=strrpos($mysnippet, "\n.")) {
                 $mysnippet = substr($mysnippet, 0, $bodyend);
            }
        }
   }
	$mysnippet = preg_replace('/\n.{0,5}>(.*)/', '', $mysnippet);

        $snipstart = strpos($mysnippet, ":\n");
        if(substr_count(trim(substr($mysnippet, 0, $snipstart)), "\n") < 2) {
                $mysnippet = substr($mysnippet, $snipstart + 1);
        } else {
                $mysnippet = substr($mysnippet, 0);
        }
   return $mysnippet;
}

function mail_db_open($database, $table='messages') {
  try {
    $dbh = new PDO('sqlite:'.$database);
  } catch (PDOExeption $e) {
    echo 'Connection failed: '.$e->getMessage();
    exit;
  }
  $dbh->exec("CREATE TABLE IF NOT EXISTS messages(
     id INTEGER PRIMARY KEY,
     msgid TEXT UNIQUE,
     mail_from TEXT,
     mail_viewed TEXT,
     rcpt_to TEXT,
     rcpt_viewed TEXT,
     rcpt_target TEXT,
     date TEXT,
     subject TEXT,
     message TEXT,
     from_hide TEXT,
     to_hide TEXT)");
  return($dbh);
}

function rslight_db_open($database, $table='overview') {
  try {
    $dbh = new PDO('sqlite:'.$database);
  } catch (PDOExeption $e) {
    echo 'Connection failed: '.$e->getMessage();
    exit;
  }
  $dbh->exec("CREATE TABLE IF NOT EXISTS overview(
     id INTEGER PRIMARY KEY,
     newsgroup TEXT,
     number TEXT,
     msgid TEXT,
     date TEXT,
     name TEXT,
     subject TEXT,
     unique (newsgroup, msgid))");
  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_date on overview(date)');
  $stmt->execute();
  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_newsgroup on overview(newsgroup)');
  $stmt->execute();
  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_msgid on overview(msgid)');
  $stmt->execute();
  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_newsgroup_number on overview(newsgroup,number)');
  $stmt->execute();
  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_name on overview(name)');
  $stmt->execute();
  return($dbh);
}

function article_db_open($database) {
  try {
    $dbh = new PDO('sqlite:'.$database);
  } catch (PDOExeption $e) {
    echo 'Connection failed: '.$e->getMessage();
    exit;
  }
  $dbh->exec("CREATE TABLE IF NOT EXISTS articles(
     id INTEGER PRIMARY KEY,
     newsgroup TEXT,
     number TEXT UNIQUE,
     msgid TEXT UNIQUE,
     date TEXT,
     name TEXT,
     subject TEXT,
     search_snippet TEXT,
     article TEXT)");

  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS db_number on articles(number)');
  $stmt->execute();
  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS db_date on articles(date)');
  $stmt->execute();
  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS db_msgid on articles(msgid)');
  $stmt->execute();
  $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS db_name on articles(name)');
  $stmt->execute();

  $dbh->exec("CREATE VIRTUAL TABLE IF NOT EXISTS search_fts USING fts5(
     newsgroup,
     number,
     msgid,
     date,
     name,
     subject,
     search_snippet)");
  $dbh->exec("CREATE TRIGGER IF NOT EXISTS after_articles_insert AFTER INSERT ON articles BEGIN
	INSERT INTO search_fts(newsgroup, number, msgid, date, name, subject, search_snippet) VALUES(new.newsgroup, new.number, new.msgid, new.date, new.name, new.subject, new.search_snippet);
	END;");
  $dbh->exec("CREATE TRIGGER IF NOT EXISTS after_articles_delete AFTER DELETE ON articles BEGIN
	DELETE FROM search_fts WHERE msgid = old.msgid;
	END;");	
return($dbh);
}

function np_get_db_article($article, $group, $makearray=1, $dbh=null) {
    global $config_dir,$path,$groupconfig,$config_name,$logdir,$spooldir;
    $logfile=$logdir.'/newsportal.log';
    $msg2="";
	$closeme = 0;
    $database = $spooldir.'/'.$group.'-articles.db3';
    if(!$dbh) {
	  if(!is_file($database)) {
	    return FALSE;
	  }
	  $dbh = article_db_open($database);
	  $closeme = 1;
    }
    $ok_article = 0;
// By Message-ID
    if(!is_numeric($article)) {
      $stmt = $dbh->prepare("SELECT * FROM articles WHERE msgid like :terms");
      $stmt->bindParam(':terms', $article);
      $stmt->execute();
      while($found = $stmt->fetch()) {
        $msg2 = $found['article'];
	$ok_article = 1;
        break;
      }
    } else {
      $stmt = $dbh->prepare("SELECT * FROM articles WHERE number = :terms");
      $stmt->bindParam(':terms', $article);
      $stmt->execute();
      while($found = $stmt->fetch()) {
        $msg2 = $found['article'];
	$ok_article = 1;
        break;
      }
    }
    if($closeme == 1) {
	  $dbh = null;
    }
    if($ok_article !== 1) {
//	file_put_contents($logfile, "\n".format_log_date()." ".$config_name." DEBUG: ".$article." from ".$group." not found in database", FILE_APPEND);
	return FALSE;
    }
//    file_put_contents($logfile, "\n".format_log_date()." ".$config_name." DEBUG: fetched: ".$article." from ".$group, FILE_APPEND);
    if($makearray == 1) {
	$thisarticle = preg_split("/\r\n|\n|\r/", trim($msg2));
	array_pop($thisarticle);
	return $thisarticle;
    } else {
	return trim($msg2);
    }
}

function get_config_value($configfile,$request) {
  global $config_dir;
    
  if ($configFileHandle = @fopen($config_dir.'/'.$configfile, 'r'))
  {
    while (!feof($configFileHandle))
    {
      $buffer = fgets($configFileHandle);
      if(strpos($buffer, $request.':') !== FALSE) {
        $dataline=$buffer;
        fclose($configFileHandle);
        $datafound = explode(':',$dataline);
        return $datafound[1];
      }
    }
    fclose($configFileHandle);
    return FALSE;
  } else {
    return FALSE;
  }
}

function throttle_hits() {
global $CONFIG, $logdir; 
$logfile=$logdir.'/newsportal.log';
  if(!isset($_SESSION['starttime'])) {
    $_SESSION['starttime'] = time();
    $_SESSION['views'] = 0;
  }
  $_SESSION['views']++;

// $loadrate = allowed article request per second
  $loadrate = .15;
  $rate = ($_SESSION['views'] / (time() - $_SESSION['starttime']));
  if (($rate > $loadrate) && ($_SESSION['views'] > 5)) {
    header("HTTP/1.0 429 Too Many Requests");
    if(!isset($_SESSION['throttled'])) {
      file_put_contents($logfile, "\n".format_log_date()." ".$config_name." Too many requests from ".$_SERVER['REMOTE_ADDR']." throttling", FILE_APPEND);
      $_SESSION['throttled'] = true;
      if(isset($_SESSION['rsactive'])) {
        unset($_SESSION['rsactive']);
      }
    }
    exit(0);
  }
}

function get_data_from_msgid($msgid) {
      global $spooldir;
      $database = $spooldir.'/articles-overview.db3';
      $articles_dbh = rslight_db_open($database);
      $articles_query = $articles_dbh->prepare('SELECT * FROM overview WHERE msgid=:messageid');
      $articles_query->execute(['messageid' => $msgid]);
      $found = 0;
      while ($row = $articles_query->fetch()) {
        $found = 1;
        break;
      }
      $dbh = null;
      if($found) {
        return $row;
      } else {
        return false;
      }
}
?>
