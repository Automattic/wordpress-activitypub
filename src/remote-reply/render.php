<?php
/**
 * Server-side rendering of the `activitypub/remote-reply` block.
 *
 * @package ActivityPub
 */

use Activitypub\Blocks;

use function Activitypub\is_activitypub_request;

if ( is_activitypub_request() || is_feed() ) {
	return;
}

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

// Get the comment ID and selected comment URL.
$comment_id       = $attributes['commentId'] ?? 0;
$selected_comment = $attributes['selectedComment'] ?? '';

// Generate a unique ID for the block.
$block_id = 'activitypub-remote-reply-block-' . wp_unique_id();

// Set up the Interactivity API config.
wp_interactivity_config(
	'activitypub/remote-reply',
	array(
		'namespace' => ACTIVITYPUB_REST_NAMESPACE,
		'i18n'      => array(
			'copied'              => __( 'Copied!', 'activitypub' ),
			'copy'                => __( 'Copy', 'activitypub' ),
			'emptyProfileError'   => __( 'Please enter a profile URL or handle.', 'activitypub' ),
			'genericError'        => __( 'An error occurred. Please try again.', 'activitypub' ),
			'invalidProfileError' => __( 'Please enter a valid profile URL or handle.', 'activitypub' ),
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
		'copyButtonText'    => __( 'Copy', 'activitypub' ),
		'errorMessage'      => '',
		'isError'           => false,
		'isLoading'         => false,
		'modal'             => array( 'isOpen' => false ),
		'remoteProfile'     => '',
		'shouldSaveProfile' => true,
	)
);

wp_interactivity_state(
	'activitypub/remote-reply',
	array(
		'hasRemoteUser' => false,
		'profileURL'    => '',
		'template'      => '',
	)
);

ob_start();
?>
<div class="activitypub-dialog__section">
	<h4><?php esc_html_e( 'Original Comment URL', 'activitypub' ); ?></h4>
	<div class="activitypub-dialog__description">
		<?php esc_html_e( 'Copy and paste the Comment URL into the search field of your favorite fediverse app or server.', 'activitypub' ); ?>
	</div>
	<div class="activitypub-dialog__button-group">
		<label for="<?php echo esc_attr( $block_id . '-profile-handle' ); ?>" class="screen-reader-text">
			<?php esc_html_e( 'Comment URL', 'activitypub' ); ?>
		</label>
		<input
			aria-readonly="true"
			id="<?php echo esc_attr( $block_id . '-profile-handle' ); ?>"
			readonly
			tabindex="-1"
			type="text"
			value="<?php echo esc_attr( $selected_comment ); ?>"
		/>
		<button
			aria-label="<?php esc_attr_e( 'Copy URL to clipboard', 'activitypub' ); ?>"
			class="wp-element-button wp-block-button__link"
			data-wp-on--click="actions.copyToClipboard"
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
	</div>
	<div class="activitypub-dialog__button-group">
		<label for="<?php echo esc_attr( $block_id . '-remote-profile' ); ?>" class="screen-reader-text">
			<?php esc_html_e( 'Your Fediverse profile', 'activitypub' ); ?>
		</label>
		<input
			data-wp-bind--aria-invalid="context.isError"
			data-wp-bind--value="context.remoteProfile"
			data-wp-on--input="actions.updateRemoteProfile"
			data-wp-on--keydown="actions.onInputKeydown"
			id="<?php echo esc_attr( $block_id . '-remote-profile' ); ?>"
			placeholder="<?php esc_attr_e( '@username@example.com', 'activitypub' ); ?>"
			type="text"
		/>
		<button
			aria-label="<?php esc_attr_e( 'Reply', 'activitypub' ); ?>"
			class="wp-element-button wp-block-button__link"
			data-wp-bind--disabled="context.isLoading"
			data-wp-on--click="actions.submitRemoteProfile"
			type="button"
		>
			<span data-wp-bind--hidden="context.isLoading"><?php esc_html_e( 'Reply', 'activitypub' ); ?></span>
			<span data-wp-bind--hidden="!context.isLoading"><?php esc_html_e( 'Loading&hellip;', 'activitypub' ); ?></span>
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
			<?php esc_html_e( 'Save my profile for future comments.', 'activitypub' ); ?>
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
	<div class="activitypub-remote-profile" hidden data-wp-bind--hidden="!state.hasRemoteUser">
		<a
			href=""
			class="comment-reply-link activitypub-remote-profile__link"
			data-wp-bind--href="state.remoteProfileUrl"
			target="_blank"
		>
			<?php
			printf(
				/* translators: %s: profile name */
				esc_html__( 'Reply as %s', 'activitypub' ),
				'<span data-wp-text="state.profileURL"></span>'
			);
			?>
		</a>

		<button
			type="button"
			class="activitypub-remote-profile__close wp-element-button wp-block-button__link"
			data-wp-on--click="actions.deleteRemoteUser"
			title="<?php esc_attr_e( 'Delete Remote Profile', 'activitypub' ); ?>"
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
		data-wp-bind--hidden="state.hasRemoteUser"
		data-wp-bind--aria-expanded="context.modal.isOpen"
		aria-label="<?php esc_attr_e( 'Reply on the Fediverse', 'activitypub' ); ?>"
		aria-haspopup="dialog"
		aria-controls="<?php echo esc_attr( $block_id . '-modal-title' ); ?>"
		role="button"
		tabindex="0"
		hidden
	>
		<?php esc_html_e( 'Reply on the Fediverse', 'activitypub' ); ?>
	</a>

	<?php
	Blocks::render_modal(
		array(
			'id'      => $block_id . '-modal',
			'title'   => __( 'Remote Reply', 'activitypub' ),
			'content' => $modal_content,
		)
	);
	?>
</div>
<?php
