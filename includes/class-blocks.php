<?php
namespace Activitypub;

use Activitypub\Collection\Followers as Followers;

class Blocks {
	public static function init() {
		\add_action( 'init', array( self::class, 'register_blocks' ) );
		\add_action( 'wp_enqueue_scripts', array( self::class, 'add_data' ) );
		\add_action( 'enqueue_block_editor_assets', array( self::class, 'add_data' ) );
	}

	public static function add_data() {
		$handle = is_admin() ? 'activitypub-followers-editor-script' : 'activitypub-followers-view-script';
		$data = array(
			'namespace' => ACTIVITYPUB_REST_NAMESPACE,
		);
		$js = sprintf( 'var _activityPubOptions = %s;', wp_json_encode( $data ) );
		\wp_add_inline_script( $handle, $js, 'before' );
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
		$per_page = absint( $attrs['per_page'] );
		$followers = Followers::get_followers( $followee_user_id, $per_page );
		$follower_count = Followers::count_followers( $followee_user_id );
		$is_followers_truncated = $follower_count > $per_page;
		$title = $attrs['title'];
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'aria-label' => __( 'Fediverse Followers', 'activitypub' ),
				'class'      => 'activitypub-follower-block',
				'data-attrs' => wp_json_encode( $attrs ),
			)
		);

		$html = '<div class="activitypub-follower-block" ' . $wrapper_attributes . '>';
		if ( $title ) {
			$html .= '<h3>' . $title . '</h3>';
		}
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
