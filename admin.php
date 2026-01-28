<?php
// Utility: Convert all literal \n (backslash+n) to <br /> for display
if (!function_exists('display_with_newlines')) {
function display_with_newlines($text) {
	$text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	// Convert literal \n (backslash+n) to <br />
	$text = str_replace('\\n', '<br />', $text);
	// Convert any real newlines (legacy) to <br />
	$text = nl2br($text);
	return $text;
}
}
//----------------------------------------------------------------------------- 
// BellaBook Copyright © Jem Turner 2004-2007,2008 unless otherwise noted
// http://www.jemjabella.co.uk/
// THIS FILE HAS BEEN MODIFIED FROM THE ORIGINAL VERSION TO ALLOW FOR 
// ADDITIONAL FEATURES
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License. See README.txt
// or LICENSE.txt for more information.
//-----------------------------------------------------------------------------

require_once('config.php');

// Plain text report handler (must be before main admin logic)
if (isset($_COOKIE['timotheus_guestbook']) && isset($_GET['p']) && $_GET['p'] == 'emailreport' && !empty($enable_email_report)) {
    if ($_COOKIE['timotheus_guestbook'] == hash('sha256', $admin_pass.$secret)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Messages for Timotheus\n\n";
        if (file_exists(ENTRIES)) {
            // Load websites.txt cache for censored URLs and summaries
            $websites_cache = [];
            $websites_file = __DIR__ . '/websites.txt';
            if (file_exists($websites_file)) {
                foreach (file($websites_file) as $line) {
                    $parts = explode('|', $line, 3);
                    if (count($parts) == 3) {
                        $websites_cache[trim($parts[0])] = [
                            'censored' => trim($parts[1]),
                            'summary' => trim($parts[2])
                        ];
                    }
                }
            }
            $entries = file(ENTRIES);
            foreach ($entries as $entry) {
                list($name, $email, $location, $date, $ip, $message) = preg_split("/,(?! )/", $entry);
                $message = trim($message, "\"\x00..\x1F");
                $location = trim($location, "\"\x00..\x1F");
                // Replace URLs in location and message with censored/summary if available
                $all_urls = [];
                preg_match_all('/https?:\/\/[\w\.-]+(?:\/[\w\.-]*)*/i', $location, $loc_urls);
                preg_match_all('/https?:\/\/[\w\.-]+(?:\/[\w\.-]*)*/i', html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $msg_urls);
                $all_urls = array_unique(array_merge($loc_urls[0], $msg_urls[0]));
                foreach ($all_urls as $url) {
                    if (isset($websites_cache[$url])) {
                        $censored = '[' . $websites_cache[$url]['censored'] . '] ' . $websites_cache[$url]['summary'];
                        $location = str_replace($url, $censored, $location);
                        $message = str_replace($url, $websites_cache[$url]['summary'] . ' [' . $websites_cache[$url]['censored'] . ']', $message);
                    } else {
                        $censored_url = preg_replace('#^https?://#', '', $url);
                        $censored_url = str_replace('.', '[dot]', $censored_url);
                        $location = str_replace($url, '[' . $censored_url . ']', $location);
                        $message = str_replace($url, '[' . $censored_url . ']', $message);
                    }
                }
				// Decode HTML entities and convert <br> and \n to newlines
				$message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$message = preg_replace('/<br\s*\/?>/i', "\n", $message);
				$message = str_replace('\\n', "\n", $message);
				// Remove any remaining HTML tags
				$message = strip_tags($message);
				// Output as UTF-8 to preserve emoji and all Unicode characters
                echo "Name: ".trim($name)."\n";
                if (!empty($location)) echo "Location: ".trim($location)."\n";
                echo "Date: ".trim($date)."\n";
                echo wordwrap(trim($message), 78)."\n";
                echo str_repeat("-", 60)."\n";
            }
        } else {
            echo "No messages found.";
        }
        exit;
    }
}

