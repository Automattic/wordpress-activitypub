<?php
/**
 * ActivityPub New Follower E-Mail template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

// Load header.
require __DIR__ . '/parts/header.php';
?>
<style>
	.card {
		background: #fff;
		border-radius: 8px;
		overflow: hidden;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}
	.card-header {
		width: 100%;
		height: 120px;
		background-color: #ccc;
		background-size: cover;
		background-position: center;
	}
	.card-body {
		display: flex;
		gap: 16px;
		align-items: flex-start;
		padding: 16px;
	}
	.card-body img {
		border-radius: 50%;
		width: 64px;
		height: 64px;
		flex-shrink: 0;
	}
	.card-content {
		flex: 1;
	}
	.card-content h2 {
		font-size: 18px;
		margin: 0 0 4px;
	}
	.card-content p {
		margin: 0 0 8px;
	}
</style>
<h1>
	<?php
	if ( Actors::BLOG_USER_ID === $args['user_id'] ) :
		esc_html_e( 'Your blog has a new follower!', 'activitypub' );
	else :
		esc_html_e( 'You have a new follower!', 'activitypub' );
	endif;
	?>
</h1>

<p>
	<?php
	if ( Actors::BLOG_USER_ID === $args['user_id'] ) :
		/* translators: %s: The name of the person who mentioned the blog. */
		$message = __( 'Meet your blog&#8217;s newest follower, %s. Here&#8217;s a quick look at their profile:', 'activitypub' );
	else :
		/* translators: %s: The name of the person who mentioned the user. */
		$message = __( 'Meet your newest follower, %s. Here&#8217;s a quick look at their profile:', 'activitypub' );
	endif;

	printf( esc_html( $message ), '<a href="' . esc_url( $args['url'] ) . '">' . esc_html( $args['webfinger'] ) . '</a>' );
	?>
</p>

<div class="card">
	<?php if ( ! empty( $args['image']['url'] ) ) : ?>
		<div class="card-header" style="background-image: url('<?php echo esc_url( $args['image']['url'] ); ?>');"></div>
	<?php endif; ?>

	<div class="card-body">
		<?php if ( ! empty( $args['icon']['url'] ) ) : ?>
			<img src="<?php echo esc_url( $args['icon']['url'] ); ?>" alt="<?php echo esc_attr( $args['name'] ); ?>">
		<?php endif; ?>
		<div class="card-content">
			<h2><?php echo esc_html( $args['name'] ); ?> <small style="font-size: 14px; color: #666;"><?php echo esc_html( $args['webfinger'] ); ?></small></h2>

			<?php if ( ! empty( $args['summary'] ) ) : ?>
				<p><?php echo wp_kses_post( nl2br( $args['summary'] ) ); ?></p>
			<?php endif; ?>

			<?php if ( isset( $args['stats']['outbox'] ) || isset( $args['stats']['followers'] ) || isset( $args['stats']['following'] ) ) : ?>
				<div class="card-stats" style="display: flex; gap: 16px; font-size: 14px; color: #666; margin-top: 16px;">
					<?php if ( null !== $args['stats']['outbox'] ) : ?>
						<div>
							<?php
							printf(
								/* translators: %s: Number of posts */
								esc_html( _n( '%s post', '%s posts', (int) $args['stats']['outbox'], 'activitypub' ) ),
								'<strong>' . esc_html( number_format_i18n( $args['stats']['outbox'] ) ) . '</strong>'
							);
							?>
						</div>
					<?php endif; ?>
					<?php if ( null !== $args['stats']['followers'] ) : ?>
						<div>
							<?php
							printf(
								/* translators: %s: Number of followers */
								esc_html( _n( '%s follower', '%s followers', (int) $args['stats']['followers'], 'activitypub' ) ),
								'<strong>' . esc_html( number_format_i18n( $args['stats']['followers'] ) ) . '</strong>'
							);
							?>
						</div>
					<?php endif; ?>
					<?php if ( null !== $args['stats']['following'] ) : ?>
						<div>
							<?php
							printf(
								/* translators: %s: Number of following */
								esc_html( _n( '%s following', '%s following', (int) $args['stats']['following'], 'activitypub' ) ),
								'<strong>' . esc_html( number_format_i18n( $args['stats']['following'] ) ) . '</strong>'
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<p>
	<a class="button" href="<?php echo esc_url( $args['url'] ); ?>">
		<?php esc_html_e( 'View Profile', 'activitypub' ); ?>
	</a>
</p>

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

// Load footer.
require __DIR__ . '/parts/footer.php';
