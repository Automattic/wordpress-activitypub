<?php
/**
 * ActivityPub E-Mail template footer.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */

use Activitypub\Collection\Actors;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$settings_path = 'profile.php#activitypub-notifications';
if ( Actors::BLOG_USER_ID === $args['user_id'] ) {
	$settings_path = 'options-general.php?page=activitypub&tab=blog-profile#activitypub-notifications';
}
?>
	<div class="footer">
		<p><?php esc_html_e( 'You are receiving this emails because of your ActivityPub plugin settings.', 'activitypub' ); ?></p>
		<p>
			<a href="<?php echo esc_url( admin_url( $settings_path ) ); ?>">
				<?php esc_html_e( 'Manage notification settings', 'activitypub' ); ?>
			</a>
		</p>
	</div>
</div><!-- .container -->
