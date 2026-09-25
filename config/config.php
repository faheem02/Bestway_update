<?php
// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Karachi');

// Dynamic BASE_URL Detection
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (preg_match('#(/[^/]*Bestway[^/]*)#i', $script, $matches)) {
        return $protocol . $host . $matches[1] . '/';
    }
    
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: '');
        $app_root = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
        if ($doc_root && $app_root) {
            $subpath = trim(str_replace($doc_root, '', $app_root), '/\\');
            if (!empty($subpath)) {
                return $protocol . $host . '/' . $subpath . '/';
            }
            return $protocol . $host . '/';
        }
    }
    
    return $protocol . $host . '/Bestway/';
}

define('BASE_URL', get_base_url());
define('APP_NAME', 'Bestway Medicine Wholesale');
define('APP_SHORT_NAME', 'Bestway Pharma');
define('CURRENCY', 'Rs. ');
define('APP_VERSION', '1.0');
