<?php
/**
 * Server-side rendering of the `activitypub/reactions` block.
 *
 * @package ActivityPub
 */

use Activitypub\Comment;

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

// Get the post ID from attributes or use the current post.
$_post_id = $attributes['postId'] ?? get_the_ID();

// Generate a unique ID for the block.
$block_id = 'activitypub-reactions-block-' . wp_unique_id();

// Initialize the reactions data.
$reactions = array();

// Maximum number of reactions to display in the facepile.
$max_reactions = 30;

// Fetch reactions data from the server.
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

	// Limit the number of reactions to display in the facepile.
	$display_comments = array_slice( $comments, 0, $max_reactions );

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
			$display_comments
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

/* @var string $content Inner blocks content. */
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<div class="activitypub-reactions">
		<div class="reaction-group" data-wp-context="{ reactionType: 'likes' }">
			<ul class="reaction-avatars">
				<template data-wp-each="context.reactions.likes.items">
					<li>
						<a
							data-wp-bind--href="context.item.url"
							data-debug-url="true"
							target="_blank"
							href="#"
							rel="noopener noreferrer"
							data-wp-on--mouseenter="actions.startWave({postId: context.postId, startIndex: index, isEntering: true, reactionType: 'likes'})"
							data-wp-on--mouseleave="actions.startWave({postId: context.postId, startIndex: index, isEntering: false, reactionType: 'likes'})"
						>
							<img
								data-wp-bind--src="context.item.avatar"
								data-wp-bind--alt="context.item.name"
								class="reaction-avatar"
								width="32"
								height="32"
								data-wp-on--error="callbacks.setDefaultAvatar"
								src=""
								alt=""
							/>
						</a>
					</li>
				</template>
			</ul>
			<button
				class="reaction-label"
				data-wp-bind--aria-label="context.reactions.likes.label"
			>
				<span data-wp-text="context.reactions.likes.label"></span>
			</button>
		</div>

		<div class="reaction-group" data-wp-context="{ reactionType: 'reposts' }">
			<ul class="reaction-avatars">
				<template data-wp-each="context.reactions.reposts.items">
					<li>
						<a
							data-wp-bind--href="context.item.url"
							data-debug-url="true"
							target="_blank"
							href="#"
							rel="noopener noreferrer"
							data-wp-on--mouseenter="actions.startWave({postId: context.postId, startIndex: index, isEntering: true, reactionType: 'reposts'})"
							data-wp-on--mouseleave="actions.startWave({postId: context.postId, startIndex: index, isEntering: false, reactionType: 'reposts'})"
						>
							<img
								data-wp-bind--src="context.item.avatar"
								data-wp-bind--alt="context.item.name"
								class="reaction-avatar"
								width="32"
								height="32"
								data-wp-on--error="callbacks.setDefaultAvatar"
								src=""
								alt=""
							/>
						</a>
					</li>
				</template>
			</ul>
			<button
				class="reaction-label"
				data-wp-bind--aria-label="context.reactions.reposts.label"
			>
				<span data-wp-text="context.reactions.reposts.label"></span>
			</button>
		</div>
	</div>
</div>
