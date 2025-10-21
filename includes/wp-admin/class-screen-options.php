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
		\add_filter( 'screen_settings', array( self::class, 'add_screen_option' ), 10, 2 );
		\add_filter( 'screen_options_show_submit', array( self::class, 'screen_options_show_submit' ), 10, 2 );
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
			case 'blocked-actors':
				self::add_blocked_actors_list_options();
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
	 * Add screen options for blocked actors list.
	 *
	 * @see Menu::admin_menu()
	 */
	public static function add_blocked_actors_list_options() {
		\add_screen_option(
			'per_page',
			array(
				'label'   => \__( 'Blocked Actors per page', 'activitypub' ),
				'default' => 20,
				'option'  => 'activitypub_blocked_actors_per_page',
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
		if ( 'activitypub_followers_per_page' === $option || 'activitypub_following_per_page' === $option || 'activitypub_blocked_actors_per_page' === $option ) {
			$value = (int) $value;

			if ( $value > 0 && $value <= 100 ) {
				return $value;
			}
		}

		return $status;
	}

	/**
	 * Add screen options.
	 *
	 * @param string $screen_settings The screen settings.
	 * @param object $screen          The screen object.
	 *
	 * @return string The screen settings.
	 */
	public static function add_screen_option( $screen_settings, $screen ) {
		if ( 'settings_page_activitypub' !== $screen->id ) {
			return $screen_settings;
		}

		// No screen options on followers, following, and blocked-actors tabs. The per_page screen options interfere with them.
		if ( \in_array( \sanitize_text_field( \wp_unslash( $_GET['tab'] ?? 'welcome' ) ), array( 'followers', 'following', 'blocked-actors' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $screen_settings;
		}

		// Verify screen options nonce.
		if ( isset( $_POST['screenoptionnonce'] ) ) {
			$nonce = \sanitize_text_field( \wp_unslash( $_POST['screenoptionnonce'] ) );
			if ( ! \wp_verify_nonce( $nonce, 'screen-options-nonce' ) ) {
				return $screen_settings;
			}
		}

		$screen_options = array(
			'activitypub_show_welcome_tab'  => __( 'Welcome Page', 'activitypub' ),
			'activitypub_show_advanced_tab' => __( 'Advanced Settings', 'activitypub' ),
		);

		/**
		 * Filters Activitypub settings screen options.
		 *
		 * @param string[] $screen_options Screen options. An array of user meta keys and screen option labels.
		 */
		$screen_options = \apply_filters( 'activitypub_screen_options', $screen_options );
		if ( empty( $screen_options ) ) {
			return $screen_settings;
		}

		foreach ( $screen_options as $option => $label ) {
			if ( isset( $_POST[ $option ] ) ) {
				$value = \sanitize_text_field( \wp_unslash( $_POST[ $option ] ) );
				\update_user_meta( \get_current_user_id(), $option, empty( $value ) ? 0 : 1 );
			}
		}

		ob_start();
		?>
		<fieldset>
			<legend class="screen-layout"><?php \esc_html_e( 'Settings Pages', 'activitypub' ); ?></legend>
			<div class="metabox-prefs-container">
				<?php foreach ( $screen_options as $option => $label ) : ?>
				<label for="<?php echo \esc_attr( $option ); ?>">
					<input name="<?php echo \esc_attr( $option ); ?>" type="hidden" value="0" />
					<input name="<?php echo \esc_attr( $option ); ?>" type="checkbox" id="<?php echo \esc_attr( $option ); ?>" value="1" <?php \checked( 1, \get_user_meta( \get_current_user_id(), $option, true ) ); ?> />
					<?php echo \esc_html( $label ); ?>
				</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php

		return ob_get_clean();
	}

	/**
	 * Show the submit button on the screen options page.
	 *
	 * @param bool   $show_submit Whether to show the submit button.
	 * @param object $screen      The screen object.
	 *
	 * @return bool Whether to show the submit button.
	 */
	public static function screen_options_show_submit( $show_submit, $screen ) {
		if ( 'settings_page_activitypub' !== $screen->id ) {
			return $show_submit;
		}

		return true;
	}
}
