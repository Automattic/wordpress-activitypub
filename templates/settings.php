<?php
\load_template(
	\dirname( __FILE__ ) . '/admin-header.php',
	true,
	array(
		'settings' => 'active',
		'welcome' => '',
	)
);
?>

<div class="privacy-settings-body hide-if-no-js">
	<div class="notice notice-info">
		<p>
			<?php
			echo \wp_kses(
				\sprintf(
					// translators:
					\__( 'If you have problems using this plugin, please check the <a href="%s">Site Health</a> to ensure that your site is compatible and/or use the "Help" tab (in the top right of the settings pages).', 'activitypub' ),
					\esc_url_raw( \admin_url( 'site-health.php' ) )
				),
				'default'
			);
			?>
		</p>
	</div>

	<p><?php \esc_html_e( 'Customize your ActivityPub settings to suit your needs.', 'activitypub' ); ?></p>

	<form method="post" action="options.php">
		<?php \settings_fields( 'activitypub' ); ?>

		<h3><?php \esc_html_e( 'Activities', 'activitypub' ); ?></h3>

		<p><?php \esc_html_e( 'All activity related settings.', 'activitypub' ); ?></p>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<?php \esc_html_e( 'Post-Content', 'activitypub' ); ?>
					</th>
					<td>
						<p>
							<label><input type="radio" name="activitypub_post_content_type" id="activitypub_post_content_type_title_link" value="title" <?php echo \checked( 'title', \get_option( 'activitypub_post_content_type', 'content' ) ); ?> /> <?php \esc_html_e( 'Title and link', 'activitypub' ); ?></label> - <span class="description"><?php \esc_html_e( 'Only the title and a link.', 'activitypub' ); ?></span>
						</p>
						<p>
							<label><input type="radio" name="activitypub_post_content_type" id="activitypub_post_content_type_excerpt" value="excerpt" <?php echo \checked( 'excerpt', \get_option( 'activitypub_post_content_type', 'content' ) ); ?> /> <?php \esc_html_e( 'Excerpt', 'activitypub' ); ?></label> - <span class="description"><?php \esc_html_e( 'A content summary, shortened to 400 characters and without markup.', 'activitypub' ); ?></span>
						</p>
						<p>
							<label><input type="radio" name="activitypub_post_content_type" id="activitypub_post_content_type_content" value="content" <?php echo \checked( 'content', \get_option( 'activitypub_post_content_type', 'content' ) ); ?> /> <?php \esc_html_e( 'Content (default)', 'activitypub' ); ?></label> - <span class="description"><?php \esc_html_e( 'The full content.', 'activitypub' ); ?></span>
						</p>
						<p>
							<label><input type="radio" name="activitypub_post_content_type" id="activitypub_post_content_type_custom" value="custom" <?php echo \checked( 'custom', \get_option( 'activitypub_post_content_type', 'content' ) ); ?> /> <?php \esc_html_e( 'Custom', 'activitypub' ); ?></label> - <span class="description"><?php \esc_html_e( 'Use the text-area below, to customize your activities.', 'activitypub' ); ?></span>
						</p>
						<p>
							<textarea name="activitypub_custom_post_content" id="activitypub_custom_post_content" rows="10" cols="50" class="large-text" placeholder="<?php echo wp_kses( ACTIVITYPUB_CUSTOM_POST_CONTENT, 'post' ); ?>"><?php echo wp_kses( \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT ), 'post' ); ?></textarea>
							<details>
								<summary><?php esc_html_e( 'See the complete list of template patterns.', 'activitypub' ); ?></summary>
								<div class="description">
									<ul>
										<li><code>%title%</code> - <?php \esc_html_e( 'The Post-Title.', 'activitypub' ); ?></li>
										<li><code>%content%</code> - <?php \esc_html_e( 'The Post-Content.', 'activitypub' ); ?></li>
										<li><code>%excerpt%</code> - <?php \esc_html_e( 'The Post-Excerpt (default 400 Chars).', 'activitypub' ); ?></li>
										<li><code>%permalink%</code> - <?php \esc_html_e( 'The Post-Permalink.', 'activitypub' ); ?></li>
										<?php // translators: ?>
										<li><code>%shortlink%</code> - <?php echo \wp_kses( \__( 'The Post-Shortlink. I can recommend <a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a>, to prettify the Shortlinks', 'activitypub' ), 'default' ); ?></li>
										<li><code>%hashtags%</code> - <?php \esc_html_e( 'The Tags as Hashtags.', 'activitypub' ); ?></li>
									</ul>
								</div>
							</details>
						</p>
						<?php // translators: ?>
						<p><?php echo \wp_kses( \__( '<a href="https://github.com/pfefferle/wordpress-activitypub/issues/new" target="_blank">Let me know</a> if you miss a template pattern.', 'activitypub' ), 'default' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php \esc_html_e( 'Activity-Object-Type', 'activitypub' ); ?>
					</th>
					<td>
						<p>
							<label><input type="radio" name="activitypub_object_type" id="activitypub_object_type_note" value="note" <?php echo \checked( 'note', \get_option( 'activitypub_object_type', 'note' ) ); ?> /> <?php \esc_html_e( 'Note (default)', 'activitypub' ); ?></label> - <span class="description"><?php \esc_html_e( 'Should work with most platforms.', 'activitypub' ); ?></span>
						</p>
						<p>
							<label><input type="radio" name="activitypub_object_type" id="activitypub_object_type_article" value="article" <?php echo \checked( 'article', \get_option( 'activitypub_object_type', 'note' ) ); ?> /> <?php \esc_html_e( 'Article', 'activitypub' ); ?></label> - <span class="description"><?php \esc_html_e( 'The presentation of the "Article" might change on different platforms. Mastodon for example shows the "Article" type as a simple link.', 'activitypub' ); ?></span>
						</p>
						<p>
							<label><input type="radio" name="activitypub_object_type" id="activitypub_object_type" value="wordpress-post-format" <?php echo \checked( 'wordpress-post-format', \get_option( 'activitypub_object_type', 'note' ) ); ?> /> <?php \esc_html_e( 'WordPress Post-Format', 'activitypub' ); ?></label> - <span class="description"><?php \esc_html_e( 'Maps the WordPress Post-Format to the ActivityPub Object Type.', 'activitypub' ); ?></span>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Supported post types', 'activitypub' ); ?></th>
					<td>
						<fieldset>
							<?php \esc_html_e( 'Enable ActivityPub support for the following post types:', 'activitypub' ); ?>

							<?php $post_types = \get_post_types( array( 'public' => true ), 'objects' ); ?>
							<?php $support_post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) : array(); ?>
							<ul>
							<?php // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
							<?php foreach ( $post_types as $post_type ) { ?>
								<li>
									<input type="checkbox" id="activitypub_support_post_types" name="activitypub_support_post_types[]" value="<?php echo \esc_attr( $post_type->name ); ?>" <?php echo \checked( true, \in_array( $post_type->name, $support_post_types, true ) ); ?> />
									<label for="<?php echo \esc_attr( $post_type->name ); ?>"><?php echo \esc_html( $post_type->label ); ?></label>
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
							<label><input type="checkbox" name="activitypub_use_hashtags" id="activitypub_use_hashtags" value="1" <?php echo \checked( '1', \get_option( 'activitypub_use_hashtags', '1' ) ); ?> /> <?php echo wp_kses( \__( 'Add hashtags in the content as native tags and replace the <code>#tag</code> with the tag-link.', 'activitypub' ), 'default' ); ?></label>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php \esc_html_e( 'HTML Whitelist', 'activitypub' ); ?>
					</th>
					<td>
						<textarea name="activitypub_allowed_html" id="activitypub_allowed_html" rows="3" cols="50" class="large-text"><?php echo esc_html( \get_option( 'activitypub_allowed_html', ACTIVITYPUB_ALLOWED_HTML ) ); ?></textarea>
						<p class="description">
							<?php
							echo \wp_kses(
								\sprintf(
									// translators:
									\__( 'A list of HTML elements, you want to whitelist for your activities. <strong>Leave list empty to support all HTML elements</strong>. Default: <code>%s</code>', 'activitypub' ),
									\esc_html( ACTIVITYPUB_ALLOWED_HTML )
								),
								'default'
							);
							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php \do_settings_fields( 'activitypub', 'activity' ); ?>

		<h3><?php \esc_html_e( 'Server', 'activitypub' ); ?></h3>

		<p><?php \esc_html_e( 'Server related settings.', 'activitypub' ); ?></p>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<?php \esc_html_e( 'Blocklist', 'activitypub' ); ?>
					</th>
					<td>
						<p class="description">
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

		<?php \do_settings_fields( 'activitypub', 'server' ); ?>

		<?php \do_settings_sections( 'activitypub' ); ?>

		<?php \submit_button(); ?>
	</form>
</div>
