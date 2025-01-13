<?php
/**
 * ActivityPub settings template.
 *
 * @package Activitypub
 */

\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'settings' => 'active',
	)
);
?>

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<form method="post" action="options.php">
		<?php \settings_fields( 'activitypub' ); ?>

		<div class="box">
			<h3><?php \esc_html_e( 'Profiles', 'activitypub' ); ?></h3>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Enable profiles by type', 'activitypub' ); ?>
						</th>
						<td>
							<p>
								<label>
									<input type="radio" name="activitypub_actor_mode" id="activitypub_actor_mode" value="<?php echo esc_attr( ACTIVITYPUB_ACTOR_MODE ); ?>" <?php echo \checked( ACTIVITYPUB_ACTOR_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ); ?> />
									<strong><?php \esc_html_e( 'Author Profiles Only', 'activitypub' ); ?></strong>
								</label>
							</p>
							<p class="description">
								<?php echo \wp_kses( \__( 'Every user with an account on this blog and the <code>activitypub</code> capability enabled gets their own ActivityPub profile.', 'activitypub' ), array( 'code' => array() ) ); ?>
								<?php // translators: %s is a URL. ?>
								<strong><?php echo \wp_kses( sprintf( \__( 'You can add/remove the capability in the <a href="%s">user settings.</a>', 'activitypub' ), admin_url( '/users.php' ) ), array( 'a' => array( 'href' => array() ) ) ); ?></strong>
								<?php echo \wp_kses( \__( 'Select all the users you want to update, choose the method from the drop-down list and click on the "Apply" button.', 'activitypub' ), array( 'code' => array() ) ); ?>
							</p>
							<p>
								<label>
									<input type="radio" name="activitypub_actor_mode" id="activitypub_actor_mode" value="<?php echo esc_attr( ACTIVITYPUB_BLOG_MODE ); ?>" <?php echo \checked( ACTIVITYPUB_BLOG_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ); ?> />
									<strong><?php \esc_html_e( 'Blog profile only', 'activitypub' ); ?></strong>
								</label>
							</p>
							<p class="description">
								<?php \esc_html_e( 'Your blog becomes a single ActivityPub profile and every post will be published under this profile instead of the individual author profiles.', 'activitypub' ); ?>
							</p>
							<p>
								<label>
									<input type="radio" name="activitypub_actor_mode" id="activitypub_actor_mode" value="<?php echo esc_attr( ACTIVITYPUB_ACTOR_AND_BLOG_MODE ); ?>" <?php echo \checked( ACTIVITYPUB_ACTOR_AND_BLOG_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ); ?> />
									<strong><?php \esc_html_e( 'Both author and blog profiles', 'activitypub' ); ?></strong>
								</label>
							</p>
							<p class="description">
								<?php \esc_html_e( "This combines both modes. Users can be followed individually, while following the blog will show boosts of individual user's posts.", 'activitypub' ); ?>
							</p>
						</td>
					</tr>
					<?php \do_settings_fields( 'activitypub', 'user' ); ?>
				</tbody>
			</table>
		</div>

		<div class="box">
			<h3><?php \esc_html_e( 'Activities', 'activitypub' ); ?></h3>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Activity-Object-Type', 'activitypub' ); ?>
						</th>
						<td>
							<p>
								<label>
									<input type="radio" name="activitypub_object_type" id="activitypub_object_type" value="wordpress-post-format" <?php echo \checked( 'wordpress-post-format', \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE ) ); ?> />
									<?php \esc_html_e( 'Automatic (default)', 'activitypub' ); ?>
									-
									<span class="description">
										<?php \esc_html_e( 'Let the plugin choose the best possible format for you.', 'activitypub' ); ?>
									</span>
								</label>
							</p>
							<p>
								<label for="activitypub_object_type_note">
									<input type="radio" name="activitypub_object_type" id="activitypub_object_type_note" value="note" <?php echo \checked( 'note', \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE ) ); ?> />
									<?php \esc_html_e( 'Note', 'activitypub' ); ?>
									-
									<span class="description">
										<?php \esc_html_e( 'Should work with most platforms.', 'activitypub' ); ?>
									</span>
								</label>
							</p>
						</td>
					</tr>
					<tr <?php echo 'wordpress-post-format' === \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE ) ? 'style="display: none"' : ''; ?>>
						<th scope="row">
							<?php \esc_html_e( 'Post content', 'activitypub' ); ?>
						</th>
						<td>
							<p><strong><?php \esc_html_e( 'These settings only apply if you use the "Note" Object-Type setting above.', 'activitypub' ); ?></strong></p>
							<p>
								<textarea name="activitypub_custom_post_content" id="activitypub_custom_post_content" rows="10" cols="50" class="large-text" placeholder="<?php echo wp_kses( ACTIVITYPUB_CUSTOM_POST_CONTENT, 'post' ); ?>"><?php echo esc_textarea( wp_kses( \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT ), 'post' ) ); ?></textarea>
								<details>
									<summary><?php esc_html_e( 'See a list of ActivityPub Template Tags.', 'activitypub' ); ?></summary>
									<div class="description">
										<ul>
											<li><code>[ap_title]</code> - <?php \esc_html_e( 'The post&#8217;s title.', 'activitypub' ); ?></li>
											<li><code>[ap_content]</code> - <?php \esc_html_e( 'The post&#8217;s content.', 'activitypub' ); ?></li>
											<li><code>[ap_excerpt]</code> - <?php \esc_html_e( 'The post&#8217;s excerpt (may be truncated).', 'activitypub' ); ?></li>
											<li><code>[ap_permalink]</code> - <?php \esc_html_e( 'The post&#8217;s permalink.', 'activitypub' ); ?></li>
											<li><code>[ap_shortlink]</code> - <?php echo \wp_kses( \__( 'The post&#8217;s shortlink. I can recommend <a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a>.', 'activitypub' ), 'default' ); ?></li>
											<li><code>[ap_hashtags]</code> - <?php \esc_html_e( 'The post&#8217;s tags as hashtags.', 'activitypub' ); ?></li>
										</ul>
										<p><?php \esc_html_e( 'You can find the full list with all possible attributes in the help section on the top-right of the screen.', 'activitypub' ); ?></p>
									</div>
								</details>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Media attachments', 'activitypub' ); ?>
						</th>
						<td>
							<input value="<?php echo esc_attr( \get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS ) ); ?>" name="activitypub_max_image_attachments" id="activitypub_max_image_attachments" type="number" min="0" />
							<p class="description">
								<?php
								echo \wp_kses(
									\sprintf(
										// translators: %s is a number.
										\__( 'The number of media (images, audio, video) to attach to posts. Default: <code>%s</code>', 'activitypub' ),
										\esc_html( ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS )
									),
									'default'
								);
								?>
							</p>
							<p class="description">
								<em>
									<?php
										esc_html_e( 'Note: audio and video attachments are only supported from Block Editor.', 'activitypub' );
									?>
								</em>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php \esc_html_e( 'Supported post types', 'activitypub' ); ?></th>
						<td>
							<fieldset>
								<?php \esc_html_e( 'Automatically publish items of the selected post types to the fediverse:', 'activitypub' ); ?>

								<ul>
								<?php
								$post_types           = \get_post_types( array( 'public' => true ), 'objects' );
								$supported_post_types = (array) \get_option( 'activitypub_support_post_types', array( 'post' ) );

								foreach ( $post_types as $_post_type ) :
									?>
									<li>
										<input type="checkbox" id="activitypub_support_post_type_<?php echo \esc_attr( $_post_type->name ); ?>" name="activitypub_support_post_types[]" value="<?php echo \esc_attr( $_post_type->name ); ?>" <?php echo \checked( \in_array( $_post_type->name, $supported_post_types, true ) ); ?> />
										<label for="activitypub_support_post_type_<?php echo \esc_attr( $_post_type->name ); ?>"><?php echo \esc_html( $_post_type->label ); ?></label>
										<span class="description">
											<?php echo \esc_html( \Activitypub\get_post_type_description( $_post_type ) ); ?>
										</span>
									</li>
								<?php endforeach; ?>
								</ul>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Hashtags', 'activitypub' ); ?>
						</th>
						<td>
							<p>
								<label><input type="checkbox" name="activitypub_use_hashtags" id="activitypub_use_hashtags" value="1" <?php echo \checked( '1', \get_option( 'activitypub_use_hashtags', '1' ) ); ?> /> <?php echo wp_kses( \__( 'Add hashtags in the content as native tags and replace the <code>#tag</code> with the tag link.', 'activitypub' ), 'default' ); ?></label>
							</p>
						</td>
					</tr>
					<?php \do_settings_fields( 'activitypub', 'activity' ); ?>
				</tbody>
			</table>
		</div>
		<div class="box">
			<h3><?php \esc_html_e( 'Notifications', 'activitypub' ); ?></h3>
			<p><?php \esc_html_e( 'Choose which notifications you want to receive. The plugin currently only supports e-mail notifications, but we will add more options in the future.', 'activitypub' ); ?></p>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Type', 'activitypub' ); ?>
						</th>
						<th>
							<?php \esc_html_e( 'E-Mail', 'activitypub' ); ?>
						</th>
					</tr>
					<tr>
						<td scope="row">
							<?php \esc_html_e( 'New followers', 'activitypub' ); ?>
						</td>
						<td>
							<label>
								<input type="checkbox" name="activitypub_mailer_new_follower" id="activitypub_mailer_new_follower" value="1" <?php \checked( '1', \get_option( 'activitypub_mailer_new_follower', '0' ) ); ?> />
							</label>
						</td>
					</tr>
					<tr>
						<td scope="row">
							<?php \esc_html_e( 'Direct Messages', 'activitypub' ); ?>
						</td>
						<td>
							<label>
								<input type="checkbox" name="activitypub_mailer_new_dm" id="activitypub_mailer_new_dm" value="1" <?php \checked( '1', \get_option( 'activitypub_mailer_new_dm', '0' ) ); ?> />
							</label>
						</td>
					</tr>
					<?php \do_settings_fields( 'activitypub', 'security' ); ?>
				</tbody>
			</table>
		</div>
		<div class="box">
			<h3><?php \esc_html_e( 'General', 'activitypub' ); ?></h3>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'OpenGraph', 'activitypub' ); ?>
						</th>
						<td>
							<p>
								<label>
									<input type="checkbox" name="activitypub_use_opengraph" id="activitypub_use_opengraph" value="1" <?php echo \checked( '1', \get_option( 'activitypub_use_opengraph', '1' ) ); ?> />
									<?php echo wp_kses( \__( 'Automatically add <code>&lt;meta name="fediverse:creator" /&gt;</code> tags for Authors and the Blog-User. You can read more about the feature on the <a href="https://blog.joinmastodon.org/2024/07/highlighting-journalism-on-mastodon/" target="_blank">Mastodon Blog</a>.', 'activitypub' ), 'post' ); ?>
								</label>
							</p>
							<p>
								<label for="activitypub_attribution_domains">
									<?php esc_html_e( 'Websites allowed to credit you.', 'activitypub' ); ?>
								</label>
								<textarea id="activitypub_attribution_domains" name="activitypub_attribution_domains" class="large-text" cols="50" rows="5" placeholder="<?php echo \esc_textarea( \Activitypub\home_host() ); ?>"><?php echo esc_textarea( \get_option( 'activitypub_attribution_domains', \Activitypub\home_host() ) ); ?></textarea>
								<?php esc_html_e( 'One per line. Protects from false attributions.', 'activitypub' ); ?>
							</p>
						</td>
					</tr>
					<?php \do_settings_fields( 'activitypub', 'general' ); ?>
					<?php \do_settings_fields( 'activitypub', 'server' ); ?>
				</tbody>
			</table>
		</div>
		<div class="box">
			<h3><?php \esc_html_e( 'Security', 'activitypub' ); ?></h3>
			<table class="form-table">
				<tbody>
					<?php if ( ! defined( 'ACTIVITYPUB_AUTHORIZED_FETCH' ) ) : ?>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Authorized-Fetch', 'activitypub' ); ?>
						</th>
						<td>
							<p>
								<label>
									<input type="checkbox" name="activitypub_authorized_fetch" id="activitypub_authorized_fetch" value="1" <?php \checked( '1', \get_option( 'activitypub_authorized_fetch', '0' ) ); ?> />
									<?php \esc_html_e( 'Require HTTP signature authentication on ActivityPub representations of public posts and profiles.', 'activitypub' ); ?>
								</label>
							</p>
							<p class="description">
								<?php \esc_html_e( '⚠ Secure mode has its limitations, which is why it is not enabled by default. It is not fully supported by all software in the fediverse, and some features may break, especially when interacting with Mastodon servers older than version 3.0. Additionally, since it requires authentication for public content, caching is not possible, leading to higher computational costs.', 'activitypub' ); ?>
							</p>
							<p class="description">
								<?php \esc_html_e( '⚠ Secure mode does not hide the HTML representations of public posts and profiles. While HTML is a less consistant format (that potentially changes often) compared to first-class ActivityPub representations or the REST API, it still poses a potential risk for content scraping.', 'activitypub' ); ?>
							</p>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row">
							<?php \esc_html_e( 'Blocklist', 'activitypub' ); ?>
						</th>
						<td>
							<p>
								<?php
								echo \wp_kses(
									\sprintf(
										// translators: %s is a URL.
										\__( 'To block servers, add the host of the server to the "<a href="%s">Disallowed Comment Keys</a>" list.', 'activitypub' ),
										\esc_url( \admin_url( 'options-discussion.php#disallowed_keys' ) )
									),
									'default'
								);
								?>
							</p>
						</td>
					</tr>
					<?php \do_settings_fields( 'activitypub', 'security' ); ?>
				</tbody>
			</table>
		</div>
		<?php \do_settings_sections( 'activitypub' ); ?>

		<?php \submit_button(); ?>
	</form>
</div>
