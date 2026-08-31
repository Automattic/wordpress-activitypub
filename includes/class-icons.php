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
 * @since unreleased
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
	 * @since unreleased
	 */
	public static function register_icons() {
		\wp_register_icon_collection(
			'activitypub',
			array(
				'label'       => \__( 'Fediverse', 'activitypub' ),
				'description' => \__( 'Logos of the Fediverse and the ActivityPub protocol.', 'activitypub' ),
			)
		);

		\wp_register_icon(
			'activitypub/fediverse',
			array(
				'label'     => \__( 'Fediverse', 'activitypub' ),
				'file_path' => ACTIVITYPUB_PLUGIN_DIR . 'assets/svg/fediverse.svg',
			)
		);

		\wp_register_icon(
			'activitypub/fediverse-symbol',
			array(
				'label'     => \__( 'Fediverse Symbol', 'activitypub' ),
				'file_path' => ACTIVITYPUB_PLUGIN_DIR . 'assets/svg/fediverse-symbol.svg',
			)
		);

		\wp_register_icon(
			'activitypub/activitypub',
			array(
				'label'     => \__( 'ActivityPub', 'activitypub' ),
				'file_path' => ACTIVITYPUB_PLUGIN_DIR . 'assets/svg/activitypub.svg',
			)
		);
	}
}
