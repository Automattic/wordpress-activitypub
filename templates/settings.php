<?php
/**
 * ActivityPub settings template.
 *
 * @package Activitypub
 */

?>

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<form method="post" action="options.php">
		<?php \settings_fields( 'activitypub' ); ?>

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
