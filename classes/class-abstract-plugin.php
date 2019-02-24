<?php

namespace Kntnt\Imgix;

/**
 * Base class for Kntnt's plugins.
 */
abstract class Abstract_Plugin {

    const LOGLEVELS = [
        'TRACE' => 0,
        'DEBUG' => 1,
        'INFO'  => 2,
        'WARN'  => 3,
        'ERROR' => 4,
        'FATAL' => 5,
        'QUIET' => 9,
    ];

    protected static $ns = null;

    protected static $site_url = null;

    protected static $site_dir = null;

    protected static $wp_url = null;

    protected static $wp_dir = null;

    protected static $wp_dir_rel_site = null;

    protected static $plugin_dir = null;

    protected static $plugin_dir_rel_site = null;

    protected static $plugin_dir_rel_wp = null;

    protected static $plugin_file = null;

    protected static $uploads_url = null;

    protected static $uploads_dir = null;

    protected static $uploads_dir_rel_site = null;

    protected static $uploads_dir_rel_wp = null;

    protected static $context = null;

    protected static $instance = null;

    protected function __construct() {

        // Set some basic properties. Remaning are set on first access.
        static::$ns = strtr(strtolower(__NAMESPACE__), '_\\', '--');
        static::$plugin_dir = self::forward_slashes(dirname(__DIR__));
        static::$plugin_file = static::$plugin_dir . '/' . static::$ns . '.php';

        // Add default loglevel.
        $opt = get_option(static::$ns, []);
        if ( ! isset($opt['loglevel'])) {
            $opt['loglevel'] = 'QUIET';
            update_option(static::$ns, $opt);
        }

        // Install script runs only on install (not activation).
        // Uninstall script runs "magically" on uninstall.
        register_activation_hook(static::$plugin_file, function () {
            if (null === get_option(static::$ns, null)) {
                require_once static::$plugin_dir . '/install.php';
            }
        });

        // L10n.
        load_plugin_textdomain(static::$ns, false, static::$ns . '/languages');
    }

    private function __clone() { }

    private function __wakeup() { }

    // Returns a singleton instance of this class.
    public static function instance($class_name = null) {

        if ( ! isset(static::$instance)) {
            static::$instance = new static;
        }

        if ($class_name) {
            $n = strtr(strtolower($class_name), '_', '-');
            $class_name = __NAMESPACE__ . '\\' . $class_name;
            require_once static::$plugin_dir . "/classes/class-$n.php";
            return new $class_name(static::$instance);
        }

        return static::$instance;

    }

    // Namespace of plugin.
    public static function ns() {
        return static::$ns;
    }

    // TODO: WRITE COMMENT
    public static function site_url($rel_path = '') {
        if ( ! isset(static::$site_url)) {
            static::$site_url = self::str_remove_tail(self::wp_url(), self::wp_dir_rel_site('/'));
        }
        return self::str_join(static::$site_url, $rel_path);
    }

    // Absolute path to servers's document root.
    // If $rel_path is left out or empty, the returned path has no trailing
    // slash. If $rel_path is given, is is appended, with
    // additional leading slash if not included in $rel_path.
    public static function site_dir($rel_path = '') {
        if ( ! isset(static::$site_dir)) {
            static::$site_dir = self::forward_slashes($_SERVER['DOCUMENT_ROOT']);
        }
        return self::str_join(static::$site_dir, $rel_path);
    }

    // URL to WordPress' root. If $rel_path is left out or empty, the returned
    // URL has no trailing slash. If $rel_path is given, is is appended, with
    // additional leading slash if not included in $rel_path.
    public static function wp_url($rel_path = '') {
        if ( ! isset(static::$wp_url)) {
            static::$wp_url = get_site_url(null, '', null);
        }
        return self::str_join(static::$wp_url, $rel_path);
    }

    // Absolute path to WordPress' installation directory.
    // If $rel_path is left out or empty, the returned path has no trailing
    // slash. If $rel_path is given, is is appended, with
    // additional leading slash if not included in $rel_path.
    public static function wp_dir($rel_path = '') {
        if ( ! isset(static::$wp_dir)) {
            static::$wp_dir = rtrim(self::forward_slashes(ABSPATH), '/');
        }
        return self::str_join(static::$wp_dir, $rel_path);
    }

