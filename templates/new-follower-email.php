<?php
/**
 * ActivityPub New Follower E-Mail template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args );
?>
<p>
	<?php
	if ( Actors::BLOG_USER_ID === $args['target'] ) :
		esc_html_e( 'Your blog has a new follower:', 'activitypub' );
	else :
		esc_html_e( 'You have a new follower:', 'activitypub' );
	endif;
	?>
</p>

<table>
	<tr>
		<td style="vertical-align: top">
			<a href="<?php echo esc_url( $args['url'] ); ?>" style="float: left; margin-right: 1em;">
				<?php if ( ! empty( $args['icon']['url'] ) ) : ?>
					<img src="<?php echo esc_url( $args['icon']['url'] ); ?>" alt="<?php echo esc_attr( $args['name'] ); ?>" width="64" height="64">
				<?php endif; ?>
			</a>
		</td>
		<td>
			<a href="<?php echo esc_url( $args['url'] ); ?>">
				<strong><?php echo esc_html( $args['name'] ); ?></strong> (<?php echo esc_html( $args['url'] ); ?>)
			</a>
			<br>
			<?php
			if ( ! empty( $args['summary'] ) ) :
				echo wp_kses_post( nl2br( $args['summary'] ) );
			endif;
			?>
		</td>
	</tr>
</table>

<p>
	<?php
	printf(
		/* translators: %s: URL to followers list. */
		wp_kses( __( 'Visit the <a href="%s">followers list</a> to see all followers.', 'activitypub' ), array( 'a' => array( 'href' => array() ) ) ),
		esc_url( admin_url( $args['admin_url'] ) )
	);
	?>
</p>

<?php

/**
 * Fires at the bottom of the new follower email.
 *
 * @param array $args The actor that followed the blog.
 */
do_action( 'activitypub_new_follower_email', $args );
