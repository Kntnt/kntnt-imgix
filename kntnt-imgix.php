<?php

/**
 * Plugin main file.
 *
 * @wordpress-plugin
 * Plugin Name:       Kntnt's Imgix plugin
 * Plugin URI:        https://www.kntnt.com/
 * Description:       Makes WordPress use <a href="https://www.imgix.com/">Imgix</a> to off-load image processing and serving.
 * Version:           1.0.0
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       kntnt-imgix
 * Domain Path:       /languages
 */
 
namespace Kntnt\Imgix;

defined('WPINC') || die;

require_once __DIR__ . '/classes/class-abstract-plugin.php';

final class Plugin extends Abstract_Plugin {

  public function run() {
 
    $this->instance('DNS_Prefetch')->run();
    $this->instance('Image_Editor')->run();

    $ctx = Plugin::context();
    Plugin::debug("Runs in %s mode", $ctx);
    switch ($ctx) {
      case 'index':
        $this->instance('Content_Parser')->run();
        break;
      case 'admin':
        $this->instance('Rewrite')->run();
        $this->instance('Settings')->run();
        break;
      case 'proxy':
        $this->instance('Proxy')->run();
        break;
    }

  }

}

add_action('plugins_loaded', function() {
  Plugin::instance()->run();
});
