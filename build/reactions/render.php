<?php
/**
 * Server-side rendering of the `activitypub/reactions` block.
 *
 * @package ActivityPub
 */

use Activitypub\Blocks;
use Activitypub\Comment;

use function Activitypub\is_activitypub_request;

if ( is_activitypub_request() || is_feed() ) {
	return;
}

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes, array( 'align' => null ) );

/* @var \WP_Block $block Current block. */
$block = $block ?? '';

/* @var string $content Block content. */
$content = $content ?? '';

if ( empty( $content ) ) {
	// Fallback for v1.0.0 blocks.
	$_title  = $attributes['title'] ?? __( 'Fediverse Reactions', 'activitypub' );
	$content = '<h6 class="wp-block-heading">' . esc_html( $_title ) . '</h6>';
	unset( $attributes['title'], $attributes['className'] );
} else {
	$content = implode( PHP_EOL, wp_list_pluck( $block->parsed_block['innerBlocks'], 'innerHTML' ) );
}

// Get the Post ID from attributes or use the current post.
$_post_id = $attributes['postId'] ?? get_the_ID();

// Generate a unique ID for the block.
$block_id = 'activitypub-reactions-block-' . wp_unique_id();

$reactions = array();

foreach ( Comment::get_comment_types() as $_type => $type_object ) {
	$_comments = get_comments(
		array(
			'post_id' => $_post_id,
			'type'    => $_type,
			'status'  => 'approve',
			'parent'  => 0,
		)
	);

	if ( empty( $_comments ) ) {
		continue;
	}

	$count = count( $_comments );
	// phpcs:disable WordPress.WP.I18n
	$label = sprintf(
		_n(
			$type_object['count_single'],
			$type_object['count_plural'],
			$count,
			'activitypub'
		),
		number_format_i18n( $count )
	);
	// phpcs:enable WordPress.WP.I18n

	$reactions[ $_type ] = array(
		'label' => $label,
		'count' => $count,
		'items' => array_map(
			function ( $comment ) {
				return array(
					'name'   => html_entity_decode( $comment->comment_author ),
					'url'    => $comment->comment_author_url,
					'avatar' => get_avatar_url( $comment ),
				);
			},
			$_comments
		),
	);
}

if ( empty( $reactions ) ) {
	echo '<!-- Reactions block: No reactions found. -->';
	return;
}

// Set up the Interactivity API config.
wp_interactivity_config(
	'activitypub/reactions',
	array(
		'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
		'namespace'        => ACTIVITYPUB_REST_NAMESPACE,
	)
);

// Set up the Interactivity API state.
wp_interactivity_state( 'activitypub/reactions', array( 'reactions' => array( $_post_id => $reactions ) ) );

// Render a subset of the most recent reactions.
$reactions = array_map(
	function ( $reaction ) use ( $attributes ) {
		$count = 20;
		if ( 'wide' === $attributes['align'] ) {
			$count = 40;
		} elseif ( 'full' === $attributes['align'] ) {
			$count = 60;
		}

		$reaction['items'] = array_slice( array_reverse( $reaction['items'] ), 0, $count );

		return $reaction;
	},
	$reactions
);

// Initialize the context for the block.
$context = array(
	'blockId'   => $block_id,
	'modal'     => array(
		'isCompact' => true,
		'isOpen'    => false,
		'items'     => array(),
	),
	'postId'    => $_post_id,
	'reactions' => $reactions,
);

// Add the block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                  => $block_id,
		'data-wp-interactive' => 'activitypub/reactions',
		'data-wp-context'     => wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ),
		'data-wp-init'        => 'callbacks.initReactions',
	)
);

ob_start();
?>
<ul class="reactions-list">
	<template data-wp-each="context.modal.items">
		<li class="reaction-item">
			<a data-wp-bind--href="context.item.url" target="_blank" rel="noopener noreferrer">
				<img
					alt=""
					data-wp-bind--alt="context.item.name"
					data-wp-bind--src="context.item.avatar"
					data-wp-on--error="callbacks.setDefaultAvatar"
					src=""
				/>
				<span class="reaction-name" data-wp-text="context.item.name"></span>
			</a>
		</li>
	</template>
</ul>
<?php
$modal_content = ob_get_clean();

?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<div class="activitypub-reactions">
		<?php
		foreach ( $reactions as $_type => $reaction ) :
			/* translators: %s: reaction type. */
			$aria_label = sprintf( __( 'View all %s', 'activitypub' ), Comment::get_comment_type_attr( $_type, 'label' ) );
			?>
		<div class="reaction-group" data-reaction-type="<?php echo esc_attr( $_type ); ?>">
			<ul class="reaction-avatars">
				<template data-wp-each="context.reactions.<?php echo esc_attr( $_type ); ?>.items">
					<li>
						<a
							data-wp-bind--href="context.item.url"
							data-wp-bind--title="context.item.name"
							target="_blank"
							rel="noopener noreferrer"
						>
							<img
								data-wp-bind--src="context.item.avatar"
								data-wp-bind--alt="context.item.name"
								data-wp-on--error="callbacks.setDefaultAvatar"
								class="reaction-avatar"
								height="32"
								width="32"
								src=""
								alt=""
							/>
						</a>
					</li>
				</template>
			</ul>
			<button
				class="reaction-label wp-element-button"
				data-reaction-type="<?php echo esc_attr( $_type ); ?>"
				data-wp-on--click="actions.toggleModal"
				aria-label="<?php echo esc_attr( $aria_label ); ?>"
			>
				<?php echo esc_html( $reaction['label'] ); ?>
			</button>
		</div>
		<?php endforeach; ?>
	</div>

	<?php
	// Render the modal using the Blocks class.
	Blocks::render_modal(
		array(
			'is_compact' => true,
			'content'    => $modal_content,
		)
	);
	?>
</div>
