<?php
/**
 * Block confirmation template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;
use Activitypub\Collection\Remote_Actors;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$actor_id = $args['actor_id'];

// Get actor and validate.
$actor = Remote_Actors::get_actor( $actor_id );
if ( is_wp_error( $actor ) ) {
	wp_die( \esc_html__( 'Invalid account.', 'activitypub' ), '', array( 'back_link' => true ) );
}

// Prepare form URL.
$base_url = add_query_arg(
	array(
		'action'   => 'block',
		'follower' => $actor_id,
		'confirm'  => 'true',
	)
);

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Block Account', 'activitypub' ); ?></h1>

	<p>
	<?php
	printf(
		/* translators: %s: username */
		esc_html__( 'You are about to block &#8220;%s&#8221;.', 'activitypub' ),
		'<strong>' . esc_html( $actor->get_preferred_username() ) . '</strong>'
	);
	?>
	</p>
	<p><?php esc_html_e( 'This will:', 'activitypub' ); ?></p>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'Block incoming requests from this account for you.', 'activitypub' ); ?></li>
		<li><?php esc_html_e( 'Remove them from your followers and following lists.', 'activitypub' ); ?></li>
	</ul>

	<form method="post" action="<?php echo esc_url( $base_url ); ?>">
		<?php wp_nonce_field( 'block-follower_' . $actor_id ); ?>

		<p><?php esc_html_e( 'You can unblock this account later from your Blocked Actors list.', 'activitypub' ); ?></p>

		<?php if ( current_user_can( 'manage_options' ) && get_current_screen()->id !== 'settings_page_activitypub' ) : ?>
			<p>
				<label>
					<input type="checkbox" name="site_wide" value="1" />
					<?php esc_html_e( 'Also block this account site-wide (affects all users and the blog actor).', 'activitypub' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<p class="submit">
			<?php submit_button( __( 'Confirm', 'activitypub' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( wp_get_referer() ); ?>" class="button"><?php esc_html_e( 'Cancel', 'activitypub' ); ?></a>
		</p>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
