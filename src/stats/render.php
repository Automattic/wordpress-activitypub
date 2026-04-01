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
$stats_year = (int) ( $attributes['year'] ?? (int) gmdate( 'Y' ) - 1 );

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

// Get actor name for the card header.
$actor      = Actors::get_by_id( $user_id );
$actor_name = ! is_wp_error( $actor ) ? $actor->get_name() : get_bloginfo( 'name' );

// Site URL for branding.
$site_name = get_bloginfo( 'name' );

$display_mode = $attributes['displayMode'] ?? 'card';
$block_id     = 'activitypub-stats-' . wp_unique_id();
$title_text   = \sprintf(
	/* translators: %d: The year */
	\__( 'Fediverse Stats %d', 'activitypub' ),
	(int) $stats_year
);

// Build card color classes and styles from block attributes.
$card_classes = array( 'activitypub-stats__card' );
$card_styles  = array();

if ( ! empty( $attributes['backgroundColor'] ) ) {
	$card_classes[] = 'has-background';
	$card_classes[] = 'has-' . $attributes['backgroundColor'] . '-background-color';
}

if ( ! empty( $attributes['textColor'] ) ) {
	$card_classes[] = 'has-text-color';
	$card_classes[] = 'has-' . $attributes['textColor'] . '-color';
}

if ( ! empty( $attributes['style']['color']['background'] ) ) {
	$card_classes[] = 'has-background';
	$card_styles[]  = 'background-color:' . $attributes['style']['color']['background'];
}

if ( ! empty( $attributes['style']['color']['text'] ) ) {
	$card_classes[] = 'has-text-color';
	$card_styles[]  = 'color:' . $attributes['style']['color']['text'];
}

if ( ! empty( $attributes['gradient'] ) ) {
	$card_classes[] = 'has-background';
	$card_classes[] = 'has-' . $attributes['gradient'] . '-gradient-background';
}

if ( ! empty( $attributes['style']['color']['gradient'] ) ) {
	$card_classes[] = 'has-background';
	$card_styles[]  = 'background:' . $attributes['style']['color']['gradient'];
}

$card_class_attr = implode( ' ', $card_classes );
$card_style_attr = ! empty( $card_styles ) ? ' style="' . esc_attr( implode( ';', $card_styles ) ) . '"' : '';

?>
<div
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput
	echo get_block_wrapper_attributes(
		array(
			'id'    => $block_id,
			'class' => 'activitypub-stats',
		)
	);
	?>
>
	<div class="<?php echo esc_attr( $card_class_attr ); ?>"<?php echo $card_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-year="<?php echo esc_attr( $stats_year ); ?>">
		<div class="activitypub-stats__header">
			<h2 class="activitypub-stats__title">
				<?php
				printf(
					/* translators: %d: The year */
					esc_html__( 'Fediverse Stats %d', 'activitypub' ),
					(int) $stats_year
				);
				?>
			</h2>
			<p class="activitypub-stats__subtitle"><?php echo esc_html( $actor_name ); ?></p>
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
			<span class="activitypub-stats__branding"><?php echo esc_html( $site_name ); ?> · <?php esc_html_e( 'Powered by ActivityPub', 'activitypub' ); ?></span>
		</div>
	</div>

	<?php if ( 'image' === $display_mode ) : ?>
		<?php
		$image_url = \rest_url( ACTIVITYPUB_REST_NAMESPACE . '/stats/image/' . $user_id . '/' . $stats_year );
		?>
		<img
			class="activitypub-stats__image"
			src="<?php echo \esc_url( $image_url ); ?>"
			alt="<?php echo \esc_attr( $title_text ); ?>"
			width="1200"
			height="630"
			loading="lazy"
		/>
	<?php endif; ?>
</div>