    // TODO: WRITE COMMENT
    public static function wp_dir_rel_site($rel_path = '') {
        if ( ! isset(static::$wp_dir_rel_site)) {
            static::$wp_dir_rel_site = ltrim(self::str_remove_head(self::wp_dir(), self::site_dir()), '/');
        }
        return self::str_join(static::$wp_dir_rel_site, $rel_path);
    }

    // Absolute path of plugin directory. No trailing slash.
    public static function plugin_dir($rel_path = '') {
        return self::str_join(static::$plugin_dir, $rel_path);
    }

    // Path of plugin directory relative the server's document root with no
    // leading slash. If $rel_path is left out or empty, the returned path has no
    // trailing slash. If $rel_path is given, is is appended, with additional
    // leading slash if not included in $rel_path.
    public static function plugin_dir_rel_site($rel_path = '') {
        if ( ! isset(static::$plugin_dir_rel_site)) {
            static::$plugin_dir_rel_site = self::str_remove_head(self::plugin_dir(), self::site_dir('/'));
        }
        return self::str_join(static::$plugin_dir_rel_site, $rel_path);
    }

    // TODO: WRITE COMMENT
    public static function plugin_dir_rel_wp($rel_path = '') {
        if ( ! isset(static::$plugin_dir_rel_wp)) {
            static::$plugin_dir_rel_wp = self::str_remove_head(self::plugin_dir(), self::wp_dir('/'));
        }
        return self::str_join(static::$plugin_dir_rel_wp, $rel_path);
    }

    // Absolute path of plugin main file. No trailing slash.
    public static function plugin_file() {
        return static::$plugin_file;
    }

    // URL to upload root. No trailing slash (e.g. /).
    // If $rel_path is left out or empty, the returned URL has no trailing
    // slash. If $rel_path is given, is is appended, with
    // additional leading slash if not included in $rel_path.
    public static function uploads_url($rel_path = '') {
        if ( ! isset(static::$uploads_url)) {
            static::$uploads_url = wp_upload_dir()['baseurl'];
        }
        return self::str_join(static::$uploads_url, $rel_path);
    }

    // Absolute path to WordPress' uploads directory.
    // If $rel_path is left out or empty, the returned path has no trailing
    // slash. If $rel_path is given, is is appended, with
    // additional leading slash if not included in $rel_path.
    public static function uploads_dir($rel_path = '') {
        if ( ! isset(static::$uploads_dir)) {
            static::$uploads_dir = wp_upload_dir()['basedir'];
        }
        return self::str_join(static::$uploads_dir, $rel_path);
    }

    // Path to WordPress' uploads directory relative the server's document root
    // with no leading slash. If $rel_path is left out or empty, the returned
    // path has no trailing slash. If $rel_path is given, is is appended, with
    // additional leading slash if not included in $rel_path.
    public static function uploads_dir_rel_site($rel_path = '') {
        if ( ! isset(static::$uploads_dir_rel_site)) {
            static::$uploads_dir_rel_site = self::str_remove_head(static::uploads_dir(), static::site_dir('/'));
        }
        return self::str_join(static::$uploads_dir_rel_site, $rel_path);
    }

    // TODO: WRITE COMMENT
    public static function uploads_dir_rel_wp($rel_path = '') {
        if ( ! isset(static::$uploads_dir_rel_wp)) {
            static::$uploads_dir_rel_wp = self::str_remove_head(static::uploads_dir(), static::wp_dir('/'));
        }
        return self::str_join(static::$uploads_dir_rel_wp, $rel_path);
    }

