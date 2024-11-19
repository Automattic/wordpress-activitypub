<?php
/**
 * Blocks file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Actors as User_Collection;

/**
 * Block class.
 */
class Blocks {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// This is already being called on the init hook, so just add it.
		self::register_blocks();

		\add_action( 'wp_enqueue_scripts', array( self::class, 'add_data' ) );
		\add_action( 'enqueue_block_editor_assets', array( self::class, 'add_data' ) );
		\add_action( 'load-post-new.php', array( self::class, 'handle_in_reply_to_get_param' ) );
		// Add editor plugin.
		\add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		\add_action( 'init', array( self::class, 'register_postmeta' ), 11 );
	}

	/**
	 * Register post meta for content warnings.
	 */
	public static function register_postmeta() {
		$ap_post_types = \get_post_types_by_support( 'activitypub' );
		foreach ( $ap_post_types as $post_type ) {
			\register_post_meta(
				$post_type,
				'activitypub_content_warning',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => function ( $warning ) {
						if ( $warning ) {
							return \sanitize_text_field( $warning );
						}

						return null;
					},
				)
			);
			\register_post_meta(
				$post_type,
				'activitypub_content_visibility',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => function ( $visibility ) {
						$options = array(
							ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC,
							ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
						);

						if ( in_array( $visibility, $options, true ) ) {
							return $visibility;
						}

						return null;
					},
				)
			);
		}
	}

	/**
	 * Enqueue the block editor assets.
	 */
	public static function enqueue_editor_assets() {
		// Check for our supported post types.
		$current_screen = \get_current_screen();
		$ap_post_types  = \get_post_types_by_support( 'activitypub' );
		if ( ! $current_screen || ! in_array( $current_screen->post_type, $ap_post_types, true ) ) {
			return;
		}
		$asset_data = include ACTIVITYPUB_PLUGIN_DIR . 'build/editor-plugin/plugin.asset.php';
		$plugin_url = plugins_url( 'build/editor-plugin/plugin.js', ACTIVITYPUB_PLUGIN_FILE );
		wp_enqueue_script( 'activitypub-block-editor', $plugin_url, $asset_data['dependencies'], $asset_data['version'], true );
	}

	/**
	 * Enqueue the reply handle script if the in_reply_to GET param is set.
	 */
	public static function handle_in_reply_to_get_param() {
		// Only load the script if the in_reply_to GET param is set, action happens there, not here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['in_reply_to'] ) ) {
			return;
		}

		$asset_data = include ACTIVITYPUB_PLUGIN_DIR . 'build/reply-intent/plugin.asset.php';
		$plugin_url = plugins_url( 'build/reply-intent/plugin.js', ACTIVITYPUB_PLUGIN_FILE );
		wp_enqueue_script( 'activitypub-reply-intent', $plugin_url, $asset_data['dependencies'], $asset_data['version'], true );
	}

	/**
	 * Add data to the block editor.
	 */
	public static function add_data() {
		$context          = is_admin() ? 'editor' : 'view';
		$followers_handle = 'activitypub-followers-' . $context . '-script';
		$follow_me_handle = 'activitypub-follow-me-' . $context . '-script';
		$data             = array(
			'namespace' => ACTIVITYPUB_REST_NAMESPACE,
			'enabled'   => array(
				'site'  => ! is_user_type_disabled( 'blog' ),
				'users' => ! is_user_type_disabled( 'user' ),
			),
		);
		$js               = sprintf( 'var _activityPubOptions = %s;', wp_json_encode( $data ) );
		\wp_add_inline_script( $followers_handle, $js, 'before' );
		\wp_add_inline_script( $follow_me_handle, $js, 'before' );
	}

	/**
	 * Register the blocks.
	 */
	public static function register_blocks() {
		\register_block_type_from_metadata(
			ACTIVITYPUB_PLUGIN_DIR . '/build/followers',
			array(
				'render_callback' => array( self::class, 'render_follower_block' ),
			)
		);
		\register_block_type_from_metadata(
			ACTIVITYPUB_PLUGIN_DIR . '/build/follow-me',
			array(
				'render_callback' => array( self::class, 'render_follow_me_block' ),
			)
		);
		\register_block_type_from_metadata(
			ACTIVITYPUB_PLUGIN_DIR . '/build/reply',
			array(
				'render_callback' => array( self::class, 'render_reply_block' ),
			)
		);
	}

	/**
	 * Get the user ID from a user string.
	 *
	 * @param string $user_string The user string. Can be a user ID, 'site', or 'inherit'.
	 * @return int|null The user ID, or null if the 'inherit' string is not supported in this context.
	 */
	private static function get_user_id( $user_string ) {
		if ( is_numeric( $user_string ) ) {
			return absint( $user_string );
		}

		// If the user string is 'site', return the Blog User ID.
		if ( 'site' === $user_string ) {
			return User_Collection::BLOG_USER_ID;
		}

		// The only other value should be 'inherit', which means to use the query context to determine the User.
		if ( 'inherit' !== $user_string ) {
			return null;
		}

		// For a homepage/front page, if the Blog User is active, use it.
		if ( ( is_front_page() || is_home() ) && ! is_user_type_disabled( 'blog' ) ) {
			return User_Collection::BLOG_USER_ID;
		}

		// If we're in a loop, use the post author.
		$author_id = get_the_author_meta( 'ID' );
		if ( $author_id ) {
			return $author_id;
		}

		// For other pages, the queried object will clue us in.
		$queried_object = get_queried_object();
		if ( ! $queried_object ) {
			return null;
		}

		// If we're on a user archive page, use that user's ID.
		if ( is_a( $queried_object, 'WP_User' ) ) {
			return $queried_object->ID;
		}

		// For a single post, use the post author's ID.
		if ( is_a( $queried_object, 'WP_Post' ) ) {
			return get_the_author_meta( 'ID' );
		}

		// We won't properly account for some conditions, like tag archives.
		return null;
	}

	/**
	 * Filter an array by a list of keys.
	 *
	 * @param array $data The array to filter.
	 * @param array $keys The keys to keep.
	 * @return array The filtered array.
	 */
	protected static function filter_array_by_keys( $data, $keys ) {
		return array_intersect_key( $data, array_flip( $keys ) );
	}

	/**
	 * Render the follow me block.
	 *
	 * @param array $attrs The block attributes.
	 * @return string The HTML to render.
	 */
	public static function render_follow_me_block( $attrs ) {
		$user_id = self::get_user_id( $attrs['selectedUser'] );
		$user    = User_Collection::get_by_id( $user_id );
		if ( is_wp_error( $user ) ) {
			if ( 'inherit' === $attrs['selectedUser'] ) {
				// If the user is 'inherit' and we couldn't determine the user, don't render anything.
				return '<!-- Follow Me block: `inherit` mode does not display on this type of page -->';
			} else {
				// If the user is a specific ID and we couldn't find it, render an error message.
				return '<!-- Follow Me block: user not found -->';
			}
		}

		$attrs['profileData'] = self::filter_array_by_keys(
			$user->to_array(),
			array( 'icon', 'name', 'webfinger' )
		);

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'aria-label' => __( 'Follow me on the Fediverse', 'activitypub' ),
				'class'      => 'activitypub-follow-me-block-wrapper',
				'data-attrs' => wp_json_encode( $attrs ),
			)
		);
		// todo: render more than an empty div?
		return '<div ' . $wrapper_attributes . '></div>';
	}

	/**
	 * Render the follower block.
	 *
	 * @param array $attrs The block attributes.
	 *
	 * @return string The HTML to render.
	 */
	public static function render_follower_block( $attrs ) {
		$followee_user_id = self::get_user_id( $attrs['selectedUser'] );
		if ( is_null( $followee_user_id ) ) {
			return '<!-- Followers block: `inherit` mode does not display on this type of page -->';
		}

		$user = User_Collection::get_by_id( $followee_user_id );
		if ( is_wp_error( $user ) ) {
			return '<!-- Followers block: `' . $followee_user_id . '` not an active ActivityPub user -->';
		}

		$per_page      = absint( $attrs['per_page'] );
		$follower_data = Followers::get_followers_with_count( $followee_user_id, $per_page );

		$attrs['followerData']['total']     = $follower_data['total'];
		$attrs['followerData']['followers'] = array_map(
			function ( $follower ) {
				return self::filter_array_by_keys(
					$follower->to_array(),
					array( 'icon', 'name', 'preferredUsername', 'url' )
				);
			},
			$follower_data['followers']
		);
		$wrapper_attributes                 = get_block_wrapper_attributes(
			array(
				'aria-label' => __( 'Fediverse Followers', 'activitypub' ),
				'class'      => 'activitypub-follower-block',
				'data-attrs' => wp_json_encode( $attrs ),
			)
		);

		$html = '<div ' . $wrapper_attributes . '>';
		if ( $attrs['title'] ) {
			$html .= '<h3>' . esc_html( $attrs['title'] ) . '</h3>';
		}
		$html .= '<ul>';
		foreach ( $follower_data['followers'] as $follower ) {
			$html .= '<li>' . self::render_follower( $follower ) . '</li>';
		}
		// We are only pagination on the JS side. Could be revisited but we gotta ship!
		$html .= '</ul></div>';
		return $html;
	}

	/**
	 * Render the reply block.
	 *
	 * @param array $attrs The block attributes.
	 *
	 * @return string The HTML to render.
	 */
	public static function render_reply_block( $attrs ) {
		/**
		 * Filter the reply block.
		 *
		 * @param string $html  The HTML to render.
		 * @param array  $attrs The block attributes.
		 */
		return apply_filters(
			'activitypub_reply_block',
			sprintf(
				'<p><a title="%2$s" aria-label="%2$s" href="%1$s" class="u-in-reply-to" target="_blank">%3$s</a></p>',
				esc_url( $attrs['url'] ),
				esc_attr__( 'This post is a response to the referenced content.', 'activitypub' ),
				// translators: %s is the URL of the post being replied to.
				sprintf( __( '&#8620;%s', 'activitypub' ), \str_replace( array( 'https://', 'http://' ), '', $attrs['url'] ) )
			),
			$attrs
		);
	}

	/**
	 * Render a follower.
	 *
	 * @param \Activitypub\Model\Follower $follower The follower to render.
	 *
	 * @return string The HTML to render.
	 */
	public static function render_follower( $follower ) {
		$external_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="components-external-link__icon css-rvs7bx esh4a730" aria-hidden="true" focusable="false"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg>';
		$template     =
			'<a href="%s" title="%s" class="components-external-link activitypub-link" target="_blank" rel="external noreferrer noopener">
				<img width="40" height="40" src="%s" class="avatar activitypub-avatar" />
				<span class="activitypub-actor">
					<strong class="activitypub-name">%s</strong>
					<span class="sep">/</span>
					<span class="activitypub-handle">@%s</span>
				</span>
				%s
			</a>';

		$data = $follower->to_array();

		return sprintf(
			$template,
			esc_url( object_to_uri( $data['url'] ) ),
			esc_attr( $data['name'] ),
			esc_attr( $data['icon']['url'] ),
			esc_html( $data['name'] ),
			esc_html( $data['preferredUsername'] ),
			$external_svg
		);
	}
}
