<?php

namespace Kntnt\Imgix;

require_once __DIR__ . '/class-image-editor-imgix.php';

class Image_Editor {

    public function __construct($plugin) { }

    public function run() {
        if (Plugin::option('imgix-domain')) {
            add_filter('wp_image_editors', function ($image_editors) {
                Plugin::trace("Filter: wp_image_editors");
                array_unshift($image_editors, __NAMESPACE__ . '\\Image_Editor_Imgix');
                return $image_editors;
            }, 9999);
        }
    }

}
