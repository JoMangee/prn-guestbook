<?php
// Summarise links in guestbook entries for offline/print use
// Usage: Run this script from the command line or browser (admin only)
// Summaries are cached in websites.txt as URL|summary

require_once('config.php');

define('WEBSITES', __DIR__ . '/websites.txt');

function extract_urls($text) {
    // Simple regex for URLs
    preg_match_all('/https?:\/\/[\w\.-]+(?:\/[\w\.-]*)*/i', $text, $matches);
    return $matches[0];
}

function fetch_summary($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (compatible; GuestbookBot/1.0)'
        ]
    ]);
    $html = @file_get_contents($url, false, $context);
    if (!$html) return '[Could not fetch: ' . $url . ']';
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    if (!$doc->loadHTML($html)) return '[Unreadable page: ' . $url . ']';
    $title = $doc->getElementsByTagName('title')->item(0);
    $summary = $title ? $title->nodeValue : '';
    // Try meta description
    foreach ($doc->getElementsByTagName('meta') as $meta) {
        if (strtolower($meta->getAttribute('name')) === 'description') {
            $summary .= ' - ' . $meta->getAttribute('content');
            break;
        }
    }
    return $summary ?: '[No summary found: ' . $url . ']';
}

// Load or initialize summary cache
$summary_cache = [];
if (file_exists(WEBSITES)) {
    foreach (file(WEBSITES) as $line) {
        $parts = explode('|', $line, 2);
        if (count($parts) == 2) {
            $summary_cache[trim($parts[0])] = trim($parts[1]);
        }
    }
}

if (!file_exists(ENTRIES)) {
    die("No entries found.\n");
}

$entries = file(ENTRIES);
$all_urls = [];
foreach ($entries as $entry) {
    list($name, $email, $location, $date, $ip, $message) = preg_split("/,(?! )/", $entry);
    $message = trim($message, "\"\x00..\x1F");
    $location = trim($location, "\"\x00..\x1F");
    $urls = array_unique(array_merge(
        extract_urls($location),
        extract_urls(html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
    ));
    foreach ($urls as $url) {
        $all_urls[$url] = true;
    }
}

// Fetch and cache new summaries
$new_summaries = false;
foreach (array_keys($all_urls) as $url) {
    if (!isset($summary_cache[$url])) {
        $summary = fetch_summary($url);
        $summary_cache[$url] = $summary;
        $new_summaries = true;
    }
}
if ($new_summaries) {
    $fh = fopen(WEBSITES, 'w');
    foreach ($summary_cache as $url => $summary) {
        fwrite($fh, $url . '|' . str_replace(["\r", "\n"], ' ', $summary) . "\n");
    }
    fclose($fh);
}

// Output report using summaries
foreach ($entries as $entry) {
    list($name, $email, $location, $date, $ip, $message) = preg_split("/,(?! )/", $entry);
    $message = trim($message, "\"\x00..\x1F");
    $location = trim($location, "\"\x00..\x1F");
    $urls = array_unique(array_merge(
        extract_urls($location),
        extract_urls(html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
    ));
    echo "Name: ".trim($name)."\n";
    if (!empty($location)) {
        $loc_out = $location;
        foreach ($urls as $url) {
            if (strpos($location, $url) !== false && isset($summary_cache[$url])) {
                $loc_out = str_replace($url, $summary_cache[$url], $loc_out);
            }
        }
        $loc_out = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $loc_out);
        echo "Location: ".trim($loc_out)."\n";
    }
    echo "Date: ".trim($date)."\n";
    $msg = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    foreach ($urls as $url) {
        if (isset($summary_cache[$url])) {
            $msg = str_replace($url, $summary_cache[$url], $msg);
        }
    }
    $msg = preg_replace('/<br\s*\/?\s*>/i', "\n", $msg);
    $msg = strip_tags($msg);
    $msg = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $msg);
    echo wordwrap(trim($msg), 78)."\n";
    echo str_repeat("-", 60)."\n";
}
