<p>
	<?php
	echo __( 'You have a new follower:', 'friends' );
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
			if ( ! empty( $actor['summary'] ) ) {
				echo wp_kses_post( nl2br( $actor['summary'] ) );
			}
			?>
		</td>
	</tr>
</table>

<?php

/**
 * { item_description }
 */
do_action( 'activitypub_new_follower_email', $actor );
