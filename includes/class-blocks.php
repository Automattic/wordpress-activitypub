<?php
namespace Activitypub;

use Activitypub\Collection\Followers as Followers;

class Blocks {
	public static function init() {
		\add_action( 'init', array( self::class, 'register_blocks' ) );
	}

	public static function register_blocks() {
		\register_block_type_from_metadata(
			ACTIVITYPUB_PLUGIN_DIR . '/build/followers',
			array(
				'render_callback' => array( self::class, 'render_follower_block' ),
			)
		);
	}

	private static function get_user_id( $user_string ) {
		if ( is_numeric( $user_string ) ) {
			return absint( $user_string );
		}
		// any other non-numeric falls back to 0, including the `site` string used in the UI
		return 0;
	}

	public static function render_follower_block( $attrs, $content, $block ) {
		$followee_user_id = self::get_user_id( $attrs['selectedUser'] );
		$followers_to_show = absint( $attrs['followersToShow'] );
		$followers = Followers::get_followers( $followee_user_id, $followers_to_show );
		$follower_count = Followers::count_followers( $followee_user_id );
		$is_followers_truncated = $follower_count > $followers_to_show;
		$title = $attrs['title'];
		$html = '<div class="activitypub-follower-block">';
		if ( $title ) {
			$html .= '<h3>' . $title . '</h3>';
		}
		if ( 0 === $follower_count ) {
			if ( is_user_logged_in() ) {
				$html .= '<p>' . __( 'No followers yet, keep publishing and that will change!', 'activitypub' ) . '</p>';
			}
			// @todo display a follow button to logged out users
			// reuse whatever ~lightbox-like thing we plan to use in the Follow block. Or can we just outright render it here? Probably.
			return $html . '</div>';
		} /* unsure. else {

			$html .= '<p>' . sprintf(
				// Translators: %s is the number of followers from the Fediverse (like Mastodon)
				_n( '%s follower', '%s followers', $follower_count, 'activitypub' ),
				number_format_i18n( $follower_count )
			) . '</p>';
		}
		*/
		$html .= '<ul>';
		foreach ( $followers as $follower ) {
			$html .= '<li>' . self::render_follower( $follower ) . '</li>';
		}
		$html .= '</ul></div>';
		return $html;
	}

	public static function render_follower( $follower ) {
		$template =
			'<a href="%s" title="%s">
				<img width="40" height="40" src="%s" class="avatar activitypub-avatar" />
				<span class="activitypub-actor"><strong>%s</strong><span class="sep">/</span>%s</span>
			</a>';
		$actor = $follower->get_actor();
		return sprintf(
			$template,
			esc_url( $actor ),
			esc_attr( $follower->get_name() ),
			esc_attr( $follower->get_avatar() ),
			esc_html( $follower->get_name() ),
			esc_html( self::get_actor_nicename( $actor ) )
		);
	}

	// todo: move this into the Follower class?
	private static function get_actor_nicename( $actor ) {
		$actor_nicename = $actor;
		if ( strpos( $actor, 'http' ) === 0 ) {
			$parts = wp_parse_url( $actor );
			$handle = preg_replace( '|^/@?|', '', $parts['path'] );
			$actor_nicename = sprintf( '%s@%s', $handle, $parts['host'] );
		}
		return $actor_nicename;
	}
}
