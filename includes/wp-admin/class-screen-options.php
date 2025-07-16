<?php
/**
 * Screen Options file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

/**
 * ActivityPub Screen Options Class.
 */
class Screen_Options {
	/**
	 * Initialize the class.
	 */
	public static function init() {
		\add_filter( 'set-screen-option', array( self::class, 'set_per_page_option' ), 10, 3 );
	}

	/**
	 * Add settings list screen options.
	 *
	 * @see Menu::admin_menu()
	 */
	public static function add_settings_list_options() {
		$tab = \sanitize_text_field( \wp_unslash( $_GET['tab'] ?? 'welcome' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		switch ( $tab ) {
			case 'followers':
				self::add_followers_list_options();
				break;
			case 'following':
				self::add_following_list_options();
				break;
		}
	}

	/**
	 * Add follower list screen options.
	 *
	 * @see Menu::admin_menu()
	 */
	public static function add_followers_list_options() {
		\add_screen_option(
			'per_page',
			array(
				'label'   => \__( 'Followers per page', 'activitypub' ),
				'default' => 20,
				'option'  => 'activitypub_followers_per_page',
			)
		);
	}

	/**
	 * Add screen options for following list.
	 *
	 * @see Menu::admin_menu()
	 */
	public static function add_following_list_options() {
		\add_screen_option(
			'per_page',
			array(
				'label'   => \__( 'Following per page', 'activitypub' ),
				'default' => 20,
				'option'  => 'activitypub_following_per_page',
			)
		);
	}

	/**
	 * Set per_page screen options.
	 *
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 * @return int
	 */
	public static function set_per_page_option( $status, $option, $value ) {
		if ( 'activitypub_followers_per_page' === $option || 'activitypub_following_per_page' === $option ) {
			$value = (int) $value;

			if ( $value > 0 && $value <= 100 ) {
				return $value;
			}
		}

		return $status;
	}
}
