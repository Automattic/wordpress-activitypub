<?php
/**
 * ActivityPub Blog Settings template.
 *
 * @package Activitypub
 */

\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'blog-profile' => 'active',
	)
);
?>

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<form method="post" action="options.php">
		<?php \settings_fields( 'activitypub_blog' ); ?>

		<div class="box">
			<h3><?php \esc_html_e( 'Blog-Profile', 'activitypub' ); ?></h3>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Manage Avatar', 'activitypub' ); ?>
						</th>
						<td>
							<?php if ( \has_site_icon() ) : ?>
							<p><img src="<?php echo esc_url( get_site_icon_url( '50' ) ); ?>" /></p>
							<?php endif; ?>
							<p class="description">
								<?php
								echo \wp_kses(
									\sprintf(
										// translators: %s is a URL.
										\__( 'The ActivityPub plugin uses the WordPress Site Icon as Avatar for the Blog-Profile, you can change the Site Icon in the "<a href="%s">General Settings</a>" of WordPress.', 'activitypub' ),
										\esc_url( \admin_url( 'options-general.php' ) )
									),
									'default'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<?php \esc_html_e( 'Manage Header Image', 'activitypub' ); ?>
						</th>
						<td>
							<?php
							$classes_for_upload_button = 'button upload-button button-add-media button-add-header-image';
							$classes_for_update_button = 'button';
							$classes_for_wrapper       = '';

							if ( (int) get_option( 'activitypub_header_image', 0 ) ) :
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
								<img id='activitypub-header-image-preview' src='<?php echo esc_url( wp_get_attachment_url( get_option( 'activitypub_header_image' ) ) ); ?>' style="max-width: 100%;" />
							</div>
							<button
								type="button"
								id="activitypub-choose-from-library-button"
								type="button"
								class="<?php echo esc_attr( $classes_for_button ); ?>"
								data-alt-classes="<?php echo esc_attr( $classes_for_button_on_change ); ?>"
								data-choose-text="<?php esc_attr_e( 'Choose a Header Image', 'activitypub' ); ?>"
								data-update-text="<?php esc_attr_e( 'Change Header Icon', 'activitypub' ); ?>"
								data-update="<?php esc_attr_e( 'Set as Header Image', 'activitypub' ); ?>"
								data-state="<?php echo esc_attr( (int) get_option( 'activitypub_header_image', 0 ) ); ?>">
								<?php if ( (int) get_option( 'activitypub_header_image', 0 ) ) : ?>
									<?php esc_html_e( 'Change Header Image', 'activitypub' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Choose a Header Image', 'activitypub' ); ?>
								<?php endif; ?>
							</button>
							<button
								id="activitypub-remove-header-image"
								type="button"
								<?php echo (int) get_option( 'activitypub_header_image', 0 ) ? 'class="button button-secondary reset"' : 'class="button button-secondary reset hidden"'; ?>>
								<?php esc_html_e( 'Remove Header Image', 'activitypub' ); ?>
							</button>
							<input type='hidden' name='activitypub_header_image' id='activitypub_header_image' value='<?php echo esc_attr( get_option( 'activitypub_header_image' ) ); ?>'>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Change profile ID', 'activitypub' ); ?>
						</th>
						<td>
							<label for="activitypub_blog_identifier">
								<input class="blog-user-identifier" name="activitypub_blog_identifier" id="activitypub_blog_identifier" type="text" value="<?php echo esc_attr( \get_option( 'activitypub_blog_identifier', \Activitypub\Model\Blog::get_default_username() ) ); ?>" />
								@<?php echo esc_html( \wp_parse_url( \home_url(), PHP_URL_HOST ) ); ?>
							</label>
							<p class="description">
								<?php \esc_html_e( 'This profile name will federate all posts written on your blog, regardless of the author who posted it.', 'activitypub' ); ?>
							</p>
							<p>
								<strong>
									<?php \esc_html_e( 'Please avoid using an existing author’s name as the blog profile ID. Fediverse platforms might use caching and this could break the functionality completely.', 'activitypub' ); ?>
								</strong>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Change Description', 'activitypub' ); ?>
						</th>
						<td>
							<label for="activitypub_blog_description">
								<textarea
									class="blog-user-description large-text"
									rows="5"
									name="activitypub_blog_description"
									id="activitypub_blog_description"
									placeholder="<?php echo esc_attr( \get_bloginfo( 'description' ) ); ?>"
								><?php echo \esc_textarea( \get_option( 'activitypub_blog_description' ) ); ?></textarea>
							</label>
							<p class="description">
								<?php \esc_html_e( 'By default the ActivityPub plugin uses the WordPress tagline as a description for the blog profile.', 'activitypub' ); ?>
							</p>
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
							$extra_fields = \Activitypub\Collection\Extra_Fields::get_actor_fields( \Activitypub\Collection\Actors::BLOG_USER_ID );

							if ( empty( $extra_fields ) ) :
								?>
							<tr>
								<td colspan="3">
									<?php \esc_html_e( 'No extra fields found.', 'activitypub' ); ?>
								</td>
							</tr>
								<?php
							endif;

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
								<a href="<?php echo esc_url( admin_url( '/post-new.php?post_type=ap_extrafield_blog' ) ); ?>" class="button">
									<?php esc_html_e( 'Add new', 'activitypub' ); ?>
								</a>
								<a href="<?php echo esc_url( admin_url( '/edit.php?post_type=ap_extrafield_blog' ) ); ?>">
									<?php esc_html_e( 'Manage all', 'activitypub' ); ?>
								</a>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php \do_settings_sections( 'activitypub_blog_profile' ); ?>

		<?php \submit_button(); ?>
	</form>
</div>
