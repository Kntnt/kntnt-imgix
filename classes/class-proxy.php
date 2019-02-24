<?php

namespace Kntnt\Imgix;

require_once __DIR__ . '/class-imgix.php';

class Proxy {

    public function __construct($plugin) { }

    public function run() {
        $img = substr($_SERVER['REQUEST_URI'], strlen(Plugin::wp_dir_rel_site()) + 2);
        $imgix_url = (new Imgix)->translate_url($img);
        if ($imgix_url !== $img) {
            Plugin::debug('Redirect from %1$s to %2$s', $_SERVER['REQUEST_URI'], $imgix_url);
            header("Location: $imgix_url");
            die();
        }
        else {
            Plugin::debug("%s not found", $_SERVER['REQUEST_URI']);
            header('HTTP/1.0 404 Not Found');
            die('404 Not Found');
        }
    }

}
