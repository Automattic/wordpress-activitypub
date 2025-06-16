<?php
/**
 * ActivityPub New Mention E-Mail template with styles.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;
use Activitypub\Embed;

use function Activitypub\site_supports_blocks;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

// Load header.
require __DIR__ . '/parts/header.php';
?>

<h1>
	<?php
	if ( Actors::BLOG_USER_ID === $args['user_id'] ) :
		esc_html_e( 'Your blog was mentioned!', 'activitypub' );
	else :
		esc_html_e( 'You were mentioned!', 'activitypub' );
	endif;
	?>
</h1>

<p>
	<?php
	if ( Actors::BLOG_USER_ID === $args['user_id'] ) :
		/* translators: %s: The name of the person who mentioned the blog. */
		$message = __( 'Looks like someone&#8217;s talking about your blog! It was just mentioned by %s in a post on the Fediverse. Here&#8217;s what they said:', 'activitypub' );
	else :
		/* translators: %s: The name of the person who mentioned the user. */
		$message = __( 'Looks like someone&#8217;s talking about you! You were just mentioned by %s in a post on the Fediverse. Here&#8217;s what they said:', 'activitypub' );
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

<?php if ( site_supports_blocks() && ! is_plugin_active( 'classic-editor/classic-editor.php' ) ) : ?>
<p>
	<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?in_reply_to=' . $args['activity']['object']['id'] ) ); ?>">
		<?php esc_html_e( 'Reply to the post', 'activitypub' ); ?>
	</a>
</p>
<?php endif; ?>

<?php
/**
 * Fires at the bottom of the new mention emails.
 *
 * @param array $args The template arguments.
 */
do_action( 'activitypub_new_mention_email', $args );

// Load footer.
require __DIR__ . '/parts/footer.php';
