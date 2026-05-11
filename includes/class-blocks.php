<?php
/**
 * Blocks file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Cache\Stats_Image;
use Activitypub\Collection\Actors;

/**
 * Block class.
 */
class Blocks {

	/**
	 * HTML tags to skip during block conversion.
	 *
	 * @var array<string>
	 */
	const SKIP_TAGS = array( 'BR', 'CITE', 'SOURCE' );

	/**
	 * HTML void elements that have no closing tag.
	 *
	 * @var array<string>
	 */
	const VOID_TAGS = array( 'AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'SOURCE', 'TRACK', 'WBR' );

	/**
	 * Map of HTML tag names to WordPress block types.
	 *
	 * @var array<string, string>
	 */
	const BLOCK_MAP = array(
		'UL'         => 'list',
		'OL'         => 'list',
		'IMG'        => 'image',
		'BLOCKQUOTE' => 'quote',
		'H1'         => 'heading',
		'H2'         => 'heading',
		'H3'         => 'heading',
		'H4'         => 'heading',
		'H5'         => 'heading',
		'H6'         => 'heading',
		'P'          => 'paragraph',
		'A'          => 'paragraph',
		'ABBR'       => 'paragraph',
		'B'          => 'paragraph',
		'CODE'       => 'paragraph',
		'EM'         => 'paragraph',
		'I'          => 'paragraph',
		'STRONG'     => 'paragraph',
		'SUB'        => 'paragraph',
		'SUP'        => 'paragraph',
		'SPAN'       => 'paragraph',
		'U'          => 'paragraph',
		'FIGURE'     => 'image',
		'HR'         => 'separator',
	);

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// This is already being called on the init hook, so just add it.
		self::register_blocks();
		self::register_patterns();
		self::register_templates();

		\add_action( 'pre_get_posts', array( self::class, 'filter_query_loop_vars' ) );

		\add_action( 'load-post-new.php', array( self::class, 'handle_in_reply_to_get_param' ) );
		// Add editor plugin.
		\add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		\add_action( 'rest_api_init', array( self::class, 'register_rest_fields' ) );

		\add_filter( 'activitypub_import_mastodon_post_data', array( self::class, 'filter_import_mastodon_post_data' ), 10, 2 );
		\add_filter( 'activitypub_attachments', array( self::class, 'add_stats_image_attachment' ), 10, 2 );

		\add_action( 'activitypub_before_get_content', array( self::class, 'add_post_transformation_callbacks' ) );
		\add_filter( 'activitypub_the_content', array( self::class, 'remove_post_transformation_callbacks' ) );
	}

