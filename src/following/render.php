<?php
/**
 * Server-side rendering of the following block.
 *
 * @package Activitypub
 */

use Activitypub\Blocks;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;
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
	$_title  = $attributes['title'] ?? __( 'Fediverse Following', 'activitypub' );
	$content = '<h3 class="wp-block-heading">' . esc_html( $_title ) . '</h3>';
	unset( $attributes['title'], $attributes['className'] );
} else {
	$content = implode( PHP_EOL, wp_list_pluck( $block->parsed_block['innerBlocks'], 'innerHTML' ) );
}

$user_id = Blocks::get_user_id( $attributes['selectedUser'] );
if ( is_null( $user_id ) ) {
	return '<!-- Following block: `inherit` mode does not display on this type of page -->';
}

$user = Actors::get_by_id( $user_id );
if ( is_wp_error( $user ) ) {
	return '<!-- Following block: `' . $user_id . '` not an active ActivityPub user -->';
}

if ( ! Actors::show_social_graph( $user_id ) ) {
	return '<!-- Following block: social graph is hidden for this user -->';
}

$_per_page      = absint( $attributes['per_page'] );
$_show_avatars  = (bool) \get_option( 'show_avatars' );
$following_data = Following::query( $user_id, $_per_page );

// Prepare items data for the Interactivity API context.
$items = array_map(
	/**
	 * Prepare following data for the Interactivity API context.
	 *
	 * @param WP_Post $following Following object.
	 *
	 * @return array
	 */
	static function ( $following ) {
		$actor    = Remote_Actors::get_actor( $following );
		$username = $actor->get_preferred_username();

		return array(
			'handle' => '@' . $username,
			'icon'   => $actor->get_icon(),
			'name'   => $actor->get_name() ?: $username,
			'url'    => object_to_uri( $actor->get_url() ) ?: $actor->get_id(),
		);
	},
	$following_data['following']
);

// Set up the Interactivity API config.
wp_interactivity_config(
	'activitypub/following',
	array(
		'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
		'namespace'        => ACTIVITYPUB_REST_NAMESPACE,
	)
);

// Set initial context data.
$context = array(
	'items'     => $items,
	'isLoading' => false,
	'order'     => $attributes['order'],
	'page'      => 1,
	'pages'     => ceil( $following_data['total'] / $_per_page ),
	'per_page'  => $_per_page,
	'total'     => $following_data['total'],
	'userId'    => $user_id,
	'endpoint'  => 'following',
);

// Get block wrapper attributes with the data-wp-interactive attribute.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                  => wp_unique_id( 'activitypub-following-block-' ),
		'data-wp-interactive' => 'activitypub/following',
		'data-wp-context'     => wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ),
	)
);
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<?php
	Blocks::render_actor_list(
		array(
			'show_avatars' => $_show_avatars,
			'total'        => $following_data['total'],
			'per_page'     => $_per_page,
			'nav_label'    => __( 'Following navigation', 'activitypub' ),
		)
	);
	?>
</div>
