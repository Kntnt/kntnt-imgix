<?php

namespace Kntnt\Imgix;

require_once __DIR__ . '/class-abstract-settings.php';

class Settings extends Abstract_Settings {

    // Returns title used as menu item and as head of settings page.
    protected function title() {
        return __('Imgix', 'kntnt-img-feeds');
    }

    // Returns all fields used on the settigs page.
    protected function form() {

        $fields = [

            'imgix-domain' => [
                'type'        => 'text',
                'label'       => __('Imgix domain', 'kntnt-imgix'),
                'description' => __('Enter a domain from "Domains" in the section "Source Details" on your Imgix source.', 'kntnt-imgix'),
                'sanitizer'   => 'sanitize_text_field',
            ],

            'imgix-token' => [
                'type'        => 'text',
                'label'       => __('Imgix secure URL token', 'kntnt-imgix'),
                'description' => __('Enter the token from "Secure URL Token" in the section "Security" on your Imgix source.', 'kntnt-imgix'),
                'sanitizer'   => 'sanitize_text_field',
            ],

            'local-multiresize' => [
                'type'        => 'checkbox',
                'label'       => __('Local copies of all sizes', 'kntnt-imgix'),
                'description' => __('When checked, WordPress creates local thumbnails of all sizes of an uploaded image. This is WordPress normal behaviour. For the few cases when the URL for an image has not been replaced with dito Imgix URL, these images will be served from your server. When unchecked, no more local thumbnails will be created on upload. This save your disk space. A request for such missing image will be redirected to Imgix. If you later want to create local thumbnails, just disable this option (or the plugin) and regenerate the thumnails with <a href="https://developer.wordpress.org/cli/commands/media/regenerate" target="_blank">WP-CLI</a> or <a href="https://wordpress.org/plugins/regenerate-thumbnails" target="_blank">Alex Mills\' plugin</a>.', 'kntnt-imgix'),
                'sanitizer'   => function ($local_multiresize) { return isset($local_multiresize); },
            ],

            'local-quality' => [
                'type'        => 'text',
                'label'       => __('Quality of local JPEG-images', 'kntnt-imgix'),
                'description' => __('Enter a quality factor 1–100 for JPEG-compression of images serverd by Imgix. The lower, the lighter file, but less quality. Typical values are 70–98.', 'kntnt-imgix'),
                'sanitizer'   => function ($local_quality) { return min(max(intval($local_quality), 1), 100); },
            ],

            'remote-quality' => [
                'type'        => 'text',
                'label'       => __('Quality of remote JPEG-images', 'kntnt-imgix'),
                'description' => __('Enter a quality factor 1–100 for JPEG-compression of images serverd by Imgix. The lower, the lighter file, but less quality. Typical values are 70–98.', 'kntnt-imgix'),
                'sanitizer'   => function ($remote_quality) {
                    return min(max(intval($remote_quality), 1), 100);
                },
            ],

            'automatic-enhancement' => [
                'type'        => 'checkbox',
                'label'       => __('Automatic enhancement', 'kntnt-imgix'),
                'description' => __('Check to enable Imgix to apply automatic enhancement on all images served remote.', 'kntnt-imgix'),
                'sanitizer'   => function ($automatic_enhancement) { return isset($automatic_enhancement); },
            ],

            'aggressive-compression' => [
                'type'        => 'checkbox',
                'label'       => __('Aggressive compression', 'kntnt-imgix'),
                'description' => __('Check to enable Imgix to apply more aggressive compression on all images served remote.', 'kntnt-imgix'),
                'sanitizer'   => function ($aggressive_compression) { return isset($aggressive_compression); },
            ],

            'format-negotiation' => [
                'type'        => 'checkbox',
                'label'       => __('Format negotiation', 'kntnt-imgix'),
                'description' => __('Check to enable Imgix to negotiatie image format for images served remote.', 'kntnt-imgix'),
                'sanitizer'   => function ($format_negotiation) { return isset($format_negotiation); },
            ],

            'strict' => [
                'type'        => 'checkbox',
                'label'       => __('Strict mode', 'kntnt-imgix'),
                'description' => __('In strict mode, local URL:s will not be replaced with Imgix dito if there is any discrepancy between the requested image and its metadata stored in the database. Uncheck this option to replace local URL:s anyway.', 'kntnt-imgix'),
                'sanitizer'   => function ($strict) { return isset($strict); },
            ],

            'performance' => [
                'type'        => 'select',
                'options'     => [
                    'fast'       => 'Fast',
                    'thoroughly' => 'Thoroughly',
                ],
                'label'       => __('Performance', 'kntnt-imgix'),
                'description' => __('If you choose <strong>Thoroughly</strong> all local image URLs in the outputted HTML will be found and replaced with Imgix dito, but it takes typically 100 milliseconds for a post. You might choose this is if you have a good caching solution. If you choose <strong>Fast</strong> the search for local image URLs and replace with Imgix dito are abut 10× faster, but not all image URL:s might be found.', 'kntnt-imgix'),
                'sanitizer'   => function ($performance) {
                    return isset($performance) ? $performance : Plugin::option('performance');
                },
            ],

        ];


        return $fields;

    }

}
