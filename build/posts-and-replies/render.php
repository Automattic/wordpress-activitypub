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
 * Render a list of posts.
 *
 * @param WP_Query $query The query object.
 * @return string HTML output.
 */
$render_post_list = static function ( $query ) {
	if ( ! $query->have_posts() ) {
		return '<p>' . \esc_html__( 'No posts found.', 'activitypub' ) . '</p>';
	}

	$output = '<ul class="ap-posts-list">';

	while ( $query->have_posts() ) {
		$query->the_post();
		$output .= '<li class="ap-posts-list__item">';
		$output .= '<a class="ap-posts-list__title" href="' . \esc_url( \get_permalink() ) . '">' . \esc_html( \get_the_title() ) . '</a>';
		$output .= '<time class="ap-posts-list__date" datetime="' . \esc_attr( \get_the_date( 'c' ) ) . '">' . \esc_html( \get_the_date() ) . '</time>';
		if ( \has_excerpt() ) {
			$output .= '<div class="ap-posts-list__excerpt">' . \wp_kses_post( \get_the_excerpt() ) . '</div>';
		}
		$output .= '</li>';
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
	return '<nav class="ap-posts-pagination" aria-label="' . \esc_attr__( 'Posts pagination', 'activitypub' ) . '">' . $links . '</nav>';
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
		<?php echo $posts_content; // phpcs:ignore WordPress.Security.EscapeOutput -- Content escaped in $render_post_list. ?>
		<?php echo $posts_pagination; // phpcs:ignore WordPress.Security.EscapeOutput -- Output from paginate_links(). ?>
	</div>

	<div
		class="ap-tabs__panel"
		data-wp-bind--hidden="!state.isPostsAndRepliesTab"
		role="tabpanel"
		id="ap-tabpanel-posts-and-replies"
		aria-labelledby="ap-tab-posts-and-replies"
	>
		<?php echo $all_posts_content; // phpcs:ignore WordPress.Security.EscapeOutput -- Content escaped in $render_post_list. ?>
		<?php echo $all_posts_pagination; // phpcs:ignore WordPress.Security.EscapeOutput -- Output from paginate_links(). ?>
	</div>
</div>