if (isset($_COOKIE['timotheus_guestbook'])) {
	// Security: Use SHA256 instead of MD5
	if ($_COOKIE['timotheus_guestbook'] == hash('sha256', $admin_pass.$secret)) {
		if (isset($_GET['p'])) $page = $_GET['p'];
		else $page = NULL;
		
		// Security: Strict file validation to prevent path traversal
		$allowed_files = array("entries.txt", "tempentries.txt");
		if (!isset($_GET['file']) || !in_array($_GET['file'], $allowed_files, true) || strpos($_GET['file'], '..') !== false || strpos($_GET['file'], '/') !== false || strpos($_GET['file'], '\\') !== false) {
			$_GET['file'] = null;
		}
		
		doAdminHeader();
		switch($page) {
		case "manageentries":
			echo "<p style='color: red;'><strong>Note:</strong> Do not try to delete multiple entries at once. Due to the setup of the guestbook this will cause the wrong entries to be deleted!</p> \n\n";
			if (filesize($_GET['file']) > 0) {
				/* More of Katy's hacky bit for pagination! */

				$entries = file($_GET['file']);
				$count = count($entries);

				echo '<p style="text-align: center;">'.$count.' entries | ';
				$numpages = ceil($count/$perpage);

				echo "pages: ";
				for ($x=1; $x<=$numpages; $x++) {
					if (isset($_GET['page']) && $x == $_GET['page'] || (!isset($_GET['page']) &&  $x == 1))
						echo '<strong>'.$x.'</strong>';
					else
						echo '<a href="admin.php?p=manageentries&amp;file='. $_GET['file'] .'&amp;page='.$x.'">'.$x.'</a> ';
				}
				echo  "</p> \n\n ";
	
				if (isset($_GET['page']) && is_numeric($_GET['page'])) $i = $perpage * ($_GET['page'] - 1);
				else $i = 0;

				$end = $i + $perpage;
	
				if ($end > $count) $end=$count;
?>
				<form action="admin.php?p=appentries" method="post">
				<table>
<?php
				// Security: Generate stronger CSRF token per session
				if (session_status() == PHP_SESSION_NONE) session_start();
				if (!isset($_SESSION['csrf_token'])) {
					$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
				}
				$csrf_token = hash('sha256', $_SESSION['csrf_token'].$secret);
				
				while ($i < $end) {
					list($name,$email,$location,$date,$ip,$message) = preg_split("/,(?! )/", $entries[$i]);
				
					$email = fixEmail($email);
					$message = trim(stripslashes($message), "\"\x00..\x1F");
					$location = trim(stripslashes($location), "\"\x00..\x1F");
					// Convert all literal \n (backslash+n) to <br /> for display (call last)
					$message_display = display_with_newlines(emoticonise(linebreaker($message)));
?>
					<tr>
						<td>
							<input type="hidden" name="hashy" id="hashy" value="<?php echo $csrf_token; ?>">

							<strong>Name:</strong> <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?><br>
							<strong>E-mail:</strong> <a href="mailto:<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></a><br>
							<?php if (!empty($location)) : ?><strong>Location:</strong> <?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?><br><?php endif; ?>
							<strong>Date:</strong> <?php echo htmlspecialchars(date($dateformat, strtotime($date)), ENT_QUOTES, 'UTF-8'); ?><br>
							<strong>IP:</strong> <a href="http://www.geobytes.com/IpLocator.htm?GetLocation&amp;ipaddress=<?php echo urlencode($ip); ?>"><?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?></a><br>
							<br>
							<a href="admin.php?p=editentry&amp;entry=<?php echo $i; ?>&amp;file=<?php echo $_GET['file']; ?>">Edit Entry</a><br>
							<a href="admin.php?p=delentry&amp;entry=<?php echo $i; ?>&amp;file=<?php echo $_GET['file']; ?>" onclick="javascript:return confirm('Are you sure you want to delete this entry?')">Delete Entry</a><br>
							<?php if ($_GET['file'] == "tempentries.txt") : ?>
							<input type="checkbox" class="check" name="appr[<?php echo $i; ?>]" value="<?php echo $i; ?>"> Approve
							<?php endif; ?>
						</td>
						<td>
							<?php echo $message_display; ?>
						</td>
					</tr>
<?php
					$i++;
				}
?>
				</table>
				<?php if ($_GET['file'] == "tempentries.txt") : ?>
					<p><input type="submit" name="submit" id="submit" value="Approve"></p>
				<?php endif; ?>
				</form>
<?php
			} else {
				echo "<p>No entries to manage!</p>";
			}
		break;
		case "appentries":
			// Security: Validate CSRF token from session
			if (session_status() == PHP_SESSION_NONE) session_start();
			if (!isset($_POST['hashy']) || !isset($_SESSION['csrf_token']) || $_POST['hashy'] != hash('sha256', $_SESSION['csrf_token'].$secret)) exit("<p>Invalid CSRF token.</p>");
			
			if (isset($_POST['appr']) && is_array($_POST['appr'])) {
				$pending = file(TEMPENTRIES);
				$approved = array();
				
				foreach ($_POST['appr'] as $entry => $id) {
					if (is_numeric($id) && array_key_exists($id, $pending)) {
						$approved[] = $pending[$id];
						unset($pending[$id]);
					}
				}
				$pending = implode("", $pending);
				doWrite(TEMPENTRIES, $pending, "w");
				
				$newentries = implode("", $approved) . "\r\n";
				sign_gbook(ENTRIES, $newentries);
				
				echo "<p>Selected entries now 'approved'.</p>";
			}
		break;
		case "editentry":
			if ($_SERVER['REQUEST_METHOD'] == "POST") {
				if (!isset($_POST['hashy']) || $_POST['hashy'] != md5(date("H").$secret)) exit("<p>Invalid hashy token.</p>");
				
				foreach ($_POST as $key => $val) {
					$$key = cleanUp($val);
				}
				// Normalize all line breaks to \n (backslash+n), remove <br> tags, and strip HTML
				$comments = str_replace(["\r\n", "\r"], "\n", $comments);
				$comments = preg_replace('/<br\s*\/?>/i', "\n", $comments);
				$comments = strip_tags($comments);
				$comments = str_replace('"', "'", $comments);
				$comments = preg_replace("/\\n{3,}/", "\\n\\n", $comments);
				$comments = trim($comments);
				// Store as literal \n in file
				$comments = str_replace("\n", "\\n", $comments);

				$editedEntry = $name . "," . breakEmail($email) . "," . $url . "," . $date . "," . $ip . "," . "\"$comments\"" . "\n";
				
				$entries = file($file);
				$entries[$gbentry] = $editedEntry;
				$entries = trim(implode($entries));

				doWrite($file, $entries, "w");

				echo '<p>Entry edited. <a href="admin.php">Return to admin</a> / <a href="admin.php?p=manageentries&amp;file='.$file.'">manage more</a>?</p>';
				exit(doAdminFooter());
			}
			echo "<p>Note: editing an entry that is in moderation will not approve it. You must do this separately.</p>";

			if (!isset($_GET['entry']) || $_GET['entry'] == "" || !is_numeric($_GET['entry'])) {
				echo "<h4>Error</h4>\r\n<p>You didn't select a valid entry.</p>";
				exit(include('footer.php'));
			} elseif (!isset($_GET['file']) || $_GET['file'] == "" || !file_exists($_GET['file'])) {
				echo "<h4>Error</h4>\r\n<p>You didn't select a valid file.</p>";
				exit(include('footer.php'));
			}
			$entries = file($_GET['file']);

			list($name,$email,$url,$odate,$ip,$message) = preg_split("/,(?! )/", $entries[$_GET['entry']]);
			
			$email = fixEmail($email);
			$message = str_replace("<br /><br />", "\r\n\r\n", trim(stripslashes($message), "\"\x00..\x1F"));
?>
			<form action="admin.php?p=editentry" method="post">
			<p>
				<input type="hidden" name="hashy" id="hashy" value="<?php echo md5(date("H").$secret); ?>">
				<input type="hidden" name="gbentry" id="gbentry" value="<?php echo $_GET['entry']; ?>">
				<input type="hidden" name="file" id="file" value="<?php echo $_GET['file']; ?>">
			
				<input type="text" name="name" id="name" value="<?php echo $name; ?>" /> <label for="name">Name</label><br>
				<input type="text" name="email" id="email" value="<?php echo $email; ?>" /> <label for="email">E-mail</label><br>
				<input type="text" name="url" id="url" value="<?php echo $url; ?>" /> <label for="url">Website</label><br>
				<input type="text" name="date" id="date" value="<?php echo $odate; ?>" /> <label for="date">Date/Time</label> <small>(yyyy-mm-dd)</small><br>
				<input type="text" name="ip" id="ip" value="<?php echo $ip; ?>" readonly="readonly" /> <label for="ip">IP Address</label><br>
				<textarea name="comments" id="comments"><?php echo $message; ?></textarea> <br>
				<input type="submit" id="submit" value="continue" />
			</p>
			</form>
<?php
		break;
		case "delentry":
			if (!isset($_GET['entry']) || $_GET['entry'] == "" || !is_numeric($_GET['entry'])) {
				echo "<h4>Error</h4>\r\n<p>You didn't select a valid entry.</p>";
				exit(include('footer.php'));
			} elseif (!isset($_GET['file']) || $_GET['file'] == "" || !file_exists($_GET['file'])) {
				echo "<h4>Error</h4>\r\n<p>You didn't select a valid file.</p>";
				exit(include('footer.php'));
			}
			
			$entries = file($_GET['file']);

			unset($entries[$_GET['entry']]);
			$entries = implode("", $entries);
			$entries = trim($entries);

			doWrite($_GET['file'], $entries, "w");

			echo '<p>Entry deleted. <a href="admin.php">Return to admin</a> / <a href="admin.php?p=manageentries&amp;file='.$_GET['file'].'">manage more</a>?</p>';
		break;
		case "editbadwords":
			if ($_SERVER['REQUEST_METHOD'] == "POST") {
				if (isset($_POST['spamwd']) && is_array($_POST['spamwd'])) {
					$badwords = array();
					
					foreach ($_POST['spamwd'] as $spamword)
						if (preg_match('/^[A-Za-z0-9]*$/', $spamword))
							$badwords[] = $spamword;
					
					$new = implode("\r\n", $badwords);
					doWrite(SPAMWDS, $new, "w");
					
					echo '<p>Spam words updated. <a href="admin.php?p=editbadwords">Manage bad words</a>?</p>';
				}
				exit(doAdminFooter());
			}
?>
			<h4>Manage Spam Words</h4>
			<p>Add each new word separately: do <strong>not</strong> use commas to separate spam words.</p>
			<form action="admin.php?p=editbadwords" method="post">
			<p>
				<input type="text" name="spamwd[]"><br>
				<input type="text" name="spamwd[]"><br>
				<input type="text" name="spamwd[]"><br>
				<input type="text" name="spamwd[]"><br>
				<input type="text" name="spamwd[]"><br>
<?php
				$spamwords = file(SPAMWDS);
				foreach ($spamwords as $word)
					echo '<input type="text" name="spamwd[]" value="'.htmlspecialchars(trim($word), ENT_QUOTES, 'UTF-8').'"s><br>';
?>
				<input type="submit" name="submit" id="submit" value="Update">
			</p>
			</form>
<?php
		break;
		case "editips":
			if ($_SERVER['REQUEST_METHOD'] == "POST") {
				if (isset($_POST['ip']) && is_array($_POST['ip'])) {
					$existing = file(IPBLOCKLST);
					
					foreach ($_POST['ip'] as $ipadd)
						if (preg_match("^((\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)(?:\.(\d|[1-9]\d|2[0-4]\d|25[0-5]|1\d\d)){3})$^", $ipadd))
							$existing[] = $ipadd;
					
					$new = implode("", $existing);
					doWrite(IPBLOCKLST, $new, "w");
					
					echo "<p>Blocked IPs updated.</p>";
				}
				exit(doAdminFooter());
			}
?>
			<h4>Manage Blocked IP Addresses</h4>
			<p>Add each new word separately: do <strong>not</strong> use commas to separate IPs.</p>
			<form action="admin.php?p=editips" method="post">
			<p>
				<input type="text" name="ip[]"><br>
				<input type="text" name="ip[]"><br>
				<input type="text" name="ip[]"><br>
				<input type="text" name="ip[]"><br>
				<input type="text" name="ip[]"><br>
<?php
				$ipadds = file(IPBLOCKLST);
				foreach ($ipadds as $ip)
					echo '<input type="text" name="ip[]" value="'.$ip.'"><br>';
?>
				<input type="submit" name="submit" id="submit" value="Update">
			</p>
			</form>
<?php
		break;
		default:
?>
			<ul></ul></ul>
			<li><a href="admin.php?p=manageentries&amp;file=entries.txt">Manage Approved Entries</a> (<?php echo countcontents(ENTRIES); ?>)</li>
			<?php if ($moderate == "yes") { ?>
				<li><a href="admin.php?p=manageentries&amp;file=tempentries.txt">Manage Pending Entries</a> (<?php echo countcontents(TEMPENTRIES); ?>)</li>
			<?php } ?>
			</ul>
			
			<ul>
			<li><a href="admin.php?p=editbadwords">Manage Spam Words</a></li>
			<li><a href="admin.php?p=editips">Manage Blocked IPs</a></li>
			<?php if (!empty($enable_email_report)) { ?>
				<li><a href="admin.php?p=emailreport" target="_blank">Generate Plain Text Report for Email</a></li>
			<?php } ?>
			<li><a href="admin.php?p=deletecache" onclick="return confirm('Are you sure you want to delete the websites.txt cache? This cannot be undone.');">Delete Link Summary Cache (websites.txt)</a></li>
			</ul>
<?php
		break;
		case "deletecache":
			$cachefile = __DIR__ . '/websites.txt';
			if (file_exists($cachefile)) {
				if (@unlink($cachefile)) {
					echo '<p>websites.txt cache deleted.</p>';
				} else {
					echo '<p>Could not delete websites.txt. Check file permissions.</p>';
				}
			} else {
				echo '<p>websites.txt cache does not exist.</p>';
			}
			echo '<p><a href="admin.php">Return to admin dashboard</a></p>';
			break;
		}
		doAdminFooter();
		exit;
	} else {
		exit("<p>Bad cookie. Clear 'em out and start again.</p>");
	}
}

