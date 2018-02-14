<?php

namespace Kntnt\Imgix;

class Rewrite {

  public function __construct($plugin) {}
  
  public function run() {
  
    // Write rewrite rules to .htaccess on activation.
    register_activation_hook(Plugin::plugin_file(), function() {
      add_filter('mod_rewrite_rules', [$this, 'mod_rewrite_rules_filter']);
      flush_rewrite_rules();
    });

    // Remove rewrite rules from .htaccess on deactivation.
    register_deactivation_hook(Plugin::plugin_file(), function() {
      remove_filter('mod_rewrite_rules', [$this, 'mod_rewrite_rules_filter']);
      flush_rewrite_rules();
    });

    // Make rewrite rules available in case of flush.
    add_filter('mod_rewrite_rules', [$this, 'mod_rewrite_rules_filter']);

  }
  
  public function mod_rewrite_rules_filter($rules) {
  
    Plugin::trace('Filter: mod_rewrite_rules');

    $rules = explode("\n", $rules);

    // Get the new rules.
    $upload_dir = Plugin::uploads_dir_rel_site();
    $plugin_dir = Plugin::plugin_dir_rel_site();
    ob_start();
    include Plugin::plugin_dir('partials/htaccess.php');
    $new_rules = explode("\n", ob_get_clean());

    // Merge the new rules into the existing one.
    $i = count($rules);
    $found_rewrite_rule = false;
    while (--$i) {
      if (!$found_rewrite_rule) {
        $found_rewrite_rule = (strncmp($rules[$i], 'RewriteRule', 11) == 0);
      }
      elseif (strncmp($rules[$i], 'RewriteCond', 11) != 0) {
        break;
      }
    }
    array_splice($rules, $i + 1, 0, $new_rules);
    $rules = array_filter($rules); // Strictly not necessary; it prettifies.
    $rules = implode("\n", $rules);

    Plugin::debug("Add following rewrite rules to .htaccess:\n%s", $rules);
    return $rules;

  }
  
}