	/**
	 * Enqueue the block editor assets.
	 */
	public static function enqueue_editor_assets() {
		$data = array(
			'namespace'             => ACTIVITYPUB_REST_NAMESPACE,
			'defaultAvatarUrl'      => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
			'enabled'               => array(
				'blog'  => ! is_user_type_disabled( 'blog' ),
				'users' => ! is_user_type_disabled( 'user' ),
			),
			'profileUrls'           => array(
				'user' => \admin_url( 'profile.php#activitypub' ),
				'blog' => \admin_url( 'options-general.php?page=activitypub&tab=blog-profile' ),
			),
			'showAvatars'           => (bool) \get_option( 'show_avatars' ),
			'defaultQuotePolicy'    => \get_option( 'activitypub_default_quote_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE ),
			'objectType'            => \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE ),
			'noteLength'            => ACTIVITYPUB_NOTE_LENGTH,
			'statsImageUrlEndpoint' => Stats_Image::is_available() ? \get_rest_url( null, ACTIVITYPUB_REST_NAMESPACE . '/stats/image-url/{user_id}/{year}' ) : '',
		);
		wp_localize_script( 'wp-editor', '_activityPubOptions', $data );

		// Check for our supported post types.
		$current_screen = \get_current_screen();
		$ap_post_types  = \get_post_types_by_support( 'activitypub' );
		if ( ! $current_screen || ! in_array( $current_screen->post_type, $ap_post_types, true ) ) {
			return;
		}

		$asset_data = include ACTIVITYPUB_PLUGIN_DIR . 'build/editor-plugin/plugin.asset.php';
		$plugin_url = plugins_url( 'build/editor-plugin/plugin.js', ACTIVITYPUB_PLUGIN_FILE );
		wp_enqueue_script( 'activitypub-block-editor', $plugin_url, $asset_data['dependencies'], $asset_data['version'], true );

		$asset_data = include ACTIVITYPUB_PLUGIN_DIR . 'build/pre-publish-panel/plugin.asset.php';
		$plugin_url = plugins_url( 'build/pre-publish-panel/plugin.js', ACTIVITYPUB_PLUGIN_FILE );
		wp_enqueue_script( 'activitypub-pre-publish-panel', $plugin_url, $asset_data['dependencies'], $asset_data['version'], true );
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
	 * Register the blocks.
	 */
	public static function register_blocks() {
		\register_block_type_from_metadata( ACTIVITYPUB_PLUGIN_DIR . '/build/extra-fields' );
		\register_block_type_from_metadata( ACTIVITYPUB_PLUGIN_DIR . '/build/follow-me' );
		\register_block_type_from_metadata( ACTIVITYPUB_PLUGIN_DIR . '/build/followers' );
		\register_block_type_from_metadata( ACTIVITYPUB_PLUGIN_DIR . '/build/posts-and-replies' );
		\register_block_type_from_metadata( ACTIVITYPUB_PLUGIN_DIR . '/build/stats' );

		// Only register the Following block if the Following feature is enabled.
		if ( '1' === \get_option( 'activitypub_following_ui', '0' ) ) {
			\register_block_type_from_metadata( ACTIVITYPUB_PLUGIN_DIR . '/build/following' );
		}
		// Register reactions block, conditionally removing facepile style if avatars are disabled.
		$reactions_args = array();
		if ( ! \get_option( 'show_avatars', true ) ) {
			$reactions_args['styles'] = array();
		}
		\register_block_type_from_metadata( ACTIVITYPUB_PLUGIN_DIR . '/build/reactions', $reactions_args );

		\register_block_type_from_metadata(
			ACTIVITYPUB_PLUGIN_DIR . '/build/reply',
			array(
				'render_callback' => array( self::class, 'render_reply_block' ),
			)
		);

		// Register remote media blocks (server-side only, no editor UI).
		\register_block_type(
			'activitypub/emoji',
			array(
				'attributes'      => array(
					'url'     => array( 'type' => 'string' ),
					'updated' => array( 'type' => 'string' ),
				),
				'render_callback' => array( self::class, 'render_emoji_block' ),
			)
		);

		\register_block_type(
			'activitypub/image',
			array(
				'attributes'      => array(
					'url' => array( 'type' => 'string' ),
				),
				'render_callback' => array( self::class, 'render_image_block' ),
			)
		);

		\register_block_type(
			'activitypub/audio',
			array(
				'attributes'      => array(
					'url' => array( 'type' => 'string' ),
				),
				'render_callback' => array( self::class, 'render_audio_block' ),
			)
		);

		\register_block_type(
			'activitypub/video',
			array(
				'attributes'      => array(
					'url' => array( 'type' => 'string' ),
				),
				'render_callback' => array( self::class, 'render_video_block' ),
			)
		);
	}

	/**
	 * Register block patterns for ActivityPub.
	 */
	public static function register_patterns() {
		// Register the ActivityPub pattern category.
		\register_block_pattern_category(
			'activitypub',
			array(
				'label' => \__( 'Fediverse', 'activitypub' ),
			)
		);

		// Register each pattern.
		require ACTIVITYPUB_PLUGIN_DIR . '/patterns/author-header.php';
		require ACTIVITYPUB_PLUGIN_DIR . '/patterns/author-profile.php';
		require ACTIVITYPUB_PLUGIN_DIR . '/patterns/follow-page.php';
		require ACTIVITYPUB_PLUGIN_DIR . '/patterns/profile-page.php';
		require ACTIVITYPUB_PLUGIN_DIR . '/patterns/social-sidebar.php';

		// Only register the Following page pattern if the Following feature is enabled.
		if ( '1' === \get_option( 'activitypub_following_ui', '0' ) ) {
			require ACTIVITYPUB_PLUGIN_DIR . '/patterns/following-page.php';
		}

		// Only register the Stats post starter pattern in December and January.
		$month = (int) \gmdate( 'n' );
		if ( 12 === $month || 1 === $month ) {
			require ACTIVITYPUB_PLUGIN_DIR . '/patterns/stats-post.php';
		}
	}

	/**
	 * Register FSE templates for block themes.
	 */
	public static function register_templates() {
		// Only register templates for block themes on WP 6.7+.
		if ( ! \function_exists( 'register_block_template' ) || ! \wp_is_block_theme() ) {
			return;
		}

		// Use the core `author` hierarchy slug so WP can resolve this for author archives.
		\register_block_template(
			'activitypub//author',
			array(
				'title'       => \__( 'Author Archive (Fediverse)', 'activitypub' ),
				'description' => \__( 'Displays an author archive with Fediverse profile and follow options.', 'activitypub' ),
				'content'     => '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->
<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
	<!-- wp:pattern {"slug":"activitypub/author-profile"} /-->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/posts-and-replies /-->
	<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template -->
			<!-- wp:post-title {"isLink":true} /-->
			<!-- wp:post-excerpt /-->
		<!-- /wp:post-template -->
		<!-- wp:query-pagination -->
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:query -->
</main>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
				'post_types'  => array(),
			)
		);
	}

	/**
	 * Register REST fields needed for blocks.
	 */
	public static function register_rest_fields() {
		// Register the post_count field for Follow Me block.
		register_rest_field(
			'user',
			'post_count',
			array(
				/**
				 * Get the number of published posts.
				 *
				 * @param array            $response   Prepared response array.
				 * @param string           $field_name The field name.
				 * @param \WP_REST_Request $request    The request object.
				 * @return int The number of published posts.
				 */
				'get_callback' => static function ( $response, $field_name, $request ) {
					return (int) count_user_posts( $request->get_param( 'id' ), 'post', true );
				},
				'schema'       => array(
					'description' => 'Number of published posts',
					'type'        => 'integer',
					'context'     => array( 'activitypub' ),
				),
			)
		);
	}

	/**
	 * Get the user ID from a user string.
	 *
	 * @param string $user_string The user string. Can be a user ID, 'blog', or 'inherit'.
	 * @return int|null The user ID, or null if the 'inherit' string is not supported in this context.
	 */
	public static function get_user_id( $user_string ) {
		if ( is_numeric( $user_string ) ) {
			return absint( $user_string );
		}

		// If the user string is 'blog', return the Blog User ID.
		if ( 'blog' === $user_string ) {
			return Actors::BLOG_USER_ID;
		}

		// The only other value should be 'inherit', which means to use the query context to determine the User.
		if ( 'inherit' !== $user_string ) {
			return null;
		}

		// For a homepage/front page, if the Blog User is active, use it.
		if ( ( is_front_page() || is_home() ) && ! is_user_type_disabled( 'blog' ) ) {
			return Actors::BLOG_USER_ID;
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
	 * Render an actor list block (followers or following).
	 *
	 * @param string    $endpoint   The endpoint type ('followers' or 'following').
	 * @param array     $attributes Block attributes.
	 * @param \WP_Block $block      Block instance.
	 * @param string    $content    Block content.
	 *
	 * @return string|void The HTML to render, or void to render nothing.
	 */
	public static function render_actor_list_block( $endpoint, $attributes, $block, $content ) {
		if ( is_activitypub_request() || \is_feed() ) {
			return '';
		}

		$attributes = \wp_parse_args( $attributes );
		$block_name = 'followers' === $endpoint ? __( 'Followers', 'activitypub' ) : __( 'Following', 'activitypub' );

		if ( empty( $content ) ) {
			// Fallback for v1.0.0 blocks.
			/* translators: %s: Block type (Followers or Following) */
			$_title  = $attributes['title'] ?? \sprintf( __( 'Fediverse %s', 'activitypub' ), $block_name );
			$content = '<h3 class="wp-block-heading">' . \esc_html( $_title ) . '</h3>';
			unset( $attributes['title'], $attributes['className'] );
		} else {
			$content = \implode( PHP_EOL, \wp_list_pluck( $block->parsed_block['innerBlocks'], 'innerHTML' ) );
		}

		$user_id = self::get_user_id( $attributes['selectedUser'] );
		if ( \is_null( $user_id ) ) {
			/* translators: %s: Block type (Followers or Following) */
			return \sprintf( '<!-- %s block: `inherit` mode does not display on this type of page -->', $block_name );
		}

		$user = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $user ) ) {
			/* translators: 1: Block type (Followers or Following), 2: User ID */
			return \sprintf( '<!-- %1$s block: `%2$s` not an active ActivityPub user -->', $block_name, $user_id );
		}

		if ( ! Actors::show_social_graph( $user_id ) ) {
			/* translators: %s: Block type (Followers or Following) */
			return \sprintf( '<!-- %s block: social graph is hidden for this user -->', $block_name );
		}

		$_per_page     = \max( 1, \absint( $attributes['per_page'] ) );
		$_show_avatars = (bool) \get_option( 'show_avatars' );

		// Query the appropriate collection.
		if ( 'followers' === $endpoint ) {
			$data  = \Activitypub\Collection\Followers::query( $user_id, $_per_page );
			$items = $data['followers'];
		} else {
			$data  = \Activitypub\Collection\Following::query( $user_id, $_per_page );
			$items = $data['following'];
		}

		// Prepare items data for the Interactivity API context.
		$prepared_items = \array_map(
			static function ( $item ) {
				$actor = \Activitypub\Collection\Remote_Actors::get_actor( $item );

				// Restrict URLs to http/https schemes to prevent XSS via javascript: URIs.
				$url = object_to_uri( $actor->get_url() ) ?: $actor->get_id();

				return array(
					'handle' => '@' . $actor->get_webfinger(),
					'icon'   => $actor->get_icon(),
					'name'   => $actor->get_name() ?: $actor->get_preferred_username(),
					'url'    => \esc_url( $url, array( 'http', 'https' ) ),
				);
			},
			$items
		);

		$store_name = 'activitypub/' . $endpoint;

		// Set up the Interactivity API config.
		\wp_interactivity_config(
			$store_name,
			array(
				'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
				'namespace'        => ACTIVITYPUB_REST_NAMESPACE,
			)
		);

		// Set initial context data.
		$context = array(
			'items'     => $prepared_items,
			'isLoading' => false,
			'order'     => $attributes['order'],
			'page'      => 1,
			'pages'     => \ceil( $data['total'] / $_per_page ),
			'perPage'   => $_per_page,
			'total'     => $data['total'],
			'userId'    => $user_id,
			'endpoint'  => $endpoint,
		);

		// Get block wrapper attributes with the data-wp-interactive attribute.
		$wrapper_attributes = \get_block_wrapper_attributes(
			array(
				'id'                  => \wp_unique_id( 'activitypub-' . $endpoint . '-block-' ),
				'data-wp-interactive' => $store_name,
				'data-wp-context'     => \wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ),
			)
		);

		/* translators: %s: Block type (Followers or Following) */
		$nav_label = \sprintf( __( '%s navigation', 'activitypub' ), $block_name );

		\ob_start();
		?>
		<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<?php
			self::render_actor_list(
				array(
					'show_avatars' => $_show_avatars,
					'total'        => $data['total'],
					'per_page'     => $_per_page,
					'nav_label'    => $nav_label,
				)
			);
			?>
		</div>
		<?php
		return \ob_get_clean();
	}

