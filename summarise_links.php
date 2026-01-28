<?php
// Summarise links in guestbook entries for offline/print use
// Usage: Run this script from the command line or browser (admin only)
// Summaries are cached in websites.txt as real_url|censored_url|summary: summary

require_once('config.php');
require_once('summarise_links_lib.php');

define('WEBSITES', __DIR__ . '/websites.txt');

$summary_cache = update_summary_cache(ENTRIES, WEBSITES);

// Output formatted HTML for browser, plain text for CLI
if (php_sapi_name() === 'cli') {
    // Plain text output (legacy)
    foreach ($summary_cache as $url => $data) {
        echo $url . ' | ' . $data['censored'] . ' | ' . $data['summary'] . "\n";
    }
} else {
    // HTML output for browser
    echo "<h2>Website Summary Cache</h2>";
    echo format_summary_cache_html($summary_cache);
    echo "<p><a href='websites.txt' target='_blank'>Download raw websites.txt</a></p>";
}
