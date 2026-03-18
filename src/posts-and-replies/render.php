<?php
/**
 * Server-side rendering of the `activitypub/posts-and-replies` block.
 *
 * @since unreleased
 *
 * @package Activitypub
 */

use Activitypub\Posts_And_Replies;

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

$active_tab   = Posts_And_Replies::get_active_tab();
$is_posts_tab = 'posts' === $active_tab;

// Build query args from the current context (author archive or not).
$query_args = Posts_And_Replies::get_query_args(
	array(
		'posts_per_page' => $attributes['postsPerPage'],
	)
);

// Run both queries: posts-only (excluding replies) and all posts.
$posts_query     = Posts_And_Replies::query( $query_args, true );
$all_posts_query = Posts_And_Replies::query( $query_args, false );

// Set up the Interactivity API context.
$context = array(
	'activeTab' => $active_tab,
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
 * @return string HTML output.
 */
// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- $block is provided by WordPress to render.php files.
$render_post_list = static function ( $query ) use ( $block ) {
	if ( ! $query->have_posts() ) {
		return '<p>' . \esc_html__( 'No posts found.', 'activitypub' ) . '</p>';
	}

	// Use saved inner blocks, or fall back to a default template.
	$block_instance = $block->parsed_block; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
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

// Render tab contents.
$posts_content        = $render_post_list( $posts_query );
$posts_pagination     = Posts_And_Replies::render_pagination( $posts_query, 'posts' );
$all_posts_content    = $render_post_list( $all_posts_query );
$all_posts_pagination = Posts_And_Replies::render_pagination( $all_posts_query, 'posts-and-replies' );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<div class="ap-tabs" role="tablist" aria-label="<?php \esc_attr_e( 'Post filtering', 'activitypub' ); ?>">
		<button
			class="ap-tabs__tab <?php echo $is_posts_tab ? 'is-active' : ''; ?>"
			data-tab="posts"
			data-wp-on--click="actions.switchTab"
			data-wp-on--keydown="actions.onKeyDown"
			data-wp-class--is-active="state.isPostsTab"
			data-wp-bind--aria-selected="state.isPostsTab"
			data-wp-bind--tabindex="state.postsTabIndex"
			role="tab"
			aria-selected="<?php echo $is_posts_tab ? 'true' : 'false'; ?>"
			aria-controls="ap-tabpanel-posts"
			id="ap-tab-posts"
			type="button"
			tabindex="<?php echo $is_posts_tab ? '0' : '-1'; ?>"
		>
			<?php \esc_html_e( 'Posts', 'activitypub' ); ?>
		</button>
		<button
			class="ap-tabs__tab <?php echo ! $is_posts_tab ? 'is-active' : ''; ?>"
			data-tab="posts-and-replies"
			data-wp-on--click="actions.switchTab"
			data-wp-on--keydown="actions.onKeyDown"
			data-wp-class--is-active="state.isPostsAndRepliesTab"
			data-wp-bind--aria-selected="state.isPostsAndRepliesTab"
			data-wp-bind--tabindex="state.postsAndRepliesTabIndex"
			role="tab"
			aria-selected="<?php echo ! $is_posts_tab ? 'true' : 'false'; ?>"
			aria-controls="ap-tabpanel-posts-and-replies"
			id="ap-tab-posts-and-replies"
			type="button"
			tabindex="<?php echo ! $is_posts_tab ? '0' : '-1'; ?>"
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
		<?php echo ! $is_posts_tab ? 'hidden' : ''; ?>
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
		<?php echo $is_posts_tab ? 'hidden' : ''; ?>
	>
		<?php echo $all_posts_content; // phpcs:ignore WordPress.Security.EscapeOutput -- Content rendered via WP_Block::render(). ?>
		<?php echo $all_posts_pagination; // phpcs:ignore WordPress.Security.EscapeOutput -- Output from paginate_links(). ?>
	</div>
</div>
