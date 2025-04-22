<?php
/**
 * ActivityPub E-Mail template footer.
 *
 * @package Activitypub
 */

?>
	<div class="footer">
		<p><?php esc_html_e( 'You are receiving this emails because of your ActivityPub plugin settings.', 'activitypub' ); ?></p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=activitypub&tab=settings' ) ); ?>">
				<?php esc_html_e( 'Manage notification settings', 'activitypub' ); ?>
			</a>
		</p>
	</div>
</div><!-- .container -->
