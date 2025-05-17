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
	$content = '<h6 class="wp-block-heading">' . \esc_html( $_title ) . '</h6>';
	unset( $attributes['title'], $attributes['className'] );
}


// Get the post ID from attributes or use the current post.
$_post_id = $attributes['postId'] ?? get_the_ID();

// Generate a unique ID for the block.
$block_id = 'activitypub-reactions-block-' . wp_unique_id();

$reactions = array();

foreach ( Comment::get_comment_types() as $type_object ) {
	$comments = \get_comments(
		array(
			'post_id' => $_post_id,
			'type'    => $type_object['type'],
			'status'  => 'approve',
		)
	);

	if ( empty( $comments ) ) {
		continue;
	}

	$count = \count( $comments );
	// phpcs:disable WordPress.WP.I18n
	$label = \sprintf(
		\_n(
			$type_object['count_single'],
			$type_object['count_plural'],
			$count,
			'activitypub'
		),
		\number_format_i18n( $count )
	);
	// phpcs:enable WordPress.WP.I18n

	$reactions[ $type_object['collection'] ] = array(
		'label' => $label,
		'items' => \array_map(
			function ( $comment ) {
				return array(
					'name'   => $comment->comment_author,
					'url'    => $comment->comment_author_url,
					'avatar' => \get_comment_meta( $comment->comment_ID, 'avatar_url', true ),
				);
			},
			$comments
		),
	);
}

// Set up the Interactivity API state.
$state = wp_interactivity_state(
	'activitypub/reactions',
	array(
		'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
	)
);

// Initialize the context for the block.
$context = array(
	'postId'         => $_post_id,
	'reactions'      => $reactions,
	'activeIndices'  => array(),
	'rotationStates' => array(),
	'timeoutRefs'    => array(),
);

// Add the block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                  => $block_id,
		'data-wp-interactive' => 'activitypub/reactions',
		'data-wp-context'     => \wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ),
		'data-wp-init'        => 'callbacks.initReactions',
	)
);
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<div class="activitypub-reactions">
		<?php foreach ( $reactions as $_type => $reaction ) : ?>
		<div class="reaction-group" data-wp-context="{ reactionType: '<?php echo esc_attr( $_type ); ?>' }" data-wp-bind--hidden="!context.reactions.<?php echo esc_attr( $_type ); ?>.items.length">
			<ul class="reaction-avatars">
				<template data-wp-each="context.reactions.<?php echo esc_attr( $_type ); ?>.items">
					<li>
						<a
							data-wp-bind--href="context.item.url"
							target="_blank"
							rel="noopener noreferrer"
							data-wp-on--mouseenter="actions.startWave({postId: context.postId, startIndex: index, isEntering: true, reactionType: context.reactionType})"
							data-wp-on--mouseleave="actions.startWave({postId: context.postId, startIndex: index, isEntering: false, reactionType: context.reactionType})"
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
				data-wp-bind--aria-label="context.reactions.<?php echo esc_attr( $_type ); ?>.label"
			>
				<span data-wp-text="context.reactions.<?php echo esc_attr( $_type ); ?>.label"></span>
			</button>
		</div>
		<?php endforeach; ?>
	</div>
</div>
