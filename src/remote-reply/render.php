<?php
/**
 * Server-side rendering of the `activitypub/remote-reply` block.
 *
 * @package ActivityPub
 */

use Activitypub\Blocks;

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

// Get the comment ID and selected comment URL.
$comment_id       = $attributes['commentId'] ?? 0;
$selected_comment = $attributes['selectedComment'] ?? '';

// Generate a unique ID for the block.
$block_id = 'activitypub-remote-reply-block-' . wp_unique_id();

// Set up the Interactivity API state.
$state = wp_interactivity_state(
	'activitypub/remote-reply',
	array(
		'namespace' => ACTIVITYPUB_REST_NAMESPACE,
		'i18n'      => array(
			'copied'              => __( 'Copied!', 'activitypub' ),
			'copy'                => __( 'Copy', 'activitypub' ),
			'emptyProfileError'   => __( 'Please enter a profile URL or handle.', 'activitypub' ),
			'genericError'        => __( 'An error occurred. Please try again.', 'activitypub' ),
			'invalidProfileError' => __( 'Please enter a valid URL or handle.', 'activitypub' ),
		),
	)
);

// Add the block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                  => $block_id,
		'class'               => 'activitypub-remote-reply reply',
		'data-wp-interactive' => 'activitypub/remote-reply',
		'data-wp-init'        => 'callbacks.init',
	)
);

$wrapper_context = wp_interactivity_data_wp_context(
	array(
		'blockId'           => $block_id,
		'commentId'         => $comment_id,
		'commentURL'        => $selected_comment,
		'copyButtonText'    => $state['i18n']['copy'],
		'errorMessage'      => '',
		'hasRemoteUser'     => false,
		'isError'           => false,
		'isLoading'         => false,
		'modal'             => array( 'isOpen' => false ),
		'profileURL'        => '',
		'remoteProfile'     => '',
		'shouldSaveProfile' => true,
		'template'          => '',
	)
);

ob_start();
?>
	<div class="activitypub-dialog__section">
		<h4><?php echo esc_html__( 'Original Comment URL', 'activitypub' ); ?></h4>
		<div class="activitypub-dialog__description">
			<?php echo esc_html__( 'Copy and paste the Comment URL into the search field of your favorite fediverse app or server.', 'activitypub' ); ?>
		</div>
		<div class="activitypub-dialog__button-group">
			<input
				type="text"
				id="profile-handle"
				value="<?php echo esc_attr( $selected_comment ); ?>"
				tabindex="-1"
				readonly
				aria-readonly="true"
			/>
			<button
				type="button"
				class="wp-element-button wp-block-button__link"
				data-wp-on--click="actions.copyToClipboard"
				aria-label="<?php echo esc_attr__( 'Copy URL to clipboard', 'activitypub' ); ?>"
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
				data-wp-on--keydown="actions.onInputKeydown"
				data-wp-bind--aria-invalid="context.isError"
			/>
			<button
				type="button"
				class="wp-element-button wp-block-button__link"
				data-wp-on--click="actions.submitRemoteProfile"
				aria-label="<?php echo esc_attr__( 'Reply', 'activitypub' ); ?>"
				data-wp-bind--disabled="context.isLoading"
			>
				<span data-wp-bind--hidden="context.isLoading">
					<?php echo esc_html__( 'Reply', 'activitypub' ); ?>
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
		<div class="activitypub-dialog__remember">
			<label>
				<input
					type="checkbox"
					checked
					data-wp-bind--checked="context.shouldSaveProfile"
					data-wp-on--change="actions.toggleRememberProfile"
				/>
				<?php echo esc_html__( 'Save my profile for future comments.', 'activitypub' ); ?>
			</label>
		</div>
	</div>
<?php
$modal_content = ob_get_clean();

?>
<div
	<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php echo $wrapper_context; // phpcs:ignore WordPress.Security.EscapeOutput ?>
>
	<div class="activitypub-remote-profile" hidden data-wp-bind--hidden="!context.hasRemoteUser">
		<a
			class="comment-reply-link activitypub-remote-profile__link"
			data-wp-bind--href="state.remoteProfileUrl"
			target="_blank"
		>
			<?php
			printf(
				/* translators: %s: profile name */
				esc_html__( 'Reply as %s', 'activitypub' ),
				'<span data-wp-text="context.profileURL"></span>'
			);
			?>
		</a>

		<button
			type="button"
			class="activitypub-remote-profile__close wp-element-button wp-block-button__link"
			data-wp-on--click="actions.deleteRemoteUser"
			title="<?php echo esc_attr__( 'Delete Remote Profile', 'activitypub' ); ?>"
		>
			<svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="18" height="18" role="img" aria-hidden="true" focusable="false">
				<path d="M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z"></path>
			</svg>
		</button>
	</div>

	<a
		class="comment-reply-link activitypub-remote-reply__link"
		data-wp-on-async--click="actions.toggleModal"
		data-wp-on-async--keydown="actions.onReplyLinkKeydown"
		data-wp-bind--hidden="context.hasRemoteUser"
		data-wp-bind--aria-expanded="context.modal.isOpen"
		aria-label="<?php echo esc_attr__( 'Reply on the Fediverse', 'activitypub' ); ?>"
		aria-haspopup="dialog"
		aria-controls="modal-heading"
		role="button"
		tabindex="0"
		hidden
	>
		<?php echo esc_html__( 'Reply on the Fediverse', 'activitypub' ); ?>
	</a>

	<?php
	Blocks::render_modal(
		array(
			'title'   => __( 'Remote Reply', 'activitypub' ),
			'content' => $modal_content,
		)
	);
	?>
</div>
<?php
