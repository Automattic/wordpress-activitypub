<?php
/**
 * Server-side rendering of the `activitypub/stats` block.
 *
 * @package Activitypub
 */

use Activitypub\Blocks;
use Activitypub\Collection\Actors;
use Activitypub\Statistics;

if ( is_feed() ) {
	return;
}

/* @var array $attributes Block attributes. */
$attributes = wp_parse_args( $attributes );

$user_id    = Blocks::get_user_id( $attributes['selectedUser'] ?? 'blog' );
$stats_year = (int) ( $attributes['year'] ?? ( (int) \gmdate( 'Y' ) - 1 ) );

// Try stored annual summary first, fall back to live computation.
$summary = Statistics::get_annual_summary( $user_id, $stats_year );

if ( ! $summary ) {
	$summary = Statistics::compile_annual_summary( $user_id, $stats_year );
}

if ( ! $summary || empty( $summary['posts_count'] ) ) {
	if ( \defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		printf(
			'<div class="components-placeholder"><div class="components-placeholder__label">%s</div><div class="components-placeholder__instructions">%s</div></div>',
			\esc_html__( 'Fediverse Stats', 'activitypub' ),
			\sprintf(
				/* translators: %d: The year */
				\esc_html__( 'No stats available for %d. Stats are collected monthly and compiled at the end of each year.', 'activitypub' ),
				(int) $stats_year
			)
		);
	}
	return;
}

// Get comment types for dynamic display.
$comment_types = Statistics::get_comment_types_for_stats();

// Calculate total engagement.
$total_engagement = 0;
foreach ( array_keys( $comment_types ) as $ct_slug ) {
	$total_engagement += $summary[ $ct_slug . '_count' ] ?? 0;
}

// Most active month name.
$most_active_month_name = '';
if ( ! empty( $summary['most_active_month'] ) ) {
	$most_active_month_name = gmdate( 'F', gmmktime( 0, 0, 0, $summary['most_active_month'], 1, $stats_year ) );
}

// Follower growth.
$followers_start      = $summary['followers_start'] ?? 0;
$followers_end        = $summary['followers_end'] ?? 0;
$followers_net_change = $summary['followers_net_change'] ?? ( $followers_end - $followers_start );
$change_sign          = $followers_net_change >= 0 ? '+' : '';

// Get actor webfinger for the card header.
$actor = Actors::get_by_id( $user_id );

if ( \is_wp_error( $actor ) ) {
	// Fall back to direct model instantiation for blog/application actors.
	if ( Actors::BLOG_USER_ID === $user_id ) {
		$actor = new \Activitypub\Model\Blog();
	} elseif ( Actors::APPLICATION_USER_ID === $user_id ) {
		$actor = new \Activitypub\Model\Application();
	}
}

$actor_webfinger = ! \is_wp_error( $actor ) ? $actor->get_webfinger() : '';

// Site name for branding.
$site_name = \get_bloginfo( 'name' );

$block_id   = 'activitypub-stats-' . \wp_unique_id();
$title_text = \sprintf(
	/* translators: %d: The year */
	\__( 'Fediverse Stats %d', 'activitypub' ),
	(int) $stats_year
);

/*
 * Build border styles using WP_Style_Engine for sanitization.
 * Border serialization is skipped in block.json to avoid double
 * rendering in the editor, so we apply it here manually.
 */
$border_result = \wp_style_engine_get_styles( array( 'border' => $attributes['style']['border'] ?? array() ) );
$extra_styles  = $border_result['css'] ?? '';

// Handle preset border color slug (not part of style.border).
if ( ! empty( $attributes['borderColor'] ) ) {
	$preset_color = 'var(--wp--preset--color--' . \sanitize_key( $attributes['borderColor'] ) . ')';
	$extra_styles = 'border-color:' . $preset_color . ';' . $extra_styles;
}

// Resolve the border color for inner elements via CSS variable.
$border_color = '';
if ( ! empty( $attributes['style']['border']['color'] ) ) {
	$border_color = $attributes['style']['border']['color'];
} elseif ( ! empty( $attributes['borderColor'] ) ) {
	$border_color = 'var(--wp--preset--color--' . \sanitize_key( $attributes['borderColor'] ) . ')';
}

if ( $border_color ) {
	$extra_styles .= '--activitypub-stats--border-color:' . \esc_attr( $border_color ) . ';';
}

$wrapper_attrs = array(
	'id'    => $block_id,
	'class' => 'activitypub-stats',
);

$wrapper_html = \get_block_wrapper_attributes( $wrapper_attrs );

// Merge border styles into the existing style attribute.
if ( $extra_styles ) {
	if ( \str_contains( $wrapper_html, 'style="' ) ) {
		$wrapper_html = \str_replace( 'style="', 'style="' . \esc_attr( $extra_styles ), $wrapper_html );
	} else {
		$wrapper_html .= ' style="' . \esc_attr( $extra_styles ) . '"';
	}
}
?>
<div
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput
	echo $wrapper_html;
	?>
	data-year="<?php echo esc_attr( $stats_year ); ?>"
