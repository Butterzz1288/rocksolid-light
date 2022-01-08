<?php
    session_start();
/*  rocksolid overboard - overboard for rslight
*  Download: https://news.novabbs.com/getrslight
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
?>

<?php
  include "config.inc.php";
  include "auth.inc";
  include "$file_newsportal";

  throttle_hits();

if(isset($frames_on) && $frames_on === true) {
?>
<script>
    var contentURL=window.location.pathname+window.location.search+window.location.hash;
    if ( window.self !== window.top ) {
        /* Great! now we move along */
    } else {
        window.location.href = '../index.php?content='+encodeURIComponent(contentURL);
    }
    top.history.replaceState({}, 'Title', 'index.php?content='+encodeURIComponent(contentURL));
</script>

<?php
}
if (isset($_GET['thisgroup'])) {
  $title.=" - "._rawurldecode(_rawurldecode($_GET['thisgroup']))." - latest messages";
} else {
  $title.=" - ".$config_name." - overboard"; 
}
include "head.inc";
$CONFIG = include($config_file);

# How many days old should articles be displayed?
if (isset($_GET['thisgroup'])) {
  $article_age = 30;
} else {
  $article_age = 30;
}

# How long in seconds to cache results
$cachetime = 60;

# Maximum number of articles to show
$maxdisplay = 500;

# How many characters of the body to display per article
$snippetlength = 240;

$spoolpath_regexp = '/'.preg_replace('/\//', '\\/', $spoolpath).'/';
$thissite = '.';

$groupconfig=$file_groups;
$cachefile=$spooldir."/".$config_name."-overboard.dat";
$oldest = (time() - (86400 * $article_age));

if (isset($_GET['thisgroup'])) {
  $grouplist = array();
  $grouplist[0] = _rawurldecode(_rawurldecode($_GET['thisgroup']));
  $cachefile=$spooldir."/".$grouplist[0]."-overboard.dat";
} else {
  $grouplist = file($groupconfig, FILE_IGNORE_NEW_LINES);
}

show_overboard_header($grouplist);

$results=0;
/* If cache is less than ? seconds old, use it */
if(is_file($cachefile)) {
  $stats = stat($cachefile);
  $oldest = $stats[9];
  $cached_overboard = unserialize(file_get_contents($cachefile));
  if($stats[9] > (time() - $cachetime)) {
    echo '<table cellspacing="0" width="100%" class="np_results_table">';
    foreach($cached_overboard as $result) {
      if(($results % 2) != 0){
        echo '<tr class="np_result_line1"><td class="np_result_line1" style="word-wrap:break-word";>';
      } else {
        echo '<tr class="np_result_line2"><td class="np_result_line2" style="word-wrap:break-word";>';
      }
      echo $result;
      $results++;
    }
    show_overboard_footer($stats, $results, true);
    exit(0);
  }
}
//ob_start();
# Iterate through groups

$database = $spooldir.'/articles-overview.db3';
$table = 'overview';
$dbh = rslight_db_open($database, $table);
$query = $dbh->prepare('SELECT * FROM '.$table.' WHERE newsgroup=:findgroup AND date >= '.$oldest.' ORDER BY date DESC LIMIT '.$maxdisplay);
$articles = array();
$db_articles = array();
foreach($grouplist as $findgroup) {
	$groups = preg_split("/(\ |\t)/", $findgroup, 2);
	$findgroup = $groups[0];

	$overboard_noshow = explode(' ', $CONFIG['overboard_noshow']);
	foreach($overboard_noshow as $noshow) {
	  if ((strpos($findgroup, $noshow) !== false) && !isset($_GET['thisgroup'])) {
	    continue 2;
	  }
	}
	$thisgroup = preg_replace('/\./', '/', $findgroup);
          if($dbh) {
            $query->execute(['findgroup' => $findgroup]);
	    $i=0;
	    while (($overviewline = $query->fetch()) !== false) {
          $articles[] = $spoolpath.$thisgroup.'/'.$overviewline['number'];
	      $db_articles[] = $findgroup.':'.$overviewline['number'].':'.$overviewline['date'].':'.$overviewline['name'];
	      $i++;
	      if($i > $maxdisplay) {
		break;
	      }
	    }
	  }	  
}
$dbh = null;

