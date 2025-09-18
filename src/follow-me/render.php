<?php
/**
 * Server-side rendering of the `activitypub/follow-me` block.
 *
 * @package ActivityPub
 */

use Activitypub\Blocks;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;

use function Activitypub\is_activitypub_request;

if ( is_activitypub_request() || is_feed() ) {
	return;
}

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

/* @var WP_Block $block Parsed block.*/
$block = $block ?? null;

/* @var string $content Inner blocks content. */
$content = $content ?? '';

// Get the user ID from the selected user attribute.
$user_id = Blocks::get_user_id( $attributes['selectedUser'] ?? 'blog' );
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

// Set up the Interactivity API config.
wp_interactivity_config(
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
	$content     = '<div class="wp-block-button"><a class="wp-element-button wp-block-button__link">' . esc_html( $button_text ) . '</a></div>';
} else {
	$content = implode( PHP_EOL, wp_list_pluck( $block->parsed_block['innerBlocks'], 'innerHTML' ) );
}

$content = Blocks::add_directions(
	$content,
	array( 'class_name' => 'wp-element-button' ),
	array(
		'data-wp-on--click'           => 'actions.toggleModal',
		'data-wp-on-async--keydown'   => 'actions.onKeydown',
		'data-wp-bind--aria-expanded' => 'context.modal.isOpen',
		'aria-label'                  => __( 'Follow me on the Fediverse', 'activitypub' ),
		'aria-haspopup'               => 'dialog',
		'aria-controls'               => $block_id . '-modal-title',
		'role'                        => 'button',
		'tabindex'                    => '0',
	)
);

$header_image = $actor->get_image();
$has_header   = ! empty( $header_image['url'] ) && str_contains( $attributes['className'] ?? '', 'is-style-profile' );

$stats = array(
	'posts'     => $user_id ? count_user_posts( $user_id, 'post', true ) : (int) wp_count_posts()->publish,
	'followers' => Followers::count_followers( $user_id ),
);

ob_start();
?>
<div class="activitypub-dialog__section">
	<h4><?php esc_html_e( 'My Profile', 'activitypub' ); ?></h4>
	<div class="activitypub-dialog__description">
		<?php esc_html_e( 'Copy and paste my profile into the search field of your favorite fediverse app or server.', 'activitypub' ); ?>
	</div>
	<div class="activitypub-dialog__button-group">
		<label for="<?php echo esc_attr( $block_id . '-profile-handle' ); ?>" class="screen-reader-text">
			<?php esc_html_e( 'My Fediverse handle', 'activitypub' ); ?>
		</label>
		<input
			aria-readonly="true"
			id="<?php echo esc_attr( $block_id . '-profile-handle' ); ?>"
			readonly
			tabindex="-1"
			type="text"
			value="<?php echo esc_attr( '@' . $actor->get_webfinger() ); ?>"
		/>
		<button
			aria-label="<?php esc_attr_e( 'Copy handle to clipboard', 'activitypub' ); ?>"
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
			data-wp-on--keydown="actions.handleKeyDown"
			id="<?php echo esc_attr( $block_id . '-remote-profile' ); ?>"
			placeholder="<?php esc_attr_e( '@username@example.com', 'activitypub' ); ?>"
			type="text"
		/>
		<button
			aria-label="<?php esc_attr_e( 'Follow', 'activitypub' ); ?>"
			class="wp-element-button wp-block-button__link"
			data-wp-bind--disabled="context.isLoading"
			data-wp-on--click="actions.submitRemoteProfile"
			type="button"
		>
			<span data-wp-bind--hidden="context.isLoading"><?php esc_html_e( 'Follow', 'activitypub' ); ?></span>
			<span data-wp-bind--hidden="!context.isLoading"><?php esc_html_e( 'Loading&hellip;', 'activitypub' ); ?></span>
		</button>
	</div>
	<div
		class="activitypub-dialog__error"
		data-wp-bind--hidden="!context.isError"
		data-wp-text="context.errorMessage"
	></div>
</div>
<?php
$modal_content = ob_get_clean();

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
					<?php /** Using `data-wp-text` to avoid @see enrich_content_data() turning it into a mention. */ ?>
					<div class="activitypub-profile__handle p-nickname p-x-webfinger" data-wp-text="context.webfinger"></div>
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
	// Render the modal using the Blocks class.
	Blocks::render_modal(
		array(
			'id'      => $block_id . '-modal',
			'content' => $modal_content,
			/* translators: %s: Profile name. */
			'title'   => sprintf( esc_html__( 'Follow %s', 'activitypub' ), esc_html( $actor->get_name() ) ),
		)
	);
	?>
</div>
