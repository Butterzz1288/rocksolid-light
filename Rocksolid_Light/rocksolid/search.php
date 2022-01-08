<?php
session_cache_limiter('public');
session_start();

include "config.inc.php";
include "newsportal.php";

throttle_hits();

$snippet_size = 100;

if(!isset($_POST['key']) || !password_verify($CONFIG['thissitekey'], $_POST['key'])) {
include "head.inc"; 

  echo '<h1 class="np_thread_headline">';
  echo '<a href="'.$file_index.'" target='.$frame['menu'].'>'.basename(getcwd()).'</a> / ';
  echo 'search</h1>';
echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
// View Latest button
  if (isset($overboard) && ($overboard == true)) {
    echo '<td>';
    echo '<form target="'.$frame['content'].'" action="overboard.php">';
    echo '<button class="np_button_link" type="submit">'.$text_thread["button_overboard"].'</button>';
    echo '</form>';
    echo '</td>';
  } else {
//    echo htmlspecialchars($CONFIG['title_full']);
  }
  if(isset($_GET['group'])) {
    $searching = $_GET['group'];
  } else {
    $searching = $config_name;
  }
  echo '<body>';
  echo '<table width=100% border="0" align="center" cellpadding="0" cellspacing="1">';
  echo '<tr>';
  echo '<form name="form1" method="post" action="search.php">';
  echo '<td>';
  echo '<table width="100%" align="center" border="0" cellpadding="3" cellspacing="1">';
  echo '<tr>';
  echo '<td colspan="3">Searching <strong>'.$searching.'</strong></td>';
  echo '</tr>';
  echo '<tr></tr>';
  echo '<tr>';
  echo '<td>Search Terms:&nbsp';
  echo '<input name="terms" type="text" id="terms" value="'.$_GET[terms].'"></td>';
  echo '</tr><tr></tr><tr><td>';

if ($_GET['searchpoint'] == 'Poster') {
  if($CONFIG['article_database'] == '1') {
    echo '<input type="radio" name="searchpoint" value="body"/>Body&nbsp;';
  }
  echo '<input type="radio" name="searchpoint" value="subject"/>Subject&nbsp;';
  echo '<input type="radio" name="searchpoint" value="name" checked="checked"/>Poster&nbsp;';
  echo '<input type="radio" name="searchpoint" value="msgid"/>Message-ID';
} else {
  if($CONFIG['article_database'] == '1') {
    echo '&nbsp;<input type="radio" name="searchpoint" value="body" checked="checked"/>Body&nbsp;';
  }
  echo '<input type="radio" name="searchpoint" value="subject"/>Subject&nbsp;';
  echo '<input type="radio" name="searchpoint" value="name"/>Poster&nbsp;';
  echo '<input type="radio" name="searchpoint" value="msgid"/>Message-ID';
}
  echo '</td></tr>';
  echo '<tr>';
  echo '<td><input name="command" type="hidden" id="command" value="Search" readonly="readonly"></td>';
  if(isset($_GET['group'])) {
    echo '<input type="hidden" name="group" value="'.$_GET['group'].'">';
  }
  echo '<input type="hidden" name="key" value="'.password_hash($CONFIG['thissitekey'], PASSWORD_DEFAULT).'">';

?>
</tr>
<tr></tr>
<tr>
<td><input type="submit" name="Submit" value="Search"></td>
</tr>
<tr><td>
<td></td><td></td>
</td></tr>
</table>
</td>
</form>
</tr>
</table>
</body>
</html>

<?php exit(0); } 

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

# Maximum number of articles to show
$maxdisplay = 1000;

$thissite = '.';

$groupconfig=$config_path."/groups.txt";

$title.=' - search results for: '.$_POST[terms];
include "head.inc";

ob_start();
if (isset($_POST['thisgroup'])) {
    echo '<h1 class="np_thread_headline">'.$grouplist[0].' (latest)</h1>';
    echo '<table cellpadding="0" cellspacing="0" width="100%" class="np_buttonbar"><tr>';
// Article List button
    echo '<td>';
    echo '<form action="'.$file_thread.'">';
    echo '<input type="hidden" name="group" value="'.$grouplist[0].'"/>';
    echo '<button class="np_button_link" type="submit">'.$text_article["back_to_group"].'</button>';
    echo '</form>';
    echo '</td>';
// Newsgroups button (hidden)
    echo '<td>';
    echo '<form action="'.$file_index.'">';
    echo '<button class="np_button_hidden" type="submit">'.$text_thread["button_grouplist"].'</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr></table>';
} else { 
    echo '<h1 class="np_thread_headline">';
    echo '<a href="'.$file_index.'" target='.$frame['menu'].'>'.basename(getcwd()).'</a> / ';
    echo 'search results for: '.$_POST['terms'].'</h1>';
    echo '<table cellpadding="0" cellspacing="0" width="100%" class="np_buttonbar"><tr>';
// Newsgroups button (hidden)
    echo '<td>';
    echo '<form action="'.$file_index.'">';
    echo '<button class="np_button_hidden" type="submit">'.$text_thread["button_grouplist"].'</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr></table>';
}
echo '<table cellspacing="0" width="100%" class="np_results_table">';

