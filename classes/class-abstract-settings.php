<?php

namespace Kntnt\Imgix;

abstract class Abstract_Settings {

  public function run() {
    add_action('admin_menu', [$this, 'add_options_page']);
  }
  
  // Add settings page to the option menu.
  public function add_options_page() {
    add_options_page($this->title(), $this->title(), $this->capability(), Plugin::ns(), [$this, 'show_settings_page']);
  }
  
  // Show settings page and update options.
  public function show_settings_page() {
  
    Plugin::trace('Menu callback: show_settings_page');

    // Abort if current user has not permission to access the settings page.
    if (!current_user_can('manage_options')) wp_die(__('Unauthorized use.', 'kntnt-imgix'));

    // Update options if the page is showned after a form post.
    if (isset($_POST[Plugin::ns()])) {

      // Abort if the form's nonce is not correct or expired.
      if (!wp_verify_nonce($_POST['_wpnonce'], Plugin::ns())) wp_die(__('Nonce failed.', 'kntnt-imgix'));

      // Update options.
      $this->update_options($_POST[Plugin::ns()]);

    }

    // Render settings page.
    $ns = Plugin::ns();
    $title = $this->title();
    $fields = $this->fields();
    $values = Plugin::option();
    
    // Add default values.
    foreach ($fields as $id => $field) {
      if (!isset($values[$id]) && isset($field['default'])) {
        $values[$id] = $field['default'];
      }
    }
    
    include Plugin::plugin_dir('partials/settings-page.php');

  }
  
  // Returns necessary capability to access the settings page. 
  protected  function capability() {
    return 'manage_options';
  }

  // Returns title used as menu item and as head of settings page.
  abstract protected  function title();
  
  // Returns all fields used on the settigs page.
  abstract protected  function form();
  
  private function fields() {
  
    $fields = $this->form();

    if (defined('WP_DEBUG') && WP_DEBUG) {
      $fields['loglevel'] = [
        'type'        => 'select',
        'options'     => array_combine(array_keys(Plugin::LOGLEVELS), array_keys(Plugin::LOGLEVELS)),
        'label'       => __('Logging', 'kntnt-imgix'),
        'description' => __("This option is only available when <code>WP_DEBUG</code> is <code>true</code> (which it is since you see this message). Select the minimum level of severity required for this plugin to write to WordPress standard error log. You should choose <strong>WARNING</strong> or higher severity if you are not debugging this plugin. On a production site, you should really turn off debugging completely (set <code>WP_DEBUG = false </code> in wp-config.php), or at least select <strong>FATAL</strong> or <strong>QUIET</strong> here.", 'kntnt-imgix'),
        'sanitizer'   => function($loglevel) {
          return isset($loglevel) ? $loglevel : Plugin::option('loglevel', 'QUIET');
        },
      ];
    }

    return $fields;

  }
    
  private function update_options($opt) {
    $fields = $this->fields();
    foreach($opt as $id => &$val) {
      if (isset($fields[$id]['sanitizer'])) {
        $sanitizer = $fields[$id]['sanitizer'];
        $opt[$id] = $sanitizer($val);
      }
    }    
    update_option(Plugin::ns(), $opt);
  }
  
}
