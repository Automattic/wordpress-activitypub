<?php
/**
 * Server-side rendering of the `activitypub/reactions` block.
 *
 * @package ActivityPub
 */

use Activitypub\Blocks;
use Activitypub\Comment;

use function Activitypub\get_post_id;
use function Activitypub\is_activitypub_request;

if ( is_activitypub_request() || is_feed() ) {
	return;
}

// Get the default display style based on WordPress avatar settings.
$default_display_style = get_option( 'show_avatars', true ) ? 'facepile' : 'compact';

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args(
	$attributes,
	array(
		'align'        => null,
		'displayStyle' => $default_display_style,
		'showActions'  => false,
	)
);

/* @var \WP_Block $block Current block. */
$block = $block ?? '';

/* @var string $content Block content. */
$content = $content ?? '';

if ( empty( $content ) ) {
	// Fallback for v1.0.0 blocks.
	$_title  = $attributes['title'] ?? __( 'Fediverse Reactions', 'activitypub' );
	$content = '<h6 class="wp-block-heading">' . esc_html( $_title ) . '</h6>';
	unset( $attributes['title'] );
} else {
	$content = implode( PHP_EOL, wp_list_pluck( $block->parsed_block['innerBlocks'], 'innerHTML' ) );
	// Hide empty headings.
	if ( empty( wp_strip_all_tags( $content ) ) ) {
		$content = '';
	}
}

// Get the Post ID from attributes or use the current post.
$_post_id = $attributes['postId'] ?? get_the_ID();

// Generate a unique ID for the block.
$block_id = 'activitypub-reactions-block-' . wp_unique_id();

/*
 * Determine display style - compact style hides avatars.
 * For auto-hooked blocks without explicit style, use avatar setting to determine style.
 */
$has_style_class = isset( $attributes['className'] ) && strpos( $attributes['className'], 'is-style-' ) !== false;
if ( ! $has_style_class ) {
	$attributes['className']    = trim( ( $attributes['className'] ?? '' ) . ' is-style-' . $default_display_style );
	$attributes['displayStyle'] = $default_display_style;
}

$show_avatars = 'facepile' === $attributes['displayStyle'];

