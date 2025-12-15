<?php
/**
 * ActivityPub Fediverse Wrapped Shareable Card Template.
 *
 * This template renders a standalone HTML card for sharing.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;
use Activitypub\Statistics;

/* @var array $args Template arguments. */
$args = wp_parse_args(
	$args ?? array(),
	array(
		'year'                 => gmdate( 'Y' ),
		'user_id'              => 0,
		'site_name'            => get_bloginfo( 'name' ),
		'posts_count'          => 0,
		'followers_total'      => 0,
		'followers_net_change' => 0,
		'most_active_month'    => null,
		'top_multiplicator'    => null,
	)
);

// Get comment types for dynamic stats display.
$comment_types = Statistics::get_comment_types_for_stats();

$is_blog    = Actors::BLOG_USER_ID === $args['user_id'];
$card_title = $is_blog ? $args['site_name'] : get_the_author_meta( 'display_name', $args['user_id'] );

// Get theme.json colors if available.
$theme_colors = array(
	'primary'    => '#667eea',
	'secondary'  => '#764ba2',
	'background' => '#ffffff',
	'text'       => '#1d2327',
	'text_muted' => '#50575e',
);

// Try to get colors from theme.json.
if ( function_exists( 'wp_get_global_settings' ) ) {
	$global_settings = wp_get_global_settings();

	// Get color palette.
	if ( ! empty( $global_settings['color']['palette']['theme'] ) ) {
		$palette = $global_settings['color']['palette']['theme'];

		foreach ( $palette as $color ) {
			$slug = $color['slug'] ?? '';
			$hex  = $color['color'] ?? '';

			if ( ! $hex ) {
				continue;
			}

			// Map common theme.json color slugs to our colors.
			switch ( $slug ) {
				case 'primary':
				case 'accent':
				case 'brand':
					$theme_colors['primary'] = $hex;
					break;
				case 'secondary':
				case 'accent-2':
					$theme_colors['secondary'] = $hex;
					break;
				case 'background':
				case 'base':
					$theme_colors['background'] = $hex;
					break;
				case 'foreground':
				case 'contrast':
				case 'text':
					$theme_colors['text'] = $hex;
					break;
			}
		}
	}

	// If no secondary color found, derive from primary.
	if ( '#764ba2' === $theme_colors['secondary'] && '#667eea' !== $theme_colors['primary'] ) {
		$theme_colors['secondary'] = $theme_colors['primary'];
	}
}

