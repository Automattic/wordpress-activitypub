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
		$html = '<div>';
		if ( $title ) {
			$html .= '<h2>' . $title . '</h2>';
		}
		$html .= '<ul>';
		foreach ( $followers as $follower ) {
			$html .= '<li>' . self::render_follower( $follower ) . '</li>';
		}
		$html .= '</ul></div>';
		return $html;
	}

	public static function render_follower( $follower ) {
		$template = '<a href="%s" title="%s"><img width="32" height="32" src="%s" class="avatar activitypub-avatar" />%s/%s</a>';
		return sprintf(
			$template,
			esc_url( $follower->get_url() ),
			esc_attr( $follower->get_name() ),
			esc_attr( $follower->get_avatar() ),
			esc_html( $follower->get_name() ),
			esc_html( $follower->get_actor() )
		);
	}
}