# Iterate through groups

$results=0;
  if(isset($_COOKIE['tzo'])) {
    $offset=$_COOKIE['tzo'];
  } else {
    $offset=$CONFIG['timezone'];
  }
  $overview = array();
  if($_POST['searchpoint'] == 'body') {
    $overview = get_body_search($group, $_POST['terms']);
  } else { 
    $overview = get_header_search($group, $_POST['terms']);
  }
  foreach($overview as $overviewline) {
/* Find section for links */
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
        if(stripos(trim($overviewline['newsgroup']), trim($group_name[0])) !== false) {
          $section=$menuitem[0];
          break 2;
        }
      }
    }

    fclose($glfp);
		    # Generate link
		    $url = "../".$section."/article-flat.php?id=".$overviewline['number']."&group="._rawurlencode($overviewline['newsgroup'])."#".$overviewline['number'];
		    $groupurl = "../".$section."/thread.php?group="._rawurlencode($overviewline['newsgroup']);
		    $fromoutput = explode("<", html_entity_decode($overviewline['name']));

		// Use local timezone if possible
		$ts = new DateTime(date($text_header["date_format"], $overviewline['date']), new DateTimeZone('UTC'));
      		$ts->add(DateInterval::createFromDateString($offset.' minutes'));

      		if($offset != 0) {
        		$newdate = $ts->format('D, j M Y H:i');
      		} else {
        		$newdate = $ts->format($text_header["date_format"]);
      		}

       		unset($ts);
		    
		$fromline=address_decode(headerDecode($overviewline['name']),"nirgendwo");

      		if (!isset($fromline[0]["personal"])) {
        		$lastname=$fromline[0]["mailbox"];;
      		} else {
        		$lastname=$fromline[0]["personal"];
      		}

		if(($results % 2) != 0){
			    echo '<tr class="np_result_line1"><td class="np_result_line1" style="word-wrap:break-word";>';
		    } else {
			    echo '<tr class="np_result_line2"><td class="np_result_line2" style="word-wrap:break-word";>';
		    }

		    echo '<p class=np_ob_subject>';
		    echo '<b><a href="'.$url.'">'.htmlspecialchars(mb_decode_mimeheader($overviewline['subject']))."</a></b>\r\n";
		    echo '</p><p class=np_ob_group>';
		    echo '<a href="'.$groupurl.'">'.$overviewline['newsgroup'].'</a>';
		    echo '</p>';
  

    $articlefrom[0] = $overviewline['name'];
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
		    if((isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true) && (strpos($fromoutput[0], '@') !== false)) {
      $poster_name = truncate_email($fromoutput[0]);
    } else {
      $poster_name = $fromoutput[0];
    }
    $poster_name = trim(mb_decode_mimeheader($poster_name), " \n\r\t\v\0\"");
	   	    echo '<p class=np_ob_posted_date>Posted: '.$newdate.' by: '.create_name_link($poster_name).'</p>';
		    if($_POST['searchpoint'] == 'body') {
			$snip = strip_tags(quoted_printable_decode($overviewline['snippet']), '<strong><font><i>');
		    } else {
		        $snip = strip_tags(quoted_printable_decode($overviewline['search_snippet']), '<strong><font><i>');
			$snip = substr($snip, 0, $snippet_size);
		    }
		    echo $snip;
		    echo '</td></tr>';
		    if($results++ > ($maxdisplay - 2))
                        break;
//	  }
}

echo '</table>';
echo "<p class=np_ob_tail><b>".$results."</b> matching articles found.</p>\r\n";
#echo "<center><i>Rocksolid Overboard</i> version ".$version;
include "tail.inc";

$thispage = ob_get_contents();

ob_end_clean();

echo $thispage;

