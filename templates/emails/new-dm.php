<?php
/**
 * ActivityPub New DM E-Mail template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;
use Activitypub\Embed;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

// Load header.
require __DIR__ . '/parts/header.php';
?>

<h1><?php esc_html_e( 'You&#8217;ve got a new message!', 'activitypub' ); ?></h1>
<p>
	<?php
	if ( Actors::BLOG_USER_ID === $args['user_id'] ) :
		/* translators: %s: The name of the person who mentioned the blog. */
		$message = __( 'Looks like someone&#8217;s reaching out! Your blog just got a direct message from %s on the Fediverse. Here&#8217;s what they said:', 'activitypub' );
	else :
		/* translators: %s: The name of the person who sent the message. */
		$message = __( 'Looks like someone&#8217;s reaching out! You just got a direct message from %s on the Fediverse. Here&#8217;s what they said:', 'activitypub' );
	endif;

	printf( esc_html( $message ), '<a href="' . esc_url( $args['actor']['url'] ) . '">' . esc_html( $args['actor']['webfinger'] ) . '</a>' );
	?>
</p>

<div class="embed">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo Embed::get_html_for_object( $args['activity']['object'] );
	?>
</div>

<?php
/**
 * Fires at the bottom of the new direct message emails.
 *
 * @param array $args The template arguments.
 */
do_action( 'activitypub_new_dm_email', $args );

// Load footer.
require __DIR__ . '/parts/footer.php';
