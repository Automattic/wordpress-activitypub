<?php
/**
 * Import state stub for tests.
 *
 * Importers define `WP_IMPORTING` while they run. A constant cannot be undefined,
 * so only require this file in a test that runs in a separate process.
 *
 * @package Activitypub
 */

if ( ! \defined( 'WP_IMPORTING' ) ) {
	\define( 'WP_IMPORTING', true );
}
