<?php

namespace Kntnt\Imgix;

require_once __DIR__ . '/class-imgix.php';

class DNS_Prefetch {

  public function __construct($plugin) {}
  
  public function run() {
    add_action('wp_head', function() {
      if (Plugin::option('imgix-domain')) {
        printf("<link rel='dns-prefetch' href='%s' />\n", esc_attr('//' . Plugin::option('imgix-domain')));
      }
    }, 1);
  }

}
