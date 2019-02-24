<?php

namespace Kntnt\Imgix;

require_once __DIR__ . '/class-imgix.php';

class Content_Parser {

    public function __construct($plugin) { }

    public function run() {

        if (Plugin::option('imgix-domain')) {

            $imgix = new Imgix;

            // Translate URL:s returned by `wp_get_attachment_image_src()`
            // if the filter `kntnt_imgix_translate_image_srcset` doesn't
            // return false.
            if (apply_filters('kntnt_imgix_translate_image_srcset', true)) {
                add_filter('wp_calculate_image_srcset', function ($sources, $size_array, $image_src, $image_meta, $attachment_id) use ($imgix) {
                    Plugin::trace('Filter: wp_calculate_image_srcset');
                    foreach ($sources as &$source) {
                        $url = $imgix->translate_url(Plugin::str_remove_head($source['url'], Plugin::wp_url('/')));
                        $source['url'] = $url;
                    }
                    return $sources;
                }, 10, 5);
            }

            // Translate URL:s returned by `wp_get_attachment_image_src()`
            // if the filter `kntnt_imgix_translate_attachment_image_src`
            // doesn't return false.
            if (apply_filters('kntnt_imgix_translate_attachment_image_src', true)) {
                add_filter('wp_get_attachment_image_src', function ($image, $attachment_id, $size, $icon) use ($imgix) {
                    Plugin::trace('Filter: wp_get_attachment_image_src');
                    $image[0] = $imgix->translate_url(Plugin::str_remove_head($image[0], Plugin::wp_url('/')));
                    return $image;
                }, 10, 4);
            }

            // Search and replace local image URLs with dito Imgix in WordPress'
            // "the content" output if the filter `kntnt_imgix_parse` returns
            // 'fast'. Default by settings page.
            if (apply_filters('kntnt_imgix_parse', Plugin::option('performance')) === 'fast') {
                add_filter('the_content', function ($content) use ($imgix) {
                    Plugin::trace('Filter: the_content');
                    $t1 = microtime(true);
                    $content = $imgix->parse_html($content);
                    $t2 = microtime(true);
                    Plugin::info("Searched and replaced local image URLs with dito Imgix in %f milliseconds.", ($t2 - $t1) * 1000);
                    return $content;
                });
            }

            // Search and replace local image URLs with dito Imgix in WordPress'
            // final output of content if `kntnt_imgix_parse` returns
            // 'thoroughly'. Default by settings page.
            if (apply_filters('kntnt_imgix_parse', Plugin::option('performance')) === 'thoroughly') {
                ob_start();
                add_action('shutdown', function () use ($imgix) {
                    Plugin::trace("Action: shutdown");
                    $t1 = microtime(true);
                    $out = '';
                    for ($level = 0; $level < ob_get_level(); ++$level) {
                        $out .= ob_get_clean();
                    }
                    echo $imgix->parse_html($out);
                    $t2 = microtime(true);
                    Plugin::info("Searched and replaced local image URLs with dito Imgix in %f milliseconds.", ($t2 - $t1) * 1000);
                }, 0);
            }

            // Allow others to add additional hooks.
            do_action('kntnt_imgix_content_parser', $imgix);

        }

    }

}
