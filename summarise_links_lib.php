<?php
// summarise_links_lib.php - Shared library for link summarisation and cache management

if (!function_exists('extract_urls')) {
    function extract_urls($text) {
        $urls = [];
        // Find http(s) links
        preg_match_all('/https?:\/\/[^\s<>"]+/i', $text, $matches1);
        if (!empty($matches1[0])) {
            $urls = array_merge($urls, $matches1[0]);
        }
        // Find plain dotted domains not part of email addresses
        // (not preceded by @, and not already in $urls)
        preg_match_all('/(?<!@)\b([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/', $text, $matches2);
        foreach ($matches2[1] as $domain) {
            // Skip if already found as a full URL
            $already = false;
            foreach ($urls as $u) {
                if (stripos($u, $domain) !== false) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                $urls[] = 'https://' . $domain;
            }
        }
        return array_unique($urls);
    }
}

if (!function_exists('extract_http_status_code')) {
    function extract_http_status_code($headers) {
        if (!is_array($headers)) {
            return 0;
        }

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                return (int)$matches[1];
            }
        }

        return 0;
    }
}

if (!function_exists('fetch_summary')) {
    function fetch_summary($url, &$host_failures = []) {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            return '[Invalid URL]';
        }

        if (isset($host_failures[$host])) {
            return $host_failures[$host];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true,
                'follow_location' => 0,
                'user_agent' => 'BellaBook-LinkSummary/1.0'
            ]
        ]);

        $html = @file_get_contents($url, false, $context);
        $status_code = extract_http_status_code($http_response_header ?? []);

        if (in_array($status_code, [401, 403, 429], true)) {
            $host_failures[$host] = '[Fetch blocked: HTTP ' . $status_code . ']';
            return $host_failures[$host];
        }

        if ($html === false) {
            $host_failures[$host] = '[Could not fetch]';
            return $host_failures[$host];
        }

        if ($status_code >= 400) {
            $host_failures[$host] = '[Fetch failed: HTTP ' . $status_code . ']';
            return $host_failures[$host];
        }

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        if (!$doc->loadHTML($html)) return '[Unreadable page]';
        $title = $doc->getElementsByTagName('title')->item(0);
        $summary = $title ? $title->nodeValue : '';
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            if (!($meta instanceof DOMElement)) {
                continue;
            }

            if (strtolower($meta->getAttribute('name')) === 'description') {
                $summary .= ' - ' . $meta->getAttribute('content');
                break;
            }
        }
        return $summary ?: '[No summary found]';
    }
}

if (!function_exists('update_summary_cache')) {
    function update_summary_cache($entries_file, $websites_file) {
        // Load or initialize summary cache
        $summary_cache = [];
        if (file_exists($websites_file)) {
            foreach (file($websites_file) as $line) {
                $parts = explode('|', $line, 3);
                if (count($parts) == 3) {
                    $summary_cache[trim($parts[0])] = [
                        'censored' => trim($parts[1]),
                        'summary' => trim($parts[2])
                    ];
                }
            }
        }
        if (!file_exists($entries_file)) {
            return $summary_cache;
        }
        $entries = file($entries_file);
        $all_urls = [];
        foreach ($entries as $entry) {
            $e = splitEntry($entry);
            if ($e['status'] === 'error') continue;
            $location = $e['url'];
            $message  = $e['message'];
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
        $host_failures = [];
        foreach (array_keys($all_urls) as $url) {
            if (!isset($summary_cache[$url])) {
                $censored_url = preg_replace('#^https?://#', '', $url);
                $censored_url = str_replace('.', '[dot]', $censored_url);
                $summary = fetch_summary($url, $host_failures);
                $summary_cache[$url] = [
                    'censored' => $censored_url,
                    'summary' => 'summary: ' . $summary
                ];
                $new_summaries = true;
            }
        }
        if ($new_summaries) {
            $fh = fopen($websites_file, 'w');
            foreach ($summary_cache as $url => $data) {
                fwrite($fh, $url . '|' . $data['censored'] . '|' . str_replace(["\r", "\n"], ' ', $data['summary']) . "\n");
            }
            fclose($fh);
        }
        return $summary_cache;
    }
}

if (!function_exists('format_summary_cache_html')) {
    function format_summary_cache_html($summary_cache) {
        $out = "<table border='1' cellpadding='4' style='border-collapse:collapse;'>";
        $out .= "<tr><th>URL</th><th>Censored</th><th>Summary</th></tr>";
        foreach ($summary_cache as $url => $data) {
            $out .= "<tr>";
            $out .= "<td>" . htmlspecialchars($url) . "</td>";
            $out .= "<td>" . htmlspecialchars($data['censored']) . "</td>";
            $out .= "<td>" . htmlspecialchars($data['summary']) . "</td>";
            $out .= "</tr>";
        }
        $out .= "</table>";
        return $out;
    }
}
