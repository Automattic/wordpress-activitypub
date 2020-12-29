<div class="wrap">
	<h1><?php \esc_html_e( 'ActivityPub Settings', 'activitypub' ); ?></h1>

	<p><?php \esc_html_e( 'ActivityPub turns your blog into a federated social network. This means you can share and talk to everyone using the ActivityPub protocol, including users of Friendica, Pleroma and Mastodon.', 'activitypub' ); ?></p>

	<form method="post" action="options.php">
		<?php \settings_fields( 'activitypub' ); ?>

		<h2><?php \esc_html_e( 'Activities', 'activitypub' ); ?></h2>

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
							<textarea name="activitypub_custom_post_content" id="activitypub_custom_post_content" rows="10" cols="50" class="large-text" placeholder="<?php echo ACTIVITYPUB_CUSTOM_POST_CONTENT; ?>"><?php echo \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT ); ?></textarea>
							<div class="description">
								<ul>
									<li><code>%title%</code> - <?php \esc_html_e( 'The Post-Title.', 'activitypub' ); ?></li>
									<li><code>%content%</code> - <?php \esc_html_e( 'The Post-Content.', 'activitypub' ); ?></li>
									<li><code>%excerpt%</code> - <?php \esc_html_e( 'The Post-Excerpt (default 400 Chars).', 'activitypub' ); ?></li>
									<li><code>%permalink%</code> - <?php \esc_html_e( 'The Post-Permalink.', 'activitypub' ); ?></li>
									<li><code>%shortlink%</code> - <?php \printf( \esc_html( 'The Post-Shortlink. I can recommend %sHum%s, to prettify the Shortlinks', 'activitypub' ), '<a href="https://wordpress.org/plugins/hum/" target="_blank">', '</a>' ); ?></li>
									<li><code>%hashtags%</code> - <?php \esc_html_e( 'The Tags as Hashtags.', 'activitypub' ); ?></li>
								</ul>
								<?php \printf( \__( '%sLet me know%s if you miss a template placeholder.', 'activitypub' ), '<a href="https://github.com/pfefferle/wordpress-activitypub/issues/new" target="_blank">', '</a>' ); ?>
							</div>
						</p>
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
							<label><input type="checkbox" name="activitypub_use_hashtags" id="activitypub_use_hashtags" value="1" <?php echo \checked( '1', \get_option( 'activitypub_use_hashtags', '1' ) ); ?> /> <?php \_e( 'Add hashtags in the content as native tags and replace the <code>#tag</code> with the tag-link.', 'activitypub' ); ?></label>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php \esc_html_e( 'HTML Whitelist', 'activitypub' ); ?>
					</th>
					<td>
						<textarea name="activitypub_allowed_html" id="activitypub_allowed_html" rows="3" cols="50" class="large-text"><?php echo \get_option( 'activitypub_allowed_html', ACTIVITYPUB_ALLOWED_HTML ); ?></textarea>
						<p class="description"><?php \_e( \sprintf( 'A list of HTML elements, you want to whitelist for your activities. <strong>Leave list empty to support all HTML elements.</strong> Default: <code>%s</code>.', \esc_html( ACTIVITYPUB_ALLOWED_HTML ) ), 'activitypub' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php \do_settings_fields( 'activitypub', 'activity' ); ?>

		<h2><?php \esc_html_e( 'Server', 'activitypub' ); ?></h2>

		<p><?php \esc_html_e( 'Server related settings.', 'activitypub' ); ?></p>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<?php \esc_html_e( 'Blocklist', 'activitypub' ); ?>
					</th>
					<td>
						<p class="description"><?php \printf( \__( 'To block servers, add the host of the server to the "<a href="%s">Disallowed Comment Keys</a>" list.', 'activitypub' ), admin_url( 'options-discussion.php#disallowed_keys' ) ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php \do_settings_fields( 'activitypub', 'server' ); ?>

		<?php \do_settings_sections( 'activitypub' ); ?>

		<?php \submit_button(); ?>
	</form>

	<p>
		<small><?php \_e( 'If you like this plugin, what about a small <a href="https://notiz.blog/donate">donation</a>?', 'activitypub' ); ?></small>
	</p>
</div>
