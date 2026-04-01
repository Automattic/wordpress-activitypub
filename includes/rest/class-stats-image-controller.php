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
						'bg'      => array(
							'description'       => \__( 'Background color as hex (without #).', 'activitypub' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_hex_color_no_hash',
						),
						'fg'      => array(
							'description'       => \__( 'Text color as hex (without #).', 'activitypub' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_hex_color_no_hash',
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

		$actor = Actors::get_by_id( $user_id );

		if ( \is_wp_error( $actor ) ) {
			if ( Actors::BLOG_USER_ID === $user_id ) {
				$actor = new \Activitypub\Model\Blog();
			} elseif ( Actors::APPLICATION_USER_ID === $user_id ) {
				$actor = new \Activitypub\Model\Application();
			}
		}

		$actor_webfinger = ! \is_wp_error( $actor ) ? $actor->get_webfinger() : '';
		$site_name       = \get_bloginfo( 'name' );

		$color_overrides = array(
			'bg' => $request->get_param( 'bg' ),
			'fg' => $request->get_param( 'fg' ),
		);

		$png_data = $this->render_image( $summary, $actor_webfinger, $site_name, $year, $color_overrides );

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
	 * @param array  $summary          The annual stats summary.
	 * @param string $actor_webfinger The actor webfinger identifier.
	 * @param string $site_name       The site name.
	 * @param int    $year            The year.
	 * @param array  $color_overrides Optional bg/fg hex color overrides (without #).
	 *
	 * @return string|\WP_Error PNG binary data or error.
	 */
	private function render_image( $summary, $actor_webfinger, $site_name, $year, $color_overrides = array() ) {
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

		\imageantialias( $image, true );

		// Resolve colors: query params override theme detection.
		$colors = $this->resolve_colors( $color_overrides );
		$bg     = \imagecolorallocate( $image, $colors['bg'][0], $colors['bg'][1], $colors['bg'][2] );
		$fg     = \imagecolorallocate( $image, $colors['fg'][0], $colors['fg'][1], $colors['fg'][2] );
		$muted  = \imagecolorallocate( $image, $colors['muted'][0], $colors['muted'][1], $colors['muted'][2] );

		\imagefill( $image, 0, 0, $bg );

		// Resolve a TTF font from the active theme or fall back.
		$font = $this->resolve_font();

		// Total engagement.
		$comment_types    = Statistics::get_comment_types_for_stats();
		$total_engagement = 0;
		foreach ( \array_keys( $comment_types ) as $slug ) {
			$total_engagement += $summary[ $slug . '_count' ] ?? 0;
		}

		$followers_end = $summary['followers_end'] ?? 0;

		// Title.
		$title = \sprintf(
			/* translators: %d: The year */
			\__( 'Fediverse Stats %d', 'activitypub' ),
			$year
		);
		$this->draw_text_centered( $image, $title, 100, 36, $fg, $font );

		// Actor name.
		if ( $actor_webfinger ) {
			$this->draw_text_centered( $image, $actor_webfinger, 150, 20, $muted, $font );
		}

		// Three big stats in a row.
		$stats = array(
			array(
				'value' => \number_format_i18n( $summary['posts_count'] ),
				'label' => \__( 'Posts', 'activitypub' ),
			),
			array(
				'value' => \number_format_i18n( $total_engagement ),
				'label' => \__( 'Engagements', 'activitypub' ),
			),
			array(
				'value' => \number_format_i18n( $followers_end ),
				'label' => \__( 'Followers', 'activitypub' ),
			),
		);

		$col_width = (int) ( $width / 3 );

		foreach ( $stats as $i => $stat ) {
			$center_x = (int) ( $col_width * $i + $col_width / 2 );
			$this->draw_text_at( $image, $stat['value'], $center_x, 300, 56, $fg, $font );
			$this->draw_text_at( $image, $stat['label'], $center_x, 355, 18, $muted, $font );
		}

		// Follower growth line.
		$followers_net = $summary['followers_net_change'] ?? 0;
		$change_sign   = $followers_net >= 0 ? '+' : '';
		$growth_text   = \sprintf(
			/* translators: %s: follower net change */
			\__( '%s followers this year', 'activitypub' ),
			$change_sign . \number_format_i18n( $followers_net )
		);
		$this->draw_text_centered( $image, $growth_text, 450, 20, $muted, $font );

		// Branding.
		$branding = $site_name . ' - ' . \__( 'Powered by ActivityPub', 'activitypub' );
		$this->draw_text_centered( $image, $branding, $height - 40, 14, $muted, $font );

		// Output to buffer.
		\ob_start();
		\imagepng( $image );
		$data = \ob_get_clean();
		\imagedestroy( $image );

		return $data;
	}

	/**
	 * Resolve colors from the active theme's Global Styles.
	 *
	 * Uses the theme's base/contrast palette colors for background and
	 * foreground text. Derives a muted color by blending toward the background.
	 *
	 * @return array Associative array with 'bg', 'fg', and 'muted' keys,
	 *               each containing an array of [r, g, b] values.
	 */
	private function resolve_colors( $overrides = array() ) {
		$bg_rgb = array( 255, 255, 255 );
		$fg_rgb = array( 17, 17, 17 );

		// Apply query param overrides first.
		if ( ! empty( $overrides['bg'] ) ) {
			$parsed = $this->parse_hex( $overrides['bg'] );
			if ( $parsed ) {
				$bg_rgb = $parsed;
			}
		}

		if ( ! empty( $overrides['fg'] ) ) {
			$parsed = $this->parse_hex( $overrides['fg'] );
			if ( $parsed ) {
				$fg_rgb = $parsed;
			}
		}

		// If both overrides are set, skip theme detection.
		if ( ! empty( $overrides['bg'] ) && ! empty( $overrides['fg'] ) ) {
			$muted_rgb = array(
				(int) ( ( $fg_rgb[0] + $bg_rgb[0] ) / 2 ),
				(int) ( ( $fg_rgb[1] + $bg_rgb[1] ) / 2 ),
				(int) ( ( $fg_rgb[2] + $bg_rgb[2] ) / 2 ),
			);

			return array(
				'bg'    => $bg_rgb,
				'fg'    => $fg_rgb,
				'muted' => $muted_rgb,
			);
		}

		$palette = array();

		$settings = \wp_get_global_settings();
		if ( ! empty( $settings['color']['palette'] ) ) {
			foreach ( $settings['color']['palette'] as $colors ) {
				foreach ( $colors as $color ) {
					$palette[ $color['slug'] ] = $color['color'];
				}
			}
		}

		// Try to resolve background color from Global Styles.
		$styles     = \wp_get_global_styles( array( 'color' ) );
		$bg_resolved = $this->resolve_style_color( $styles['background'] ?? '', $palette );
		$fg_resolved = $this->resolve_style_color( $styles['text'] ?? '', $palette );

		if ( $bg_resolved ) {
			$bg_rgb = $bg_resolved;
		}

		if ( $fg_resolved ) {
			$fg_rgb = $fg_resolved;
		}

		// If styles didn't give us colors, try common palette slug conventions.
		if ( ! $bg_resolved || ! $fg_resolved ) {
			// Slug conventions across themes: base/contrast, background/foreground, white/black.
			$bg_slugs = array( 'base', 'background', 'white' );
			$fg_slugs = array( 'contrast', 'foreground', 'black', 'dark-gray' );

			if ( ! $bg_resolved ) {
				foreach ( $bg_slugs as $slug ) {
					if ( ! empty( $palette[ $slug ] ) ) {
						$parsed = $this->parse_hex( $palette[ $slug ] );
						if ( $parsed ) {
							$bg_rgb = $parsed;
							break;
						}
					}
				}
			}

			if ( ! $fg_resolved ) {
				foreach ( $fg_slugs as $slug ) {
					if ( ! empty( $palette[ $slug ] ) ) {
						$parsed = $this->parse_hex( $palette[ $slug ] );
						if ( $parsed ) {
							$fg_rgb = $parsed;
							break;
						}
					}
				}
			}
		}

		// Muted: blend foreground 50% toward background.
		$muted_rgb = array(
			(int) ( ( $fg_rgb[0] + $bg_rgb[0] ) / 2 ),
			(int) ( ( $fg_rgb[1] + $bg_rgb[1] ) / 2 ),
			(int) ( ( $fg_rgb[2] + $bg_rgb[2] ) / 2 ),
		);

		return array(
			'bg'    => $bg_rgb,
			'fg'    => $fg_rgb,
			'muted' => $muted_rgb,
		);
	}

	/**
	 * Resolve a color value from Global Styles.
	 *
	 * Handles hex colors directly and CSS variables referencing palette colors.
	 *
	 * @param string $value   The color value (hex or CSS variable).
	 * @param array  $palette The merged color palette (slug => hex).
	 *
	 * @return array|false RGB array or false if unresolvable.
	 */
	private function resolve_style_color( $value, $palette ) {
		if ( empty( $value ) ) {
			return false;
		}

		// Direct hex.
		if ( '#' === $value[0] ) {
			return $this->parse_hex( $value );
		}

		// CSS variable: var(--wp--preset--color--slug).
		if ( \preg_match( '/--color--([a-z0-9-]+)/', $value, $matches ) ) {
			$slug = $matches[1];
			if ( ! empty( $palette[ $slug ] ) ) {
				return $this->parse_hex( $palette[ $slug ] );
			}
		}

		return false;
	}

	/**
	 * Parse a hex color string into an RGB array.
	 *
	 * @param string $hex The hex color (e.g. '#FF0000' or '#F00').
	 *
	 * @return array|false Array of [r, g, b] or false on failure.
	 */
	private function parse_hex( $hex ) {
		$hex = \ltrim( $hex, '#' );

		if ( 3 === \strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== \strlen( $hex ) ) {
			return false;
		}

		return array(
			\hexdec( \substr( $hex, 0, 2 ) ),
			\hexdec( \substr( $hex, 2, 2 ) ),
			\hexdec( \substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Resolve a TTF font file from the active theme.
	 *
	 * Looks for TTF files referenced in the theme's font families via
	 * Global Styles, then falls back to any TTF in the theme directory,
	 * then to bundled WordPress theme fonts.
	 *
	 * @return string|false Path to a TTF file, or false if none found.
	 */
	private function resolve_font() {
		// Determine which font family slug the body text uses.
		$body_slug = '';
		$styles    = \wp_get_global_styles( array( 'typography' ) );
		if ( ! empty( $styles['fontFamily'] ) ) {
			// Extract slug from var(--wp--preset--font-family--slug).
			if ( \preg_match( '/--font-family--([a-z0-9-]+)/', $styles['fontFamily'], $matches ) ) {
				$body_slug = $matches[1];
			}
		}

		// Search theme font families for a TTF/OTF file.
		$settings = \wp_get_global_settings();
		if ( ! empty( $settings['typography']['fontFamilies'] ) ) {
			// If we know the body slug, try that family first.
			$all_families = array();
			foreach ( $settings['typography']['fontFamilies'] as $families ) {
				foreach ( $families as $family ) {
					$all_families[] = $family;
				}
			}

			// Sort: body font first.
			if ( $body_slug ) {
				\usort(
					$all_families,
					function ( $a, $b ) use ( $body_slug ) {
						$a_match = ( $a['slug'] ?? '' ) === $body_slug ? 0 : 1;
						$b_match = ( $b['slug'] ?? '' ) === $body_slug ? 0 : 1;
						return $a_match - $b_match;
					}
				);
			}

			foreach ( $all_families as $family ) {
				if ( empty( $family['fontFace'] ) ) {
					continue;
				}
				foreach ( $family['fontFace'] as $face ) {
					$src = \is_array( $face['src'] ) ? $face['src'][0] : $face['src'];

					if ( ! \preg_match( '/\.(ttf|otf)$/i', $src ) ) {
						continue;
					}

					if ( 0 === \strpos( $src, 'file:./' ) ) {
						$src = \get_theme_file_path( \substr( $src, 7 ) );
					}

					if ( \file_exists( $src ) ) {
						return $src;
					}
				}
			}
		}

		// Try the Font Library (WP 6.5+).
		$font_families = \get_posts(
			array(
				'post_type'      => 'wp_font_family',
				'posts_per_page' => 10,
				'post_status'    => 'publish',
			)
		);

		foreach ( $font_families as $font_family ) {
			$faces = \get_posts(
				array(
					'post_type'      => 'wp_font_face',
					'post_parent'    => $font_family->ID,
					'posts_per_page' => 10,
					'post_status'    => 'publish',
				)
			);

			foreach ( $faces as $face ) {
				$file = \get_post_meta( $face->ID, '_wp_font_face_file', true );
				if ( $file && \preg_match( '/\.(ttf|otf)$/i', $file ) ) {
					$path = \path_join( \wp_get_font_dir()['path'], $file );
					if ( \file_exists( $path ) ) {
						return $path;
					}
				}
			}
		}

		// Fall back: common WordPress bundled theme fonts.
		$fallbacks = array(
			ABSPATH . 'wp-content/themes/twentytwentytwo/assets/fonts/dm-sans/DMSans-Regular.ttf',
			ABSPATH . 'wp-content/themes/twentytwentythree/assets/fonts/dm-sans/DMSans-Regular.ttf',
		);

		foreach ( $fallbacks as $path ) {
			if ( \file_exists( $path ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * Draw centered text on the image.
	 *
	 * Uses TrueType rendering when a font is available, falls back to
	 * GD built-in fonts.
	 *
	 * @param resource    $image The image resource.
	 * @param string      $text  The text to draw.
	 * @param int         $y     The y position (baseline for TTF, top for built-in).
	 * @param int|float   $size  Font size in points (TTF) or 1-5 (built-in).
	 * @param int         $color The text color.
	 * @param string|false $font  Path to TTF file, or false for built-in.
	 */
	private function draw_text_centered( $image, $text, $y, $size, $color, $font = false ) {
		if ( $font && \function_exists( 'imagefttext' ) ) {
			$bbox       = \imageftbbox( $size, 0, $font, $text );
			$text_width = $bbox[2] - $bbox[0];
			$x          = (int) ( ( self::WIDTH - $text_width ) / 2 );
			\imagefttext( $image, $size, 0, $x, $y, $color, $font, $text );
		} else {
			$builtin_size = \min( 5, \max( 1, (int) ( $size / 10 ) ) );
			$font_width   = \imagefontwidth( $builtin_size );
			$text_width   = $font_width * \strlen( $text );
			$x            = (int) ( ( self::WIDTH - $text_width ) / 2 );
			\imagestring( $image, $builtin_size, $x, $y, $text, $color );
		}
	}

	/**
	 * Draw text centered at a specific x position.
	 *
	 * @param resource    $image The image resource.
	 * @param string      $text  The text to draw.
	 * @param int         $x     The center x position.
	 * @param int         $y     The y position.
	 * @param int|float   $size  Font size in points (TTF) or 1-5 (built-in).
	 * @param int         $color The text color.
	 * @param string|false $font  Path to TTF file, or false for built-in.
	 */
	private function draw_text_at( $image, $text, $x, $y, $size, $color, $font = false ) {
		if ( $font && \function_exists( 'imagefttext' ) ) {
			$bbox       = \imageftbbox( $size, 0, $font, $text );
			$text_width = $bbox[2] - $bbox[0];
			\imagefttext( $image, $size, 0, (int) ( $x - $text_width / 2 ), $y, $color, $font, $text );
		} else {
			$builtin_size = \min( 5, \max( 1, (int) ( $size / 10 ) ) );
			$font_width   = \imagefontwidth( $builtin_size );
			$text_width   = $font_width * \strlen( $text );
			\imagestring( $image, $builtin_size, (int) ( $x - $text_width / 2 ), $y, $text, $color );
		}
	}

}