// CSS custom properties for the theme colors.
$css_vars = sprintf(
	'--ap-primary: %s; --ap-secondary: %s; --ap-background: %s; --ap-text: %s; --ap-text-muted: %s;',
	esc_attr( $theme_colors['primary'] ),
	esc_attr( $theme_colors['secondary'] ),
	esc_attr( $theme_colors['background'] ),
	esc_attr( $theme_colors['text'] ),
	esc_attr( $theme_colors['text_muted'] )
);
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=600">
	<title><?php echo esc_html( sprintf( '%s - Fediverse Wrapped %d', $card_title, $args['year'] ) ); ?></title>
	<style>
		:root {
			<?php echo $css_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		}
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
			background: linear-gradient(135deg, var(--ap-primary) 0%, var(--ap-secondary) 100%);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}
		.card {
			width: 600px;
			background: var(--ap-background);
			border-radius: 24px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
			overflow: hidden;
		}
		.card-header {
			background: linear-gradient(135deg, var(--ap-primary) 0%, var(--ap-secondary) 100%);
			color: #fff;
			padding: 40px;
			text-align: center;
		}
		.card-header h1 {
			font-size: 28px;
			font-weight: 700;
			margin-bottom: 8px;
		}
		.card-header .subtitle {
			font-size: 16px;
			opacity: 0.9;
		}
		.card-header .year {
			font-size: 64px;
			font-weight: 800;
			margin: 20px 0;
			letter-spacing: -2px;
		}
		.stats-section {
			padding: 40px;
		}
		.stats-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 24px;
			margin-bottom: 32px;
		}
		.stat {
			text-align: center;
			padding: 20px;
			background: #f8f9fa;
			border-radius: 16px;
		}
		.stat-value {
			font-size: 48px;
			font-weight: 800;
			color: var(--ap-text);
			line-height: 1;
		}
		.stat-label {
			font-size: 14px;
			color: var(--ap-text-muted);
			margin-top: 8px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		.highlight {
			background: linear-gradient(135deg, var(--ap-primary) 0%, var(--ap-secondary) 100%);
			color: #fff;
			padding: 24px;
			border-radius: 16px;
			text-align: center;
			margin-bottom: 24px;
		}
		.highlight-label {
			font-size: 12px;
			text-transform: uppercase;
			letter-spacing: 1px;
			opacity: 0.8;
			margin-bottom: 8px;
		}
		.highlight-value {
			font-size: 24px;
			font-weight: 700;
		}
		.follower-growth {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 16px;
			padding: 20px;
			background: #f0fdf4;
			border-radius: 16px;
			margin-bottom: 24px;
		}
		.follower-growth.negative {
			background: #fef2f2;
		}
		.growth-value {
			font-size: 36px;
			font-weight: 800;
			color: #16a34a;
		}
		.follower-growth.negative .growth-value {
			color: #dc2626;
		}
		.growth-label {
			font-size: 14px;
			color: var(--ap-text-muted);
		}
		.supporter {
			text-align: center;
			padding: 20px;
			background: #fffbeb;
			border-radius: 16px;
		}
		.supporter-label {
			font-size: 12px;
			text-transform: uppercase;
			letter-spacing: 1px;
			color: #92400e;
			margin-bottom: 8px;
		}
		.supporter-name {
			font-size: 18px;
			font-weight: 600;
			color: var(--ap-text);
		}
		.supporter-count {
			font-size: 14px;
			color: var(--ap-text-muted);
			margin-top: 4px;
		}
		.card-footer {
			padding: 24px 40px;
			background: #f8f9fa;
			text-align: center;
			border-top: 1px solid #e5e7eb;
		}
		.card-footer .brand {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			font-size: 14px;
			color: var(--ap-text-muted);
		}
		.card-footer .brand svg {
			width: 20px;
			height: 20px;
		}
	</style>
</head>
<body>
	<div class="card">
		<div class="card-header">
			<h1><?php echo esc_html( $card_title ); ?></h1>
			<p class="subtitle"><?php esc_html_e( 'Fediverse Wrapped', 'activitypub' ); ?></p>
			<p class="year"><?php echo esc_html( $args['year'] ); ?></p>
		</div>

		<div class="stats-section">
			<div class="stats-grid">
				<div class="stat">
					<div class="stat-value"><?php echo esc_html( number_format_i18n( $args['posts_count'] ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Posts', 'activitypub' ); ?></div>
				</div>
				<?php foreach ( $comment_types as $slug => $type_info ) : ?>
				<div class="stat">
					<div class="stat-value"><?php echo esc_html( number_format_i18n( $args[ $slug . '_count' ] ?? 0 ) ); ?></div>
					<div class="stat-label"><?php echo esc_html( $type_info['label'] ); ?></div>
				</div>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $args['most_active_month'] ) ) : ?>
			<div class="highlight">
				<div class="highlight-label"><?php esc_html_e( 'Most Active Month', 'activitypub' ); ?></div>
				<div class="highlight-value"><?php echo esc_html( date_i18n( 'F', strtotime( sprintf( '%d-%02d-01', $args['year'], $args['most_active_month'] ) ) ) ); ?></div>
			</div>
			<?php endif; ?>

			<?php
			$net_change   = $args['followers_net_change'];
			$change_class = $net_change >= 0 ? '' : 'negative';
			$change_sign  = $net_change >= 0 ? '+' : '';
			?>
			<div class="follower-growth <?php echo esc_attr( $change_class ); ?>">
				<div class="growth-value"><?php echo esc_html( $change_sign . number_format_i18n( $net_change ) ); ?></div>
				<div class="growth-label">
					<?php esc_html_e( 'Follower Growth', 'activitypub' ); ?><br>
					<?php
					printf(
						/* translators: %s: total followers */
						esc_html__( 'Now at %s followers', 'activitypub' ),
						esc_html( number_format_i18n( $args['followers_total'] ) )
					);
					?>
				</div>
			</div>

			<?php if ( ! empty( $args['top_multiplicator'] ) && ! empty( $args['top_multiplicator']['name'] ) ) : ?>
			<div class="supporter">
				<div class="supporter-label"><?php esc_html_e( 'Top Supporter', 'activitypub' ); ?></div>
				<div class="supporter-name"><?php echo esc_html( $args['top_multiplicator']['name'] ); ?></div>
				<div class="supporter-count">
					<?php
					printf(
						/* translators: %s: number of boosts */
						esc_html( _n( '%s boost', '%s boosts', $args['top_multiplicator']['count'], 'activitypub' ) ),
						esc_html( number_format_i18n( $args['top_multiplicator']['count'] ) )
					);
					?>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<div class="card-footer">
			<div class="brand">
				<svg viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
				</svg>
				<?php esc_html_e( 'Powered by ActivityPub', 'activitypub' ); ?>
			</div>
		</div>
	</div>
</body>
</html>