// Fetch reactions.
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
			static function ( $comment ) {
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
$config = array(
	'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
	'namespace'        => ACTIVITYPUB_REST_NAMESPACE,
);

if ( $attributes['showActions'] ) {
	$config['i18n'] = array(
		'copied'              => __( 'Copied!', 'activitypub' ),
		'copy'                => __( 'Copy', 'activitypub' ),
		'emptyProfileError'   => __( 'Please enter a profile URL or handle.', 'activitypub' ),
		'genericError'        => __( 'An error occurred. Please try again.', 'activitypub' ),
		'intentLabelLike'     => __( 'Like this post', 'activitypub' ),
		'intentLabelAnnounce' => __( 'Boost this post', 'activitypub' ),
		'invalidProfileError' => __( 'Please enter a valid profile URL or handle.', 'activitypub' ),
	);
}

wp_interactivity_config( 'activitypub/reactions', $config );

// Set up the Interactivity API state.
wp_interactivity_state( 'activitypub/reactions', array( 'reactions' => array( $_post_id => $reactions ) ) );

// Render a subset of the most recent reactions for facepile.
$reactions = array_map(
	static function ( $reaction ) use ( $attributes ) {
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
		'title'     => '',
	),
	'postId'    => $_post_id,
	'reactions' => $reactions,
);

if ( $attributes['showActions'] ) {
	$context['modal']['intent']   = '';
	$context['copyButtonText']    = __( 'Copy', 'activitypub' );
	$context['errorMessage']      = '';
	$context['isError']           = false;
	$context['isLoading']         = false;
	$context['postUrl']           = get_post_id( $_post_id );
	$context['remoteProfile']     = '';
	$context['shouldSaveProfile'] = true;
}

// Map comment types to remote intent types.
if ( $attributes['showActions'] ) {
	$intent_map = array(
		'like'   => 'like',
		'repost' => 'announce',
		'quote'  => 'announce',
	);
}

// Build reactions content.
ob_start();
?>
<div class="activitypub-reactions">
	<?php
	foreach ( $reactions as $_type => $reaction ) :
		/* translators: %s: reaction type. */
		$aria_label = sprintf( __( 'View all %s', 'activitypub' ), Comment::get_comment_type_attr( $_type, 'label' ) );
		$intent     = isset( $intent_map[ $_type ] ) ? $intent_map[ $_type ] : '';
		?>
	<div class="reaction-group" data-reaction-type="<?php echo esc_attr( $_type ); ?>">
		<?php if ( $attributes['showActions'] && $intent ) : ?>
		<button
			class="reaction-action-button has-text-color has-background"
			data-intent="<?php echo esc_attr( $intent ); ?>"
			data-wp-on--click="actions.openIntentModal"
			type="button"
			aria-label="<?php echo esc_attr( Comment::get_comment_type_attr( $_type, 'singular' ) ); ?>"
		>
			<?php echo esc_html( Comment::get_comment_type_attr( $_type, 'singular' ) ); ?>
		</button>
		<?php endif; ?>
		<?php if ( $show_avatars ) : ?>
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
		<?php endif; ?>
		<button
			class="reaction-label has-text-color has-background"
			data-reaction-type="<?php echo esc_attr( $_type ); ?>"
			data-wp-on--click="actions.toggleModal"
			type="button"
			aria-label="<?php echo esc_attr( $aria_label ); ?>"
		>
			<?php echo esc_html( $reaction['label'] ); ?>
		</button>
	</div>
	<?php endforeach; ?>
</div>
<?php
$reactions_content = ob_get_clean();

// Build modal content: reactors list (compact) + intent dialog (full-size).
ob_start();
?>
<div data-wp-bind--hidden="!context.modal.isCompact">
	<ul class="reactions-list">
		<template data-wp-each="context.modal.items">
			<li class="reaction-item">
				<a data-wp-bind--href="context.item.url" target="_blank" rel="noopener noreferrer">
					<?php if ( $show_avatars ) : ?>
					<img
						alt=""
						data-wp-bind--alt="context.item.name"
						data-wp-bind--src="context.item.avatar"
						data-wp-on--error="callbacks.setDefaultAvatar"
						src=""
					/>
					<?php endif; ?>
					<span class="reaction-name" data-wp-text="context.item.name"></span>
				</a>
			</li>
		</template>
	</ul>
</div>
<?php if ( $attributes['showActions'] ) : ?>
<div class="activitypub-intent-dialog" data-wp-bind--hidden="context.modal.isCompact">
	<div class="activitypub-dialog__section">
		<h4><?php esc_html_e( 'Post URL', 'activitypub' ); ?></h4>
		<div class="activitypub-dialog__description">
			<?php esc_html_e( 'Paste the post URL into the search field of your favorite open social app or platform.', 'activitypub' ); ?>
		</div>
		<div class="activitypub-dialog__button-group">
			<label for="<?php echo esc_attr( $block_id . '-post-url' ); ?>" class="screen-reader-text">
				<?php esc_html_e( 'Post URL', 'activitypub' ); ?>
			</label>
			<input
				aria-readonly="true"
				class="wp-block-search__input"
				id="<?php echo esc_attr( $block_id . '-post-url' ); ?>"
				readonly
				tabindex="-1"
				type="text"
				data-wp-bind--value="context.postUrl"
			/>
			<button
				aria-label="<?php esc_attr_e( 'Copy URL to clipboard', 'activitypub' ); ?>"
				class="wp-element-button"
				data-wp-on--click="actions.copyPostUrl"
				type="button"
			>
				<span data-wp-text="context.copyButtonText"></span>
			</button>
		</div>
	</div>
	<div class="activitypub-dialog__section">
		<h4><?php esc_html_e( 'Your Profile', 'activitypub' ); ?></h4>
		<div class="activitypub-dialog__description">
			<?php esc_html_e( 'Or, if you know your own profile, we can start things that way!', 'activitypub' ); ?>
			<?php Blocks::render_modal_help(); ?>
		</div>
		<div class="activitypub-dialog__button-group">
			<label for="<?php echo esc_attr( $block_id . '-remote-profile' ); ?>" class="screen-reader-text">
				<?php esc_html_e( 'Your Fediverse profile', 'activitypub' ); ?>
			</label>
			<input
				class="wp-block-search__input"
				data-wp-bind--aria-invalid="context.isError"
				data-wp-bind--value="context.remoteProfile"
				data-wp-on--input="actions.updateIntentProfile"
				data-wp-on--keydown="actions.onIntentKeydown"
				id="<?php echo esc_attr( $block_id . '-remote-profile' ); ?>"
				placeholder="<?php esc_attr_e( '@username@example.com', 'activitypub' ); ?>"
				type="text"
			/>
			<button
				class="wp-element-button"
				data-wp-bind--disabled="context.isLoading"
				data-wp-on--click="actions.submitIntent"
				type="button"
			>
				<span data-wp-bind--hidden="context.isLoading"><?php esc_html_e( 'Go', 'activitypub' ); ?></span>
				<span data-wp-bind--hidden="!context.isLoading"><?php esc_html_e( 'Loading…', 'activitypub' ); ?></span>
			</button>
		</div>
		<div
			class="activitypub-dialog__error"
			data-wp-bind--hidden="!context.isError"
			data-wp-text="context.errorMessage"
		></div>
		<div class="activitypub-dialog__remember">
			<label>
				<input
					checked
					data-wp-bind--checked="context.shouldSaveProfile"
					data-wp-on--change="actions.toggleRememberProfile"
					type="checkbox"
				/>
				<?php esc_html_e( 'Remember my profile for future interactions.', 'activitypub' ); ?>
			</label>
		</div>
	</div>
</div>
<?php endif; ?>
<?php
$modal_content = ob_get_clean();

// Render the shared modal with both contents.
$modal_args = array(
	'content' => $modal_content,
);

if ( $attributes['showActions'] ) {
	$modal_args['title_binding'] = 'context.modal.title';
} else {
	$modal_args['is_compact'] = true;
}

ob_start();
Blocks::render_modal( $modal_args );
$inner_content = $reactions_content . ob_get_clean();

$wrapper_attrs = array(
	'id'                  => $block_id,
	'class'               => $attributes['className'] ?? '',
	'data-wp-interactive' => 'activitypub/reactions',
	'data-wp-context'     => wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ),
	'data-wp-init'        => 'callbacks.initReactions',
);

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_attrs );

// Render the block with common wrapper.
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php echo $inner_content; // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
