<?php

namespace Kntnt\Imgix\Proxy_Bootstrap;

// Find WordPress root.
$dir = __DIR__;
while (!file_exists("$dir/wp-load.php")) {
  $dir = dirname($dir);
}

// Load WordPress without the theme.
require_once $dir . '/wp-load.php';
require_once $dir . '/wp-admin/includes/plugin.php';
require_once $dir . '/wp-admin/includes/file.php';

// Make sure this is a legitimate call of the script
$ns = basename(dirname(__FILE__));
if (!is_plugin_active("$ns/$ns.php") || !isset(get_option($ns, [])['imgix-domain'])) {
  header('HTTP/1.0 404 Not Found');
  die('404 Not Found');
}

// Bootstrap the plugin.
require_once __DIR__ . "/$ns.php";
