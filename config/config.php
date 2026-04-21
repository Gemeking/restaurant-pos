<?php
// Application configuration
define('APP_NAME',  'Restaurant POS');
define('TAX_RATE',  0.15);   // 15% VAT

// Dynamically detect BASE_URL so the app works in any XAMPP subfolder
(function() {
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script    = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    preg_match('#^(/.*?/restaurant_pos)(?=/|$)#i', $script, $m);
    $base_path = $m[1] ?? '/restaurant_pos';
    define('BASE_URL', $protocol . '://' . $host . $base_path . '/');
})();

// Session timeout in seconds (8 hours)
define('SESSION_TIMEOUT', 28800);