function get_body_search($group, $terms) {
  GLOBAL $CONFIG, $config_name, $spooldir, $snippet_size;
  $terms = preg_replace("/'/", ' ', $terms);
  $terms = trim($terms);
  if($terms[0] !== '"' || substr($terms, -1) !== '"') {
    $terms = preg_replace('/"/', '', $terms);
    $terms = preg_replace("/\ /", '" "', $terms);
    $terms = preg_replace('/"NEAR"/', 'NEAR', $terms);
    $terms = preg_replace('/"AND"/', 'AND', $terms);
    $terms = preg_replace('/"OR"/', 'OR', $terms);
    $terms = preg_replace('/"NOT"/', 'NOT', $terms);
    $terms = '"'.$terms.'"';
  }
    if(isset($_POST['group'])) {
      $grouplist[0] = $_POST['group'];
    } else {
      $local_groupfile=$spooldir."/".$config_name."/local_groups.txt";
      $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    foreach($grouplist as $thisgroup) {
      $name = explode(':', $thisgroup);
      $group=$name[0];
      $database = $spooldir.'/'.$group.'-articles.db3';
      if(!is_file($database)) {
	    continue;
      }
      $dbh = article_db_open($database);
      $stmt = $dbh->prepare("SELECT snippet(search_fts, 6, '<strong><font class=search_result><i>', '</i></font></strong>', '...', $snippet_size) as snippet, newsgroup, number, name, date, subject, rank FROM search_fts WHERE search_fts MATCH 'search_snippet:$terms' ORDER BY rank");
      $stmt->execute();

      while ($row = $stmt->fetch()) {
        $overview[] = $row;
      }
      $dbh = null;
    }
  usort($overview, function($a, $b) {
    return $a['rank'] <=> $b['rank'];
  });
  return $overview;
}

function get_header_search($group, $terms) {
  GLOBAL $CONFIG, $config_name, $spooldir, $snippet_size;
  $terms = preg_replace('/\%/', '\%', $terms);
  $searchterms = "%".$terms."%";
    if(isset($_POST['group']) && $_POST['searchpoint'] != 'msgid') {
      $grouplist[0] = $_POST['group'];
    } elseif($_POST['searchpoint'] != 'msgid') {
        $local_groupfile=$spooldir."/".$config_name."/local_groups.txt";
        $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    } else {
        $local_groupfile=$spooldir."/spoolnews/groups.txt";
        $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    # Prepare search database
    $database = $spooldir.'/articles-overview.db3';
    $table = 'overview';
    $dbh = rslight_db_open($database, $table);
    $overview = array();

    foreach($grouplist as $thisgroup) {
      $name = explode(':', $thisgroup);
      $group=$name[0];
      $article_database = $spooldir.'/'.$group.'-articles.db3';
      if(!is_file($article_database)) {
            continue;
      }
      $article_dbh = article_db_open($article_database);
      $article_stmt = $article_dbh->prepare("SELECT * FROM articles WHERE number=:number");
          if(is_multibyte($_POST['terms'])) {
            $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:group");
            $stmt->bindParam(':group', $group);
            $stmt->execute();
            while($found = $stmt->fetch()) {
		    if(stripos(mb_decode_mimeheader($found[$_POST['searchpoint']]), $_POST['terms']) !== false) {
          $article_stmt->bindParam(':number', $found['number']);
          $article_stmt->execute();
          $found_snip = $article_stmt->fetch();
          $found['search_snippet'] = $found_snip['search_snippet'];
          $found['sort_date'] = $found_snip['date'];
                $overview[] = $found;
              }
            }
          } else {
            $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:group AND ".$_POST['searchpoint']." like :terms ESCAPE '\' ORDER BY date DESC");
	    $stmt->bindParam(':group', $group);
            $stmt->bindParam(':terms', $searchterms);
            $stmt->execute();
            while($found = $stmt->fetch()) {
	      $article_stmt->bindParam(':number', $found['number']);
	      $article_stmt->execute();
	      $found_snip = $article_stmt->fetch();
	      $found['search_snippet'] = $found_snip['search_snippet'];
	      $found['sort_date'] = $found_snip['date'];
              $overview[] = $found;
            }
          }
    $article_dbh = null;
    }
  $dbh = null;
  usort($overview, function($b, $a) {
    return $a['sort_date'] <=> $b['sort_date'];
  });
	return $overview;
}

function highlightStr($haystack, $needle) {
    preg_match_all("/$needle+/i", $haystack, $matches);
    if (is_array($matches[0]) && count($matches[0]) >= 1) {
	foreach ($matches[0] as $match) {
	    $haystack = str_replace($match, '<b>'.$match.'</b>', $haystack);
	}
    }
    return $haystack;
}
?>
</body>
</html>
