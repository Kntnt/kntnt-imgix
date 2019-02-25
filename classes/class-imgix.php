<?php

namespace Kntnt\Imgix;

require_once __DIR__ . '/../lib/Imgix/ShardStrategy.php';
require_once __DIR__ . '/../lib/Imgix/UrlHelper.php';
require_once __DIR__ . '/../lib/Imgix/UrlBuilder.php';

class Imgix {

    private $params = [];

    private $builder;

    static public function get_relpath($abspath) {
        return Plugin::str_remove_head($abspath, Plugin::uploads_dir_rel_wp('/'));
    }

    public function __construct() {

        // Scale and crop.fit scale
        $this->params['fit'] = "scale";

        // Automatic improvements.
        $auto = [];
        if (Plugin::option('automatic-enhancement')) {
            $auto[] = 'enhance';
        }
        if (Plugin::option('aggressive-compression')) {
            $auto[] = 'compress';
        }
        if (Plugin::option('format-negotiation')) {
            $auto[] = 'format';
        }
        $this->params['auto'] = implode(',', $auto);

        // Set quality.
        $this->params['q'] = Plugin::option('remote-quality');

        // Create link builder for Imgix.
        $this->builder = new \Imgix\UrlBuilder(Plugin::option('imgix-domain'));
        $this->builder->setUseHttps(is_ssl());
        if ($imgix_token = Plugin::option('imgix-token')) {
            $this->builder->setSignKey($imgix_token);
        }

    }

    public function parse_html($content) {
        Plugin::debug('Parsing HTML content for local image URLs to translate into Imgix dito.');
        $wp_url = preg_quote(Plugin::wp_url());
        $uploads_dir_rel_wp = preg_quote(Plugin::uploads_dir_rel_wp());
        $pattern = "<(?:img|source)\s+[^>]*src\s*=\s*['\"](?:|$wp_url)/\K($uploads_dir_rel_wp/(?:\d{4}/\d{2}/)?(?:[^'\"]+?)(?:(\d+)x(\d+))?\.(?:jpg|jpeg|gif|png))(?=['\"]\s+[^>]*/>)";
        $pattern = apply_filters('kntnt_imgix_parse_html_pattern', "`$pattern`i", $wp_url, $uploads_dir_rel_wp);
        $content = preg_replace_callback($pattern, function ($matches) {
            return htmlspecialchars($this->translate_url($matches[1], array_slice($matches, 2)));
        }, $content);
        return $content;
    }

