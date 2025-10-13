<?php
/**
 * ActivityPub New Quote E-Mail template with styles.
 *
 * @package Activitypub
 */

use Activitypub\Embed;

use function Activitypub\site_supports_blocks;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

// For QuoteRequest activities, the instrument contains the quote post URL.
$quote_object = $args['activity']['instrument'] ?? $args['activity']['object'];

$comment_author = esc_html( $args['actor']['webfinger'] );
if ( ! empty( $args['actor']['url'] ) ) {
	$comment_author = sprintf( '<a href="%s">%s</a>', esc_url( $args['actor']['url'] ), esc_html( $args['actor']['webfinger'] ) );
}

/* translators: %s: The name of the person who quoted the post. */
$message = __( 'Looks like one of your posts caught someone&#8217;s attention! %s just shared one of your posts.', 'activitypub' );

// Determine the message based on available data.
if ( ! empty( $args['quoted_url'] ) ) {
	if ( ! empty( $args['quoted_title'] ) ) {
		/* translators: 1: actor name/link, 2: quoted post URL, 3: quoted post title */
		$message = __( 'Looks like one of your posts caught someone&#8217;s attention! %1$s just shared <a href="%2$s">%3$s</a>.', 'activitypub' );
	} else {
		/* translators: 1: actor name/link, 2: quoted post URL */
		$message = __( 'Looks like one of your posts caught someone&#8217;s attention! %1$s just shared <a href="%2$s">your post</a>.', 'activitypub' );
	}
}

// Load header.
require __DIR__ . '/parts/header.php';
?>

<h1><?php esc_html_e( 'Your post was quoted!', 'activitypub' ); ?></h1>

<p>
	<?php
	printf(
		wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ),
		wp_kses( $comment_author, array( 'a' => array( 'href' => array() ) ) ),
		esc_url( $args['quoted_url'] ),
		esc_html( $args['quoted_title'] )
	);
	?>
</p>

<?php

// Only show embed if we have a valid object (not just a URL string).
if ( is_array( $quote_object ) ) :
	?>
	<p>
		<strong><?php esc_html_e( 'Here&#8217;s what they said:', 'activitypub' ); ?></strong>
	</p>
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