	/**
	 * Render the emoji block.
	 *
	 * Replaces emoji shortcode with cached img tag at runtime.
	 *
	 * @param array  $attrs   The block attributes.
	 * @param string $content The block inner content (emoji shortcode).
	 *
	 * @return string The rendered emoji img tag.
	 */
	public static function render_emoji_block( $attrs, $content ) {
		if ( empty( $attrs['url'] ) || empty( $content ) ) {
			return $content;
		}

		$url       = $attrs['url'];
		$shortcode = trim( $content );
		$name      = trim( $shortcode, ':' );

		/**
		 * Filters a remote media URL for caching.
		 *
		 * @param string      $url       The remote media URL.
		 * @param string      $context   The context ('emoji').
		 * @param int|null    $entity_id The entity ID.
		 * @param array       $options   Additional options.
		 */
		$cached_url = \apply_filters(
			'activitypub_remote_media_url',
			$url,
			'emoji',
			null,
			array( 'updated' => $attrs['updated'] ?? null )
		);

		return Emoji::get_img_tag( $cached_url ?: $url, $name );
	}

	/**
	 * Render the image block.
	 *
	 * Replaces remote image URL with cached URL at runtime.
	 *
	 * @param array  $attrs   The block attributes.
	 * @param string $content The block inner content (img tag).
	 *
	 * @return string The rendered content with cached URL.
	 */
	public static function render_image_block( $attrs, $content ) {
		if ( empty( $attrs['url'] ) || empty( $content ) ) {
			return $content;
		}

		$url = $attrs['url'];

		// Get entity ID from context.
		$entity_id = null;
		$post      = \get_post();
		if ( $post ) {
			$entity_id = $post->ID;
		}

		/**
		 * Filters a remote image URL for caching.
		 *
		 * @param string      $url       The remote image URL.
		 * @param string      $context   The context ('media').
		 * @param int|null    $entity_id The entity ID.
		 * @param array       $options   Additional options.
		 */
		$cached_url = \apply_filters( 'activitypub_remote_media_url', $url, 'media', $entity_id, array() );

		if ( $cached_url && $cached_url !== $url ) {
			return \str_replace( $url, $cached_url, $content );
		}

		return $content;
	}

