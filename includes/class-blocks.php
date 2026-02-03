<?php
/**
 * Blocks file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;

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

		\add_action( 'load-post-new.php', array( self::class, 'handle_in_reply_to_get_param' ) );
		// Add editor plugin.
		\add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		\add_action( 'rest_api_init', array( self::class, 'register_rest_fields' ) );

		\add_filter( 'activitypub_import_mastodon_post_data', array( self::class, 'filter_import_mastodon_post_data' ), 10, 2 );

		\add_action( 'activitypub_before_get_content', array( self::class, 'add_post_transformation_callbacks' ) );
		\add_filter( 'activitypub_the_content', array( self::class, 'remove_post_transformation_callbacks' ) );
	}

	/**
	 * Enqueue the block editor assets.
	 */
	public static function enqueue_editor_assets() {
		$data = array(
			'namespace'          => ACTIVITYPUB_REST_NAMESPACE,
			'defaultAvatarUrl'   => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
			'enabled'            => array(
				'blog'  => ! is_user_type_disabled( 'blog' ),
				'users' => ! is_user_type_disabled( 'user' ),
			),
			'profileUrls'        => array(
				'user' => \admin_url( 'profile.php#activitypub' ),
				'blog' => \admin_url( 'options-general.php?page=activitypub&tab=blog-profile' ),
			),
			'showAvatars'        => (bool) \get_option( 'show_avatars' ),
			'defaultQuotePolicy' => \get_option( 'activitypub_default_quote_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE ),
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
	 * @param array $args Arguments for the modal.
	 */
	public static function render_modal( $args = array() ) {
		$defaults = array(
			'content'    => '',
			'id'         => '',
			'is_compact' => false,
			'title'      => '',
		);

		$args = \wp_parse_args( $args, $defaults );
		?>

		<div
			class="activitypub-modal__overlay<?php echo \esc_attr( $args['is_compact'] ? ' compact' : '' ); ?>"
			data-wp-bind--hidden="!context.modal.isOpen"
			data-wp-watch="callbacks.handleModalEffects"
			role="dialog"
			aria-modal="true"
			hidden
		>
			<div class="activitypub-modal__frame">
				<?php if ( ! $args['is_compact'] || ! empty( $args['title'] ) ) : ?>
					<div class="activitypub-modal__header">
						<h2
							class="activitypub-modal__title"
							<?php if ( ! empty( $args['id'] ) ) : ?>
								id="<?php echo \esc_attr( $args['id'] . '-title' ); ?>"
							<?php endif; ?>
						><?php echo \esc_html( $args['title'] ); ?></h2>
						<button
							type="button"
							class="activitypub-modal__close wp-element-button wp-block-button__link"
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
}
