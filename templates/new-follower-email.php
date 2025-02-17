<?php
/**
 * ActivityPub New Follower E-Mail template.
 *
 * @package Activitypub
 */

if ( ! isset( $actor ) ) {
	$actor = array();
	return;
}

?>
<p>
	<?php
	esc_html_e( 'You have a new follower:', 'activitypub' );
	?>
</p>

<table>
	<tr>
		<td style="vertical-align: top">
			<a href="<?php echo esc_url( $actor['url'] ); ?>" style="float: left; margin-right: 1em;">
				<?php if ( ! empty( $actor['icon']['url'] ) ) : ?>
				<img src="<?php echo esc_url( $actor['icon']['url'] ); ?>" alt="<?php echo esc_attr( $actor['name'] ); ?>" width="64" height="64">
				<?php endif; ?>
			</a>
		</td>
		<td>
			<a href="<?php echo esc_url( $actor['url'] ); ?>">
				<strong><?php echo esc_html( $actor['name'] ); ?></strong> (<?php echo esc_html( $actor['url'] ); ?>)
			</a>
			<br>
			<?php
			if ( ! empty( $actor['summary'] ) ) :
				echo wp_kses_post( nl2br( $actor['summary'] ) );
			endif;
			?>
		</td>
	</tr>
</table>

<?php

/**
 * Fires at the bottom of the new follower email.
 *
 * @param array $actor The actor that followed the blog.
 */
do_action( 'activitypub_new_follower_email', $actor );