$files = array();
if($CONFIG['article_database'] == '1') {
  foreach($db_articles as $article) {
    $order=explode(':', $article);
    $files[$order[2]] = $article;
  }
} else {
  foreach($articles as $article) {
    if(is_dir($article)) {
		continue;
    }
    $files[filemtime($article)] = $article;
  }
}
krsort($files);
echo '<table cellspacing="0" width="100%" class="np_results_table">';

foreach($files as $article) {
    if(!isset($cachedate)) {
      $cachedate = time();
    }
    if($CONFIG['article_database'] == '1') {
      $data = explode(':', $article);
      $articledata = np_get_db_article($data[1], $data[0], 0);
    } else {
      $articledata = file_get_contents($article);
    }
    $bodystart = strpos($articledata, $localeol);

    $header = substr($articledata, 0, $bodystart);
    $body = substr($articledata, $bodystart+1);
    $body = substr($body, strpos($body, PHP_EOL));

	if(($multi = strpos($body, 'Content-Type: text/plain')) != false) {
		$bodystart = strpos($body, $localeol);
		$body = substr($body, $bodystart+1);
	    $body = substr($body, strpos($body, PHP_EOL));
	}
    # Find group name and article number
    if($CONFIG['article_database'] == '1') {
      $group = $data[0];
      $articlenumber = $data[1];
      $groupname = $group;
    } else {
      $group = preg_replace($spoolpath_regexp, '', $article);
      $group = preg_replace('/\//', '.', $group);
      $findme = strrpos($group, '.');
      $groupname = substr($group, 0, $findme);
      $articlenumber = substr($group, $findme+1);
    }
    # Generate link
    $url = $thissite."/article-flat.php?id=".$articlenumber."&group="._rawurlencode($groupname)."#".$articlenumber;
    $groupurl = $thissite."/thread.php?group="._rawurlencode($groupname);
    preg_match('/Subject:.*/', $header, $subject);
    $output = explode("Subject: ",$subject[0], 2);

    preg_match('/Date:.*/', $header, $articledate);
    $dateoutput = explode("Date: ",$articledate[0]);
    $thisdate = strtotime($dateoutput[1]);

    if(($thisdate > time()) || ($thisdate < $oldest)) {
      continue;
    }
    $local_poster=false;
    if(preg_match('/X-Rslight-Site:.*/', $header, $site)) {
      $site_match = explode("X-Rslight-Site: ", $site[0]);
      preg_match('/Message-ID:.*/', $header, $mid);
      $mid_match = explode("Message-ID: ",$mid[0]);
      $rslight_site = $site_match[1];
      $rslight_mid = $mid_match[1];
      if(password_verify($CONFIG['thissitekey'].$rslight_mid, $rslight_site)) {
        $local_poster=true;
      }
  }

    preg_match('/Message-ID:.*/i', $header, $articleid);
    $getid = explode(": ", $articleid[0]);
    $thismsgid = hash('crc32', serialize($getid[1]));

    preg_match('/References:.*/i', $header, $ref);
    $getrefs = explode(': ', $ref[0]);
    $ref = preg_split("/[\s]+/", $getrefs[1]);
    if($getrefs[1] && $refid = get_data_from_msgid($ref[0])) {
      // Check that article to link is new enough for newsportal to display
      $groupinfo = file($spooldir.'/'.$refid[newsgroup].'-info.txt');
      $range = explode(' ', $groupinfo[1]);
      if($refid[number] > ($range[0] - 1)) {
        $threadref = $ref[0];
      } else {
        $threadref = false;
      }
    } else {
      $threadref = false;
    }
    preg_match('/Content-Transfer-Encoding:.*/', $header, $te);
    $content_transfer_encoding = explode("Content-Transfer-Encoding: ", $te[0]); 

    preg_match('/.*charset=.*/', $header, $te);
    $content_type = explode("Content-Type: text/plain; charset=", $te[0]);

    $date_interval = $dateoutput[1];
    
    preg_match('/Content-Transfer-Encoding:.*/', $header, $encoding);
    $this_encoding = explode("Content-Transfer-Encoding: ", $encoding[0]);
    if(trim($this_encoding[1]) == "base64") {
      $body=base64_decode($body);
    }
    if($CONFIG['article_database'] == '1') {
      $articlefrom[0] = $data[3];
    } else { 
      preg_match('/From:.*/', htmlspecialchars($header), $articlefrom);
      $isfrom = explode("From: ", $articlefrom[0]);    
      $articlefrom[0] = $isfrom[1];
    }
    $fromoutput = explode("<", html_entity_decode($articlefrom[0]));

// Just an email address?
    if(strlen($fromoutput[0]) < 2) {
	preg_match("/\<([^\)]*)\@/", html_entity_decode($articlefrom[0]), $fromaddress);
	$fromoutput[0] = $fromaddress[1];
    }
    if(strpos($fromoutput[0], "(")) {
	preg_match("/\(([^\)]*)\)/", html_entity_decode($articlefrom[0]), $fromaddress);
	$fromoutput[0] = $fromaddress[1];
    }

    if(($results % 2) != 0){
	$this_output = '<tr class="np_result_line1"><td class="np_result_line1" style="word-wrap:break-word";>';
    } else {
	$this_output = '<tr class="np_result_line2"><td class="np_result_line2" style="word-wrap:break-word";>';
    }
    $this_output = '<p class=np_ob_subject>';
    if($threadref) {
      $this_output.= '<b><a href="'.$url.'">'.mb_decode_mimeheader($output[1]).'"</a></b><font class="np_ob_group"><a href="article-flat.php?id='.$refid[number].'&group='.rawurlencode($refid[newsgroup]).'#'.$refid[number].'"> (thread)</a></font>'."\r\n"; 
    } else {
      $this_output.= '<b><a href="'.$url.'">'.mb_decode_mimeheader($output[1])."</a></b>\r\n";
    }
    $this_output.= '</p><p class=np_ob_group>';
    $this_output.= '<a href="'.$groupurl.'"><span class="visited">'.$groupname.'</span></a>';
    $this_output.= '</p>';
    if((isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true) && (strpos($fromoutput[0], '@') !== false)) {
      $poster_name = truncate_email($fromoutput[0]);
    } else {
      $poster_name = $fromoutput[0]; 
    }
  if($local_poster) {
    $this_output.= '<p class=np_ob_posted_date>Posted: '.$date_interval.' by: <i>'.create_name_link(mb_decode_mimeheader($poster_name)).'</i></p>';
  } else {
    $this_output.= '<p class=np_ob_posted_date>Posted: '.$date_interval.' by: '.create_name_link(mb_decode_mimeheader($poster_name)).'</p>'; 
  }
    # Try to display useful snippet
	if($stop=strpos($body, "begin 644 "))
		$body=substr($body, 0, $stop);
    $body = quoted_printable_decode($body);
    $mysnippet = recode_charset($body, $content_type[1], "utf8");
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
		$mysnippet = substr($mysnippet, $snipstart + 1, $snippetlength);
	} else {
		$mysnippet = substr($mysnippet, 0, $snippetlength);
	}
    $this_output.= "<p class=np_ob_body>".htmlspecialchars($mysnippet, ENT_QUOTES)."</p>\r\n";
    $this_output.= '</td></tr>';
    $this_overboard[$thismsgid] = $this_output;

    if($results++ > ($maxdisplay - 2))
	  break;
}
    if(isset($cached_overboard) && isset($this_overboard)) {
      $new_overboard = array_merge($this_overboard, $cached_overboard);
      $new_overboard = array_slice($new_overboard, 0, $maxdisplay);
      file_put_contents($cachefile, serialize($new_overboard));
    } elseif(isset($this_overboard)) {
      $new_overboard = $this_overboard;
      file_put_contents($cachefile, serialize($new_overboard));
    } else {
      $new_overboard = $cached_overboard;
    }
    if(isset($cachedate)) {
      touch($cachefile, $cachedate);
    }
    $results = 0;

    foreach($new_overboard as $result) {

    if(($results % 2) != 0){
        echo '<tr class="np_result_line1"><td class="np_result_line1" style="word-wrap:break-word";>';
      } else {
        echo '<tr class="np_result_line2"><td class="np_result_line2" style="word-wrap:break-word";>';
      }
      echo $result;
      if($results++ > ($maxdisplay - 2))
	    break;
    }

    show_overboard_footer(null, $results, null);

