<?php
/**
 * Toolbox template.
 *
 * @package Activitypub
 */

?>

<div class="card activitypub" id="activitypub">
	<h2><?php esc_html_e( 'Fediverse Bookmarklet ⁂', 'activitypub' ); ?></h2>
	<p>
		<?php esc_html_e( 'This lightweight bookmarklet makes it simple to reply to content on any webpage that supports ActivityPub, enhancing your interaction on the fediverse.', 'activitypub' ); ?>
	</p>
	<form>
		<h3><?php esc_html_e( 'Install Bookmarklet', 'activitypub' ); ?></h3>
		<p><?php esc_html_e( 'Drag and drop this button to your browser’s bookmark bar or save this bookmarklet to reply to posts on other websites from your blog! When visiting a post on another site, click the bookmarklet to start a reply.', 'activitypub' ); ?></p>
		<p class="activitypub-bookmarklet-wrapper">
			<a class="activitypub-bookmarklet button" onclick="return false;" href="<?php echo esc_attr( \Activitypub\get_reply_intent_js() ); ?>" style="cursor: grab;">
				<?php // translators: The host (domain) of the Blog. ?>
				<?php printf( esc_html__( 'Reply from %s', 'activitypub' ), esc_attr( \wp_parse_url( \home_url(), PHP_URL_HOST ) ) ); ?>
			</a>
		</p>
		<div class="activitypub-code-wrap clear" id="activitypub-code-wrap">
			<p id="activitypub-code-desc">
				<?php esc_html_e( 'Or copy the following code and create a new bookmark. Paste the code into the new bookmark&#8217;s URL field.', 'activitypub' ); ?>
			</p>
			<p>
				<textarea id="activitypub-bookmarklet-code" class="large-text activitypub-code" rows="5" readonly="readonly" aria-labelledby="activitypub-code-desc"><?php echo esc_textarea( \Activitypub\get_reply_intent_js() ); ?></textarea>
			</p>
			<p><span class="dashicons dashicons-clipboard"></span> <a href="javascript:;" class="copy-activitypub-bookmarklet-code" style="cursor: copy;"><?php esc_html_e( 'Copy to clipboard', 'activitypub' ); ?></a></p>
		</div>
		<script>
		jQuery( document ).ready( function( $ ) {
			var $copyActivitypubBookmarkletCode = $( '.copy-activitypub-bookmarklet-code' );
			$copyActivitypubBookmarkletCode.on( 'click', function( event ) {
				// Get the text field.
				var copyText = document.getElementById("activitypub-bookmarklet-code");

				// Select the text field.
				copyText.select();
				copyText.setSelectionRange(0, 99999); // For mobile devices.

				// Copy the text inside the text field.
				navigator.clipboard.writeText(copyText.value);
			});
		});
		</script>
	</form>
	<h3><?php esc_html_e( 'Reply Intent', 'activitypub' ); ?></h3>
	<p><?php esc_html_e( 'The Reply Intent makes it easy for you to compose and post a Reply to your audience from a link on the Fediverse (Mastodon, Pixelfed, ...).', 'activitypub' ); ?></p>
	<h4><?php esc_html_e( 'URL', 'activitypub' ); ?></h4>
	<p><code><?php echo esc_url( \admin_url( 'post-new.php' ) ); ?></code></p>
	<h4><?php esc_html_e( 'Query parameters', 'activitypub' ); ?></h4>
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th>
					<?php esc_html_e( 'Parameter', 'activitypub' ); ?>
				</th>
				<th>
					<?php esc_html_e( 'Description', 'activitypub' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>in_reply_to</td>
				<td><?php esc_html_e( 'The URL of the content you want to reply to.', 'activitypub' ); ?></td>
			</tr>
			<tr>
				<td>post_type</td>
				<td><?php esc_html_e( 'The Post-Type you want to use for replies.', 'activitypub' ); ?></td>
			</tr>
		</tbody>
	</table>
	<p><?php esc_html_e( 'There might be more query parameters in the future.', 'activitypub' ); ?></p>
</div>
