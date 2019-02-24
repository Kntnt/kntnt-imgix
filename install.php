<?php

defined('WPINC') || die;

add_option('kntnt-imgix', [
    'imgix-domain'           => '',
    'imgix-token'            => '',
    'local-multiresize'      => true,
    'local-quality'          => 90,
    'remote-quality'         => 75,
    'automatic-enhancement'  => false,
    'aggressive-compression' => true,
    'format-negotiation'     => true,
    'strict'                 => true,
    'performance'            => 'fast',
    'loglevel'               => 'QUIET',
]);
