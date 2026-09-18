<?php
/**
 * Icons file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Icons class.
 *
 * Registers the Fediverse and ActivityPub logos with the Icons API,
 * so they can be used in the block editor's Icon block.
 *
 * @since 9.3.0
 */
class Icons {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// The Icons API was introduced in WordPress 7.1.
		if ( ! \function_exists( 'wp_register_icon_collection' ) || ! \function_exists( 'wp_register_icon' ) ) {
			return;
		}

		\add_action( 'init', array( self::class, 'register_icons' ), 11 );
	}

	/**
	 * Register the icon collection and icons.
	 *
	 * @since 9.3.0
	 */
	public static function register_icons() {
		\wp_register_icon_collection(
			'activitypub',
			array(
				'label'       => \__( 'Fediverse', 'activitypub' ),
				'description' => \__( 'Logos of the Fediverse and the ActivityPub protocol.', 'activitypub' ),
			)
		);

		/*
		 * The proposed Fediverse logo: five connected nodes forming a pentagram.
		 *
		 * @see https://commons.wikimedia.org/wiki/File:Fediverse_logo_proposal.svg
		 * @license CC0-1.0
		 */
		\wp_register_icon(
			'activitypub/fediverse',
			array(
				'label'     => \__( 'Fediverse', 'activitypub' ),
				'file_path' => ACTIVITYPUB_PLUGIN_DIR . 'assets/svg/fediverse.svg',
			)
		);

		/*
		 * The Fediverse symbol: an asterism (⁂), several stars coming together.
		 *
		 * @see https://symbol.fediverse.info/en
		 * @license CC0-1.0
		 */
		\wp_register_icon(
			'activitypub/fediverse-symbol',
			array(
				'label'     => \__( 'Fediverse Symbol', 'activitypub' ),
				'file_path' => ACTIVITYPUB_PLUGIN_DIR . 'assets/svg/fediverse-symbol.svg',
			)
		);

		/*
		 * The official logo of the ActivityPub protocol.
		 *
		 * @see https://activitypub.rocks/
		 * @license CC0-1.0
		 */
		\wp_register_icon(
			'activitypub/activitypub',
			array(
				'label'     => \__( 'ActivityPub', 'activitypub' ),
				'file_path' => ACTIVITYPUB_PLUGIN_DIR . 'assets/svg/activitypub.svg',
			)
		);
	}
}
