<?php

namespace Kntnt\Imgix;

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once __DIR__ . '/../lib/Imgix/ShardStrategy.php';
require_once __DIR__ . '/../lib/Imgix/UrlHelper.php';
require_once __DIR__ . '/../lib/Imgix/UrlBuilder.php';

class Image_Editor_Imgix extends \WP_Image_Editor {

    // Supported file suffix.
    private static $supported_file_suffixes = [
        'jpg',
        'jpeg',
        'jxr',
        'jp2',
        'png',
        'gif',
        'webp',
        'tif',
        'tiff',
        'bmp',
    ];

    // Supported mime types.
    private static $supported_mime_types = [
        'image/jpeg',
        'image/pjpg',
        'image/jxr',
        'image/jp2',
        'image/png',
        'image/png8',
        'image/png32',
        'image/gif',
        'image/webp',
        'image/tiff',
        'image/bmp',
    ];

    // Imgix URL-builder.
    private $builder = null;

    // Canvas rotation 0°, 90°, 180° or 270° counter-clockwise
    private $or;

    // Image rotation on canvas in degreens [0°,90).
    private $rot;

    // Source rectangle
    private $rect;

    // Swap around the horizontal axis (top becomes bottom and vice versa).
    private $flip;

    // Swap around the vertical axis (left becomes right and vice versa).
    private $flop;

    // The original image file.
    private $org_file;

    // The original image dimensions.
    private $org_size;

    public function __construct($file) {
        $file = realpath($file);
        Plugin::info("Imgix Image Editor created for %s", $file);
        parent::__construct($file);
    }

    public static function test($args = []) {
        if ( ! Plugin::option('imgix-domain')) {
            return false;
        }
        $regex = '~' . Plugin::uploads_dir_rel_site() . '/(?:\d{4}/\d{2}/)?[^/]+\.(?:' . implode("|", self::$supported_file_suffixes) . ')$~i';
        if (isset($args['path']) && ! preg_match($regex, $args['path'])) {
            return false;
        }
        return true;
    }

    public static function supports_mime_type($mime_type) {
        return in_array($mime_type, self::$supported_mime_types);
    }

    public function load() {

        // Don't do anything if this have been called already.
        if ($this->builder) {
            return true;
        }

        // Check that the original file exists and is readable.
        if ( ! is_readable($this->file)) {
            return new \WP_Error('error_loading_image', __("File doesn't exist?"), $this->file);
        }

        // Get information about the image.
        $size = @getimagesize($this->file);
        if ( ! $size) {
            return new \WP_Error('invalid_image', __('Could not read image size.'), $this->file);
        }

        // Create link builder for Imgix.
        $this->builder = new \Imgix\UrlBuilder(Plugin::option('imgix-domain'));
        $this->builder->setUseHttps(is_ssl());
        if ($imgix_token = Plugin::option('imgix-token')) {
            $this->builder->setSignKey($imgix_token);
        }

        // Disable automatic oriantation bases on EXIF for compability with
        // the interface of WP Image Editor,
        $this->or = 0;

        // Default values.
        $this->rot = 0;
        $this->flip = false;
        $this->flop = false;
        $this->rect = [
            'x'      => 0,
            'y'      => 0,
            'width'  => $size[0],
            'height' => $size[1],
        ];
        $this->update_size($size[0], $size[1]);
        $this->org_file = $this->file;
        $this->org_size = $this->size;
        $this->mime_type = $size['mime'];

        $this->default_quality = Plugin::option('local-quality');
        $this->set_quality();

    }

    public function resize($max_w, $max_h, $crop = false) {
        if (($this->size['width'] == $max_w) && ($this->size['height'] == $max_h)) {
            return true;
        }
        return $this->_resize($max_w, $max_h, $crop);
    }

    private function _resize($max_w, $max_h, $crop) {

        $dims = image_resize_dimensions($this->size['width'], $this->size['height'], $max_w, $max_h, $crop);
        if ( ! $dims) {
            return new \WP_Error('error_getting_dimensions', 'Could not calculate resized image dimensions', $this->file);
        }
        list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $dims;
        $this->rect = [
            'x'      => $this->rect['x'] + $src_x,
            'y'      => $this->rect['y'] + $src_y,
            'width'  => $src_w,
            'height' => $src_h,
        ];
        $this->update_size($dst_w, $dst_h);

        return true;

    }

