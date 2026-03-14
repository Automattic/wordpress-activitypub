<?php
/**
 * Server-side rendering of the `activitypub/posts-and-replies` block.
 *
 * Renders a tab bar that controls query filtering via URL parameter.
 * Works with a sibling `core/query` block that inherits from the template.
 * The actual query filtering happens in {@see Blocks::filter_query_loop_vars()}.
 *
 * @since unreleased
 *
 * @package Activitypub
 */

use function Activitypub\is_activitypub_request;

if ( is_activitypub_request() || \is_feed() ) {
	return;
}

// Only render on author archives.
if ( ! \is_author() ) {
	return;
}

// Determine active tab from URL parameter.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$active_tab = isset( $_GET['ap_tab'] ) ? \sanitize_key( $_GET['ap_tab'] ) : 'posts';
if ( ! in_array( $active_tab, array( 'posts', 'posts-and-replies' ), true ) ) {
	$active_tab = 'posts';
}

$current_url = \remove_query_arg( array( 'ap_tab', 'paged' ) );
$posts_url   = \add_query_arg( 'ap_tab', 'posts', $current_url );
$all_url     = \add_query_arg( 'ap_tab', 'posts-and-replies', $current_url );

$wrapper_attributes = \get_block_wrapper_attributes();
?>
<nav <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?> aria-label="<?php \esc_attr_e( 'Post filtering', 'activitypub' ); ?>">
	<div class="ap-tabs">
		<a
			class="ap-tabs__tab <?php echo 'posts' === $active_tab ? 'is-active' : ''; ?>"
			href="<?php echo \esc_url( $posts_url ); ?>"
			<?php echo 'posts' === $active_tab ? 'aria-current="page"' : ''; ?>
		>
			<?php \esc_html_e( 'Posts', 'activitypub' ); ?>
		</a>
		<a
			class="ap-tabs__tab <?php echo 'posts-and-replies' === $active_tab ? 'is-active' : ''; ?>"
			href="<?php echo \esc_url( $all_url ); ?>"
			<?php echo 'posts-and-replies' === $active_tab ? 'aria-current="page"' : ''; ?>
		>
			<?php \esc_html_e( 'Posts & Replies', 'activitypub' ); ?>
		</a>
	</div>
</nav>
