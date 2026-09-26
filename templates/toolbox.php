<?php
/**
 * Toolbox template.
 *
 * @package Activitypub
 */

$reply_intent_js = \Activitypub\get_reply_intent_js();
$quote_intent_js = \Activitypub\get_quote_intent_js();
?>

<div class="card activitypub" id="activitypub-bookmarklet">
	<h2><?php esc_html_e( 'Fediverse Bookmarklet ⁂', 'activitypub' ); ?></h2>
	<p>
		<?php esc_html_e( 'These lightweight bookmarklets make it simple to reply to or quote content on any webpage that supports ActivityPub, enhancing your interaction on the fediverse.', 'activitypub' ); ?>
	</p>
	<form>
		<h3><?php esc_html_e( 'Install Bookmarklet', 'activitypub' ); ?></h3>
		<p><?php esc_html_e( 'Drag one of these buttons to your browser’s bookmark bar. When you visit a post on another site, click it to reply to that post or to quote it from your blog.', 'activitypub' ); ?></p>
		<p class="activitypub-bookmarklet-buttons">
			<a class="activitypub-bookmarklet button" onclick="return false;" href="<?php echo esc_attr( $reply_intent_js ); ?>">
				<?php // translators: The host (domain) of the Blog. ?>
				<?php printf( esc_html__( 'Reply from %s', 'activitypub' ), esc_attr( \wp_parse_url( \home_url(), PHP_URL_HOST ) ) ); ?>
			</a>
			<a class="activitypub-bookmarklet button" onclick="return false;" href="<?php echo esc_attr( $quote_intent_js ); ?>">
				<?php // translators: The host (domain) of the Blog. ?>
				<?php printf( esc_html__( 'Quote from %s', 'activitypub' ), esc_attr( \wp_parse_url( \home_url(), PHP_URL_HOST ) ) ); ?>
			</a>
		</p>
		<div class="activitypub-bookmarklet-code-wrap" id="activitypub-bookmarklet-code-wrap">
			<p id="activitypub-bookmarklet-code-desc">
				<?php esc_html_e( 'Or create a bookmark by hand and paste this code into its URL field.', 'activitypub' ); ?>
			</p>
			<p>
				<label for="activitypub-bookmarklet-select"><?php esc_html_e( 'Bookmarklet', 'activitypub' ); ?></label>
				<select id="activitypub-bookmarklet-select" class="activitypub-bookmarklet-select">
					<option value="reply" data-code="<?php echo esc_attr( $reply_intent_js ); ?>"><?php esc_html_e( 'Reply', 'activitypub' ); ?></option>
					<option value="quote" data-code="<?php echo esc_attr( $quote_intent_js ); ?>"><?php esc_html_e( 'Quote', 'activitypub' ); ?></option>
				</select>
			</p>
			<p>
				<textarea id="activitypub-bookmarklet-code" class="large-text activitypub-bookmarklet-code" rows="6" readonly="readonly" aria-labelledby="activitypub-bookmarklet-code-desc"><?php echo esc_textarea( $reply_intent_js ); ?></textarea>
			</p>
			<p><span class="dashicons dashicons-clipboard"></span> <a href="javascript:;" class="activitypub-bookmarklet-copy" style="cursor: copy;"><?php esc_html_e( 'Copy to clipboard', 'activitypub' ); ?></a></p>
		</div>
		<script>
		jQuery( document ).ready( function( $ ) {
			var $code = $( '#activitypub-bookmarklet-code' );

			// The select decides which bookmarklet the code box and the copy link refer to.
			$( '#activitypub-bookmarklet-select' ).on( 'change', function() {
				$code.val( $( this ).find( 'option:selected' ).data( 'code' ) );
			} );

			$( '.activitypub-bookmarklet-copy' ).on( 'click', function() {
				var field = $code[0];

				field.select();
				field.setSelectionRange( 0, 99999 ); // For mobile devices.

				navigator.clipboard.writeText( field.value );
			} );
		} );
		</script>
	</form>
</div>
<div class="card activitypub" id="activitypub-intents">
	<h2><?php esc_html_e( 'Fediverse Intents ⁂', 'activitypub' ); ?></h2>
	<p><?php esc_html_e( 'A Post Intent opens the editor with a Fediverse link already filled in, so you can write about a post from Mastodon, Pixelfed or any other Fediverse server without copying the URL by hand. The bookmarklet above uses it, and you can also link to it yourself.', 'activitypub' ); ?></p>
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
				<td><?php esc_html_e( 'The URL of the post you want to reply to. The editor opens with a Reply block for that URL, and your post federates as a reply to it.', 'activitypub' ); ?></td>
			</tr>
			<tr>
				<td>quotation_of</td>
				<td><?php esc_html_e( 'The URL of the post you want to quote. The editor opens with a Federated Quote block for that URL, and the quoted author is asked for permission.', 'activitypub' ); ?></td>
			</tr>
			<tr>
				<td>post_type</td>
				<td><?php esc_html_e( 'The post type to compose in, for example post or a custom type. Defaults to the post type WordPress would open anyway.', 'activitypub' ); ?></td>
			</tr>
		</tbody>
	</table>
	<p><?php esc_html_e( 'There might be more query parameters in the future.', 'activitypub' ); ?></p>
</div>
