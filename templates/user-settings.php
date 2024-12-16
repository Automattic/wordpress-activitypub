<?php
/**
 * ActivityPub User Settings template.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args( $args, array( 'description' => '' ) );

$user = \Activitypub\Collection\Actors::get_by_id( \get_current_user_id() ); ?>
<h2 id="activitypub"><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h2>

<p><?php esc_html_e( 'Define what others can see on your public Fediverse profile and next to your posts. With a profile picture and a fully completed profile, you are more likely to gain interactions and followers.', 'activitypub' ); ?></p>

<p><?php esc_html_e( 'The ActivityPub plugin tries to take as much information as possible from your profile settings. However, the following settings are not supported by WordPress or should be adjusted independently of the WordPress settings.', 'activitypub' ); ?></p>

<table class="form-table">
	<tbody>
		<tr>
			<th scope="row">
				<label><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></label>
			</th>
			<td>
				<p>
					<code><?php echo \esc_html( $user->get_webfinger() ); ?></code> or
					<code><?php echo \esc_url( $user->get_url() ); ?></code>
				</p>
				<?php // translators: the webfinger resource. ?>
				<p class="description"><?php \printf( \esc_html__( 'Follow "@%s" by searching for it on Mastodon, Friendica, etc.', 'activitypub' ), \esc_html( $user->get_webfinger() ) ); ?></p>
			</td>
		</tr>
		<tr class="activitypub-description-wrap">
			<th>
				<label for="activitypub_description"><?php \esc_html_e( 'Biography', 'activitypub' ); ?></label>
			</th>
			<td>
				<textarea name="activitypub_description" id="activitypub_description" rows="5" cols="30" placeholder="<?php echo \esc_attr( get_user_meta( \get_current_user_id(), 'description', true ) ); ?>"><?php echo \esc_html( $args['description'] ); ?></textarea>
				<p class="description"><?php \esc_html_e( 'If you wish to use different biographical info for the fediverse, enter your alternate bio here.', 'activitypub' ); ?></p>
			</td>
		</tr>
		<tr scope="row">
			<th>
				<label><?php \esc_html_e( 'Header Image', 'activitypub' ); ?></label>
			</th>
			<td>
				<?php
				$classes_for_upload_button = 'button upload-button button-add-media button-add-header-image';
				$classes_for_update_button = 'button';
				$classes_for_wrapper       = '';

				$header_image = \get_user_option( 'activitypub_header_image', \get_current_user_id() );

				if ( (int) $header_image ) :
					$classes_for_wrapper         .= ' has-header-image';
					$classes_for_button           = $classes_for_update_button;
					$classes_for_button_on_change = $classes_for_upload_button;
				else :
					$classes_for_wrapper         .= ' hidden';
					$classes_for_button           = $classes_for_upload_button;
					$classes_for_button_on_change = $classes_for_update_button;
				endif;
				?>
				<div id="activitypub-header-image-preview-wrapper" class='<?php echo esc_attr( $classes_for_wrapper ); ?>'>
					<img id='activitypub-header-image-preview' src='<?php echo \esc_url( \wp_get_attachment_url( $header_image ) ); ?>' style="max-width: 100%;" alt="" />
				</div>
				<button
					type="button"
					id="activitypub-choose-from-library-button"
					type="button"
					class="<?php echo \esc_attr( $classes_for_button ); ?>"
					data-alt-classes="<?php echo \esc_attr( $classes_for_button_on_change ); ?>"
					data-choose-text="<?php \esc_attr_e( 'Choose a Header Image', 'activitypub' ); ?>"
					data-update-text="<?php \esc_attr_e( 'Change Header Icon', 'activitypub' ); ?>"
					data-update="<?php \esc_attr_e( 'Set as Header Image', 'activitypub' ); ?>"
					<?php
					// We only need to constrain the user_id for users who can't edit others' posts.
					if ( ! \current_user_can( 'edit_others_posts' ) ) :
						printf( 'data-user-id="%s"', esc_attr( \get_current_user_id() ) );
					endif;
					?>
					data-state="<?php echo \esc_attr( (int) $header_image ); ?>">
					<?php if ( (int) $header_image ) : ?>
						<?php \esc_html_e( 'Change Header Image', 'activitypub' ); ?>
					<?php else : ?>
						<?php \esc_html_e( 'Choose a Header Image', 'activitypub' ); ?>
					<?php endif; ?>
				</button>
				<button
					id="activitypub-remove-header-image"
					type="button"
					<?php echo (int) $header_image ? 'class="button button-secondary reset"' : 'class="button button-secondary reset hidden"'; ?>>
					<?php esc_html_e( 'Remove Header Image', 'activitypub' ); ?>
				</button>
				<input type='hidden' name='activitypub_header_image' id='activitypub_header_image' value='<?php echo esc_attr( $header_image ); ?>'>
			</td>
		</tr>
		<tr scope="row">
			<th>
				<label><?php \esc_html_e( 'Extra Fields', 'activitypub' ); ?></label>
			</th>
			<td>
				<p class="description">
					<?php
						\esc_html_e( 'Your homepage, social profiles, pronouns, age, anything you want.', 'activitypub' );
					?>
				</p>

				<table class="widefat striped activitypub-extra-fields" role="presentation" style="margin: 15px 0;">
				<?php
				$extra_fields = \Activitypub\Collection\Extra_Fields::get_actor_fields( \get_current_user_id() );

				foreach ( $extra_fields as $extra_field ) :
					?>
				<tr>
					<td><?php echo \esc_html( $extra_field->post_title ); ?></td>
					<td><?php echo \wp_kses_post( \get_the_excerpt( $extra_field ) ); ?></td>
					<td>
						<a href="<?php echo \esc_url( \get_edit_post_link( $extra_field->ID ) ); ?>" class="button">
							<?php \esc_html_e( 'Edit', 'activitypub' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
				</table>

				<p>
					<a href="<?php echo esc_url( admin_url( '/post-new.php?post_type=ap_extrafield' ) ); ?>" class="button">
						<?php esc_html_e( 'Add new', 'activitypub' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( '/edit.php?post_type=ap_extrafield' ) ); ?>">
						<?php esc_html_e( 'Manage all', 'activitypub' ); ?>
					</a>
				</p>
			</td>
		</tr>
	</tbody>
</table>

<?php wp_nonce_field( 'activitypub-user-settings', '_apnonce' ); ?>
