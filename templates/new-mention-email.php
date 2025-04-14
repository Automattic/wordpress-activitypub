<?php
/**
 * ActivityPub New Mention E-Mail template with styles.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;
use Activitypub\Embed;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

?>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			font-size: 16px;
			line-height: 1.5;
			color: #222;
			background-color: #ffffff;
			margin: 0;
			padding: 0;
		}
		.container {
			max-width: 600px;
			margin: 20px auto;
			padding: 20px;
			background-color: #f9f9f9;
			border-radius: 8px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		}
		h1 {
			font-size: 20px;
			margin-bottom: 16px;
		}
		a.button {
			display: inline-block;
			background-color: #2271b1;
			color: #ffffff !important;
			text-decoration: none;
			padding: 10px 16px;
			border-radius: 4px;
			font-weight: bold;
			margin-top: 10px;
		}
		.footer {
			font-size: 13px;
			color: #777;
			margin-top: 30px;
		}
	</style>

	<div class="container">
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

			printf( esc_html( $message ), '<a href="' . esc_url( $args['activity']['actor'] ) . '">' . esc_html( $args['webfinger'] ) . '</a>' );
			?>
		</p>

		<div class="embed">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Embed::get_html_for_object( $args['activity']['object'] );
			?>
		</div>

		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?in_reply_to=' . $args['activity']['object']['id'] ) ); ?>">
				<?php esc_html_e( 'Reply to the post', 'activitypub' ); ?>
			</a>
		</p>

		<div class="footer">
			<p><?php esc_html_e( 'You are receiving this email because of your ActivityPub plugin settings.', 'activitypub' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=activitypub&tab=settings' ) ); ?>">
					<?php esc_html_e( 'Manage notification settings', 'activitypub' ); ?>
				</a>
			</p>
		</div>
	</div>

<?php
/**
 * Fires at the bottom of the new mention email.
 *
 * @param array $args The template arguments.
 */
do_action( 'activitypub_new_mention_email', $args );