    public function multi_resize($sizes) {

        $metadata = [];

        // Store orginal image information.
        $org_rect = $this->rect;
        $org_size = $this->size;

        // For each image size, resize and compute the metadata.
        foreach ($sizes as $size => $dst_size) {

            // If image size is identical to this image size, there are not much to do.
            if (($dst_size['width'] == $org_size['width']) && ($dst_size['height'] == $org_size['height'])) {
                continue;
            }

            // If images has no width or height, there is not much to do.
            if ( ! isset($dst_size['width']) && ! isset($dst_size['height'])) {
                continue;
            }

            // Add defaults for missing parameters.
            if ( ! isset($dst_size['width'])) {
                $dst_size['width'] = null;
            }
            if ( ! isset($dst_size['height'])) {
                $dst_size['height'] = null;
            }
            if ( ! isset($dst_size['crop'])) {
                $dst_size['crop'] = false;
            }

            $ok = $this->_resize($dst_size['width'], $dst_size['height'], $dst_size['crop']);

            $duplicate = $this->size['width'] == $org_size['width'] && $this->size['height'] == $org_size['height'];
            if ( ! is_wp_error($ok) && ! $duplicate) {

                $resize = $this->_save();

                if ($resize && ! is_wp_error($resize)) {
                    unset($resize['path']);
                    $metadata[$size] = $resize;
                }

            }

            // Restore orginal image information.
            $this->rect = $org_rect;
            $this->size = $org_size;

        }

        return $metadata;

    }

    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false) {

        if ( ! $dst_w) {
            $dst_w = $src_w;
        }

        if ( ! $dst_h) {
            $dst_h = $src_h;
        }

        if ($src_abs) {
            $src_w -= $src_x;
            $src_h -= $src_y;
        }

        $src_x += $this->rect['x'];
        $src_y += $this->rect['y'];

        $this->rect = [
            'x'      => $src_x,
            'y'      => $src_y,
            'width'  => $src_w,
            'height' => $src_h,
        ];

        $this->update_size($dst_w, $dst_h);

        return true;

    }

    public function rotate($angle) {

        // If the image is flipped or flopped (both not both) the point of rotation has
        // also been flipped or flopped, respectively, changing counter clockwise to
        // clockwise rotation and vice versa.
        if ($this->flip xor $this->flop) {
            $angle = -$angle;
        }

        // Calculate the accumulated rotation and mormalize it to the interval [0,360).
        // Imgix doesn't provide a method to rotate canvas to an arbitrary angle, so we
        // approximate by rotating canvas to the closest possible angle, and then rotate
        // the imge on the cavas the remaning part.
        $angle += $this->or + $this->rot;
        $angle = ($angle % 360 + 360) % 360;
        $old_or = $this->or;
        $this->or = [0, 90, 180, 270][intval(($angle + 45) % 360 / 90)];
        $this->rot = $angle - $this->or;

        // Calculate the rotation.
        $angle_diff = $this->or - $old_or;
        if ($this->flip xor $this->flop) {
            $angle_diff = -$angle_diff;
        }

        // The source rectangle must be rotated with the image canvas.
        switch ($angle_diff) {
            case 0:
                $W = $this->org_size['width'];
                $H = $this->org_size['height'];
                $w = $this->rect['width'];
                $h = $this->rect['height'];
                $x = $this->rect['x'];
                $y = $this->rect['y'];
                break;
            case -270:
            case 90:
                $W = $this->org_size['height'];
                $H = $this->org_size['width'];
                $w = $this->rect['height'];
                $h = $this->rect['width'];
                $x = $this->rect['y'];
                $y = $H - $h - $this->rect['x'];;
                break;
            case -180:
            case 180:
                $W = $this->org_size['width'];
                $H = $this->org_size['height'];
                $w = $this->rect['width'];
                $h = $this->rect['height'];
                $x = $W - $w - $this->rect['x'];
                $y = $H - $h - $this->rect['y'];
                break;
            case -90:
            case 270:
                $W = $this->org_size['height'];
                $H = $this->org_size['width'];
                $w = $this->rect['height'];
                $h = $this->rect['width'];
                $x = $W - $w - $this->rect['y'];
                $y = $this->rect['x'];
                break;
        }
        $this->rect = ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
        $this->org_size = ['width' => $W, 'height' => $H];
        $this->update_size($w, $h);

        return true;

    }

    public function flip($horz, $vert) {

        // Flip and flop is done after rotation, so nothing special needs to be done.
        if ($horz) {
            $this->flip = ! $this->flip;
            $this->rect['y'] = $this->org_size['height'] - $this->rect['height'] - $this->rect['y'];
        }

        if ($vert) {
            $this->flop = ! $this->flop;
            $this->rect['x'] = $this->org_size['width'] - $this->rect['width'] - $this->rect['x'];
        }

        return true;

    }

    public function stream($mime_type = null) {

        // Make sure we have a mime type.
        list($filename, $extension, $mime_type) = $this->get_output_format(null, $mime_type);

        // Read te image from Imgix and write it to stdout.
        header("Content-type: $mime_type");
        $this->fetch();

    }

    public function save($filename = null, $mime_type = null) {

        // Save the image this editor represents to the provided file.
        $saved = $this->_save($filename, $mime_type);

        // If everything worked as supposed, update this image editor
        // with the actual filename and mime type under which the
        // image was saved.
        if ( ! is_wp_error($saved)) {
            $this->file = $saved['path'];
            $this->mime_type = $saved['mime-type'];
        }

        return $saved;

    }

    private function _save($path = null, $mime_type = null) {

        // Make sure we have the best output format information.
        list($path, $extension, $mime_type) = $this->get_output_format($path, $mime_type);
        if ( ! $path) {
            $path = $this->generate_filename(null, null, $extension);
        }

        // Fetch the image and save it to a local file if that option is enabled.
        if (Plugin::option('local-multiresize')) {

            // Fetch the image.
            $ok = $this->make_image($path, [$this, 'fetch'], [$path]);
            if ( ! $ok) {
                return $ok;
            }

            // Set same permissions as parent folder, strip off the executable bits.
            $perms = stat(dirname($path))['mode'] & 0000666;
            @chmod($path, $perms);

        }

        // Filters the name of the saved image file.
        $file = wp_basename(apply_filters('image_make_intermediate_size', $path));

        // Return metadata.
        return [
            'path'      => $path,
            'file'      => $file,
            'width'     => $this->size['width'],
            'height'    => $this->size['height'],
            'mime-type' => $mime_type,
        ];

    }

    public function fetch($out = null) {

        // Make sure rect is all integers.
        $rect = $this->rect;
        array_walk($rect, function (&$v) {
            $v = round($v);
        });

        // Make sure size is all integers.
        $size = $this->size;
        array_walk($size, function (&$v) {
            $v = round($v);
        });

        // Rotate and flip.
        $params['or'] = (360 - $this->or) % 360;
        $params['rot'] = $this->rot;
        $params['flip'] = ($this->flop ? 'h' : '') . ($this->flip ? 'v' : '');

        // Since Imgix resize and crop before flip/flop, we need to reset
        // the coordinate system.
        if ($this->flop) {
            $rect['x'] = $this->org_size['width'] - $rect['width'] - $rect['x'];
        }
        if ($this->flip) {
            $rect['y'] = $this->org_size['height'] - $rect['height'] - $rect['y'];
        }

        // Scale and crop.
        $params['fit'] = "scale";
        $params['rect'] = implode(',', $rect);
        $params['w'] = $size['width'];
        $params['h'] = $size['height'];

        // Set quality.
        $params['q'] = $this->get_quality();

        // Build Imgix URL.
        $url = substr($this->org_file, strlen(Plugin::uploads_dir()) + 1);
        $url = $this->builder->createURL($url, $params);

        try {

            // Initalize cURL
            $ch = curl_init();
            if ($ch === false) {
                return new \WP_Error('error_loading_image', __("Can't write to file."), $this->file);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

            // If $out is given, write to it instead of stdout which is
            // the deafult behaviour.
            if ($out) {
                $fp = fopen($out, 'wb');
                if ($fp === false) {
                    return new \WP_Error('error_loading_image', __("Can't read from $url"), $this->file);
                }
                curl_setopt($ch, CURLOPT_FILE, $fp);
            }

            // Read and write the image.
            curl_exec($ch);
            if (curl_error($ch)) {
                return new \WP_Error('error_loading_image', __("Failed writing image to file."), $this->file);
            }

        } finally {

            if (isset($ch)) {
                curl_close($ch);
            }

            if (isset($fp)) {
                fclose($fp);
            }

        }

        return true;

    }

}
