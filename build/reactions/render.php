<?php
/**
 * Server-side rendering of the `activitypub/reactions` block.
 *
 * @package ActivityPub
 */

use Activitypub\Comment;

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

/* @var string $content Inner blocks content. */
if ( empty( $content ) ) {
	// Fallback for v1.0.0 blocks.
	$_title  = $attributes['title'] ?? \__( 'Fediverse Reactions', 'activitypub' );
	$content = '<h6 class="wp-block-heading">' . esc_html( $_title ) . '</h6>';
	unset( $attributes['title'], $attributes['className'] );
}


// Get the post ID from attributes or use the current post.
$_post_id = $attributes['postId'] ?? get_the_ID();

// Generate a unique ID for the block.
$block_id = 'activitypub-reactions-block-' . wp_unique_id();

$reactions = array();

foreach ( Comment::get_comment_types() as $type_object ) {
	$comments = get_comments(
		array(
			'post_id' => $_post_id,
			'type'    => $type_object['type'],
			'status'  => 'approve',
		)
	);

	if ( empty( $comments ) ) {
		continue;
	}

	// repeat entries in the array 100times.
	$comments = array_fill( 0, 100, $comments[0] );

	$count = count( $comments );
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

	$reactions[ $type_object['collection'] ] = array(
		'label' => $label,
		'count' => $count,
		'items' => array_map(
			function ( $comment ) {
				return array(
					'id'     => $comment->comment_ID,
					'name'   => $comment->comment_author,
					'url'    => $comment->comment_author_url,
					'avatar' => get_comment_meta( $comment->comment_ID, 'avatar_url', true ),
				);
			},
			$comments // Load all comments without slicing
		),
	);
}

// Set up the Interactivity API state.
wp_interactivity_state(
	'activitypub/reactions',
	array(
		'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
		'namespace'        => ACTIVITYPUB_REST_NAMESPACE,
	)
);

// Initialize the context for the block.
$context = array(
	'postId'    => $_post_id,
	'reactions' => $reactions,
	'modal'     => array(
		'isOpen'       => false,
		'reactionType' => null,
		'items'        => array(),
		'isLoading'    => false,
	),
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
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<div class="activitypub-reactions">
		<?php foreach ( $reactions as $_type => $reaction ) : ?>
		<div class="reaction-group" data-wp-bind--hidden="!context.reactions.<?php echo esc_attr( $_type ); ?>.items.length" data-reaction-type="<?php echo esc_attr( $_type ); ?>">
			<ul class="reaction-avatars">
				<template data-wp-each="context.reactions.<?php echo esc_attr( $_type ); ?>.items">
					<li>
						<a
							data-wp-bind--href="context.item.url"
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
				class="reaction-label"
				data-reaction-type="<?php echo esc_attr( $_type ); ?>"
				data-wp-on--click="actions.openModal"
				aria-label="
				<?php
				echo esc_attr(
					sprintf(
						/* translators: %s: reaction type. */
						__( 'View all %s', 'activitypub' ),
						$_type
					)
				);
				?>
				"
			>
				<?php echo esc_html( $reaction['label'] ); ?>
			</button>
		</div>
		<?php endforeach; ?>
	</div>

	<div
		class="activitypub-modal__overlay"
		data-wp-bind--hidden="!context.modal.isOpen"
		data-wp-on--click="actions.closeModal"
		role="dialog"
		aria-modal="true"
		aria-labelledby="modal-heading"
	>
		<div class="activitypub-modal__frame">
			<div class="activitypub-modal__header">
				<h2 id="modal-heading" class="activitypub-modal__title" data-wp-text="context.modal.reactionType">
				</h2>
				<button
					type="button"
					class="activitypub-modal__close"
					data-wp-on--click="actions.closeModal"
					aria-label="<?php esc_attr_e( 'Close', 'activitypub' ); ?>"
				>
					<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<path d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"></path>
					</svg>
				</button>
			</div>
			<div class="activitypub-modal__content">
				<ul class="reactions-list">
					<template data-wp-each="context.modal.items">
						<li class="reaction-item">
							<a data-wp-bind--href="context.item.url" target="_blank" rel="noopener noreferrer">
								<img
									data-wp-bind--src="context.item.avatar"
									data-wp-bind--alt="context.item.name"
									data-wp-on--error="callbacks.setDefaultAvatar"
									height="48"
									width="48"
									src=""
									alt=""
								/>
								<span class="reaction-name" data-wp-text="context.item.name"></span>
							</a>
						</li>
					</template>
				</ul>
			</div>
		</div>
	</div>
</div>
