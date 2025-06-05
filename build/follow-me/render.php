<?php
/**
 * Server-side rendering of the `activitypub/follow-me` block.
 *
 * @package ActivityPub
 */

use Activitypub\Blocks;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

/* @var WP_Block $block Parsed block.*/
$block = $block ?? null;

/* @var string $content Inner blocks content. */
$content = $content ?? '';

// Get the user ID from the selected user attribute.
$user_id = Blocks::get_user_id( $attributes['selectedUser'] ?? 'site' );
$actor   = Actors::get_by_id( $user_id );
if ( is_wp_error( $actor ) ) {
	return;
}

// Generate a unique ID for the block.
$block_id = 'activitypub-follow-me-block-' . wp_unique_id();

// Get block style information.
$style            = wp_get_global_styles();
$background_color = $attributes['backgroundColor'] ?? $style['color']['background'] ?? '';
$button_style     = $attributes['style'] ?? array();

// Set up the Interactivity API state.
wp_interactivity_state(
	'activitypub/follow-me',
	array(
		'namespace' => ACTIVITYPUB_REST_NAMESPACE,
		'i18n'      => array(
			'copy'                => __( 'Copy', 'activitypub' ),
			'copied'              => __( 'Copied!', 'activitypub' ),
			'emptyProfileError'   => __( 'Please enter a profile URL or handle.', 'activitypub' ),
			'genericError'        => __( 'An error occurred. Please try again.', 'activitypub' ),
			'invalidProfileError' => __( 'Please enter a valid profile URL or handle.', 'activitypub' ),
		),
	)
);

// Add the block wrapper attributes.
$wrapper_attributes = array(
	'id'                  => $block_id,
	'class'               => 'activitypub-follow-me-block-wrapper',
	'data-wp-interactive' => 'activitypub/follow-me',
	'data-wp-init'        => 'callbacks.initButtonStyles',
);
if ( isset( $attributes['buttonOnly'] ) ) {
	$wrapper_attributes['class'] .= ' is-style-button-only';
}

$wrapper_context = wp_interactivity_data_wp_context(
	array(
		'backgroundColor' => $background_color,
		'blockId'         => $block_id,
		'buttonStyle'     => $button_style,
		'copyButtonText'  => __( 'Copy', 'activitypub' ),
		'errorMessage'    => '',
		'isError'         => false,
		'isLoading'       => false,
		'modal'           => array( 'isOpen' => false ),
		'remoteProfile'   => '',
		'userId'          => $user_id,
		'webfinger'       => '@' . $actor->get_webfinger(),
	)
);

if ( empty( $content ) ) {
	$button_text = $attributes['buttonText'] ?? __( 'Follow', 'activitypub' );
	$content     = '<div class="wp-block-button"><button class="wp-block-button__link wp-element-button">' . esc_html( $button_text ) . '</button></div>';
} else {
	$content = implode( PHP_EOL, wp_list_pluck( $block->parsed_block['innerBlocks'], 'innerHTML' ) );
}

$content = Blocks::add_directions(
	$content,
	array( 'class_name' => 'wp-element-button' ),
	array(
		'data-wp-on--click'           => 'actions.toggleModal',
		'data-wp-bind--aria-expanded' => 'context.modal.isOpen',
		'aria-label'                  => __( 'Follow me on the Fediverse', 'activitypub' ),
		'aria-haspopup'               => 'dialog',
		'aria-controls'               => 'modal-heading',
	)
);

$header_image = $actor->get_image();
$has_header   = ! empty( $header_image['url'] ) && str_contains( $attributes['className'] ?? '', 'is-style-profile' );

$stats = array(
	'posts'     => count_user_posts( $user_id, 'post', true ),
	'followers' => Followers::count_followers( $user_id ),
);

