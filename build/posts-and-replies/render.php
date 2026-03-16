<?php
/**
 * Server-side rendering of the `activitypub/posts-and-replies` block.
 *
 * @since unreleased
 *
 * @package Activitypub
 */

use function Activitypub\is_activitypub_request;

if ( is_activitypub_request() || \is_feed() ) {
	return;
}

/* @var array $attributes Block attributes. */
$attributes = \wp_parse_args(
	$attributes ?? array(),
	array(
		'postsPerPage' => 10,
	)
);

// Determine the author from the current query context.
$author_id = 0;
if ( \is_author() ) {
	$author = \get_queried_object();
	if ( $author instanceof \WP_User ) {
		$author_id = $author->ID;
	}
}

// If not on an author archive, bail.
if ( ! $author_id ) {
	return;
}

// Get the current page number from the main query.
$current_page = max( 1, \get_query_var( 'paged', 1 ) );

// Base query args.
$base_args = array(
	'author'         => $author_id,
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $attributes['postsPerPage'],
	'paged'          => $current_page,
);

/**
 * Filter to exclude posts that contain the activitypub/reply block.
 *
 * @param string   $where The WHERE clause.
 * @param WP_Query $query The query object.
 * @return string Modified WHERE clause.
 */
$exclude_replies_filter = static function ( $where, $query ) {
	if ( $query->get( 'activitypub_exclude_replies' ) ) {
		global $wpdb;
		$where .= $wpdb->prepare(
			" AND {$wpdb->posts}.post_content NOT LIKE %s",
			'%<!-- wp:activitypub/reply%'
		);
	}
	return $where;
};

\add_filter( 'posts_where', $exclude_replies_filter, 10, 2 );

// Query for "Posts" tab (excluding replies).
$posts_query = new \WP_Query(
	array_merge(
		$base_args,
		array( 'activitypub_exclude_replies' => true )
	)
);

\remove_filter( 'posts_where', $exclude_replies_filter, 10 );

// Query for "Posts & Replies" tab (all posts).
$all_posts_query = new \WP_Query( $base_args );

// Set up the Interactivity API context.
$context = array(
	'activeTab' => 'posts',
);

$wrapper_attributes = \get_block_wrapper_attributes(
	array(
		'data-wp-interactive' => 'activitypub/posts-and-replies',
		'data-wp-context'     => \wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ),
	)
);

/**
 * Render a list of posts using inner blocks.
 *
 * Follows the core/post-template pattern: loops through query results
 * and renders the block's inner blocks for each post, injecting the
 * current post's context so blocks like post-title and post-date
 * render the correct content.
 *
 * @param WP_Query $query The query object.
 * @param WP_Block $block The parent block instance.
 * @return string HTML output.
 */
