<?php
/**
 * Server-side rendering of the `activitypub/follow-me` block.
 *
 * @package ActivityPub
 */

use Activitypub\Blocks;
use Activitypub\Collection\Actors;

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

// Get the user ID from the selected user attribute.
$selected_user = $attributes['selectedUser'] ?? 'site';
$user_id       = Blocks::get_user_id( $selected_user );
$button_only   = $attributes['buttonOnly'] ?? false;

// Generate a unique ID for the block.
$block_id = 'activitypub-follow-me-block-' . wp_unique_id();

// Get block style information.
$style            = wp_get_global_styles();
$background_color = $attributes['backgroundColor'] ?? $style['color']['background'] ?? '';

// Get button style from block attributes.
$button_style = $attributes['style'] ?? array();

$actor = Actors::get_by_id( $user_id );
if ( is_wp_error( $actor ) ) {
	return;
}

// Set up the Interactivity API state.
$state = wp_interactivity_state(
	'activitypub/follow-me',
	array(
		'namespace' => ACTIVITYPUB_REST_NAMESPACE,
		'i18n'      => array(
			'copied'              => __( 'Copied!', 'activitypub' ),
			'copy'                => __( 'Copy', 'activitypub' ),
			'emptyProfileError'   => __( 'Please enter a profile URL or handle.', 'activitypub' ),
			'invalidProfileError' => __( 'Please enter a valid URL or handle.', 'activitypub' ),
			'genericError'        => __( 'An error occurred. Please try again.', 'activitypub' ),
		),
	)
);

// Add the block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                           => $block_id,
		'class'                        => 'activitypub-follow-me-block-wrapper',
		'data-wp-interactive'          => 'activitypub/follow-me',
		'data-wp-init'                 => 'callbacks.initButtonStyles',
		'data-wp-on-document--keydown' => 'callbacks.documentKeydown',
		'data-wp-on-document--click'   => 'callbacks.documentClick',
	)
);

$wrapper_context = wp_interactivity_data_wp_context(
	array(
		'blockId'         => $block_id,
		'isModalOpen'     => false,
		'remoteProfile'   => '',
		'isLoading'       => false,
		'isError'         => false,
		'errorMessage'    => '',
		'copyButtonText'  => $state['i18n']['copy'],
		'userId'          => $user_id,
		'buttonOnly'      => $button_only,
		'buttonStyle'     => $button_style,
		'backgroundColor' => $background_color,
		'webfinger'       => '@' . $actor->get_webfinger(),
	)
);

/* @var string $content Inner blocks content. */
if ( empty( $content ) ) {
	$button_text = $attributes['buttonText'] ?? __( 'Follow', 'activitypub' );
	$content     = '<div class="wp-block-button"><button class="wp-block-button__link wp-element-button">' . esc_html( $button_text ) . '</button></div>';
}
$content = Blocks::add_directions(
	$content,
	array( 'class_name' => 'wp-element-button' ),
	array(
		'data-wp-on--click'           => 'actions.toggleModal',
		'data-wp-bind--aria-expanded' => 'context.isModalOpen',
		'aria-label'                  => __( 'Follow me on the Fediverse', 'activitypub' ),
		'aria-haspopup'               => 'dialog',
		'aria-controls'               => 'modal-heading',
	)
);

?>
<div
	<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php echo $wrapper_context; // phpcs:ignore WordPress.Security.EscapeOutput ?>
>
	<div class="activitypub-profile">
		<?php if ( ! $button_only ) : ?>
			<img
				class="activitypub-profile__avatar"
				src="<?php echo esc_url( $actor->get_icon()['url'] ); ?>"
				alt="<?php echo esc_attr( $actor->get_name() ); ?>"
			/>
			<div class="activitypub-profile__content">
				<div class="activitypub-profile__name"><?php echo esc_html( $actor->get_name() ); ?></div>
				<div class="activitypub-profile__handle"><?php echo esc_html( '@' . $actor->get_webfinger() ); ?></div>
			</div>
		<?php endif; ?>

		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>

	<div
		class="activitypub-modal__overlay"
		data-wp-bind--hidden="!context.isModalOpen"
		role="dialog"
		aria-modal="true"
		aria-labelledby="modal-heading"
	>
		<div class="activitypub-modal__frame">
			<div class="activitypub-modal__header">
				<h2 id="modal-heading" class="activitypub-modal__title">
					<?php
					printf(
						/* translators: %s: Profile name. */
						esc_html__( 'Follow %s', 'activitypub' ),
						esc_html( $actor->get_name() )
					);
					?>
				</h2>
				<button
					type="button"
					class="activitypub-modal__close wp-element-button wp-block-button__link"
					data-wp-on--click="actions.closeModal"
					aria-label="<?php echo esc_attr__( 'Close dialog', 'activitypub' ); ?>"
				>
					<svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" role="img" aria-hidden="true" focusable="false">
						<path d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"></path>
					</svg>
				</button>
			</div>
			<div class="activitypub-modal__content">
				<div class="activitypub-dialog__section">
					<h4><?php echo esc_html__( 'My Profile', 'activitypub' ); ?></h4>
					<div class="activitypub-dialog__description">
						<?php echo esc_html__( 'Copy and paste my profile into the search field of your favorite fediverse app or server.', 'activitypub' ); ?>
					</div>
					<div class="activitypub-dialog__button-group">
						<input
							type="text"
							id="profile-handle"
							value="<?php echo esc_attr( '@' . $actor->get_webfinger() ); ?>"
							tabindex="-1"
							readonly
							aria-readonly="true"
						/>
						<button
							type="button"
							class="wp-element-button wp-block-button__link"
							data-wp-on--click="actions.copyToClipboard"
							aria-label="<?php echo esc_attr__( 'Copy handle to clipboard', 'activitypub' ); ?>"
						>
							<span data-wp-text="context.copyButtonText"></span>
						</button>
					</div>
				</div>
				<div class="activitypub-dialog__section">
					<h4><?php echo esc_html__( 'Your Profile', 'activitypub' ); ?></h4>
					<div class="activitypub-dialog__description">
						<?php echo esc_html__( 'Or, if you know your own profile, we can start things that way!', 'activitypub' ); ?>
					</div>
					<div class="activitypub-dialog__button-group">
						<input
							type="text"
							id="remote-profile"
							placeholder="<?php echo esc_attr__( '@username@example.com', 'activitypub' ); ?>"
							data-wp-bind--value="context.remoteProfile"
							data-wp-on--input="actions.updateRemoteProfile"
							data-wp-on--keydown="actions.handleKeyDown"
							data-wp-bind--aria-invalid="context.isError"
						/>
						<button
							type="button"
							class="wp-element-button wp-block-button__link"
							data-wp-on--click="actions.submitRemoteProfile"
							aria-label="<?php echo esc_attr__( 'Follow', 'activitypub' ); ?>"
							data-wp-bind--disabled="context.isLoading"
						>
							<span data-wp-bind--hidden="context.isLoading">
								<?php echo esc_html__( 'Follow', 'activitypub' ); ?>
							</span>
							<span data-wp-bind--hidden="!context.isLoading">
								<?php echo esc_html__( 'Loading...', 'activitypub' ); ?>
							</span>
						</button>
					</div>
					<div
						class="activitypub-dialog__error"
						data-wp-bind--hidden="!context.isError"
						data-wp-text="context.errorMessage"
					></div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