    public function translate_url($req_abspath, $matches = []) {

        Plugin::info("Requested image: %s", $req_abspath);

        // Get the requested image size. The requested image is assumed to be
        // the original image if $reg_size is empty. The requested image is
        // assumed to be a resized version of an original image if
        // $reg_size contains the with and height of the requested image.
        $req_size = $this->get_requested_size($req_abspath, $matches);
        if ($req_size) {
            Plugin::debug("Requested image size: %sx%s", $req_size['w'], $req_size['h']);
        }

        // Get original image's path relative Wordpress home directory.
        $org_abspath = $this->get_original_image_path($req_abspath, $req_size);
        if ($fallback_url = $this->check($org_abspath && is_readable(Plugin::wp_dir($org_abspath)), $req_abspath)) {
            Plugin::warn("Didn't find original image of %s.", Plugin::wp_dir($req_abspath));
            Plugin::debug("Expected readable original image: %s", Plugin::wp_dir($org_abspath));
            return $fallback_url;
        }
        Plugin::debug("Path to original: %s", $org_abspath);

        // Get path relative Wordpress upload directory.
        // Currently it is assumed to be wp-content/uploads relative Wordpress
        // home directory. TODO: Allow this directory to be located anywhere
        // including outside Wordpress directory structure.
        $org_relpath = $this->get_relpath($org_abspath);
        Plugin::debug("Relative path: %s", $org_relpath);

        // Query Wordpress database for the post id of the attachment
        // associated with the requested image.
        $query = new \WP_Query([
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            'fields'      => 'ids',
            'meta_query'  => [
                [
                    'compare' => 'LIKE',
                    'value'   => $org_relpath,
                    'key'     => '_wp_attachment_metadata',
                ],
            ],
        ]);
        if ($fallback_url = $this->check($query->have_posts(), $req_abspath, $org_abspath, $req_size)) {
            Plugin::warn("Media library has no original image of %s.", Plugin::wp_dir($req_abspath));
            Plugin::debug("Expected original image: %s", Plugin::wp_dir($org_abspath));
            return $fallback_url;
        }
        if ($fallback_url = $this->check(count($query->posts) == 1, $req_abspath, $org_abspath, $req_size)) {
            Plugin::warn("Media library has no original image of %s.", Plugin::wp_dir($req_abspath));
            return $fallback_url;
        }
        Plugin::debug("Found attachment %s in media library of %s.", $query->posts[0], $req_abspath);

        // If a request for original file, there is nothing more to do than
        // return the Imgix URL.
        if ($org_abspath === $req_abspath) {
            Plugin::debug("Request for original file.");
            return $this->imgix_url($org_relpath);
        }

        // Get metadata for the image's attachment.
        $src = wp_get_attachment_metadata($query->posts[0]);

        // Get slug of the requested image size.
        list($image_size, $image_data) = $this->pluck($src['sizes'], 'file', basename($req_abspath));
        if ($fallback_url = $this->check($image_size, $req_abspath, $org_abspath, $req_size)) {
            Plugin::warn("No match in database for requested image size of %s.", Plugin::wp_dir($req_abspath));
            return $fallback_url;
        }
        Plugin::debug("Match in database for requested image size of %s.", Plugin::wp_dir($req_abspath));

        // Get the image size data (i.e. width, height and crop).
        $dst = $this->image_size_data($image_size);
        if ($fallback_url = $this->check($dst, $req_abspath, $org_abspath, $req_size)) {
            Plugin::warn("No match between defined image sizes and requested image size of %s.", Plugin::wp_dir($req_abspath));
            return $fallback_url;
        }
        Plugin::debug("Match between defined image sizes and requested image size of %s", Plugin::wp_dir($req_abspath));

        // Allow other plugins to filter the array
        // [ $src_w, $src_h, $dst_w, $dst_h, $crop ] before it is passed to
        // the Wordpress function image_resize_dimensions().
        $dims = apply_filters('kntnt_imgix_before_image_resize_dimensions', [
            $src['width'],
            $src['height'],
            $dst['width'],
            $dst['height'],
            $dst['crop'],
        ], $req_size);

        // Determine the source and destination rectangles for scaling.
        $dims = image_resize_dimensions(...$dims);

        // Allow other plugins to filter the array
        // [ $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ]
        // returned by the Wordpress function image_resize_dimensions().
        $dims = apply_filters('kntnt_imgix_after_image_resize_dimensions', $dims, $req_size);

        if ($fallback_url = $this->check($dims, $req_abspath, $org_abspath, $req_size)) {
            Plugin::warn("No source and destination rectangles for generating %s.", Plugin::wp_dir($req_abspath));
            return $fallback_url;
        }
        Plugin::debug("Source and destination rectangles %s", $dims);

        // Get the bounding boxes for source and destination, and build Imgix
        // parameters.
        list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $dims;
        $imgix_params = [
            'w'    => $dst_w,
            'h'    => $dst_h,
            'rect' => "$src_x,$src_y,$src_w,$src_h",
        ];

        // Build and return the Imgix URL.
        $url = $this->imgix_url($org_relpath, $imgix_params);
        Plugin::trace("Build and return Imgix URL: %s", $url);
        return $url;

    }

