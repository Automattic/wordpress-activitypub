<?php
/**
 * Server-side rendering of the followers block.
 *
 * @package Activitypub
 */

namespace Activitypub\Followers;

use Activitypub\Blocks;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

/* @var string $content Inner blocks content. */
if ( empty( $content ) ) {
	// Fallback for v1.0.0 blocks.
	$_title  = $attributes['title'] ?? __( 'Fediverse Followers', 'activitypub' );
	$content = '<h3 class="wp-block-heading">' . esc_html( $_title ) . '</h3>';
	unset( $attributes['title'], $attributes['className'] );
}

// Get block attributes from the $attributes variable that WordPress passes to this file.
$followee_user_id = Blocks::get_user_id( $attributes['selectedUser'] );
if ( is_null( $followee_user_id ) ) {
	return '<!-- Followers block: `inherit` mode does not display on this type of page -->';
}

$user = Actors::get_by_id( $followee_user_id );
if ( is_wp_error( $user ) ) {
	return '<!-- Followers block: `' . $followee_user_id . '` not an active ActivityPub user -->';
}

$_per_page     = absint( $attributes['per_page'] );
$follower_data = Followers::get_followers_with_count( $followee_user_id, $_per_page );

// Prepare Followers data for the Interactivity API context.
$followers = array_map(
	function ( $follower ) {
		$data = $follower->to_array();

		return array_intersect_key( $data, array_flip( array( 'icon', 'name', 'preferredUsername', 'url' ) ) );
	},
	$follower_data['followers']
);

// Set up the Interactivity API state.
wp_interactivity_state(
	'activitypub/followers',
	array(
		'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
		'namespace'        => ACTIVITYPUB_REST_NAMESPACE,
	)
);

// Set initial context data.
$context = array(
	'userId'    => $followee_user_id,
	'page'      => 1,
	'perPage'   => $_per_page,
	'order'     => $attributes['order'],
	'followers' => $followers,
	'total'     => $follower_data['total'],
	'pages'     => ceil( $follower_data['total'] / $_per_page ),
	'isLoading' => false,
);

// Get block wrapper attributes with the data-wp-interactive attribute.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                  => wp_unique_id( 'activitypub-followers-block-' ),
		'data-wp-interactive' => 'activitypub/followers',
		'data-wp-context'     => wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ),
	)
);

// Generate the HTML for the followers block.
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<div class="followers-container">
		<ul class="followers-list">
			<template data-wp-each="context.followers">
				<li class="follower-item">
					<a data-wp-bind--href="context.item.url"
						class="follower-link"
						target="_blank"
						rel="external noreferrer noopener"
						data-wp-bind--title="context.item.preferredUsername">

						<img
							data-wp-bind--src="context.item.icon.url"
							data-wp-on--error="callbacks.setDefaultAvatar"
							src=""
							alt=""
							class="follower-avatar"
							width="48"
							height="48"
						>

						<div class="follower-info">
							<span class="follower-name" data-wp-text="context.item.name"></span>
							<span class="follower-username" data-wp-text="context.item.preferredUsername"></span>
						</div>

						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="external-link-icon" aria-hidden="true" focusable="false">
							<path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
						</svg>
					</a>
				</li>
			</template>
		</ul>

		<?php if ( $follower_data['total'] > $_per_page ) : ?>
		<nav class="followers-pagination" role="navigation">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Follower navigation', 'activitypub' ); ?></h1>
			<button
				class="pagination-prev wp-block-button__link wp-element-button"
				data-wp-on--click="actions.prevPage"
				data-wp-bind--disabled="state.hidePrevButton"
				aria-label="<?php esc_attr_e( 'Previous page', 'activitypub' ); ?>"
				disabled
			>
				<?php esc_html_e( 'Previous', 'activitypub' ); ?>
			</button>

			<div class="pagination-info" data-wp-text="state.paginationText"></div>

			<button
				class="pagination-next wp-block-button__link wp-element-button"
				data-wp-on--click="actions.nextPage"
				data-wp-bind--disabled="state.hideNextButton"
				aria-label="<?php esc_attr_e( 'Next page', 'activitypub' ); ?>"
			>
				<?php esc_html_e( 'Next', 'activitypub' ); ?>
			</button>
		</nav>

		<div class="followers-loading" data-wp-bind--aria-hidden="!context.isLoading">
			<div class="loading-spinner"></div>
		</div>
		<?php endif; ?>
	</div>
</div>
