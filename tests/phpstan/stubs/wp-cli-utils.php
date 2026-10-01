<?php
/**
 * Minimal WP-CLI utility stubs for static analysis: only what the plugin calls.
 *
 * @package Activitypub
 */

// phpcs:ignoreFile

namespace WP_CLI\Utils;

/**
 * @param array<string, mixed> $assoc_args
 * @param string               $flag
 * @param mixed                $default_value
 * @return mixed
 */
function get_flag_value( $assoc_args, $flag, $default_value = null ) {}

/**
 * @param string               $format
 * @param array<int, mixed>    $items
 * @param array<int, string>|string $fields
 * @return void
 */
function format_items( $format, $items, $fields ) {}
