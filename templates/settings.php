<?php
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'settings'     => 'active',
		'welcome'      => '',
		'followers'    => '',
		'blog-profile' => '',
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
									<input type="checkbox" name="activitypub_enable_users" id="activitypub_enable_users" value="1" <?php echo \checked( '1', \get_option( 'activitypub_enable_users', '1' ) ); ?> />
									<?php \esc_html_e( 'Enable Author-Profiles', 'activitypub' ); ?>
								</label>
							</p>
							<p class="description">
								<?php echo \wp_kses( \__( 'Every author on this blog (with the <code>activitypub</code> capability) gets their own ActivityPub profile.', 'activitypub' ), array( 'code' => array() ) ); ?>
								<?php // translators: %s is a URL. ?>
								<strong><?php echo \wp_kses( sprintf( \__( 'You can add/remove the capability in the <a href="%s">user settings.</a>', 'activitypub' ), admin_url( '/users.php' ) ), array( 'a' => array( 'href' => array() ) ) ); ?></strong>
								<?php echo \wp_kses( \__( 'Select all the users you want to update, choose the method from the drop-down list and click on the "Apply" button.', 'activitypub' ), array( 'code' => array() ) ); ?>
							</p>
							<p>
								<label>
									<input type="checkbox" name="activitypub_enable_blog_user" id="activitypub_enable_blog_user" value="1" <?php echo \checked( '1', \get_option( 'activitypub_enable_blog_user', '0' ) ); ?> />
									<?php \esc_html_e( 'Enable Blog-Profile', 'activitypub' ); ?>
								</label>
							</p>
							<p class="description">
								<?php \esc_html_e( 'Your blog becomes an ActivityPub profile.', 'activitypub' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php \do_settings_fields( 'activitypub', 'user' ); ?>
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
								<label for="activitypub_object_type_note">
									<input type="radio" name="activitypub_object_type" id="activitypub_object_type_note" value="note" <?php echo \checked( 'note', \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE ) ); ?> />
									<?php \esc_html_e( 'Note (default)', 'activitypub' ); ?>
									-
									<span class="description">
										<?php \esc_html_e( 'Should work with most platforms.', 'activitypub' ); ?>
									</span>
								</label>
							</p>
							<p>
								<label>
									<input type="radio" name="activitypub_object_type" id="activitypub_object_type" value="wordpress-post-format" <?php echo \checked( 'wordpress-post-format', \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE ) ); ?> />
									<?php \esc_html_e( 'WordPress Post-Format', 'activitypub' ); ?>
									-
									<span class="description">
										<?php \esc_html_e( 'Maps the WordPress Post-Format to the ActivityPub Object Type.', 'activitypub' ); ?>
									</span>
								</label>
							</p>

						</td>
					</tr>
					<?php // phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace ?>
					<tr <?php if ( 'wordpress-post-format' === \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE ) ) { echo 'style="display: none"'; } ?>>
						<th scope="row">
							<?php \esc_html_e( 'Post content', 'activitypub' ); ?>
						</th>
						<td>
							<p><strong><?php \esc_html_e( 'These settings only apply if you use the "Note" Object-Type setting above.', 'activitypub' ); ?></strong></p>
							<p>
								<label for="activitypub_post_content_type_title_link">
									<input type="radio" name="activitypub_post_content_type" id="activitypub_post_content_type_title_link" value="title" <?php echo \checked( 'title', \get_option( 'activitypub_post_content_type', 'content' ) ); ?> />
									<?php \esc_html_e( 'Title and link', 'activitypub' ); ?>
									-
									<span class="description">
										<?php \esc_html_e( 'Only the title and a link.', 'activitypub' ); ?>
									</span>
								</label>
							</p>
							<p>
								<label for="activitypub_post_content_type_excerpt">
									<input type="radio" name="activitypub_post_content_type" id="activitypub_post_content_type_excerpt" value="excerpt" <?php echo \checked( 'excerpt', \get_option( 'activitypub_post_content_type', 'content' ) ); ?> />
									<?php \esc_html_e( 'Excerpt', 'activitypub' ); ?>
									-
									<span class="description">
										<?php \esc_html_e( 'A content summary without markup (truncated if no excerpt is provided).', 'activitypub' ); ?>
									</span>
								</label>
							</p>
							<p>
								<label for="activitypub_post_content_type_content">
									<input type="radio" name="activitypub_post_content_type" id="activitypub_post_content_type_content" value="content" <?php echo \checked( 'content', \get_option( 'activitypub_post_content_type', 'content' ) ); ?> />
									<?php \esc_html_e( 'Content (default)', 'activitypub' ); ?>
									-
									<span class="description">
										<?php \esc_html_e( 'The full content.', 'activitypub' ); ?>
									</span>
								</label>
							</p>
							<p>
								<label for="activitypub_post_content_type_custom">
									<input type="radio" name="activitypub_post_content_type" id="activitypub_post_content_type_custom" value="custom" <?php echo \checked( 'custom', \get_option( 'activitypub_post_content_type', 'content' ) ); ?> />
									<?php \esc_html_e( 'Custom', 'activitypub' ); ?>
									-
									<span class="description">
										<?php \esc_html_e( 'Use the text area below, to customize your activities.', 'activitypub' ); ?>
									</span>
								</label>
							</p>
							<p>
								<textarea name="activitypub_custom_post_content" id="activitypub_custom_post_content" rows="10" cols="50" class="large-text" placeholder="<?php echo wp_kses( ACTIVITYPUB_CUSTOM_POST_CONTENT, 'post' ); ?>"><?php echo esc_textarea( wp_kses( \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT ), 'post' ) ); ?></textarea>
								<details>
									<summary><?php esc_html_e( 'See a list of ActivityPub Template Tags.', 'activitypub' ); ?></summary>
									<div class="description">
										<ul>
											<li><code>[ap_title]</code> - <?php \esc_html_e( 'The post\'s title.', 'activitypub' ); ?></li>
											<li><code>[ap_content]</code> - <?php \esc_html_e( 'The post\'s content.', 'activitypub' ); ?></li>
											<li><code>[ap_excerpt]</code> - <?php \esc_html_e( 'The post\'s excerpt (may be truncated).', 'activitypub' ); ?></li>
											<li><code>[ap_permalink]</code> - <?php \esc_html_e( 'The post\'s permalink.', 'activitypub' ); ?></li>
											<li><code>[ap_shortlink]</code> - <?php echo \wp_kses( \__( 'The post\'s shortlink. I can recommend <a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a>.', 'activitypub' ), 'default' ); ?></li>
											<li><code>[ap_hashtags]</code> - <?php \esc_html_e( 'The post\'s tags as hashtags.', 'activitypub' ); ?></li>
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
										// translators:
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

								<?php $post_types = \get_post_types( array( 'public' => true ), 'objects' ); ?>
								<?php $support_post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post' ) ) : array(); ?>
								<ul>
								<?php // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
								<?php foreach ( $post_types as $post_type ) { ?>
									<li>
										<input type="checkbox" id="activitypub_support_post_type_<?php echo \esc_attr( $post_type->name ); ?>" name="activitypub_support_post_types[]" value="<?php echo \esc_attr( $post_type->name ); ?>" <?php echo \checked( \in_array( $post_type->name, $support_post_types, true ) ); ?> />
										<label for="activitypub_support_post_type_<?php echo \esc_attr( $post_type->name ); ?>"><?php echo \esc_html( $post_type->label ); ?></label>
										<span class="description">
											<?php echo \esc_html( \Activitypub\get_post_type_description( $post_type ) ); ?>
										</span>
									</li>
								<?php } ?>
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
				</tbody>
			</table>

			<?php \do_settings_fields( 'activitypub', 'activity' ); ?>
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
								<label><input type="checkbox" name="activitypub_use_opengraph" id="activitypub_use_opengraph" value="1" <?php echo \checked( '1', \get_option( 'activitypub_use_opengraph', '1' ) ); ?> /> <?php echo wp_kses( \__( 'Automatically add <code>&lt;meta name="fediverse:creator" /&gt;</code> tags for Authors and the Blog-User. You can read more about the feature on the <a href="https://blog.joinmastodon.org/2024/07/highlighting-journalism-on-mastodon/" target="_blank">Mastodon Blog</a>.', 'activitypub' ), 'post' ); ?></label>
							</p>
						</td>
					</tr>
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
										\esc_attr( \admin_url( 'options-discussion.php#disallowed_keys' ) )
									),
									'default'
								);
								?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php \do_settings_fields( 'activitypub', 'general' ); ?>
			<?php \do_settings_fields( 'activitypub', 'server' ); ?>
		</div>
		<?php \do_settings_sections( 'activitypub' ); ?>

		<?php \submit_button(); ?>
	</form>
</div>