    // Returns the context in which the plugin is executed: 'index' => public
    // interface and 'admin' => admin interface and other alternatives including
    // login and cron.
    public static function context() {
        if ( ! isset(static::$context)) {
            if (defined('WP_CLI') && WP_CLI) {
                static::$context = 'wp-cli';
            }
            else {
                $ctx = $_SERVER['SCRIPT_FILENAME'];
                $ctx = substr($ctx, 0, -4);
                $ctx = substr($ctx, strlen(ABSPATH));
                while (($c = dirname($ctx)) !== '.') {
                    $ctx = $c;
                }
                $ctx = self::str_remove_substr($ctx, 'wp-');
                if ($ctx === 'content') {
                    $ctx = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_FILENAME);
                }
                static::$context = $ctx;
            }
        }
        return static::$context;
    }

    // If $key is left out or empty, e.g. `Plugin::option()`, returns an array
    // with this plugins all options if existing, otherwise $default.
    // If $key is includd and non-empty, e.g. `Plugin::option('key')`, returns
    // `Plugin::option()['key']` if the aforementioned array has an index 'key',
    // otherwise $default.
    public static function option($key = '', $default = false) {
        $opt = get_option(static::$ns, null);
        if ($opt === null) {
            return $default;
        }
        if (empty($key)) {
            return $opt;
        }
        return isset($opt[$key]) ? $opt[$key] : $default;
    }

    // Joins the left hand side string ($lhs) and the righ hand string ($rhs)
    // with a single occurance of $separator in between if both are non-empty.
    // If $separator is left out, `/` is used; thus making
    // str_join($path, $rel_path) returning a path consisting of $path followed
    // by $rel_path.
    public static final function str_join($lhs, $rhs, $separator = '/') {
        if ($lhs && $rhs) {
            $lhs = rtrim($lhs, $separator) . $separator . ltrim($rhs, $separator);
        }
        return $lhs;
    }

    // Returns $str with the n first charactes removed, where n is the length
    // of $head.
    public static final function str_remove_head($str, $head) {
        return substr($str, strlen($head));
    }

    // Returns $str with the n last charactes removed, where n is the length
    // of $tail.
    public static final function str_remove_tail($str, $tail) {
        return substr($str, 0, -strlen($tail));
    }

    // Returns $str with all ocuuranes of $substr removed.
    public static final function str_remove_substr($str, $substr) {
        return str_ireplace($substr, '', $str);
    }

    // Replaces \ with / in $str.
    public static final function forward_slashes($str) {
        return strtr($str, '\\', '/');
    }

    // Writes $message to log if loglevel is set to 'TRACE'.
    // Use TRACE for finest-grained informational events.
    public static final function trace($message, ...$args) {
        static::log('TRACE', $message, $args);
    }

    // Writes $message to log through sprintf() if loglevel is set to 'DEBUG' or lower.
    // Arrays and objects in $args are replaced with pretty printed strings
    // before $args are used as argument in sprintf().
    // Use DEBUG for fine-grained informational events that are most useful to
    // debug the plugin.
    public static final function debug($message, ...$args) {
        static::log('DEBUG', $message, $args);
    }

    // Writes $message to log through sprintf() if loglevel is set to 'INFO' or lower.
    // Arrays and objects in $args are replaced with pretty printed strings
    // before $args are used as argument in sprintf().
    // Use INFO for informational messages that highlight the progress of the
    // plugin at coarse-grained level.
    public static final function info($message, ...$args) {
        static::log('INFO', $message, $args);
    }

    // Writes $message to log through sprintf() if loglevel is set to 'WARN' or lower.
    // Arrays and objects in $args are replaced with pretty printed strings
    // before $args are used as argument in sprintf().
    // Use WARN for potentially harmful situations which still allow the plugin
    // to continue running.
    public static final function warn($message, ...$args) {
        static::log('WARN', $message, $args);
    }

    // Writes $message to log through sprintf() if loglevel is set to 'ERROR' or lower.
    // Arrays and objects in $args are replaced with pretty printed strings
    // before $args are used as argument in sprintf().
    // Use ERROR for error events that might still allow the plugin to continue
    // running.
    public static final function error($message, ...$args) {
        static::log('ERROR', $message, $args);
    }

    // Writes $message to log through sprintf() if loglevel is set to 'FATAL' or lower.
    // Arrays and objects in $args are replaced with pretty printed strings
    // before $args are used as argument in sprintf().
    // Use FATAL for very severe error events that will presumably lead WordPress
    // to abort.
    public static final function fatal($message, ...$args) {
        static::log('FATAL', $message, $args);
    }

    // Write $message to PHP.:s error log.
    private static function log($loglevel, $message, $args) {
        if (defined('WP_DEBUG') && WP_DEBUG && self::LOGLEVELS[self::option('loglevel', 'QUIET')] <= self::LOGLEVELS[$loglevel]) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2];
            $caller = $caller['class'] . '->' . $caller['function'] . '()';
            foreach ($args as &$arg) {
                if (is_array($arg) || is_object($arg)) {
                    $arg = print_r($arg, true);
                }
            }
            $message = sprintf($message, ...$args);
            error_log("$loglevel: $caller: $message");
        }
    }

}