if (isset($_GET['p']) && $_GET['p'] == "login") {
	if ($_POST['name'] != $admin_name || $_POST['pass'] != $admin_pass) {
		doAdminHeader();
?>
			<p>Sorry, that username and password combination is not valid. Try again.</p>

	    <form method="post" action="admin.php">
	    Username:<br>
	    <input type="text" name="name"><br>
	    Password:<br>
	    <input type="password" name="pass"><br>
	    <input type="submit" name="submit" value="Login">
	    </form>
<?php
		doAdminFooter();
		exit;
	} else if ($_POST['name'] == $admin_name && $_POST['pass'] == $admin_pass) {
		// Security: Start session and use SHA256 with secure cookie flags
		if (session_status() == PHP_SESSION_NONE) session_start();
		setcookie('timotheus_guestbook', hash('sha256', $_POST['pass'].$secret), time()+(31*86400), '/', '', isset($_SERVER['HTTPS']), true);
		header("Location: admin.php");
		exit;
	} else {
		setcookie('timotheus_guestbook', '', time()-3600, '/', '', isset($_SERVER['HTTPS']), true);
		header("Location: admin.php");
		exit;
	}
}
doAdminHeader();
?>
    <form method="post" action="admin.php?p=login">
    Username:<br>
    <input type="text" name="name"><br>
    Password:<br>
    <input type="password" name="pass"><br>
    <input type="submit" name="submit" value="Login">
    </form>
<?php
doAdminFooter();