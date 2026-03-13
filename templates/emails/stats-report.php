<?php
/**
 * ActivityPub Stats Report E-Mail template.
 *
 * Used for both monthly and annual reports.
 * All texts are passed via $args to keep the template logic-free.
 *
 * Expected $args keys:
 * - title          (string) The email heading.
 * - intro          (string) The intro paragraph.
 * - closing        (string) The closing paragraph.
 * - supporter_text (string) The top supporter text (HTML allowed).
 * - posts_count    (int)    Number of federated posts.
 * - top_posts      (array)  Top posts with title, url, engagement_count.
 * - top_multiplicator (array|null) Top supporter data.
 * - followers_count   (int) New followers gained.
 * - followers_total   (int) Total follower count.
 * - followers_start   (int) Followers at period start (annual only).
 * - followers_end     (int) Followers at period end (annual only).
 * - followers_text    (string) The follower detail text (HTML allowed).
 * - most_active_month_name (string) Most active month (annual only).
 * - {type}_count   (int)    Per-engagement-type counts.
 *
 * @package Activitypub
 */

use Activitypub\Statistics;

/* @var array $args Template arguments. */
$args = wp_parse_args(
	$args ?? array(),
	array(
		'title'                  => '',
		'intro'                  => '',
		'closing'                => '',
		'posts_count'            => 0,
		'followers_count'        => 0,
		'followers_total'        => 0,
		'followers_net_change'   => null,
		'followers_text'         => '',
		'most_active_month_name' => '',
		'top_posts'              => array(),
		'top_multiplicator'      => null,
		'supporter_text'         => '',
		'user_id'                => 0,
	)
);

// Get comment types for dynamic stats display.
$comment_types = Statistics::get_comment_types_for_stats();

// Load header.
require __DIR__ . '/parts/header.php';
?>
<style>
	.stats-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 12px;
		margin: 20px 0;
	}
	.stats-grid .stat {
		flex: 1 1 calc(50% - 6px);
		min-width: 100px;
		background: #fff;
		border-radius: 8px;
		padding: 14px;
		text-align: center;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}
	.stats-grid .stat-value {
		display: block;
		font-size: 28px;
		font-weight: 700;
		color: #1d2327;
		line-height: 1.2;
	}
	.stats-grid .stat-label {
		display: block;
		font-size: 13px;
		color: #50575e;
		margin-top: 4px;
	}
	.info-box {
		background: #fff;
		border-radius: 8px;
		padding: 16px;
		margin: 20px 0;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}
	.info-box h3 {
		margin: 0 0 8px;
		font-size: 16px;
		color: #1d2327;
	}
	.info-box p {
		margin: 0;
		font-size: 14px;
		color: #50575e;
	}
	.follower-change {
		font-size: 24px;
		font-weight: 700;
		color: #00a32a;
		margin: 4px 0 8px;
	}
	.follower-change.negative {
		color: #d63638;
	}
	.top-posts ol {
		margin: 8px 0 0;
		padding-left: 20px;
	}
	.top-posts li {
		margin: 6px 0;
		font-size: 14px;
	}
	.top-posts a {
		color: #2271b1;
		text-decoration: none;
	}
	.top-posts .engagement {
		font-size: 12px;
		color: #50575e;
	}
</style>

<h1><?php echo esc_html( $args['title'] ); ?></h1>

<p><?php echo esc_html( $args['intro'] ); ?></p>

<div class="stats-grid">
	<div class="stat">
		<span class="stat-value"><?php echo esc_html( number_format_i18n( $args['posts_count'] ) ); ?></span>
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
<div class="info-box">
	<h3><?php esc_html_e( 'Most Active Month', 'activitypub' ); ?></h3>
	<p><?php echo esc_html( $args['most_active_month_name'] ); ?></p>
</div>
<?php endif; ?>

<?php
$net_change = $args['followers_net_change'] ?? $args['followers_count'] ?? null;
if ( null !== $net_change ) :
	$change_class = $net_change >= 0 ? '' : 'negative';
	$change_sign  = $net_change >= 0 ? '+' : '';
	?>
<div class="info-box">
	<h3><?php esc_html_e( 'Follower Growth', 'activitypub' ); ?></h3>
	<p class="follower-change <?php echo esc_attr( $change_class ); ?>">
		<?php echo esc_html( $change_sign . number_format_i18n( $net_change ) ); ?>
	</p>
	<?php if ( ! empty( $args['followers_text'] ) ) : ?>
	<p><?php echo wp_kses( $args['followers_text'], array( 'strong' => array() ) ); ?></p>
	<?php endif; ?>
</div>
<?php endif; ?>

<?php if ( ! empty( $args['top_multiplicator'] ) && ! empty( $args['supporter_text'] ) ) : ?>
<div class="info-box">
	<h3><?php esc_html_e( 'Top Supporter', 'activitypub' ); ?></h3>
	<p>
		<?php
		echo wp_kses(
			$args['supporter_text'],
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		);
		?>
	</p>
</div>
<?php endif; ?>

<?php if ( ! empty( $args['top_posts'] ) ) : ?>
<div class="info-box top-posts">
	<h3><?php esc_html_e( 'Top Posts', 'activitypub' ); ?></h3>
	<ol>
		<?php foreach ( $args['top_posts'] as $top_post ) : ?>
		<li>
			<a href="<?php echo esc_url( $top_post['url'] ); ?>"><?php echo esc_html( $top_post['title'] ?: __( '(no title)', 'activitypub' ) ); ?></a>
			<span class="engagement">
				<?php
				printf(
					/* translators: %s: engagement count */
					esc_html__( '(%s engagements)', 'activitypub' ),
					esc_html( number_format_i18n( $top_post['engagement_count'] ?? 0 ) )
				);
				?>
			</span>
		</li>
		<?php endforeach; ?>
	</ol>
</div>
<?php endif; ?>

<p><?php echo esc_html( $args['closing'] ?? '' ); ?></p>

<?php
/**
 * Fires at the bottom of the stats report email.
 *
 * @param array $args The stats data.
 */
do_action( 'activitypub_stats_report_email', $args );

// Load footer.
require __DIR__ . '/parts/footer.php';
