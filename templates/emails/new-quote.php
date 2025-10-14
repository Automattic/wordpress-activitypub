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

$comment_author = esc_html( $args['actor']['webfinger'] );
if ( ! empty( $args['actor']['url'] ) ) {
	$comment_author = sprintf( '<a href="%s">%s</a>', esc_url( $args['actor']['url'] ), esc_html( $args['actor']['webfinger'] ) );
}

/* translators: 1: actor name/link, 2: quoted post URL */
$message = __( 'Looks like one of your posts caught someone&#8217;s attention! %1$s just shared <a href="%2$s">your post</a>. Here&#8217;s what they said:', 'activitypub' );
if ( ! empty( $args['quoted_title'] ) ) {
	/* translators: 1: actor name/link, 2: quoted post URL, 3: quoted post title */
	$message = __( 'Looks like one of your posts caught someone&#8217;s attention! %1$s just shared <a href="%2$s">%3$s</a>. Here&#8217;s what they said:', 'activitypub' );
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

<div class="embed">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo Embed::get_html_for_object( $args['quote_object'] );
	?>
</div>

<?php if ( site_supports_blocks() && ! is_plugin_active( 'classic-editor/classic-editor.php' ) ) : ?>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?in_reply_to=' . rawurlencode( $args['quote_object']['id'] ) ) ); ?>">
			<?php esc_html_e( 'Reply to the post', 'activitypub' ); ?>
		</a>
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
