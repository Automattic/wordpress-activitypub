<?php
/**
 * Statistics Dashboard Widget Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Statistics;

use function Activitypub\is_user_type_disabled;
use function Activitypub\user_can_activitypub;

/**
 * Statistics Dashboard Widget Class.
 *
 * Provides dashboard widgets for ActivityPub statistics.
 */
class Statistics_Dashboard {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'wp_dashboard_setup', array( self::class, 'add_dashboard_widgets' ) );
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_styles' ) );
		\add_action( 'wp_ajax_activitypub_get_stats', array( self::class, 'ajax_get_stats' ) );
	}

	/**
	 * Enqueue styles for the dashboard widget.
	 *
	 * @param string $hook The current admin page.
	 */
	public static function enqueue_styles( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}

		\wp_enqueue_style(
			'activitypub-statistics',
			\plugins_url( 'assets/css/activitypub-statistics.css', ACTIVITYPUB_PLUGIN_FILE ),
			array(),
			ACTIVITYPUB_PLUGIN_VERSION
		);

		\wp_enqueue_script(
			'activitypub-statistics',
			\plugins_url( 'assets/js/activitypub-statistics.js', ACTIVITYPUB_PLUGIN_FILE ),
			array( 'jquery' ),
			ACTIVITYPUB_PLUGIN_VERSION,
			true
		);

		// Get available actors for the widget.
		$actors = self::get_available_actors();

		// Get registered comment types for the chart.
		$comment_types = Statistics::get_comment_types_for_stats();

		// Get WordPress admin colors for the chart.
		$chart_colors = self::get_chart_colors();

		\wp_localize_script(
			'activitypub-statistics',
			'activitypubStats',
			array(
				'ajaxUrl'      => \admin_url( 'admin-ajax.php' ),
				'nonce'        => \wp_create_nonce( 'activitypub_stats' ),
				'actors'       => $actors,
				'defaultActor' => ! empty( $actors ) ? $actors[0]['id'] : null,
				'commentTypes' => $comment_types,
				'chartColors'  => $chart_colors,
				'monthNames'   => array(
					\__( 'Jan', 'activitypub' ),
					\__( 'Feb', 'activitypub' ),
					\__( 'Mar', 'activitypub' ),
					\__( 'Apr', 'activitypub' ),
					\__( 'May', 'activitypub' ),
					\__( 'Jun', 'activitypub' ),
					\__( 'Jul', 'activitypub' ),
					\__( 'Aug', 'activitypub' ),
					\__( 'Sep', 'activitypub' ),
					\__( 'Oct', 'activitypub' ),
					\__( 'Nov', 'activitypub' ),
					\__( 'Dec', 'activitypub' ),
				),
				'i18n'         => array(
					'topSupporter'   => \__( 'Top Supporter', 'activitypub' ),
					'topPosts'       => \__( 'Top Posts', 'activitypub' ),
					'engagements'    => \__( 'engagements', 'activitypub' ),
					'noTitle'        => \__( '(no title)', 'activitypub' ),
					'vsLastYear'     => \__( 'vs last year', 'activitypub' ),
					'thisMonth'      => \__( 'This Month', 'activitypub' ),
					'yearlyActivity' => \__( 'Yearly Activity', 'activitypub' ),
					'notEnoughData'  => \__( 'Not enough data yet', 'activitypub' ),
				),
			)
		);
	}

	/**
	 * Get colors for the chart based on WordPress admin color scheme.
	 *
	 * Colors are assigned dynamically to all comment types from the stats system.
	 * No hardcoded type lists - any registered type gets a color automatically.
	 *
	 * @return array Associative array of comment type slugs to hex colors.
	 */
	private static function get_chart_colors() {
		global $_wp_admin_css_colors;

		// Get all comment types from the statistics system.
		$comment_types = Statistics::get_comment_types_for_stats();

		// Build color palette from WordPress admin colors.
		$palette = array( '#d63638', '#00a32a', '#2271b1', '#dba617', '#8c8f94', '#9b59b6', '#1abc9c', '#e74c3c' );

		// Try to get the current admin color scheme.
		$admin_color = \get_user_option( 'admin_color', \get_current_user_id() );

		if ( $admin_color && isset( $_wp_admin_css_colors[ $admin_color ] ) ) {
			$scheme = $_wp_admin_css_colors[ $admin_color ];

			if ( ! empty( $scheme->colors ) && \count( $scheme->colors ) >= 4 ) {
				// Use colors from the admin scheme as the primary palette.
				$palette = \array_merge( $scheme->colors, $palette );
				$palette = \array_unique( $palette );
			}
		}

		// Assign colors to each comment type dynamically.
		$colors = array();
		$index  = 0;

		foreach ( $comment_types as $slug => $type ) {
			$colors[ $slug ] = $palette[ $index % \count( $palette ) ];
			++$index;
		}

		return $colors;
	}

	/**
	 * Get available actors (user/blog) for the current user.
	 *
	 * @return array Array of available actors with id and label.
	 */
	private static function get_available_actors() {
		$actors = array();

		// Check if current user can access their own stats.
		if ( user_can_activitypub( \get_current_user_id() ) && ! is_user_type_disabled( 'user' ) ) {
			$actors[] = array(
				'id'    => \get_current_user_id(),
				'label' => \__( 'Your Stats', 'activitypub' ),
			);
		}

		// Check if blog stats are available.
		if ( ! is_user_type_disabled( 'blog' ) && \current_user_can( 'manage_options' ) ) {
			$actors[] = array(
				'id'    => Actors::BLOG_USER_ID,
				'label' => \__( 'Blog Stats', 'activitypub' ),
			);
		}

		return $actors;
	}

	/**
	 * Add dashboard widgets.
	 */
	public static function add_dashboard_widgets() {
		// Only add widget if user has access to at least one actor type.
		$has_user_access = user_can_activitypub( \get_current_user_id() ) && ! is_user_type_disabled( 'user' );
		$has_blog_access = ! is_user_type_disabled( 'blog' ) && \current_user_can( 'manage_options' );

		if ( ! $has_user_access && ! $has_blog_access ) {
			return;
		}

		\wp_add_dashboard_widget(
			'activitypub_stats',
			\__( 'Fediverse Stats', 'activitypub' ),
			array( self::class, 'render_stats_widget' ),
			null,
			null,
			'normal',
			'high'
		);
	}

	/**
	 * Render the unified stats widget.
	 */
	public static function render_stats_widget() {
		$actors = self::get_available_actors();

		if ( empty( $actors ) ) {
			return;
		}

		$default_actor = $actors[0]['id'];
		$stats         = Statistics::get_current_stats( $default_actor, 'month' );
		$comparison    = Statistics::get_year_comparison( $default_actor );
		$monthly_data  = Statistics::get_yearly_monthly_breakdown( $default_actor );
		$comment_types = Statistics::get_comment_types_for_stats();
		$chart_colors  = self::get_chart_colors();
		?>
		<div class="activitypub-stats-widget" data-user-id="<?php echo \esc_attr( $default_actor ); ?>">
			<div class="activitypub-stats-header">
				<?php if ( \count( $actors ) > 1 ) : ?>
				<select class="activitypub-stats-actor-select">
					<?php foreach ( $actors as $actor ) : ?>
					<option value="<?php echo \esc_attr( $actor['id'] ); ?>"><?php echo \esc_html( $actor['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>
			</div>

			<!-- Comparison Highlights -->
			<div class="activitypub-stats-highlights">
				<h4><?php \esc_html_e( 'This Month', 'activitypub' ); ?></h4>
				<div class="activitypub-highlights-grid">
					<?php self::render_highlight_stat( 'posts', \__( 'Posts', 'activitypub' ), $comparison['posts'] ); ?>
					<?php
					// Render highlights for each registered comment type dynamically.
					foreach ( $comment_types as $slug => $type ) :
						if ( isset( $comparison[ $slug ] ) ) :
							self::render_highlight_stat( $slug, $type['label'], $comparison[ $slug ] );
						endif;
					endforeach;
					?>
					<?php self::render_highlight_stat( 'followers', \__( 'Followers', 'activitypub' ), $comparison['followers'] ); ?>
				</div>
			</div>

			<!-- Yearly Activity Graph -->
			<div class="activitypub-stats-graph">
				<h4><?php \esc_html_e( 'Yearly Activity', 'activitypub' ); ?></h4>
				<div class="activitypub-graph-container">
					<?php echo self::render_line_chart( $monthly_data, $comment_types, $chart_colors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="activitypub-graph-legend">
					<?php foreach ( $comment_types as $slug => $type ) : ?>
					<span class="legend-item" style="--legend-color: <?php echo \esc_attr( $chart_colors[ $slug ] ?? '#8c8f94' ); ?>;">
						<?php echo \esc_html( $type['label'] ); ?>
					</span>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( ! empty( $stats['top_multiplicator'] ) ) : ?>
			<div class="activitypub-stats-multiplicator">
				<h4><?php \esc_html_e( 'Top Supporter', 'activitypub' ); ?></h4>
				<p>
					<a href="<?php echo \esc_url( $stats['top_multiplicator']['url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo \esc_html( $stats['top_multiplicator']['name'] ); ?>
					</a>
					<?php
					printf(
						/* translators: %s: number of boosts */
						\esc_html( _n( '(%s boost)', '(%s boosts)', $stats['top_multiplicator']['count'], 'activitypub' ) ),
						\esc_html( \number_format_i18n( $stats['top_multiplicator']['count'] ) )
					);
					?>
				</p>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $stats['top_posts'] ) ) : ?>
			<div class="activitypub-stats-top-posts">
				<h4><?php \esc_html_e( 'Top Posts', 'activitypub' ); ?></h4>
				<ul>
					<?php foreach ( $stats['top_posts'] as $post ) : ?>
					<li>
						<a href="<?php echo \esc_url( $post['url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo \esc_html( $post['title'] ?: __( '(no title)', 'activitypub' ) ); ?>
						</a>
						<span class="engagement-count">
							<?php
							printf(
								/* translators: %s: engagement count */
								\esc_html__( '%s engagements', 'activitypub' ),
								\esc_html( \number_format_i18n( $post['engagement_count'] ) )
							);
							?>
						</span>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a highlight stat with comparison.
	 *
	 * @param string $key   The stat key.
	 * @param string $label The stat label.
	 * @param array  $data  The stat data with current and change values.
	 */
	private static function render_highlight_stat( $key, $label, $data ) {
		$change_class = '';
		$change_text  = '';

		if ( 0 !== $data['change'] ) {
			$change_class = $data['change'] > 0 ? 'positive' : 'negative';
			$change_text  = $data['change'] > 0 ? '+' . \number_format_i18n( $data['change'] ) : \number_format_i18n( $data['change'] );
		}
		?>
		<div class="activitypub-highlight" data-stat="<?php echo \esc_attr( $key ); ?>">
			<span class="activitypub-highlight-value"><?php echo \esc_html( \number_format_i18n( $data['current'] ) ); ?></span>
			<?php if ( $change_text ) : ?>
			<span class="activitypub-highlight-change <?php echo \esc_attr( $change_class ); ?>">(<?php echo \esc_html( $change_text ); ?>)</span>
			<?php endif; ?>
			<span class="activitypub-highlight-label"><?php echo \esc_html( $label ); ?></span>
		</div>
		<?php
	}

	/**
	 * Render an SVG line chart for monthly data.
	 *
	 * @param array $monthly_data  The monthly data array.
	 * @param array $comment_types The registered comment types.
	 * @param array $chart_colors  The colors for each comment type.
	 *
	 * @return string The SVG markup.
	 */
	private static function render_line_chart( $monthly_data, $comment_types, $chart_colors ) {
		$width      = 400;
		$height     = 120;
		$padding    = 10;
		$chart_w    = $width - ( $padding * 2 );
		$chart_h    = $height - ( $padding * 2 );
		$months     = \array_values( $monthly_data );
		$num_months = \count( $months );

		if ( $num_months < 2 ) {
			return '<div class="activitypub-graph-empty">' . \esc_html__( 'Not enough data yet', 'activitypub' ) . '</div>';
		}

		// Find max value across all comment types for scaling.
		$max_value = 1;
		foreach ( $comment_types as $slug => $type ) {
			$key    = $slug . '_count';
			$values = \array_column( $months, $key );
			if ( ! empty( $values ) ) {
				$max = \max( $values );
				if ( $max > $max_value ) {
					$max_value = $max;
				}
			}
		}

		// Generate points for each comment type line.
		$all_points = array();
		$x_labels   = array();

		foreach ( $comment_types as $slug => $type ) {
			$all_points[ $slug ] = array();
		}

		foreach ( $months as $i => $data ) {
			$x = $padding + ( $i / ( $num_months - 1 ) ) * $chart_w;

			foreach ( $comment_types as $slug => $type ) {
				$key   = $slug . '_count';
				$count = $data[ $key ] ?? 0;
				$y     = $padding + $chart_h - ( ( $count / $max_value ) * $chart_h );

				$all_points[ $slug ][] = \round( $x, 1 ) . ',' . \round( $y, 1 );
			}

			$x_labels[] = array(
				'x'     => $x,
				'label' => \date_i18n( 'M', \strtotime( \gmdate( 'Y' ) . '-' . $data['month'] . '-01' ) ),
			);
		}

		// Build SVG.
		$svg = '<svg class="activitypub-line-chart" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="xMidYMid meet" data-monthly="' . \esc_attr( \wp_json_encode( $months ) ) . '">';

		// Grid lines.
		$svg .= '<g class="grid-lines">';
		for ( $i = 0; $i <= 4; $i++ ) {
			$y    = $padding + ( $i / 4 ) * $chart_h;
			$svg .= '<line x1="' . $padding . '" y1="' . $y . '" x2="' . ( $width - $padding ) . '" y2="' . $y . '" stroke="#e5e7eb" stroke-width="1" />';
		}
		$svg .= '</g>';

		// Area fills (semi-transparent) for each comment type.
		$base_y = $padding + $chart_h;
		foreach ( $comment_types as $slug => $type ) {
			$color     = $chart_colors[ $slug ] ?? '#8c8f94';
			$fill_rgba = self::hex_to_rgba( $color, 0.1 );
			$svg      .= '<polygon class="area-' . \esc_attr( $slug ) . '" points="' . $padding . ',' . $base_y . ' ' . \implode( ' ', $all_points[ $slug ] ) . ' ' . ( $width - $padding ) . ',' . $base_y . '" fill="' . \esc_attr( $fill_rgba ) . '" />';
		}

		// Lines for each comment type.
		foreach ( $comment_types as $slug => $type ) {
			$color = $chart_colors[ $slug ] ?? '#8c8f94';
			$svg  .= '<polyline class="line-' . \esc_attr( $slug ) . '" points="' . \implode( ' ', $all_points[ $slug ] ) . '" fill="none" stroke="' . \esc_attr( $color ) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />';
		}

		// Data points for each comment type.
		$svg .= '<g class="data-points">';
		foreach ( $months as $i => $data ) {
			$x = $padding + ( $i / ( $num_months - 1 ) ) * $chart_w;

			foreach ( $comment_types as $slug => $type ) {
				$key   = $slug . '_count';
				$count = $data[ $key ] ?? 0;
				$y     = $padding + $chart_h - ( ( $count / $max_value ) * $chart_h );
				$color = $chart_colors[ $slug ] ?? '#8c8f94';
				$label = $type['singular'] ?? $type['label'];

				$svg .= '<circle cx="' . \round( $x, 1 ) . '" cy="' . \round( $y, 1 ) . '" r="3" fill="' . \esc_attr( $color ) . '" class="point-' . \esc_attr( $slug ) . '"><title>' . \esc_html( $count ) . ' ' . \esc_attr( strtolower( $label ) ) . '</title></circle>';
			}
		}
		$svg .= '</g>';

		$svg .= '</svg>';

		// Month labels below chart.
		$svg .= '<div class="activitypub-graph-labels">';
		foreach ( $x_labels as $label ) {
			$left = ( $label['x'] / $width ) * 100;
			$svg .= '<span style="left: ' . \round( $left, 1 ) . '%;">' . \esc_html( $label['label'] ) . '</span>';
		}
		$svg .= '</div>';

		return $svg;
	}

	/**
	 * Convert hex color to rgba.
	 *
	 * @param string $hex   The hex color.
	 * @param float  $alpha The alpha value (0-1).
	 *
	 * @return string The rgba color string.
	 */
	private static function hex_to_rgba( $hex, $alpha = 1 ) {
		$hex = \ltrim( $hex, '#' );

		if ( 3 === \strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = \hexdec( \substr( $hex, 0, 2 ) );
		$g = \hexdec( \substr( $hex, 2, 2 ) );
		$b = \hexdec( \substr( $hex, 4, 2 ) );

		return \sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha );
	}

	/**
	 * AJAX handler for getting stats.
	 */
	public static function ajax_get_stats() {
		\check_ajax_referer( 'activitypub_stats', 'nonce' );

		$user_id = isset( $_POST['user_id'] ) ? \intval( $_POST['user_id'] ) : \get_current_user_id();

		// Check permissions.
		if ( Actors::BLOG_USER_ID !== $user_id && \get_current_user_id() !== $user_id ) {
			\wp_send_json_error( array( 'message' => \__( 'Unauthorized', 'activitypub' ) ) );
		}

		if ( Actors::BLOG_USER_ID === $user_id && ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Unauthorized', 'activitypub' ) ) );
		}

		$stats         = Statistics::get_current_stats( $user_id, 'month' );
		$comparison    = Statistics::get_year_comparison( $user_id );
		$monthly_data  = Statistics::get_yearly_monthly_breakdown( $user_id );
		$comment_types = Statistics::get_comment_types_for_stats();

		// Build comparison data dynamically based on registered comment types.
		$comparison_data = array(
			'posts'     => array(
				'current'          => \number_format_i18n( $comparison['posts']['current'] ),
				'change'           => $comparison['posts']['change'],
				'change_formatted' => self::format_change( $comparison['posts']['change'] ),
			),
			'followers' => array(
				'current'          => \number_format_i18n( $comparison['followers']['current'] ),
				'change'           => $comparison['followers']['change'],
				'change_formatted' => self::format_change( $comparison['followers']['change'] ),
			),
		);

		// Add comparison for each registered comment type.
		foreach ( $comment_types as $slug => $type ) {
			if ( isset( $comparison[ $slug ] ) ) {
				$comparison_data[ $slug ] = array(
					'current'          => \number_format_i18n( $comparison[ $slug ]['current'] ),
					'change'           => $comparison[ $slug ]['change'],
					'change_formatted' => self::format_change( $comparison[ $slug ]['change'] ),
				);
			}
		}

		// Format numbers for display.
		$formatted = array(
			'stats'      => array(
				'posts_count'       => \number_format_i18n( $stats['posts_count'] ),
				'followers_total'   => \number_format_i18n( $stats['followers_total'] ),
				'top_posts'         => $stats['top_posts'],
				'top_multiplicator' => $stats['top_multiplicator'],
			),
			'comparison' => $comparison_data,
			'monthly'    => \array_values( $monthly_data ),
		);

		\wp_send_json_success( $formatted );
	}

	/**
	 * Format a change value for display.
	 *
	 * @param int $change The change value.
	 *
	 * @return string The formatted change string.
	 */
	private static function format_change( $change ) {
		if ( 0 === $change ) {
			return '';
		}

		return $change > 0 ? '+' . \number_format_i18n( $change ) : \number_format_i18n( $change );
	}
}
