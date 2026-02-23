<?php
/**
 * ActivityPub Annual Wrapped E-Mail template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;
use Activitypub\Statistics;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

// Get comment types for dynamic stats display.
$comment_types = Statistics::get_comment_types_for_stats();

// Load header.
require __DIR__ . '/parts/header.php';
?>
<style>
	.stats-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 16px;
		margin: 24px 0;
	}
	.stats-grid .stat {
		flex: 1 1 calc(50% - 8px);
		min-width: 120px;
		background: #f0f0f1;
		border-radius: 8px;
		padding: 16px;
		text-align: center;
	}
	.stats-grid .stat-value {
		display: block;
		font-size: 32px;
		font-weight: 700;
		color: #1d2327;
		line-height: 1.2;
	}
	.stats-grid .stat-label {
		display: block;
		font-size: 14px;
		color: #50575e;
		margin-top: 4px;
	}
	.highlight-box {
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		color: #fff;
		border-radius: 8px;
		padding: 20px;
		margin: 24px 0;
		text-align: center;
	}
	.highlight-box h3 {
		margin: 0 0 8px;
		font-size: 18px;
		font-weight: 600;
	}
	.highlight-box p {
		margin: 0;
		font-size: 14px;
		opacity: 0.9;
	}
	.top-posts {
		margin: 24px 0;
	}
	.top-posts h3 {
		font-size: 16px;
		margin: 0 0 12px;
	}
	.top-posts ol {
		margin: 0;
		padding-left: 20px;
	}
	.top-posts li {
		margin: 8px 0;
	}
	.top-posts a {
		color: #2271b1;
		text-decoration: none;
	}
	.top-posts .engagement {
		font-size: 12px;
		color: #50575e;
	}
	.follower-section {
		background: #f0f0f1;
		border-radius: 8px;
		padding: 16px;
		margin: 24px 0;
	}
	.follower-section h3 {
		margin: 0 0 8px;
		font-size: 16px;
	}
	.follower-change {
		font-size: 24px;
		font-weight: 700;
		color: #00a32a;
	}
	.follower-change.negative {
		color: #d63638;
	}
	.supporter-section {
		background: #fff8e5;
		border-left: 4px solid #dba617;
		padding: 16px;
		margin: 24px 0;
	}
	.supporter-section h3 {
		margin: 0 0 8px;
		font-size: 16px;
		color: #1d2327;
	}
	.supporter-section p {
		margin: 0;
		font-size: 14px;
	}
</style>

<h1>
	<?php
	if ( Actors::BLOG_USER_ID === $args['user_id'] ) :
		printf(
			/* translators: %d: Year */
			esc_html__( 'Your Blog&#8217;s %d Fediverse Wrapped', 'activitypub' ),
			(int) $args['year']
		);
	else :
		printf(
			/* translators: %d: Year */
			esc_html__( 'Your %d Fediverse Wrapped', 'activitypub' ),
			(int) $args['year']
		);
	endif;
	?>
</h1>

<p>
	<?php
	if ( Actors::BLOG_USER_ID === $args['user_id'] ) :
		esc_html_e( 'Here&#8217;s a look back at how your blog connected with the Fediverse this year.', 'activitypub' );
	else :
		esc_html_e( 'Here&#8217;s a look back at how you connected with the Fediverse this year.', 'activitypub' );
	endif;
	?>
</p>

<div class="stats-grid">
	<div class="stat">
		<span class="stat-value"><?php echo esc_html( number_format_i18n( $args['posts_count'] ?? 0 ) ); ?></span>
		<span class="stat-label"><?php esc_html_e( 'Posts Federated', 'activitypub' ); ?></span>
	</div>
	<?php foreach ( $comment_types as $slug => $type_info ) : ?>
	<div class="stat">
		<span class="stat-value"><?php echo esc_html( number_format_i18n( $args[ $slug . '_count' ] ?? 0 ) ); ?></span>
		<span class="stat-label"><?php echo esc_html( $type_info['label'] ); ?></span>
	</div>
	<?php endforeach; ?>
</div>

<?php if ( ! empty( $args['most_active_month_name'] ) ) : ?>
<div class="highlight-box">
	<h3><?php esc_html_e( 'Most Active Month', 'activitypub' ); ?></h3>
	<p><?php echo esc_html( $args['most_active_month_name'] ); ?></p>
</div>
<?php endif; ?>

<div class="follower-section">
	<h3><?php esc_html_e( 'Follower Growth', 'activitypub' ); ?></h3>
	<?php
	$net_change   = $args['followers_net_change'] ?? 0;
	$change_class = $net_change >= 0 ? '' : 'negative';
	$change_sign  = $net_change >= 0 ? '+' : '';
	?>
	<p class="follower-change <?php echo esc_attr( $change_class ); ?>">
		<?php echo esc_html( $change_sign . number_format_i18n( $net_change ) ); ?>
	</p>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: 1: followers at start, 2: followers at end */
				__( 'You started the year with %1$s followers and ended with %2$s.', 'activitypub' ),
				'<strong>' . esc_html( number_format_i18n( $args['followers_start'] ?? 0 ) ) . '</strong>',
				'<strong>' . esc_html( number_format_i18n( $args['followers_end'] ?? 0 ) ) . '</strong>'
			),
			array( 'strong' => array() )
		);
		?>
	</p>
</div>

<?php if ( ! empty( $args['top_multiplicator'] ) && ! empty( $args['top_multiplicator']['name'] ) ) : ?>
<div class="supporter-section">
	<h3><?php esc_html_e( 'Your Biggest Supporter', 'activitypub' ); ?></h3>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: 1: supporter name, 2: number of boosts */
				__( '%1$s boosted your content %2$s times this year!', 'activitypub' ),
				'<strong><a href="' . esc_url( $args['top_multiplicator']['url'] ?? '' ) . '">' . esc_html( $args['top_multiplicator']['name'] ) . '</a></strong>',
				'<strong>' . esc_html( number_format_i18n( $args['top_multiplicator']['count'] ?? 0 ) ) . '</strong>'
			),
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		);
		?>
	</p>
</div>
<?php endif; ?>

<p>
	<?php esc_html_e( 'Thank you for being part of the Fediverse! Here&#8217;s to another great year of decentralized social networking.', 'activitypub' ); ?>
</p>

<?php
/**
 * Fires at the bottom of the annual wrapped email.
 *
 * @param array $args The annual summary data.
 */
do_action( 'activitypub_annual_wrapped_email', $args );

// Load footer.
require __DIR__ . '/parts/footer.php';