>
			<div class="activitypub-stats__header">
				<h2 class="activitypub-stats__title"><?php echo \esc_html( $title_text ); ?></h2>
				<?php if ( $actor_webfinger ) : ?>
					<p class="activitypub-stats__subtitle"><?php echo \esc_html( '@' . $actor_webfinger ); ?></p>
				<?php endif; ?>
			</div>

			<div class="activitypub-stats__stats">
				<div class="activitypub-stats__stat activitypub-stats__stat--highlight">
					<span class="activitypub-stats__stat-value"><?php echo esc_html( number_format_i18n( $summary['posts_count'] ) ); ?></span>
					<span class="activitypub-stats__stat-label"><?php esc_html_e( 'Posts Federated', 'activitypub' ); ?></span>
				</div>
				<div class="activitypub-stats__stat activitypub-stats__stat--highlight">
					<span class="activitypub-stats__stat-value"><?php echo esc_html( number_format_i18n( $total_engagement ) ); ?></span>
					<span class="activitypub-stats__stat-label"><?php esc_html_e( 'Total Engagements', 'activitypub' ); ?></span>
				</div>
			</div>

			<div class="activitypub-stats__engagement">
				<?php foreach ( $comment_types as $slug => $type_info ) : ?>
					<?php $count = $summary[ $slug . '_count' ] ?? 0; ?>
					<?php if ( $count > 0 ) : ?>
						<div class="activitypub-stats__stat">
							<span class="activitypub-stats__stat-value"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							<span class="activitypub-stats__stat-label"><?php echo esc_html( $type_info['label'] ); ?></span>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<div class="activitypub-stats__details">
				<div class="activitypub-stats__detail">
					<span class="activitypub-stats__detail-label"><?php esc_html_e( 'Follower Growth', 'activitypub' ); ?></span>
					<span class="activitypub-stats__detail-value">
						<?php echo esc_html( $change_sign . number_format_i18n( $followers_net_change ) ); ?>
					</span>
					<span class="activitypub-stats__detail-extra">
						<?php
						printf(
							/* translators: 1: follower count at start of year, 2: follower count at end of year */
							esc_html__( '%1$s → %2$s followers', 'activitypub' ),
							esc_html( number_format_i18n( $followers_start ) ),
							esc_html( number_format_i18n( $followers_end ) )
						);
						?>
					</span>
				</div>

				<?php if ( $most_active_month_name ) : ?>
					<div class="activitypub-stats__detail">
						<span class="activitypub-stats__detail-label"><?php esc_html_e( 'Most Active Month', 'activitypub' ); ?></span>
						<span class="activitypub-stats__detail-value"><?php echo esc_html( $most_active_month_name ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $summary['top_multiplicator'] ) ) : ?>
					<div class="activitypub-stats__detail">
						<span class="activitypub-stats__detail-label"><?php esc_html_e( 'Top Supporter', 'activitypub' ); ?></span>
						<span class="activitypub-stats__detail-value">
							<a href="<?php echo esc_url( $summary['top_multiplicator']['url'] ); ?>"><?php echo esc_html( $summary['top_multiplicator']['name'] ); ?></a>
						</span>
						<span class="activitypub-stats__detail-extra">
							<?php
							printf(
								/* translators: %s: Number of boosts */
								esc_html( _n( '%s boost', '%s boosts', (int) $summary['top_multiplicator']['count'], 'activitypub' ) ),
								esc_html( number_format_i18n( $summary['top_multiplicator']['count'] ) )
							);
							?>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $summary['top_posts'] ) ) : ?>
				<div class="activitypub-stats__top-posts">
					<h3 class="activitypub-stats__section-title"><?php esc_html_e( 'Top Posts', 'activitypub' ); ?></h3>
					<ol>
						<?php foreach ( array_slice( $summary['top_posts'], 0, 5 ) as $top_post ) : ?>
							<li>
								<a href="<?php echo esc_url( $top_post['url'] ); ?>">
									<?php echo esc_html( $top_post['title'] ? $top_post['title'] : __( '(no title)', 'activitypub' ) ); ?>
								</a>
								<span class="activitypub-stats__post-engagement">
									<?php
									printf(
										/* translators: %s: engagement count */
										esc_html__( '%s engagements', 'activitypub' ),
										esc_html( number_format_i18n( $top_post['engagement_count'] ?? 0 ) )
									);
									?>
								</span>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>

			<div class="activitypub-stats__footer">
				<span class="activitypub-stats__branding"><?php echo \esc_html( $site_name ); ?> · <?php \esc_html_e( 'Powered by the ActivityPub plugin', 'activitypub' ); ?></span>
			</div>
</div>
