<?php
namespace Activitypub;

use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI commands
 *
 * @package Activitypub
 */
class Cli extends WP_CLI_Command {
	/**
	 * See the Plugin Meta-Informations
	 *
	 * ## OPTIONS
	 *
	 * [--Name]
	 * The Plugin Name
	 *
	 * [--PluginURI]
	 * The Plugin URI
	 *
	 * [--Version]
	 * The Plugin Version
	 *
	 * [--Description]
	 * The Plugin Description
	 *
	 * [--Author]
	 * The Plugin Author
	 *
	 * [--AuthorURI]
	 * The Plugin Author URI
	 *
	 * [--TextDomain]
	 * The Plugin Text Domain
	 *
	 * [--DomainPath]
	 * The Plugin Domain Path
	 *
	 * [--Network]
	 * The Plugin Network
	 *
	 * [--RequiresWP]
	 * The Plugin Requires at least
	 *
	 * [--RequiresPHP]
	 * The Plugin Requires PHP
	 *
	 * [--UpdateURI]
	 * The Plugin Update URI
	 *
	 * See: https://developer.wordpress.org/reference/functions/get_plugin_data/#return
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp webmention meta
	 *
	 *     $ wp webmention meta --Version
	 *     Version: 1.0.0
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function meta( $args, $assoc_args ) {
		$plugin_data = get_plugin_meta();

		if ( $assoc_args ) {
			$plugin_data = array_intersect_key( $plugin_data, $assoc_args );
		} else {
			WP_CLI::line( __( "ActivityPub Plugin Meta:\n", 'activitypub' ) );
		}

		foreach ( $plugin_data as $key => $value ) {
			WP_CLI::line( $key . ':	' . $value );
		}
	}

	/**
	 * Remove the blog from the Fediverse.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub self-destruct
	 *
	 * @subcommand self-destruct
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function self_destruct( $args, $assoc_args ) {
		$question = __( 'We are in the process of deleting your blog from the Fediverse. This action could be irreversible, so are you sure you want to continue?', 'activitypub' );
		WP_CLI::confirm( WP_CLI::colorize( "%r{$question}%n" ), $assoc_args = array() );

		WP_CLI::success( __( 'Deleting your Blog from the Fediverse...', 'activitypub' ) );

		// Deactivate the ActivityPub Plugin after the deletion.
		WP_CLI::runcommand( 'plugin deactivate activitypub' );
	}

	/**
	 * Delete or Update a User.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform. Either `delete` or `update`.
	 * ---
	 * options:
	 *   - delete
	 *   - update
	 * ---
	 *
	 * <id>
	 * : The id of the registered WordPress user.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub user delete 1
	 *
	 * @synopsis <action> <id>
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function user( $args, $assoc_args ) {
		// @todo add code
	}
}