    // Returns the array [ 'w' => <width>, 'h' => <height> ] where <width> and
    // <height> are integers representing the requested image width and height,
    // respectively.
    private function get_requested_size($req_abspath, $matches) {

        // Allow another plugin to override this method.
        if ($req_size = apply_filters('kntnt_imgix_get_requested_size', [], $req_abspath, $matches)) {
            Plugin::trace('Filter `get_requested_size` returns %s', $req_size);
            return $req_size;
        }

        if ( ! $matches) {
            $pattern = '-(?<w>\d+)x(?<h>\d+)\.(?:jpg|jpeg|gif|png)$';
            preg_match("`$pattern`i", $req_abspath, $matches);
        }

        return array_map('intval', $matches);

    }

    // Returns the path relative Wordpress home directory where the original
    // image should exist.
    private function get_original_image_path($req_abspath, $req_size) {

        // Allow another plugin to override this method.
        if ($org_abspath = apply_filters('kntnt_imgix_get_original_image_path', '', $req_abspath, $req_size)) {
            Plugin::trace('Filter `get_original_image_path` returns %s', $org_abspath);
            return $org_abspath;
        }

        // If no size is given, the original image is assumed to be requested.
        if ( ! $req_size) {
            return $req_abspath;
        }

        // $req_abspath = wp-content/uploads/2017/08/example-image-300x200.jpg
        //             OR wp-content/uploads/2017/08/example-image.jpg
        // $org_abspath = wp-content/uploads/2017/08/example-image.jpg
        // $org_relpath = 2017/08/example-image.jpg
        $p = pathinfo($req_abspath);
        $p['filename'] = substr($p['filename'], 0, -strlen("-{$req_size['w']}x{$req_size['h']}"));
        if ( ! isset($p['extension'])) {
            return '';
        }
        return $p['dirname'] . '/' . $p['filename'] . '.' . $p['extension'];

    }

    // Returns a fallback url if $value is missing,
    // and false if $value is not missing.
    private function check($value, $req_abspath, $org_abspath = '', $req_size = []) {
        if ($value) {
            return false;
        }
        elseif (Plugin::option('strict', false)) {
            return $req_abspath;
        }
        else {
            return $this->imgix_url($org_abspath ? $org_abspath : $req_abspath, $req_size); // $req_size is empty if $org_abspath is empty.
        }
    }

    // Search an array of arrays, and returns the first occurrence of
    // an array $a with an element such that $a[$lookup_key] == $lookup_value.
    private function pluck($list, $lookup_key, $lookup_value) {
        foreach ($list as $key => $val) {
            if (isset($val[$lookup_key]) && $val[$lookup_key] == $lookup_value) {
                return [$key, $val];
            }
        }
        return [];
    }

    // Get the requested image size data. Try first if the requested image size
    // is additional to the built-in, or override the built-ins. If not, assume
    // it is built-in and not overridden.
    private function image_size_data($image_size) {
        if (($size = $this->additional_image_sizes($image_size))) {
            return [
                'width'  => $size['width'],
                'height' => $size['height'],
                'crop'   => array_key_exists('crop', $size) ? $size['crop'] : false,
            ];
        }
        elseif (in_array($image_size, ['thumbnail', 'medium', 'medium_large', 'large'])) {
            return [
                'width'  => get_option($image_size . '_size_w'),
                'height' => get_option($image_size . '_size_h'),
                'crop'   => ('thumbnail' === $image_size ? (bool)get_option('thumbnail_crop') : false),
            ];
        }
        return false;
    }

    // Get the additional image sizes, if any.
    private function additional_image_sizes($image_size) {

        global $_wp_additional_image_sizes;

        // If other than the built in image sizes exists, and the requested
        // image size is one of them, return its data.
        if (isset($_wp_additional_image_sizes) && isset($_wp_additional_image_sizes[$image_size])) {
            return $_wp_additional_image_sizes[$image_size];
        }

        // The requested image size must be one of the buit in.
        return [];

    }

    private function imgix_url($org_relpath, $imgix_params = []) {

        // Allow other plugins to alter Imgix parameters.
        // See also filter `imgix_parameters`.
        $params = apply_filters('kntnt_imgix_parameters_final', $imgix_params + $this->params);

        return $this->builder->createURL($org_relpath, $params);

    }

}
