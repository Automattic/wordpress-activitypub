<?php
/**
 * Stats Image cache class.
 *
 * @package Activitypub
 * @since unreleased
 */

namespace Activitypub\Cache;

use Activitypub\Collection\Actors;
use Activitypub\Statistics;

/**
 * Stats Image cache class.
 *
 * Generates, caches, and serves shareable stats images.
 * Images are stored in /wp-content/uploads/activitypub/stats/{user_id}/
 */
class Stats_Image {

	/**
	 * Base directory for cached stats images relative to uploads.
	 *
	 * @var string
	 */
	const BASE_DIR = '/activitypub/stats/';

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
	 * Check if the GD library is available.
	 *
	 * @return bool Whether GD is available.
	 */
	public static function is_available() {
		return \function_exists( 'imagecreatetruecolor' );
	}

	/**
	 * Get the public URL for a stats image, generating it if needed.
	 *
	 * @param int   $user_id         The user ID.
	 * @param int   $year            The year.
	 * @param array $color_overrides Optional bg/fg hex overrides (without #).
	 *
	 * @return string|\WP_Error The public URL or error.
	 */
	public static function get_url( $user_id, $year, $color_overrides = array() ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'gd_not_available', \__( 'GD library is not available.', 'activitypub' ), array( 'status' => 501 ) );
		}

		// If local caching is disabled, use the REST endpoint for on-the-fly generation.
		if ( ! self::is_enabled() ) {
			$url = \get_rest_url( null, ACTIVITYPUB_REST_NAMESPACE . '/stats/image/' . $user_id . '/' . $year );

			/**
			 * Filters the stats image URL.
			 *
			 * Can be used to route through a CDN or image proxy like Photon.
			 *
			 * @since unreleased
			 *
			 * @param string $url     The image URL.
			 * @param int    $user_id The user ID.
			 * @param int    $year    The year.
			 */
			return \apply_filters( 'activitypub_stats_image_url', $url, $user_id, $year );
		}

		$cache_key = self::get_cache_key( $user_id, $year, $color_overrides );
		$cached    = self::get_cached( $cache_key );

		if ( ! $cached ) {
			$cached = self::generate( $user_id, $year, $color_overrides );
		}

		if ( \is_wp_error( $cached ) ) {
			return $cached;
		}

		$url = self::path_to_url( $cached['path'] );

		/** This filter is documented in includes/cache/class-stats-image.php */
		return \apply_filters( 'activitypub_stats_image_url', $url, $user_id, $year );
	}

	/**
	 * Check if stats image caching is enabled.
	 *
	 * Uses the same filter pattern as other cache types:
	 * `activitypub_cache_stats_image_enabled`.
	 *
	 * @return bool Whether caching is enabled.
	 */
	private static function is_enabled() {
		/**
		 * Filters whether stats image caching is enabled.
		 *
		 * @since unreleased
		 *
		 * @param bool $enabled Whether caching is enabled. Default true.
		 */
		return (bool) \apply_filters( 'activitypub_cache_stats_image_enabled', true );
	}

	/**
	 * Serve a stats image, generating it if needed.
	 *
	 * Outputs headers and image data, then exits.
	 *
	 * @param int   $user_id         The user ID.
	 * @param int   $year            The year.
	 * @param array $color_overrides Optional bg/fg hex overrides (without #).
	 *
	 * @return \WP_Error|void Error on failure, exits on success.
	 */
	public static function serve( $user_id, $year, $color_overrides = array() ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'gd_not_available', \__( 'GD library is not available.', 'activitypub' ), array( 'status' => 501 ) );
		}

		$cache_key = self::get_cache_key( $user_id, $year, $color_overrides );
		$cached    = self::get_cached( $cache_key );

		if ( ! $cached ) {
			$cached = self::generate( $user_id, $year, $color_overrides );
		}

		if ( \is_wp_error( $cached ) ) {
			return $cached;
		}

		\header( 'Content-Type: ' . $cached['mime_type'] );
		\header( 'Content-Length: ' . \filesize( $cached['path'] ) );
		\header( 'Cache-Control: public, max-age=86400' );

		\readfile( $cached['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Generate the stats image and save to cache.
	 *
	 * @param int   $user_id         The user ID.
	 * @param int   $year            The year.
	 * @param array $color_overrides Optional bg/fg hex overrides (without #).
	 *
	 * @return array|\WP_Error { path, mime_type } or error.
	 */
	public static function generate( $user_id, $year, $color_overrides = array() ) {
		if ( ! \function_exists( 'imagecreatetruecolor' ) ) {
			return new \WP_Error(
				'gd_not_available',
				\__( 'GD library is not available.', 'activitypub' ),
				array( 'status' => 501 )
			);
		}

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

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp_file = self::render( $summary, $actor_webfinger, $site_name, $year, $color_overrides );

		if ( \is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$cache_key = self::get_cache_key( $user_id, $year, $color_overrides );
		$result    = self::optimize_and_store( $tmp_file, $cache_key );

		\wp_delete_file( $tmp_file );

		return $result;
	}

	/**
	 * Build a cache key from the image parameters.
	 *
	 * @param int   $user_id         The user ID.
	 * @param int   $year            The year.
	 * @param array $color_overrides The color overrides.
	 *
	 * @return array Cache key with dir, base, hash.
	 */
	private static function get_cache_key( $user_id, $year, $color_overrides ) {
		$upload_dir = \wp_upload_dir();
		$hash       = \md5( \wp_json_encode( \array_filter( $color_overrides ) ) );

		return array(
			'dir'  => $upload_dir['basedir'] . self::BASE_DIR . $user_id,
			'base' => \sprintf( 'stats-%d-%s', $year, $hash ),
		);
	}

	/**
	 * Look for a cached image.
	 *
	 * @param array $cache_key The cache key.
	 *
	 * @return array|false { path, mime_type } or false if not cached.
	 */
	private static function get_cached( $cache_key ) {
		$extensions = array(
			'webp' => 'image/webp',
			'png'  => 'image/png',
		);

		foreach ( $extensions as $ext => $mime ) {
			$path = $cache_key['dir'] . '/' . $cache_key['base'] . '.' . $ext;
			if ( \file_exists( $path ) ) {
				return array(
					'path'      => $path,
					'mime_type' => $mime,
				);
			}
		}

		return false;
	}

	/**
	 * Optimize the image via WP_Image_Editor and save to cache.
	 *
	 * @param string $tmp_file  Path to the temporary PNG.
	 * @param array  $cache_key The cache key.
	 *
	 * @return array|\WP_Error { path, mime_type } or error.
	 */
	private static function optimize_and_store( $tmp_file, $cache_key ) {
		if ( ! \wp_mkdir_p( $cache_key['dir'] ) ) {
			return new \WP_Error(
				'cache_dir_failed',
				\__( 'Failed to create cache directory.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		$editor    = \wp_get_image_editor( $tmp_file );
		$mime_type = 'image/png';
		$ext       = 'png';

		if ( ! \is_wp_error( $editor ) && $editor->supports_mime_type( 'image/webp' ) ) {
			$mime_type = 'image/webp';
			$ext       = 'webp';
		}

		$dest_path = $cache_key['dir'] . '/' . $cache_key['base'] . '.' . $ext;

		if ( ! \is_wp_error( $editor ) ) {
			$result = $editor->save( $dest_path, $mime_type );

			if ( ! \is_wp_error( $result ) ) {
				return array(
					'path'      => $result['path'],
					'mime_type' => $mime_type,
				);
			}
		}

		// Fallback: copy the PNG directly.
		\copy( $tmp_file, $dest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		return array(
			'path'      => $dest_path,
			'mime_type' => 'image/png',
		);
	}

	/**
	 * Convert a filesystem path to a public URL.
	 *
	 * @param string $path The filesystem path.
	 *
	 * @return string The public URL.
	 */
	private static function path_to_url( $path ) {
		$upload_dir = \wp_upload_dir();
		return \str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $path );
	}

	/**
	 * Render the stats image as a temporary PNG file.
	 *
	 * @param array  $summary         The annual stats summary.
	 * @param string $actor_webfinger The actor webfinger identifier.
	 * @param string $site_name       The site name.
	 * @param int    $year            The year.
	 * @param array  $color_overrides Optional bg/fg hex color overrides.
	 *
	 * @return string|\WP_Error Path to temporary PNG file or error.
	 */
	private static function render( $summary, $actor_webfinger, $site_name, $year, $color_overrides = array() ) {
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

		$colors = self::resolve_colors( $color_overrides );
		$bg     = \imagecolorallocate( $image, $colors['bg'][0], $colors['bg'][1], $colors['bg'][2] );
		$fg     = \imagecolorallocate( $image, $colors['fg'][0], $colors['fg'][1], $colors['fg'][2] );
		$muted  = \imagecolorallocate( $image, $colors['muted'][0], $colors['muted'][1], $colors['muted'][2] );

		\imagefill( $image, 0, 0, $bg );

		$font = self::resolve_font();

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
		self::draw_text_centered( $image, $title, 100, 36, $fg, $font );

		// Actor webfinger.
		if ( $actor_webfinger ) {
			self::draw_text_centered( $image, $actor_webfinger, 150, 20, $muted, $font );
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
			self::draw_text_at( $image, $stat['value'], $center_x, 300, 56, $fg, $font );
			self::draw_text_at( $image, $stat['label'], $center_x, 355, 18, $muted, $font );
		}

		// Follower growth line.
		$followers_net = $summary['followers_net_change'] ?? 0;
		$change_sign   = $followers_net >= 0 ? '+' : '';
		$growth_text   = \sprintf(
			/* translators: %s: follower net change */
			\__( '%s followers this year', 'activitypub' ),
			$change_sign . \number_format_i18n( $followers_net )
		);
		self::draw_text_centered( $image, $growth_text, 450, 20, $muted, $font );

		// Branding.
		$branding = $site_name . ' - ' . \__( 'Powered by ActivityPub', 'activitypub' );
		self::draw_text_centered( $image, $branding, $height - 40, 14, $muted, $font );

		// Save to temp file.
		$tmp_file = \wp_tempnam( 'activitypub-stats-' );
		\imagepng( $image, $tmp_file );

		// imagedestroy() is deprecated since PHP 8.5 and a no-op since 8.0.
		if ( \PHP_VERSION_ID < 80000 ) {
			\imagedestroy( $image );
		}

		return $tmp_file;
	}

	/**
	 * Resolve colors from theme Global Styles or overrides.
	 *
	 * @param array $overrides Optional bg/fg hex color overrides.
	 *
	 * @return array Associative array with 'bg', 'fg', and 'muted' RGB arrays.
	 */
	private static function resolve_colors( $overrides = array() ) {
		$bg_rgb = array( 255, 255, 255 );
		$fg_rgb = array( 17, 17, 17 );

		if ( ! empty( $overrides['bg'] ) ) {
			$parsed = self::parse_hex( $overrides['bg'] );
			if ( $parsed ) {
				$bg_rgb = $parsed;
			}
		}

		if ( ! empty( $overrides['fg'] ) ) {
			$parsed = self::parse_hex( $overrides['fg'] );
			if ( $parsed ) {
				$fg_rgb = $parsed;
			}
		}

		if ( ! empty( $overrides['bg'] ) && ! empty( $overrides['fg'] ) ) {
			return self::build_color_set( $bg_rgb, $fg_rgb );
		}

		$palette  = array();
		$settings = \wp_get_global_settings();
		if ( ! empty( $settings['color']['palette'] ) ) {
			foreach ( $settings['color']['palette'] as $colors ) {
				foreach ( $colors as $color ) {
					$palette[ $color['slug'] ] = $color['color'];
				}
			}
		}

		$styles      = \wp_get_global_styles( array( 'color' ) );
		$bg_resolved = self::resolve_style_color( $styles['background'] ?? '', $palette );
		$fg_resolved = self::resolve_style_color( $styles['text'] ?? '', $palette );

		if ( $bg_resolved ) {
			$bg_rgb = $bg_resolved;
		}

		if ( $fg_resolved ) {
			$fg_rgb = $fg_resolved;
		}

		if ( ! $bg_resolved || ! $fg_resolved ) {
			$bg_slugs = array( 'base', 'background', 'white' );
			$fg_slugs = array( 'contrast', 'foreground', 'black', 'dark-gray' );

			if ( ! $bg_resolved ) {
				foreach ( $bg_slugs as $slug ) {
					if ( ! empty( $palette[ $slug ] ) ) {
						$parsed = self::parse_hex( $palette[ $slug ] );
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
						$parsed = self::parse_hex( $palette[ $slug ] );
						if ( $parsed ) {
							$fg_rgb = $parsed;
							break;
						}
					}
				}
			}
		}

		return self::build_color_set( $bg_rgb, $fg_rgb );
	}

	/**
	 * Build a color set with a derived muted color.
	 *
	 * @param array $bg_rgb Background RGB.
	 * @param array $fg_rgb Foreground RGB.
	 *
	 * @return array { bg, fg, muted } RGB arrays.
	 */
	private static function build_color_set( $bg_rgb, $fg_rgb ) {
		return array(
			'bg'    => $bg_rgb,
			'fg'    => $fg_rgb,
			'muted' => array(
				(int) ( ( $fg_rgb[0] + $bg_rgb[0] ) / 2 ),
				(int) ( ( $fg_rgb[1] + $bg_rgb[1] ) / 2 ),
				(int) ( ( $fg_rgb[2] + $bg_rgb[2] ) / 2 ),
			),
		);
	}

	/**
	 * Resolve a color value from Global Styles.
	 *
	 * @param string $value   The color value (hex or CSS variable).
	 * @param array  $palette The merged color palette (slug => hex).
	 *
	 * @return array|false RGB array or false.
	 */
	private static function resolve_style_color( $value, $palette ) {
		if ( empty( $value ) ) {
			return false;
		}

		if ( '#' === $value[0] ) {
			return self::parse_hex( $value );
		}

		if ( \preg_match( '/--color--([a-z0-9-]+)/', $value, $matches ) ) {
			$slug = $matches[1];
			if ( ! empty( $palette[ $slug ] ) ) {
				return self::parse_hex( $palette[ $slug ] );
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
	private static function parse_hex( $hex ) {
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
	 * Resolve a TTF font file from the active theme or Font Library.
	 *
	 * @return string|false Path to a TTF file, or false if none found.
	 */
	private static function resolve_font() {
		$body_slug = '';
		$styles    = \wp_get_global_styles( array( 'typography' ) );
		if ( ! empty( $styles['fontFamily'] ) ) {
			if ( \preg_match( '/--font-family--([a-z0-9-]+)/', $styles['fontFamily'], $matches ) ) {
				$body_slug = $matches[1];
			}
		}

		$settings = \wp_get_global_settings();
		if ( ! empty( $settings['typography']['fontFamilies'] ) ) {
			$all_families = array();
			foreach ( $settings['typography']['fontFamilies'] as $families ) {
				foreach ( $families as $family ) {
					$all_families[] = $family;
				}
			}

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
	 * @param resource     $image The image resource.
	 * @param string       $text  The text to draw.
	 * @param int          $y     The y position.
	 * @param int|float    $size  Font size in points (TTF) or 1-5 (built-in).
	 * @param int          $color The text color.
	 * @param string|false $font  Path to TTF file, or false for built-in.
	 */
	private static function draw_text_centered( $image, $text, $y, $size, $color, $font = false ) {
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
	 * @param resource     $image The image resource.
	 * @param string       $text  The text to draw.
	 * @param int          $x     The center x position.
	 * @param int          $y     The y position.
	 * @param int|float    $size  Font size in points (TTF) or 1-5 (built-in).
	 * @param int          $color The text color.
	 * @param string|false $font  Path to TTF file, or false for built-in.
	 */
	private static function draw_text_at( $image, $text, $x, $y, $size, $color, $font = false ) {
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
