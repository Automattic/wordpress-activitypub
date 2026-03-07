<?php
/**
 * Plugin Name: ATproto DID for Bridgy Fed
 * Description: Allows you to serve an ATproto DID from your blog's `.well-known` directory to allow Bridgy Fed to use your blog's hostname as its Bluesky handle
 * Version: 0.0.1
 * Author: Julian Fietkau
 * License: Creative Commons Zero 1.0 Universal
 * License URI: https://creativecommons.org/publicdomain/zero/1.0/
 * Requires Plugins:  activitypub
 */

namespace Activitypub\Snippets;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

function atproto_did_register_rule() {
    \add_rewrite_rule(
        '^.well-known/atproto-did$',
        'index.php?rest_route=/atproto_did/atproto-did',
        'top'
    );
    \flush_rewrite_rules();
}

function atproto_did_rest_init() {
    \register_rest_route( 'atproto_did', 'atproto-did', array(
        'methods'   => 'GET',
        'callback'  => 'atproto_did_rest_endpoint'
    ) );
}

function atproto_did_rest_endpoint() {
    \header( 'Content-Type: text/plain' );
    // Insert your DID here:
    echo( 'did:plc:________________________' );
    exit();
}

\add_action( 'init', 'atproto_did_register_rule' );
\add_action( 'rest_api_init', 'atproto_did_rest_init' );
