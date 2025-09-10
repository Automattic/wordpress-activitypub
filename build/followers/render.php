<?php
/**
 * Server-side rendering of the followers block.
 *
 * @package Activitypub
 */

use Activitypub\Blocks;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\is_activitypub_request;
use function Activitypub\object_to_uri;

if ( is_activitypub_request() || is_feed() ) {
	return;
}

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

/* @var WP_Block $block Current block. */
$block = $block ?? '';

/* @var string $content Block content. */
$content = $content ?? '';

if ( empty( $content ) ) {
	// Fallback for v1.0.0 blocks.
	$_title  = $attributes['title'] ?? __( 'Fediverse Followers', 'activitypub' );
	$content = '<h3 class="wp-block-heading">' . esc_html( $_title ) . '</h3>';
	unset( $attributes['title'], $attributes['className'] );
} else {
	$content = implode( PHP_EOL, wp_list_pluck( $block->parsed_block['innerBlocks'], 'innerHTML' ) );
}

$user_id = Blocks::get_user_id( $attributes['selectedUser'] );
if ( is_null( $user_id ) ) {
	return '<!-- Followers block: `inherit` mode does not display on this type of page -->';
}

$user = Actors::get_by_id( $user_id );
if ( is_wp_error( $user ) ) {
	return '<!-- Followers block: `' . $user_id . '` not an active ActivityPub user -->';
}

$_per_page     = absint( $attributes['per_page'] );
$follower_data = Followers::get_followers_with_count( $user_id, $_per_page );

// Prepare Followers data for the Interactivity API context.
$followers = array_map(
	/**
	 * Prepare follower data for the Interactivity API context.
	 *
	 * @param WP_Post $follower Follower object.
	 *
	 * @return array
	 */
	function ( $follower ) {
		$actor    = Remote_Actors::get_actor( $follower );
		$username = $actor->get_preferred_username();

		return array(
			'handle' => '@' . $username,
			'icon'   => $actor->get_icon(),
			'name'   => $actor->get_name() ?? $username,
			'url'    => object_to_uri( $actor->get_url() ) ?? $actor->get_id(),
		);
	},
	$follower_data['followers']
);

// Set up the Interactivity API config.
wp_interactivity_config(
	'activitypub/followers',
	array(
		'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
		'namespace'        => ACTIVITYPUB_REST_NAMESPACE,
	)
);

// Set initial context data.
$context = array(
	'followers' => $followers,
	'isLoading' => false,
	'order'     => $attributes['order'],
	'page'      => 1,
	'pages'     => ceil( $follower_data['total'] / $_per_page ),
	'per_page'  => $_per_page,
	'total'     => $follower_data['total'],
	'userId'    => $user_id,
);

// Get block wrapper attributes with the data-wp-interactive attribute.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                  => wp_unique_id( 'activitypub-followers-block-' ),
		'data-wp-interactive' => 'activitypub/followers',
		'data-wp-context'     => wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ),
	)
);
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
						data-wp-bind--title="context.item.handle">

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
							<span class="follower-username" data-wp-text="context.item.handle"></span>
						</div>

						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="external-link-icon" aria-hidden="true" focusable="false" fill="currentColor">
							<path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
						</svg>
					</a>
				</li>
			</template>
		</ul>

		<?php if ( $follower_data['total'] > $_per_page ) : ?>
		<nav class="followers-pagination" role="navigation">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Follower navigation', 'activitypub' ); ?></h1>
			<a
				class="pagination-previous"
				data-wp-on-async--click="actions.previousPage"
				data-wp-bind--aria-disabled="state.disablePreviousLink"
				aria-label="<?php esc_attr_e( 'Previous page', 'activitypub' ); ?>"
			>
				<?php esc_html_e( 'Previous', 'activitypub' ); ?>
			</a>

			<div class="pagination-info" data-wp-text="state.paginationText"></div>

			<a
				class="pagination-next"
				data-wp-on-async--click="actions.nextPage"
				data-wp-bind--aria-disabled="state.disableNextLink"
				aria-label="<?php esc_attr_e( 'Next page', 'activitypub' ); ?>"
			>
				<?php esc_html_e( 'Next', 'activitypub' ); ?>
			</a>
		</nav>

		<div class="followers-loading" data-wp-bind--aria-hidden="!context.isLoading">
			<div class="loading-spinner"></div>
		</div>
		<?php endif; ?>
	</div>
</div>