	/**
	 * Render the audio block.
	 *
	 * Replaces remote audio URL with cached URL at runtime.
	 *
	 * @param array  $attrs   The block attributes.
	 * @param string $content The block inner content (audio tag).
	 *
	 * @return string The rendered content with cached URL.
	 */
	public static function render_audio_block( $attrs, $content ) {
		if ( empty( $attrs['url'] ) || empty( $content ) ) {
			return $content;
		}

		$url = $attrs['url'];

		// Get entity ID from context.
		$entity_id = null;
		$post      = \get_post();
		if ( $post ) {
			$entity_id = $post->ID;
		}

		/**
		 * Filters a remote audio URL for caching.
		 *
		 * @param string      $url       The remote audio URL.
		 * @param string      $context   The context ('audio').
		 * @param int|null    $entity_id The entity ID.
		 * @param array       $options   Additional options.
		 */
		$cached_url = \apply_filters( 'activitypub_remote_media_url', $url, 'audio', $entity_id, array() );

		if ( $cached_url && $cached_url !== $url ) {
			return \str_replace( $url, $cached_url, $content );
		}

		return $content;
	}

	/**
	 * Render the video block.
	 *
	 * Replaces remote video URL with cached URL at runtime.
	 *
	 * @param array  $attrs   The block attributes.
	 * @param string $content The block inner content (video tag).
	 *
	 * @return string The rendered content with cached URL.
	 */
	public static function render_video_block( $attrs, $content ) {
		if ( empty( $attrs['url'] ) || empty( $content ) ) {
			return $content;
		}

		$url = $attrs['url'];

		// Get entity ID from context.
		$entity_id = null;
		$post      = \get_post();
		if ( $post ) {
			$entity_id = $post->ID;
		}

		/**
		 * Filters a remote video URL for caching.
		 *
		 * @param string      $url       The remote video URL.
		 * @param string      $context   The context ('video').
		 * @param int|null    $entity_id The entity ID.
		 * @param array       $options   Additional options.
		 */
		$cached_url = \apply_filters( 'activitypub_remote_media_url', $url, 'video', $entity_id, array() );

		if ( $cached_url && $cached_url !== $url ) {
			return \str_replace( $url, $cached_url, $content );
		}

		return $content;
	}

