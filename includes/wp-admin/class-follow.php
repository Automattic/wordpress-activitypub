<?php
/**
 * Follow class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Webfinger;

/**
 * ActivityPub Follow class.
 */
class Follow {
	/**
	 * Initialize the settings fields.
	 */
	public static function init() {
		\wp_enqueue_style( 'activitypub-follow-me', plugins_url( 'build/follow-me/style-index.css', ACTIVITYPUB_PLUGIN_FILE ), array(), ACTIVITYPUB_PLUGIN_VERSION );
		\add_action( 'load-settings_page_activitypub_follow', array( self::class, 'register_settings_fields' ) );
	}

	/**
	 * Page.
	 */
	public static function follow_page() {
		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/admin-header.php', true, array( 'tabs' => array() ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id  = \sanitize_text_field( \wp_unslash( $_GET['id'] ?? '' ) );
		$_id = $id;

		if ( empty( $_id ) ) {
			$post = null;
		} elseif ( is_numeric( $_id ) ) {
			$post = \get_post( $_id );
		} else {
			$_id = Webfinger::resolve( $_id );
			if ( \is_wp_error( $_id ) ) {
				$post = null;
			} else {
				$post = Actors::fetch_remote_by_uri( $_id );
			}
		}

		$actor = Actors::get_actor( $post );

		if ( \is_wp_error( $actor ) ) {
			\load_template(
				ACTIVITYPUB_PLUGIN_DIR . 'templates/search-actor.php',
				true,
				array(
					'id'    => $id,
					'actor' => $actor,
				)
			);
		} else {
			\load_template(
				ACTIVITYPUB_PLUGIN_DIR . 'templates/follow.php',
				true,
				array(
					'id'      => $id,
					'actor'   => $actor,
					'post'    => $post,
					'follows' => Followers::follows( $post->ID, \get_current_user_id() ),
				)
			);
		}
	}

	/**
	 * Add help tab.
	 */
	public static function add_follow_help_tab() {
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'activitypub-follow-help-tab',
				'title'   => \__( 'Follow', 'activitypub' ),
				'content' => \__( 'Follow', 'activitypub' ),
			)
		);
	}

	/**
	 * Register settings fields.
	 */
	public static function register_settings_fields() {
		// Add settings sections.
		\add_settings_section(
			'activitypub_follow',
			__( 'Follow', 'activitypub' ),
			array( self::class, 'render_search' ),
			'activitypub_follow'
		);
	}

	/**
	 * Render follow field.
	 */
	public static function render_search() {
		echo 'aa';
	}
}
