<?php
/**
 * Server-side rendering of the `activitypub/follow-me` block.
 *
 * @package ActivityPub
 */

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

// Get the user ID from the selected user attribute.
$selected_user = $attributes['selectedUser'] ?? 'site';
$user_id       = 'site' === $selected_user ? 0 : intval( $selected_user );
$button_only   = $attributes['buttonOnly'] ?? false;
$button_text   = $attributes['buttonText'] ?? __( 'Follow', 'activitypub' );
$button_size   = $attributes['buttonSize'] ?? 'default';

// Generate a unique ID for the block.
$block_id = 'activitypub-follow-me-block-' . wp_unique_id();

// Get block style information.
$style            = wp_get_global_styles();
$background_color = $style['color']['background'] ?? '';

// Get button style from block attributes.
$button_style = $attributes['style'] ?? array();

$button_class = '';
if ( 'small' === $button_size ) {
	$button_class = 'is-small';
} elseif ( 'compact' === $button_size ) {
	$button_class = 'is-compact';
}

// Set up the Interactivity API state.
wp_interactivity_state(
	'activitypub/follow-me',
	array(
		'profile'         => array(
			'loading' => true,
			'data'    => array(
				'avatar'    => 'https://secure.gravatar.com/avatar/default?s=120',
				'webfinger' => '@well@hello.dolly',
				'name'      => __( 'Hello Dolly Fan Account', 'activitypub' ),
				'url'       => '#',
			),
		),
		'userId'          => $user_id,
		'namespace'       => ACTIVITYPUB_REST_NAMESPACE,
		'buttonOnly'      => $button_only,
		'buttonText'      => $button_text,
		'buttonSize'      => $button_size,
		'buttonStyle'     => $button_style,
		'backgroundColor' => $background_color,
		'i18n'            => array(
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
		'data-wp-init'                 => 'callbacks.init',
		'data-wp-on-document--keydown' => 'callbacks.documentKeydown',
		'data-wp-on-document--click'   => 'callbacks.documentClick',
	)
);

$wrapper_context = wp_interactivity_data_wp_context(
	array(
		'blockId'        => $block_id,
		'isModalOpen'    => false,
		'remoteProfile'  => '',
		'isLoading'      => false,
		'isError'        => false,
		'errorMessage'   => '',
		'copyButtonText' => __( 'Copy', 'activitypub' ),
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
				data-wp-bind--src="state.profile.data.avatar"
				data-wp-bind--alt="state.profile.data.name"
				alt=""
			/>
			<div class="activitypub-profile__content">
				<div
					class="activitypub-profile__name"
					data-wp-text="state.profile.data.name"
				></div>
				<div
					class="activitypub-profile__handle"
					data-wp-text="state.profile.data.webfinger"
					data-wp-bind--title="state.profile.data.webfinger"
				></div>
			</div>
		<?php endif; ?>

		<button
			class="activitypub-profile__follow components-button is-primary <?php echo esc_attr( $button_class ); ?>"
			data-wp-on--click="actions.toggleModal"
			aria-haspopup="dialog"
			data-wp-bind--aria-expanded="state.isModalOpen"
			aria-label="<?php echo esc_attr__( 'Follow me on the Fediverse', 'activitypub' ); ?>"
			data-wp-bind--size="state.buttonSize"
			data-wp-text="state.buttonText"
		></button>
	</div>

	<!-- Modal is placed inside the wrapper div alongside the profile section -->
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
					<?php echo esc_html__( 'Follow', 'activitypub' ); ?> <span data-wp-text="state.profile.data.name"></span>
				</h2>
				<button
					type="button"
					class="activitypub-modal__close"
					data-wp-on--click="actions.closeModal"
					aria-label="<?php echo esc_attr__( 'Close dialog', 'activitypub' ); ?>"
				>
					<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" role="img" aria-hidden="true" focusable="false">
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
							data-wp-bind--value="state.profile.data.webfinger"
							readonly
						/>
						<button
							class="components-button is-primary"
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
						(eg <code>@yourusername@example.com</code>)
					</div>
					<div class="activitypub-dialog__button-group">
						<input
							type="text"
							id="remote-profile"
							data-wp-bind--value="context.remoteProfile"
							data-wp-on--input="actions.updateRemoteProfile"
							data-wp-on--keydown="actions.handleKeyDown"
							data-wp-bind--aria-invalid="context.isError"
						/>
						<button
							class="components-button is-primary"
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