	/**
	 * Render the reply block.
	 *
	 * @param array $attrs The block attributes.
	 *
	 * @return string The HTML to render.
	 */
	public static function render_reply_block( $attrs ) {
		if ( is_activitypub_request() ) {
			$attrs['embedPost'] = false;
		}

		// Return early if no URL is provided.
		if ( empty( $attrs['url'] ) ) {
			return null;
		}

		$show_embed = isset( $attrs['embedPost'] ) && $attrs['embedPost'];

		$wrapper_attrs = get_block_wrapper_attributes(
			array(
				'aria-label'       => __( 'Reply', 'activitypub' ),
				'class'            => 'activitypub-reply-block',
				'data-in-reply-to' => $attrs['url'],
			)
		);

		$html = '<div ' . $wrapper_attrs . '>';

		// Try to get and append the embed if requested.
		$embed = null;
		if ( $show_embed ) {
			// Use the theme's content width or a reasonable default to avoid narrow embeds.
			$embed_width = ! empty( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : 600;
			$embed       = wp_oembed_get( $attrs['url'], array( 'width' => $embed_width ) );
			if ( $embed ) {
				$html .= $embed;
				\wp_enqueue_script( 'wp-embed' );
			}
		}

		// Show the link if embed is not requested or if embed failed.
		if ( ! $show_embed || ! $embed ) {
			$html .= sprintf(
				'<p><a title="%2$s" aria-label="%2$s" href="%1$s" class="u-in-reply-to" target="_blank">%3$s</a></p>',
				esc_url( $attrs['url'] ),
				esc_attr__( 'This post is a response to the referenced content.', 'activitypub' ),
				// translators: %s is the URL of the post being replied to.
				sprintf( __( '&#8620;%s', 'activitypub' ), \str_replace( array( 'https://', 'http://' ), '', esc_url( $attrs['url'] ) ) )
			);
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders a modal component that can be used by different blocks.
	 *
	 * @param array $args {
	 *     Arguments for the modal.
	 *
	 *     @type string $content       The modal content HTML.
	 *     @type string $id            Optional ID prefix for the modal elements.
	 *     @type bool   $is_compact    Whether the modal is compact (popover-style). Default false.
	 *     @type string $title         Static title text for the modal header.
	 *     @type string $title_binding Optional Interactivity API binding for a dynamic title
	 *                                 (e.g. 'context.modal.title'). When set, uses data-wp-text
	 *                                 on the title element and enables dynamic compact toggling.
	 * }
	 */
	public static function render_modal( $args = array() ) {
		$defaults = array(
			'content'       => '',
			'id'            => '',
			'is_compact'    => false,
			'title'         => '',
			'title_binding' => '',
		);

		$args = \wp_parse_args( $args, $defaults );
		?>

		<div
			class="activitypub-modal__overlay<?php echo \esc_attr( $args['is_compact'] ? ' compact' : '' ); ?>"
			data-wp-bind--hidden="!context.modal.isOpen"
			data-wp-watch="callbacks.handleModalEffects"
			<?php if ( ! empty( $args['title_binding'] ) ) : ?>
				data-wp-class--compact="context.modal.isCompact"
			<?php endif; ?>
			role="dialog"
			aria-modal="true"
			hidden
		>
			<div class="activitypub-modal__frame">
				<?php if ( ! $args['is_compact'] || ! empty( $args['title'] ) || ! empty( $args['title_binding'] ) ) : ?>
					<div class="activitypub-modal__header">
						<h2
							class="activitypub-modal__title"
							<?php if ( ! empty( $args['id'] ) ) : ?>
								id="<?php echo \esc_attr( $args['id'] . '-title' ); ?>"
							<?php endif; ?>
							<?php if ( ! empty( $args['title_binding'] ) ) : ?>
								data-wp-text="<?php echo \esc_attr( $args['title_binding'] ); ?>"
							<?php endif; ?>
						><?php echo \esc_html( $args['title'] ); ?></h2>
						<button
							type="button"
							class="activitypub-modal__close wp-element-button"
							data-wp-on--click="actions.closeModal"
							aria-label="<?php echo \esc_attr__( 'Close dialog', 'activitypub' ); ?>"
						>
							<svg fill="currentColor" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
								<path d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"></path>
							</svg>
						</button>
					</div>
				<?php endif; ?>
				<div class="activitypub-modal__content">
					<?php echo $args['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a help section explaining the Fediverse inside modal dialogs.
	 *
	 * Outputs a collapsible `<details>` element that explains decentralized
	 * interactions to users unfamiliar with the Fediverse.
	 *
	 * @since 8.0.0
	 */
	public static function render_modal_help() {
		?>
		<details class="activitypub-dialog__help">
			<summary><?php \esc_html_e( 'Why do I need to enter my profile?', 'activitypub' ); ?></summary>
			<p>
				<?php \esc_html_e( 'This site is part of the ⁂ open social web, a network of interconnected social platforms (like Mastodon, Pixelfed, Friendica, and others). Unlike centralized social media, your account lives on a platform of your choice, and you can interact with people across different platforms.', 'activitypub' ); ?>
			</p>
			<p>
				<?php \esc_html_e( 'By entering your profile, we can send you to your account where you can complete this action.', 'activitypub' ); ?>
			</p>
		</details>
		<?php
	}

	/**
	 * Renders an actor list component that can be used by different blocks.
	 *
	 * @param array $args Arguments for the actor list.
	 */
	public static function render_actor_list( $args = array() ) {
		$defaults = array(
			'show_avatars'    => true,
			'show_pagination' => true,
			'total'           => 0,
			'per_page'        => 10,
			'nav_label'       => __( 'Actor navigation', 'activitypub' ),
		);

		$args = \wp_parse_args( $args, $defaults );

		// Sanitize numeric values, ensuring per_page is at least 1 to avoid division by zero.
		$args['total']    = \absint( $args['total'] );
		$args['per_page'] = \max( 1, \absint( $args['per_page'] ) );
		?>

		<div class="activitypub-actor-list-container">
			<ul class="activitypub-actor-list">
				<template data-wp-each="context.items">
					<li class="activitypub-actor-item">
						<a href="#"
							data-wp-bind--href="context.item.url"
							class="activitypub-actor-link"
							target="_blank"
							rel="external noreferrer noopener"
							data-wp-bind--title="context.item.handle">

							<?php if ( $args['show_avatars'] ) : ?>
							<img
								data-wp-bind--src="context.item.icon.url"
								data-wp-on--error="callbacks.setDefaultAvatar"
								src=""
								alt=""
								class="activitypub-actor-avatar"
								width="48"
								height="48"
							>
							<?php endif; ?>

							<div class="activitypub-actor-info">
								<span class="activitypub-actor-name" data-wp-text="context.item.name"></span>
								<span class="activitypub-actor-handle" data-wp-text="context.item.handle"></span>
							</div>

							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="external-link-icon" aria-hidden="true" focusable="false" fill="currentColor">
								<path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
							</svg>
						</a>
					</li>
				</template>
			</ul>

			<?php if ( $args['show_pagination'] && $args['total'] > $args['per_page'] ) : ?>
			<nav class="activitypub-actor-list-pagination" role="navigation">
				<h1 class="screen-reader-text"><?php echo \esc_html( $args['nav_label'] ); ?></h1>
				<a
					href="#"
					role="button"
					class="pagination-previous"
					data-wp-on-async--click="actions.previousPage"
					data-wp-bind--aria-disabled="state.disablePreviousLink"
					aria-label="<?php \esc_attr_e( 'Previous page', 'activitypub' ); ?>"
				>
					<?php \esc_html_e( 'Previous', 'activitypub' ); ?>
				</a>

				<div class="pagination-info" data-wp-text="state.paginationText"></div>

				<a
					href="#"
					role="button"
					class="pagination-next"
					data-wp-on-async--click="actions.nextPage"
					data-wp-bind--aria-disabled="state.disableNextLink"
					aria-label="<?php \esc_attr_e( 'Next page', 'activitypub' ); ?>"
				>
					<?php \esc_html_e( 'Next', 'activitypub' ); ?>
				</a>
			</nav>

			<div class="activitypub-actor-list-loading" data-wp-bind--aria-hidden="!context.isLoading">
				<div class="loading-spinner"></div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Converts content to blocks before saving to the database.
	 *
	 * @param array $data The post data to be inserted.
	 * @param array $post The Mastodon Create activity.
	 *
	 * @return array
	 */
	public static function filter_import_mastodon_post_data( $data, $post ) {
		// Convert paragraphs to blocks.
		\preg_match_all( '#<p>.*?</p>#is', $data['post_content'], $matches );
		$blocks = \array_map(
			static function ( $paragraph ) {
				return '<!-- wp:paragraph -->' . PHP_EOL . $paragraph . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL;
			},
			$matches[0] ?? array()
		);

		$data['post_content'] = \rtrim( \implode( PHP_EOL, $blocks ), PHP_EOL );

		// Add reply block if it's a reply.
		if ( ! empty( $post['object']['inReplyTo'] ) ) {
			$reply_block          = \sprintf( '<!-- wp:activitypub/reply {"url":"%1$s","embedPost":true} /-->' . PHP_EOL, \esc_url( $post['object']['inReplyTo'] ) );
			$data['post_content'] = $reply_block . $data['post_content'];
		}

		return $data;
	}

	/**
	 * Add Interactivity directions to the specified element.
	 *
	 * @param string   $content    The block content.
	 * @param string[] $selector   The selector for the element to add directions to.
	 * @param string[] $attributes The attributes to add to the element.
	 *
	 * @return string The updated content.
	 */
	public static function add_directions( $content, $selector, $attributes ) {
		$tags = new \WP_HTML_Tag_Processor( $content );

		while ( $tags->next_tag( $selector ) ) {
			foreach ( $attributes as $key => $value ) {
				if ( 'class' === $key ) {
					$tags->add_class( $value );
					continue;
				}

				$tags->set_attribute( $key, $value );
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * Add post transformation callbacks.
	 *
	 * @param object $post The post object.
	 */
	public static function add_post_transformation_callbacks( $post ) {
		\add_filter( 'render_block_core/embed', array( self::class, 'revert_embed_links' ), 10, 2 );
		\add_filter( 'render_block_activitypub/stats', '__return_empty_string' );

		// Only transform reply link if it's the first block in the post.
		$blocks = \parse_blocks( $post->post_content );
		if ( ! empty( $blocks ) && 'activitypub/reply' === $blocks[0]['blockName'] ) {
			\add_filter( 'render_block_activitypub/reply', array( self::class, 'generate_reply_link' ), 10, 2 );
		}
	}

	/**
	 * Remove post transformation callbacks.
	 *
	 * @param string $content The post content.
	 *
	 * @return string The updated content.
	 */
	public static function remove_post_transformation_callbacks( $content ) {
		\remove_filter( 'render_block_core/embed', array( self::class, 'revert_embed_links' ) );
		\remove_filter( 'render_block_activitypub/reply', array( self::class, 'generate_reply_link' ) );
		\remove_filter( 'render_block_activitypub/stats', '__return_empty_string' );

		return $content;
	}

	/**
	 * Generate HTML @ link for reply block.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string The HTML @ link.
	 */
	public static function generate_reply_link( $block_content, $block ) {
		// Unhook ourselves after first execution to ensure only the first reply block gets transformed.
		\remove_filter( 'render_block_activitypub/reply', array( self::class, 'generate_reply_link' ) );

		// Return empty string if no URL is provided.
		if ( empty( $block['attrs']['url'] ) ) {
			return '';
		}

		$url = $block['attrs']['url'];

		// Try to get ActivityPub representation. Is likely already cached.
		$object = Http::get_remote_object( $url );
		if ( \is_wp_error( $object ) ) {
			return '';
		}

		$author_url = $object['attributedTo'] ?? '';
		if ( ! $author_url ) {
			return '';
		}

		// Fetch author information.
		$author = Http::get_remote_object( $author_url );
		if ( \is_wp_error( $author ) ) {
			return '';
		}

		// Get webfinger identifier.
		$webfinger = '';
		if ( ! empty( $author['webfinger'] ) ) {
			$webfinger = \str_replace( 'acct:', '', $author['webfinger'] );
		} elseif ( ! empty( $author['preferredUsername'] ) && ! empty( $author['url'] ) ) {
			// Construct webfinger-style identifier from username and domain.
			$domain    = \wp_parse_url( $author['url'], PHP_URL_HOST );
			$webfinger = '@' . $author['preferredUsername'] . '@' . $domain;
		}

		if ( ! $webfinger ) {
			return '';
		}

		// Generate HTML @ link.
		return \sprintf(
			'<p class="ap-reply-mention"><a rel="mention ugc" href="%1$s" title="%2$s">%3$s</a></p>',
			\esc_url( $url ),
			\esc_attr( $webfinger ),
			\esc_html( '@' . strtok( $webfinger, '@' ) )
		);
	}

	/**
	 * Add the stats image as an attachment when a post contains the stats block.
	 *
	 * Parses the post content for activitypub/stats blocks and appends each
	 * as an Image attachment to the ActivityPub object.
	 *
	 * @since 8.1.0
	 *
	 * @param array    $attachments The existing attachments.
	 * @param \WP_Post $post        The post object.
	 *
	 * @return array The attachments with stats images appended.
	 */
	public static function add_stats_image_attachment( $attachments, $post ) {
		if ( ! Stats_Image::is_available() ) {
			return $attachments;
		}

		/*
		 * The stats image intentionally bypasses the `activitypub_max_image_attachments`
		 * limit because it replaces the block content rather than being an inline image
		 * extracted from the post. It is always appended so that the share-pic is
		 * included in the federated activity regardless of the attachment cap.
		 */
		$blocks       = \parse_blocks( $post->post_content );
		$stats_blocks = self::find_blocks_recursive( $blocks, 'activitypub/stats' );

		foreach ( $stats_blocks as $block ) {
			$user_id = self::get_user_id( $block['attrs']['selectedUser'] ?? 'blog' );

			if ( null === $user_id ) {
				continue;
			}

			$year = (int) ( $block['attrs']['year'] ?? (int) \gmdate( 'Y' ) - 1 );
			$url  = Stats_Image::get_url( $user_id, $year );

			if ( \is_wp_error( $url ) ) {
				continue;
			}

			// Determine mime type from URL extension.
			$mime_type = \str_ends_with( $url, '.webp' ) ? 'image/webp' : 'image/png';

			$attachments[] = array(
				'type'      => 'Image',
				'mediaType' => $mime_type,
				'url'       => $url,
				'name'      => \sprintf(
					/* translators: %d: The year */
					\__( 'Fediverse Stats %d', 'activitypub' ),
					$year
				),
			);
		}

		return $attachments;
	}

	/**
	 * Recursively find blocks of a given type in a block tree.
	 *
	 * @since 8.1.0
	 *
	 * @param array  $blocks     The parsed blocks.
	 * @param string $block_name The block name to search for.
	 *
	 * @return array The matching blocks.
	 */
	private static function find_blocks_recursive( $blocks, $block_name ) {
		$found = array();

		foreach ( $blocks as $block ) {
			if ( $block_name === $block['blockName'] ) {
				$found[] = $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = \array_merge( $found, self::find_blocks_recursive( $block['innerBlocks'], $block_name ) );
			}
		}

		return $found;
	}

	/**
	 * Transform Embed blocks to block level link.
	 *
	 * Remote servers will simply drop iframe elements, rendering incomplete content.
	 *
	 * @see https://www.w3.org/TR/activitypub/#security-sanitizing-content
	 * @see https://www.w3.org/wiki/ActivityPub/Primer/HTML
	 *
	 * @param string $block_content The block content (html).
	 * @param object $block         The block object.
	 *
	 * @return string A block level link
	 */
	public static function revert_embed_links( $block_content, $block ) {
		if ( ! isset( $block['attrs']['url'] ) ) {
			return $block_content;
		}
		return '<p><a href="' . esc_url( $block['attrs']['url'] ) . '">' . $block['attrs']['url'] . '</a></p>';
	}

	/**
	 * Convert HTML content to blocks.
	 *
	 * Tokenizes the content with wp_html_split(), tracks nesting depth,
	 * and wraps each top-level element in block comment delimiters.
	 *
	 * @since 8.1.0
	 *
	 * @param string $content The HTML content.
	 *
	 * @return string The content converted to blocks.
	 */
	public static function convert_from_html( $content ) {
		if ( empty( $content ) ) {
			return '';
		}

		$tokens       = \wp_html_split( $content );
		$_content     = '';
		$depth        = 0;
		$current_tag  = '';
		$current_html = '';

		foreach ( $tokens as $token ) {
			if ( '' === $token ) {
				continue;
			}

			// Text content — accumulate only inside a top-level element.
			if ( '<' !== $token[0] ) {
				if ( $depth > 0 ) {
					$current_html .= $token;
				}
				continue;
			}

			// Closing tag.
			if ( '/' === $token[1] ) {
				$current_html .= $token;
				--$depth;

				if ( 0 === $depth && '' !== $current_tag ) {
					$_content    .= self::to_block( $current_tag, $current_html );
					$current_tag  = '';
					$current_html = '';
				}
				continue;
			}

			// Extract the tag name from the opening tag.
			if ( ! \preg_match( '/^<([a-zA-Z][a-zA-Z0-9]*)/', $token, $m ) ) {
				if ( $depth > 0 ) {
					$current_html .= $token;
				}
				continue;
			}

			$tag = \strtoupper( $m[1] );

			// Start of a new top-level element.
			if ( 0 === $depth ) {
				$current_tag  = $tag;
				$current_html = $token;
			} else {
				$current_html .= $token;
			}

			// Void elements don't increase depth — flush immediately at top level.
			if ( \in_array( $tag, self::VOID_TAGS, true ) ) {
				if ( 0 === $depth && '' !== $current_tag ) {
					$_content    .= self::to_block( $current_tag, $current_html );
					$current_tag  = '';
					$current_html = '';
				}
			} else {
				++$depth;
			}
		}

		return $_content;
	}

	/**
	 * Wrap an HTML element in block comment delimiters.
	 *
	 * @since 8.1.0
	 *
	 * @param string $tag  The uppercase tag name.
	 * @param string $html The element HTML.
	 *
	 * @return string The block-wrapped HTML, or empty string for skipped tags.
	 */
	private static function to_block( $tag, $html ) {
		if ( \in_array( $tag, self::SKIP_TAGS, true ) ) {
			return '';
		}

		$block_type  = self::BLOCK_MAP[ $tag ] ?? 'html';
		$block_attrs = array();

		if ( 'OL' === $tag ) {
			$block_attrs['ordered'] = true;
		}

		return \get_comment_delimited_block_content( $block_type, $block_attrs, \trim( $html ) );
	}

	/**
	 * Filter the main query to exclude replies.
	 *
	 * Adds a WHERE clause to exclude posts containing the `activitypub/reply`
	 * block when the visitor has explicitly requested the "Posts" tab via
	 * `?filter=posts`. This filters the main query so that Query Loop blocks
	 * with `inherit: true` also pick up the filter.
	 *
	 * The filter only attaches on that explicit opt-in. Admin, feed, and any
	 * regular frontend request (front page, archives, search…) are never
	 * touched, which is why no block-presence probing is needed: the only
	 * way `?filter=posts` appears in a URL is from a click on the
	 * `activitypub/posts-and-replies` tab block.
	 *
	 * @since 8.1.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public static function filter_query_loop_vars( $query ) {
		// Never touch admin or feed queries.
		if ( \is_admin() || $query->is_feed() ) {
			return;
		}

		if ( ! $query->is_main_query() || $query->is_singular() ) {
			return;
		}

		// Skip the reply-exclusion filter for queries that only target
		// non-ActivityPub post types to avoid a full table scan.
		$query_post_type = $query->get( 'post_type' );
		if ( ! empty( $query_post_type ) && 'any' !== $query_post_type ) {
			$query_post_types = (array) $query_post_type;
			if ( ! array_intersect( $query_post_types, \get_post_types_by_support( 'activitypub' ) ) ) {
				return;
			}
		}

		// Only filter when the "Posts" tab has been explicitly selected.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['filter'] ) || 'posts' !== \sanitize_key( \wp_unslash( $_GET['filter'] ) ) ) {
			return;
		}

		\add_filter( 'posts_where', array( self::class, 'exclude_replies_where' ) );
	}

	/**
	 * Exclude posts containing the activitypub/reply block.
	 *
	 * Removes itself after the first execution to avoid
	 * affecting secondary queries on the same page.
	 *
	 * @since 8.1.0
	 *
	 * @param string $where The WHERE clause.
	 * @return string Modified WHERE clause.
	 */
	public static function exclude_replies_where( $where ) {
		\remove_filter( 'posts_where', array( self::class, 'exclude_replies_where' ) );

		global $wpdb;

		$where .= $wpdb->prepare(
			" AND {$wpdb->posts}.post_content NOT LIKE %s",
			'%<!-- wp:activitypub/reply%'
		);

		return $where;
	}
}
