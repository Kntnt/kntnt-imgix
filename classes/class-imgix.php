<?php

namespace Kntnt\Imgix;

require_once __DIR__ . '/../lib/Imgix/ShardStrategy.php';
require_once __DIR__ . '/../lib/Imgix/UrlHelper.php';
require_once __DIR__ . '/../lib/Imgix/UrlBuilder.php';

class Imgix {

    private $params = [];

    private $builder;

    public function __construct() {

        // Scale and crop.
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
        $pattern = Plugin::wp_url() . '/(' . Plugin::uploads_dir_rel_wp() . '/(?:\d{4}/\d{2}/)?(?:[^ \'"]+?)(?:(\d+)x(\d+))?\.(?:jpg|jpeg|gif|png))';
        return preg_replace_callback("~$pattern~i", function ($matches) {
            return htmlspecialchars($this->translate_url($matches[1], array_slice($matches, 2)));
        }, $content);
    }

    public function translate_url($req_abspath, $req_size = []) {
        // Width and height indicated by the requested file (if at all).
        if ($req_size) {
            $req_size = [intval($req_size[0]), intval($req_size[1])];
        }
        else {
            $pattern = '-(\d+)x(\d+)\.(?:jpg|jpeg|gif|png)$';
            preg_match("~$pattern~i", $req_abspath, $matches);
            $req_size = array_slice($matches, 1);
        }

        // $req_abspath = wp-content/uploads/2017/08/example-image-300x200.jpg  OR
        //                wp-content/uploads/2017/08/example-image.jpg
        // $org_abspath = wp-content/uploads/2017/08/example-image.jpg
        // $org_relpath = 2017/08/example-image.jpg
        if ($req_size) {
            $p = pathinfo($req_abspath);
            $p['filename'] = substr($p['filename'], 0, -strlen("-{$req_size[0]}x{$req_size[1]}"));
            $org_abspath = $p['dirname'] . '/' . $p['filename'] . '.' . $p['extension'];
        }
        else {
            $org_abspath = $req_abspath;
        }
        $org_relpath = Plugin::str_remove_head($org_abspath, Plugin::uploads_dir_rel_wp('/'));

        Plugin::info("Requested image: %s", $req_abspath);
        Plugin::trace("Path to original: %s", $org_abspath);
        Plugin::trace("Relative path: %s", $org_relpath);

        // Check that the original file exists and is readable.
        if ( ! is_readable(Plugin::wp_dir($org_abspath))) {
            Plugin::error("Original file isn't readable: %s", Plugin::wp_dir($org_abspath));
            return $req_abspath;
        }

        // Query WordPress database for the post id of the attachement associated
        // with the requested image.
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

        // Check that there really was an attachement associated with
        // the requested image.
        if ( ! $query->have_posts()) {
            if (Plugin::option('strict', true) || ! $req_size) {
                Plugin::error("Not in database: %s", $req_abspath);
                return $req_abspath;
            }
            else {
                Plugin::warn("Not in database: %s", $req_abspath);
                return $this->imgix_url($org_relpath, ['w' => $req_size[0], 'h' => $req_size[1]]);
            }
        }

        // If there are more than one posts, something is wrong with the database.
        if (count($query->posts) != 1) {
            if (Plugin::option('strict', true)) {
                Plugin::error("Found %d possible attachements for the image %s", count($query->posts), $req_abspath);
                return $req_abspath;
            }
            else {
                Plugin::warn("Found %d possible attachements for the image %s", count($query->posts), $req_abspath);
            }
        }

        // Get the attachement's post id.
        $attachment_id = $query->posts[0];
        Plugin::debug("Found image in database: %s", $attachment_id);

        // If a request for original file, there is nothing more to do than return
        // the Imgix URL.
        if ($org_abspath === $req_abspath) {
            Plugin::debug("Request for original file.");
            return $this->imgix_url($org_relpath);
        }

        // Get metadata for the image's attachement.
        $src = wp_get_attachment_metadata($attachment_id);

        // Get slug and metrics of the requested image size.
        Plugin::trace("Get slug and metrics of the requested image size.");
        @list($image_size, $image_data) = $this->pluck($src['sizes'], 'file', basename($req_abspath));

        // Check that the requested image size exist.
        if ( ! $image_size) {
            if (Plugin::option('strict', true)) {
                Plugin::error("Requested size not in database: %s", $req_abspath);
                return $req_abspath;
            }
            else {
                Plugin::warn("Requested size not in database: %s", $req_abspath);
                return $this->imgix_url($org_relpath, ['w' => $req_size[0], 'h' => $req_size[1]]);
            }
        }

        // Get the image size data (i.e. width, height and crop).
        Plugin::trace("Get width, height and crop of the requested image size.");
        $dst = $this->image_size_data($image_size, $image_data);

        // Check that the image format exists.
        if ( ! $dst) {
            if (Plugin::option('strict', true)) {
                Plugin::error('Image format `%1$s` doesn\'t exit: %2$s', $image_size, $req_abspath);
                return $req_abspath;
            }
            else {
                Plugin::warn('Image format `%1$s` doesn\'t exit: %2$s', $image_size, $req_abspath);
                return $this->imgix_url($org_relpath, ['w' => $req_size[0], 'h' => $req_size[1]]);
            }
        }

        // Get source and destination rectangles.
        Plugin::trace("Get source and destination rectangles.");
        $dims = image_resize_dimensions($src['width'], $src['height'], $dst['width'], $dst['height'], $dst['crop']);

        // WordPress don't upscale images, so $dims might be emopty.
        if ($dims) {

            // Get the bounding boxes for source and destination, and build Imgix
            // parameters.
            list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $dims;
            $imgix_params = [
                'w'    => $dst_w,
                'h'    => $dst_h,
                'rect' => "$src_x,$src_y,$src_w,$src_h",
            ];

        }
        else {

            $imgix_params = [];

        }

        // Build and return the Imgix URL.
        $url = $this->imgix_url($org_relpath, $imgix_params);
        Plugin::debug("Build and return Imgix URL: %s", $url);
        return $url;

    }

    // Search an array of arrays, and returns the first occurance of an array $a
    // with an element such that $a[$lookup_key] == $lookup_value.
    private function pluck($list, $lookup_key, $lookup_value) {
        foreach ($list as $key => $val) {
            if (isset($val[$lookup_key]) && $val[$lookup_key] == $lookup_value) {
                return [$key, $val];
            }
        }
        return [];
    }

    // Get the requested image size data. Try first if the requested image size
    // is additional to the built-in, or override the buit-ins. If not, assume
    // it is built-in and not overidden.
    private function image_size_data($image_size, $image_data) {
        if (($image_sizes = $this->additional_image_sizes($image_size))) {
            return [
                'width'  => $image_sizes['width'],
                'height' => $image_sizes['height'],
                'crop'   => array_key_exists('crop', $image_sizes) ? $image_sizes['crop'] : false,
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

        // I know, I know… Unfortunately, there is no other way (to my knowledge) to
        // get an image size's value for the `crop` attribute. At least, I try to
        // hide the bad in an own function. :-)
        global $_wp_additional_image_sizes;

        // If other than the built in image sizes exists, and the requested image
        // size is one of them, return its data.
        if (isset($_wp_additional_image_sizes) && isset($_wp_additional_image_sizes[$image_size])) {
            return $_wp_additional_image_sizes[$image_size];
        }

        // The requested image size must be one of the buit in.
        return false;

    }

    private function imgix_url($org_relpath, $imgix_params = []) {
        return $this->builder->createURL($org_relpath, $imgix_params + $this->params);
    }

}