echo '</body></html>';

function show_overboard_header($grouplist) {
  global $text_thread, $text_article, $file_index, $file_thread;

if (isset($_GET['thisgroup'])) {
    echo '<h1 class="np_thread_headline">';
    echo '<a href="'.$file_index.'" target='.$frame['menu'].'>'.basename(getcwd()).'</a> / ';
    echo '<a href="'.$file_thread.'?group='.rawurlencode($grouplist[0]).'" target='.$frame[content].'>'.htmlspecialchars(group_displaY_name($grouplist[0])).'</a> / ';
    echo ' latest</h1>';
    echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
// Refresh button
    echo '<td>';
    echo '<form action="overboard.php">';
    echo '<input type="hidden" name="thisgroup" value="'.$_GET['thisgroup'].'"/>';
    echo '<button class="np_button_link" type="submit">'.$text_article["refresh"].'</button>';
    echo '</form>';
    echo '</td>';
// Article List button
    echo '<td>';
    echo '<form action="'.$file_thread.'">';
    echo '<input type="hidden" name="group" value="'.$grouplist[0].'"/>';
    echo '<button class="np_button_link" type="submit">'.htmlspecialchars(group_display_name($grouplist[0])).'</button>';
    echo '</form>';
    echo '</td>';
// Newsgroups button (hidden)
  if(isset($frames_on) && $frames_on === true) {
    echo '<td>';
    echo '<form action="'.$file_index.'">';
    echo '<button class="np_button_hidden" type="submit">'.$text_thread["button_grouplist"].'</button>';
    echo '</form>';
    echo '</td>';
  }
    echo '<td width=100%></td></tr></table>';
} else {
    echo '<h1 class="np_thread_headline">';
    echo '<a href="'.$file_index.'" target='.$frame['menu'].'>'.basename(getcwd()).'</a> / ';
    echo 'latest messages</h1>';
    echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
// Refresh button
    echo '<td>';
    echo '<form action="overboard.php">';
    echo '<button class="np_button_link" type="submit">'.$text_article["refresh"].'</button>';
    echo '</form>';
    echo '</td>';
// Newsgroups button (hidden)
  if(isset($frames_on) && $frames_on === true) {
    echo '<td>';
    echo '<form action="'.$file_index.'">';
    echo '<button class="np_button_hidden" type="submit">'.$text_thread["button_grouplist"].'</button>';
    echo '</form>';
    echo '</td>';
  }
    echo '<td width=100%></td></tr></table>';
}
}

function show_overboard_footer($stats, $results, $iscached) {
    echo '</table>';
    echo "<p class=np_ob_tail><b>".$results."</b> recent articles found.</p>\r\n";
    #echo "<center><i>Rocksolid Overboard</i> version ".$version;
    include "tail.inc";
    if($iscached) {
      echo "<p class=np_ob_tail><font size='1em'>cached copy: ".date("D M j G:i:s T Y", $stats[9])."</font></p>\r\n";
    }
}
?>
