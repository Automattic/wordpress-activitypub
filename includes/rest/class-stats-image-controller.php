<?php
/**
 * Stats_Image_Controller file.
 *
 * Generates a shareable PNG image of ActivityPub statistics.
 *
 * @package Activitypub
 * @since unreleased
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Statistics;

/**
 * REST controller that renders stats as a PNG image.
 *
 * Endpoint: /activitypub/v1/stats/image/<user_id>/<year>
 * Returns a 1200×630 PNG suitable for Open Graph / social media cards.
 */
class Stats_Image_Controller extends \WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'stats/image';

	/**
	 * Image width in pixels.
	 *
	 * @var int
	 */
	const WIDTH = 1200;

	/**
	 * Image height in pixels.
	 *
	 * @var int
	 */
	const HEIGHT = 630;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user_id>[\d]+)/(?P<year>[\d]{4})',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'user_id' => array(
							'description'       => \__( 'The user ID to generate the stats image for.', 'activitypub' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'year'    => array(
							'description'       => \__( 'The year to display stats for.', 'activitypub' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Generate and return the stats image.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response with PNG data or error.
	 */
	public function get_item( $request ) {
		if ( ! \function_exists( 'imagecreatetruecolor' ) ) {
			return new \WP_Error(
				'gd_not_available',
				\__( 'GD library is not available.', 'activitypub' ),
				array( 'status' => 501 )
			);
		}

		$user_id = (int) $request->get_param( 'user_id' );
		$year    = (int) $request->get_param( 'year' );

		$summary = Statistics::get_annual_summary( $user_id, $year );

		if ( ! $summary ) {
			$summary = Statistics::compile_annual_summary( $user_id, $year );
		}

		if ( ! $summary || empty( $summary['posts_count'] ) ) {
			return new \WP_Error(
				'no_stats',
				\__( 'No statistics available for this period.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$actor      = Actors::get_by_id( $user_id );
		$actor_name = ! \is_wp_error( $actor ) ? $actor->get_name() : \get_bloginfo( 'name' );
		$site_name  = \get_bloginfo( 'name' );

		$png_data = $this->render_image( $summary, $actor_name, $site_name, $year );

		if ( \is_wp_error( $png_data ) ) {
			return $png_data;
		}

		$response = new \WP_REST_Response( null, 200 );
		$response->set_headers(
			array(
				'Content-Type'   => 'image/png',
				'Content-Length' => strlen( $png_data ),
				'Cache-Control'  => 'public, max-age=86400',
			)
		);

		// Output the image directly and exit, since WP REST API can't stream binary.
		header( 'Content-Type: image/png' );
		header( 'Content-Length: ' . strlen( $png_data ) );
		header( 'Cache-Control: public, max-age=86400' );
		echo $png_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Render the stats image as PNG.
	 *
	 * @param array  $summary    The annual stats summary.
	 * @param string $actor_name The actor display name.
	 * @param string $site_name  The site name.
	 * @param int    $year       The year.
	 *
	 * @return string|\WP_Error PNG binary data or error.
	 */
	private function render_image( $summary, $actor_name, $site_name, $year ) {
		$width  = self::WIDTH;
		$height = self::HEIGHT;

		$image = \imagecreatetruecolor( $width, $height );

		if ( ! $image ) {
			return new \WP_Error(
				'image_create_failed',
				\__( 'Failed to create image.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		// Enable anti-aliasing.
		\imageantialias( $image, true );

		// Colors.
		$bg     = \imagecolorallocate( $image, 255, 255, 255 );
		$fg     = \imagecolorallocate( $image, 29, 35, 39 );
		$muted  = \imagecolorallocate( $image, 120, 120, 120 );
		$subtle = \imagecolorallocate( $image, 170, 170, 170 );
		$border = \imagecolorallocate( $image, 230, 230, 230 );

		\imagefill( $image, 0, 0, $bg );

		// Comment types for engagement breakdown.
		$comment_types    = Statistics::get_comment_types_for_stats();
		$total_engagement = 0;
		foreach ( \array_keys( $comment_types ) as $slug ) {
			$total_engagement += $summary[ $slug . '_count' ] ?? 0;
		}

		// Title.
		$cur_y = 60;
		$title = \sprintf(
			/* translators: %d: The year */
			\__( 'Fediverse Stats %d', 'activitypub' ),
			$year
		);
		$this->draw_text_centered( $image, $title, $cur_y, 5, $fg );
		$cur_y += 40;

		// Subtitle (actor name).
		$this->draw_text_centered( $image, $actor_name, $cur_y, 3, $muted );
		$cur_y += 50;

		// Highlight stats: Posts & Engagements.
		$box_gap   = 20;
		$box_width = ( $width - 80 - $box_gap ) / 2;

		$this->draw_stat_box(
			$image,
			40,
			$cur_y,
			$box_width,
			90,
			\number_format_i18n( $summary['posts_count'] ),
			\__( 'Posts Federated', 'activitypub' ),
			$fg,
			$muted,
			$border
		);

		$this->draw_stat_box(
			$image,
			40 + $box_width + $box_gap,
			$cur_y,
			$box_width,
			90,
			\number_format_i18n( $total_engagement ),
			\__( 'Total Engagements', 'activitypub' ),
			$fg,
			$muted,
			$border
		);
		$cur_y += 110;

		// Engagement breakdown.
		$engagement_items = array();
		foreach ( $comment_types as $slug => $type_info ) {
			$count = $summary[ $slug . '_count' ] ?? 0;
			if ( $count > 0 ) {
				$engagement_items[] = array(
					'value' => \number_format_i18n( $count ),
					'label' => $type_info['label'],
				);
			}
		}

		if ( ! empty( $engagement_items ) ) {
			$cols       = \min( \count( $engagement_items ), 4 );
			$gap        = 12;
			$item_width = ( $width - 80 - $gap * ( $cols - 1 ) ) / $cols;

			foreach ( $engagement_items as $i => $item ) {
				$col = $i % $cols;
				$row = (int) ( $i / $cols );
				$x   = 40 + $col * ( $item_width + $gap );
				$y   = $cur_y + $row * 70;

				$this->draw_stat_box( $image, $x, $y, $item_width, 58, $item['value'], $item['label'], $fg, $muted, $border );
			}

			$rows   = (int) ceil( \count( $engagement_items ) / $cols );
			$cur_y += $rows * 70 + 20;
		}

		// Details row: Follower Growth, Most Active Month, Top Supporter.
		$details = array();

		$followers_net = $summary['followers_net_change'] ?? ( ( $summary['followers_end'] ?? 0 ) - ( $summary['followers_start'] ?? 0 ) );
		$change_sign   = $followers_net >= 0 ? '+' : '';
		$details[]     = array(
			'label' => \__( 'Follower Growth', 'activitypub' ),
			'value' => $change_sign . \number_format_i18n( $followers_net ),
			'extra' => \sprintf(
				/* translators: 1: follower count at start of year, 2: follower count at end of year */
				\__( '%1$s → %2$s followers', 'activitypub' ),
				\number_format_i18n( $summary['followers_start'] ?? 0 ),
				\number_format_i18n( $summary['followers_end'] ?? 0 )
			),
		);

		if ( ! empty( $summary['most_active_month'] ) ) {
			$details[] = array(
				'label' => \__( 'Most Active Month', 'activitypub' ),
				'value' => \gmdate( 'F', \gmmktime( 0, 0, 0, $summary['most_active_month'], 1, $year ) ),
				'extra' => '',
			);
		}

		if ( ! empty( $summary['top_multiplicator'] ) ) {
			$details[] = array(
				'label' => \__( 'Top Supporter', 'activitypub' ),
				'value' => $summary['top_multiplicator']['name'],
				'extra' => \sprintf(
					/* translators: %s: Number of boosts */
					\_n( '%s boost', '%s boosts', (int) $summary['top_multiplicator']['count'], 'activitypub' ),
					\number_format_i18n( $summary['top_multiplicator']['count'] )
				),
			);
		}

		if ( ! empty( $details ) ) {
			$cols       = \min( \count( $details ), 3 );
			$gap        = 16;
			$item_width = ( $width - 80 - $gap * ( $cols - 1 ) ) / $cols;

			foreach ( $details as $i => $detail ) {
				$x = 40 + $i * ( $item_width + $gap );
				$this->draw_detail_box( $image, $x, $cur_y, $item_width, 80, $detail, $fg, $muted, $subtle, $border );
			}
			$cur_y += 100;
		}

		// Branding footer.
		$branding = $site_name . ' · ' . \__( 'Powered by ActivityPub', 'activitypub' );
		$this->draw_text_centered( $image, $branding, $height - 30, 2, $subtle );

		// Output to buffer.
		\ob_start();
		\imagepng( $image );
		$data = \ob_get_clean();
		\imagedestroy( $image );

		return $data;
	}

	/**
	 * Draw centered text on the image.
	 *
	 * @param resource $image The image resource.
	 * @param string   $text  The text to draw.
	 * @param int      $y     The y position.
	 * @param int      $size  The font size (1-5 for built-in fonts).
	 * @param int      $color The text color.
	 */
	private function draw_text_centered( $image, $text, $y, $size, $color ) {
		$font_width = \imagefontwidth( $size );
		$text_width = $font_width * \strlen( $text );
		$x          = (int) ( ( self::WIDTH - $text_width ) / 2 );
		\imagestring( $image, $size, $x, $y, $text, $color );
	}

	/**
	 * Draw a stat box with value and label.
	 *
	 * @param resource $image     The image resource.
	 * @param int      $x         The x position.
	 * @param int      $y         The y position.
	 * @param int      $w         The width.
	 * @param int      $h         The height.
	 * @param string   $value     The stat value.
	 * @param string   $label     The stat label.
	 * @param int      $fg_color  Foreground color.
	 * @param int      $sub_color Muted color for label.
	 * @param int      $border    Border color.
	 */
	private function draw_stat_box( $image, $x, $y, $w, $h, $value, $label, $fg_color, $sub_color, $border ) {
		\imagerectangle( $image, $x, $y, $x + $w, $y + $h, $border );

		// Value centered.
		$font_width = \imagefontwidth( 5 );
		$text_width = $font_width * \strlen( $value );
		$text_x     = (int) ( $x + ( $w - $text_width ) / 2 );
		\imagestring( $image, 5, $text_x, $y + (int) ( $h / 2 ) - 16, $value, $fg_color );

		// Label centered below.
		$font_width = \imagefontwidth( 2 );
		$text_width = $font_width * \strlen( $label );
		$text_x     = (int) ( $x + ( $w - $text_width ) / 2 );
		\imagestring( $image, 2, $text_x, $y + (int) ( $h / 2 ) + 8, $label, $sub_color );
	}

	/**
	 * Draw a detail box with label, value, and extra text.
	 *
	 * @param resource $image        The image resource.
	 * @param int      $x            The x position.
	 * @param int      $y            The y position.
	 * @param int      $w            The width.
	 * @param int      $h            The height.
	 * @param array    $detail       Detail data with label, value, extra.
	 * @param int      $fg_color     Foreground color.
	 * @param int      $muted_color  Muted color.
	 * @param int      $subtle_color Subtle color.
	 * @param int      $border       Border color.
	 */
	private function draw_detail_box( $image, $x, $y, $w, $h, $detail, $fg_color, $muted_color, $subtle_color, $border ) {
		\imagerectangle( $image, $x, $y, $x + $w, $y + $h, $border );

		\imagestring( $image, 2, $x + 10, $y + 8, $detail['label'], $subtle_color );
		\imagestring( $image, 4, $x + 10, $y + 28, $detail['value'], $fg_color );

		if ( ! empty( $detail['extra'] ) ) {
			\imagestring( $image, 2, $x + 10, $y + 52, $detail['extra'], $subtle_color );
		}
	}
}