?>
<div
	<?php echo get_block_wrapper_attributes( $wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php echo $wrapper_context; // phpcs:ignore WordPress.Security.EscapeOutput ?>
>
	<div class="activitypub-profile p-author h-card">
		<?php if ( $has_header ) : ?>
			<div class="activitypub-profile__header" style="background-image: url('<?php echo esc_url( $header_image['url'] ); ?>');"></div>
		<?php endif; ?>

		<div class="activitypub-profile__body">
			<img
				class="activitypub-profile__avatar u-photo"
				src="<?php echo esc_url( $actor->get_icon()['url'] ); ?>"
				alt="<?php echo esc_attr( $actor->get_name() ); ?>"
			/>

			<div class="activitypub-profile__content">
				<div class="activitypub-profile__info">
					<div class="activitypub-profile__name p-name"><?php echo esc_html( $actor->get_name() ); ?></div>
					<div class="activitypub-profile__handle p-nickname p-x-webfinger"><?php echo esc_html( '@' . $actor->get_webfinger() ); ?></div>
				</div>

				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>

				<?php if ( $actor->get_summary() ) : ?>
					<div class="activitypub-profile__bio p-note">
						<?php echo wp_kses_post( $actor->get_summary() ); ?>
					</div>
				<?php endif; ?>

				<div class="activitypub-profile__stats">
					<?php if ( null !== $stats['posts'] ) : ?>
						<div>
							<?php
							printf(
								/* translators: %s: Number of posts */
								esc_html( _n( '%s post', '%s posts', (int) $stats['posts'], 'activitypub' ) ),
								'<strong>' . esc_html( number_format_i18n( $stats['posts'] ) ) . '</strong>'
							);
							?>
						</div>
					<?php endif; ?>
					<?php if ( null !== $stats['followers'] ) : ?>
						<div>
							<?php
							printf(
								/* translators: %s: Number of followers */
								esc_html( _n( '%s follower', '%s followers', (int) $stats['followers'], 'activitypub' ) ),
								'<strong>' . esc_html( number_format_i18n( $stats['followers'] ) ) . '</strong>'
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<?php
	$modal_content = '
		<div class="activitypub-dialog__section">
			<h4>' . esc_html__( 'My Profile', 'activitypub' ) . '</h4>
			<div class="activitypub-dialog__description">
				' . esc_html__( 'Copy and paste my profile into the search field of your favorite fediverse app or server.', 'activitypub' ) . '
			</div>
			<div class="activitypub-dialog__button-group">
				<input
					type="text"
					id="profile-handle"
					value="' . esc_attr( '@' . $actor->get_webfinger() ) . '"
					tabindex="-1"
					readonly
					aria-readonly="true"
				/>
				<button
					type="button"
					class="wp-element-button wp-block-button__link"
					data-wp-on--click="actions.copyToClipboard"
					aria-label="' . esc_attr__( 'Copy handle to clipboard', 'activitypub' ) . '"
				>
					<span data-wp-text="context.copyButtonText"></span>
				</button>
			</div>
		</div>
		<div class="activitypub-dialog__section">
			<h4>' . esc_html__( 'Your Profile', 'activitypub' ) . '</h4>
			<div class="activitypub-dialog__description">
				' . esc_html__( 'Or, if you know your own profile, we can start things that way!', 'activitypub' ) . '
			</div>
			<div class="activitypub-dialog__button-group">
				<input
					type="text"
					id="remote-profile"
					placeholder="' . esc_attr__( '@username@example.com', 'activitypub' ) . '"
					data-wp-bind--value="context.remoteProfile"
					data-wp-on--input="actions.updateRemoteProfile"
					data-wp-on--keydown="actions.handleKeyDown"
					data-wp-bind--aria-invalid="context.isError"
				/>
				<button
					type="button"
					class="wp-element-button wp-block-button__link"
					data-wp-on--click="actions.submitRemoteProfile"
					aria-label="' . esc_attr__( 'Follow', 'activitypub' ) . '"
					data-wp-bind--disabled="context.isLoading"
				>
					<span data-wp-bind--hidden="context.isLoading">' . esc_html__( 'Follow', 'activitypub' ) . '</span>
					<span data-wp-bind--hidden="!context.isLoading">' . esc_html__( 'Loading&hellip;', 'activitypub' ) . '</span>
				</button>
			</div>
			<div
				class="activitypub-dialog__error"
				data-wp-bind--hidden="!context.isError"
				data-wp-text="context.errorMessage"
			></div>
		</div>
	';

	// Render the modal using the Blocks class.
	Blocks::render_modal(
		array(
			'content' => $modal_content,
			/* translators: %s: Profile name. */
			'title'   => sprintf( esc_html__( 'Follow %s', 'activitypub' ), esc_html( $actor->get_name() ) ),
		)
	);
	?>
</div>