$render_post_list = static function ( $query ) use ( $block ) {
	if ( ! $query->have_posts() ) {
		return '<p>' . \esc_html__( 'No posts found.', 'activitypub' ) . '</p>';
	}

	// Use saved inner blocks, or fall back to a default template.
	$block_instance = $block->parsed_block;
	if ( empty( $block_instance['innerBlocks'] ) ) {
		$block_instance['innerBlocks'] = array(
			array(
				'blockName'    => 'core/post-title',
				'attrs'        => array( 'isLink' => true ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'blockName'    => 'core/post-date',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'blockName'    => 'core/post-excerpt',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
		);
		// Each null maps to an inner block; WP_Block::render() uses this
		// to determine where inner blocks are placed in the output.
		$block_instance['innerContent'] = array( null, null, null );
	}

	// Prevent re-running this render callback for the inner content.
	$block_instance['blockName'] = 'core/null';

	// Enqueue core block styles since we render their markup without actual block registration.
	$core_blocks = array( 'post-template', 'post-title', 'post-date', 'post-excerpt', 'query-pagination' );
	foreach ( $core_blocks as $block_name ) {
		\wp_enqueue_style( "wp-block-{$block_name}" );
	}

	$output = '<ul class="wp-block-post-template is-layout-flow wp-block-post-template-is-layout-flow">';

	while ( $query->have_posts() ) {
		$query->the_post();

		$post_id   = \get_the_ID();
		$post_type = \get_post_type();

		// Inject the current post's context so inner blocks (post-title,
		// post-date, etc.) render content for this specific post.
		$filter_block_context = static function ( $context ) use ( $post_id, $post_type ) {
			$context['postType'] = $post_type;
			$context['postId']   = $post_id;
			return $context;
		};

		\add_filter( 'render_block_context', $filter_block_context, 1 );

		// Render inner blocks with dynamic=false to skip the parent's render callback.
		$block_content = ( new \WP_Block( $block_instance ) )->render( array( 'dynamic' => false ) );

		\remove_filter( 'render_block_context', $filter_block_context, 1 );

		$post_classes = \implode( ' ', \get_post_class( 'wp-block-post' ) );
		$output      .= '<li class="' . \esc_attr( $post_classes ) . '">' . $block_content . '</li>';
	}

	$output .= '</ul>';

	\wp_reset_postdata();

	return $output;
};

/**
 * Render pagination links.
 *
 * @param WP_Query $query The query object.
 * @return string HTML output.
 */
$render_pagination = static function ( $query ) {
	if ( $query->max_num_pages <= 1 ) {
		return '';
	}

	$big   = 999999999;
	$links = \paginate_links(
		array(
			'base'    => str_replace( $big, '%#%', \esc_url( \get_pagenum_link( $big ) ) ),
			'format'  => '',
			'total'   => $query->max_num_pages,
			'current' => $query->get( 'paged' ) ? $query->get( 'paged' ) : 1,
		)
	);

	if ( ! $links ) {
		return '';
	}

	// paginate_links() returns safe HTML with escaped URLs.
	return '<nav class="wp-block-query-pagination is-layout-flex wp-block-query-pagination-is-layout-flex" aria-label="' . \esc_attr__( 'Posts pagination', 'activitypub' ) . '">' . $links . '</nav>';
};

// Render posts tab content.
$posts_content    = $render_post_list( $posts_query );
$posts_pagination = $render_pagination( $posts_query );

// Render posts & replies tab content.
$all_posts_content    = $render_post_list( $all_posts_query );
$all_posts_pagination = $render_pagination( $all_posts_query );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<div class="ap-tabs" role="tablist" aria-label="<?php \esc_attr_e( 'Post filtering', 'activitypub' ); ?>">
		<button
			class="ap-tabs__tab"
			data-tab="posts"
			data-wp-on--click="actions.switchTab"
			data-wp-class--is-active="state.isPostsTab"
			data-wp-bind--aria-selected="state.isPostsTab"
			role="tab"
			aria-selected="true"
			aria-controls="ap-tabpanel-posts"
			id="ap-tab-posts"
			type="button"
		>
			<?php \esc_html_e( 'Posts', 'activitypub' ); ?>
		</button>
		<button
			class="ap-tabs__tab"
			data-tab="posts-and-replies"
			data-wp-on--click="actions.switchTab"
			data-wp-class--is-active="state.isPostsAndRepliesTab"
			data-wp-bind--aria-selected="state.isPostsAndRepliesTab"
			role="tab"
			aria-selected="false"
			aria-controls="ap-tabpanel-posts-and-replies"
			id="ap-tab-posts-and-replies"
			type="button"
		>
			<?php \esc_html_e( 'Posts & Replies', 'activitypub' ); ?>
		</button>
	</div>

	<div
		class="ap-tabs__panel"
		data-wp-bind--hidden="!state.isPostsTab"
		role="tabpanel"
		id="ap-tabpanel-posts"
		aria-labelledby="ap-tab-posts"
	>
		<?php echo $posts_content; // phpcs:ignore WordPress.Security.EscapeOutput -- Content rendered via WP_Block::render(). ?>
		<?php echo $posts_pagination; // phpcs:ignore WordPress.Security.EscapeOutput -- Output from paginate_links(). ?>
	</div>

	<div
		class="ap-tabs__panel"
		data-wp-bind--hidden="!state.isPostsAndRepliesTab"
		role="tabpanel"
		id="ap-tabpanel-posts-and-replies"
		aria-labelledby="ap-tab-posts-and-replies"
	>
		<?php echo $all_posts_content; // phpcs:ignore WordPress.Security.EscapeOutput -- Content rendered via WP_Block::render(). ?>
		<?php echo $all_posts_pagination; // phpcs:ignore WordPress.Security.EscapeOutput -- Output from paginate_links(). ?>
	</div>
</div>
