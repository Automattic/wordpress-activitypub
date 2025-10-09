<?php
/**
 * ActivityPub New Quote E-Mail template with styles.
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

<h1><?php esc_html_e( 'Your post was quoted!', 'activitypub' ); ?></h1>

<p>
	<?php
	/* translators: %s: The name of the person who quoted the post. */
	$message = __( 'Looks like someone&#8217;s sharing one of your posts! Your post was just quoted by %s on the Fediverse. Here&#8217;s what they said:', 'activitypub' );

	printf( esc_html( $message ), '<a href="' . esc_url( $args['actor']['url'] ) . '">' . esc_html( $args['actor']['webfinger'] ) . '</a>' );
	?>
</p>

<?php
// For QuoteRequest activities, the instrument contains the quote post URL.
$quote_object = $args['activity']['instrument'] ?? $args['activity']['object'];

// Only show embed if we have a valid object (not just a URL string).
if ( is_array( $quote_object ) ) :
	?>
	<div class="embed">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Embed::get_html_for_object( $quote_object );
		?>
	</div>

	<?php if ( site_supports_blocks() && ! is_plugin_active( 'classic-editor/classic-editor.php' ) ) : ?>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?in_reply_to=' . $quote_object['id'] ) ); ?>">
			<?php esc_html_e( 'Reply to the post', 'activitypub' ); ?>
		</a>
	</p>
	<?php endif; ?>
<?php elseif ( ! empty( $args['quoted_url'] ) ) : ?>
	<p>
		<strong><?php esc_html_e( 'Your post:', 'activitypub' ); ?></strong>
		<a href="<?php echo esc_url( $args['quoted_url'] ); ?>"><?php echo esc_html( $args['quoted_url'] ); ?></a>
	</p>
<?php endif; ?>

<?php
/**
 * Fires at the bottom of the new quote emails.
 *
 * @param array $args The template arguments.
 */
do_action( 'activitypub_new_quote_email', $args );

// Load footer.
require __DIR__ . '/parts/footer.php';
