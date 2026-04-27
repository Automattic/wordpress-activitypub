<?php
/**
 * Stats Image cache class.
 *
 * @package Activitypub
 * @since 8.1.0
 */

namespace Activitypub\Cache;

use Activitypub\Collection\Actors;
use Activitypub\Model\Application;
use Activitypub\Model\Blog;
use Activitypub\Statistics;

/**
 * Stats Image cache class.
 *
 * Generates, caches, and serves shareable stats images.
 * Extends the File cache base class for storage, optimization, and cleanup.
 * Images are stored in /wp-content/uploads/activitypub/stats/{user_id}/
 */
class Stats_Image extends File {

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
	 * Get the cache type identifier.
	 *
	 * @return string Cache type.
	 */
	public static function get_type() {
		return 'stats_image';
	}

	/**
	 * Get the base directory path relative to uploads.
	 *
	 * @return string Base directory path.
	 */
	public static function get_base_dir() {
		return '/activitypub/stats/';
	}

	/**
	 * Get the context identifier for the filter.
	 *
	 * @return string Context identifier.
	 */
	public static function get_context() {
		return 'stats_image';
	}

	/**
	 * Get the maximum dimension for images of this type.
	 *
	 * Stats images have a fixed size, so no resizing is needed.
	 *
	 * @return int Maximum width/height in pixels.
	 */
	public static function get_max_dimension() {
		return self::WIDTH;
	}

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
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 *
	 * @return string|\WP_Error The public URL or error.
	 */
	public static function get_url( $user_id, $year ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'gd_not_available', \__( 'GD library is not available.', 'activitypub' ), array( 'status' => 501 ) );
		}

		// If local caching is disabled, use the REST endpoint for on-the-fly generation.
		if ( ! static::is_enabled() ) {
			$url = \get_rest_url( null, ACTIVITYPUB_REST_NAMESPACE . '/stats/image/' . $user_id . '/' . $year );

			/**
			 * Filters the stats image URL.
			 *
			 * Can be used to route through a CDN or image proxy like Photon.
			 *
			 * @since 8.1.0
			 *
			 * @param string $url     The image URL.
			 * @param int    $user_id The user ID.
			 * @param int    $year    The year.
			 */
			return \apply_filters( 'activitypub_stats_image_url', $url, $user_id, $year );
		}

		$hash  = self::get_hash( $user_id, $year );
		$paths = static::get_storage_paths( $user_id );

		// Check for cached file using the base class glob pattern.
		$pattern = static::escape_glob_pattern( $paths['basedir'] . '/stats-' . $year . '-' . $hash ) . '.*';
		$matches = \glob( $pattern );

		if ( ! empty( $matches ) && \is_file( $matches[0] ) ) {
			$url = $paths['baseurl'] . '/' . \basename( $matches[0] );

			/** This filter is documented in includes/cache/class-stats-image.php */
			return \apply_filters( 'activitypub_stats_image_url', $url, $user_id, $year );
		}

		// Generate the image.
		$result = self::generate( $user_id, $year );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		$url = $paths['baseurl'] . '/' . \basename( $result );

		/** This filter is documented in includes/cache/class-stats-image.php */
		return \apply_filters( 'activitypub_stats_image_url', $url, $user_id, $year );
	}

	/**
	 * Serve a stats image, generating it if needed.
	 *
	 * Outputs headers and image data, then exits.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 *
	 * @return \WP_Error|void Error on failure, exits on success.
	 */
	public static function serve( $user_id, $year ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'gd_not_available', \__( 'GD library is not available.', 'activitypub' ), array( 'status' => 501 ) );
		}

		$hash  = self::get_hash( $user_id, $year );
		$paths = static::get_storage_paths( $user_id );

		// Check for cached file.
		$pattern = static::escape_glob_pattern( $paths['basedir'] . '/stats-' . $year . '-' . $hash ) . '.*';
		$matches = \glob( $pattern );
		$file    = ( ! empty( $matches ) && \is_file( $matches[0] ) ) ? $matches[0] : null;

		if ( ! $file ) {
			$file = self::generate( $user_id, $year );
		}

		if ( \is_wp_error( $file ) ) {
			return $file;
		}

		$mime_type = static::get_file_mime_type( $file );

		\header( 'Content-Type: ' . ( $mime_type ?: 'image/png' ) );
		\header( 'Content-Length: ' . \filesize( $file ) );
		\header( 'Cache-Control: public, max-age=86400' );
		\header( 'X-Content-Type-Options: nosniff' );

		\readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Generate the stats image and save to cache.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 *
	 * @return string|\WP_Error Cached file path or error.
	 */
	public static function generate( $user_id, $year ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'gd_not_available', \__( 'GD library is not available.', 'activitypub' ), array( 'status' => 501 ) );
		}

		$summary = Statistics::get_annual_summary( $user_id, $year );

		if ( ! $summary ) {
			$summary = Statistics::compile_annual_summary( $user_id, $year );
		}

		if ( ! $summary || empty( $summary['posts_count'] ) ) {
			return new \WP_Error( 'no_stats', \__( 'No statistics available for this period.', 'activitypub' ), array( 'status' => 404 ) );
		}

		$actor = Actors::get_by_id( $user_id );

		if ( \is_wp_error( $actor ) ) {
			if ( Actors::BLOG_USER_ID === $user_id ) {
				$actor = new Blog();
			} elseif ( Actors::APPLICATION_USER_ID === $user_id ) {
				$actor = new Application();
			}
		}

		$actor_webfinger = ! \is_wp_error( $actor ) ? $actor->get_webfinger() : '';
		$site_name       = \get_bloginfo( 'name' );

		if ( ! \function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp_file = self::render( $summary, $actor_webfinger, $site_name, $year );

		if ( \is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Use the base class storage paths and optimization.
		$paths = static::get_storage_paths( $user_id );

		if ( ! \wp_mkdir_p( $paths['basedir'] ) ) {
			\wp_delete_file( $tmp_file );
			return new \WP_Error( 'cache_dir_failed', \__( 'Failed to create cache directory.', 'activitypub' ), array( 'status' => 500 ) );
		}

		// Remove old cached images for this year before saving the new one.
		$old_files = \glob( static::escape_glob_pattern( $paths['basedir'] . '/stats-' . $year . '-' ) . '*.*' );
		if ( $old_files ) {
			foreach ( $old_files as $old_file ) {
				\wp_delete_file( $old_file );
			}
		}

		$hash      = self::get_hash( $user_id, $year );
		$dest_name = \sprintf( 'stats-%d-%s.png', $year, $hash );
		$dest_path = $paths['basedir'] . '/' . $dest_name;

		static::get_filesystem()->move( $tmp_file, $dest_path, true );

		// Keep as PNG for maximum compatibility when sharing on social networks.
		return $dest_path;
	}

	/**
	 * Generate a hash for cache invalidation.
	 *
	 * Includes the theme stylesheet, version, and stats compilation
	 * timestamp so cached images are regenerated when the theme or
	 * the underlying stats data changes.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 *
	 * @return string The hash string.
	 */
	private static function get_hash( $user_id = 0, $year = 0 ) {
		$parts = array(
			\get_stylesheet(),
			\wp_get_theme()->get( 'Version' ),
		);

		if ( $user_id && $year ) {
			$summary = Statistics::get_annual_summary( $user_id, $year );

			if ( $summary && ! empty( $summary['compiled_at'] ) ) {
				$parts[] = $summary['compiled_at'];
			}
		}

		return \md5( \wp_json_encode( $parts ) );
	}

	/**
	 * Render the stats image as a temporary PNG file.
	 *
	 * @param array  $summary         The annual stats summary.
	 * @param string $actor_webfinger The actor webfinger identifier.
	 * @param string $site_name       The site name.
	 * @param int    $year            The year.
	 * @return string|\WP_Error Path to temporary PNG file or error.
	 */
	private static function render( $summary, $actor_webfinger, $site_name, $year ) {
		$width  = self::WIDTH;
		$height = self::HEIGHT;

		$image = \imagecreatetruecolor( $width, $height );

		if ( ! $image ) {
			return new \WP_Error( 'image_create_failed', \__( 'Failed to create image.', 'activitypub' ), array( 'status' => 500 ) );
		}

		\imageantialias( $image, true );

		$colors = self::resolve_colors();
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

		// Title.
		$title = \sprintf(
			/* translators: %d: The year */
			\__( 'Fediverse Stats %d', 'activitypub' ),
			$year
		);
		self::draw_text( $image, $title, null, 100, 36, $fg, $font );

		// Actor webfinger.
		if ( $actor_webfinger ) {
			self::draw_text( $image, $actor_webfinger, null, 150, 20, $muted, $font );
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
				'value' => \number_format_i18n( $summary['followers_end'] ?? 0 ),
				'label' => \__( 'Followers', 'activitypub' ),
			),
		);

		$col_width = (int) ( $width / 3 );

		foreach ( $stats as $i => $stat ) {
			$center_x = (int) ( $col_width * $i + $col_width / 2 );
			self::draw_text( $image, $stat['value'], $center_x, 300, 56, $fg, $font );
			self::draw_text( $image, $stat['label'], $center_x, 355, 18, $muted, $font );
		}

		// Follower growth line.
		$followers_net = $summary['followers_net_change'] ?? 0;
		$change_sign   = $followers_net >= 0 ? '+' : '';
		$growth_text   = \sprintf(
			/* translators: %s: follower net change */
			\__( '%s followers this year', 'activitypub' ),
			$change_sign . \number_format_i18n( $followers_net )
		);
		self::draw_text( $image, $growth_text, null, 450, 20, $muted, $font );

		// Branding.
		$branding = $site_name . ' - ' . \__( 'Powered by ActivityPub', 'activitypub' );
		self::draw_text( $image, $branding, null, $height - 40, 14, $muted, $font );

		// Save to temp file.
		$tmp_file = \wp_tempnam( 'activitypub-stats-' );

		if ( ! $tmp_file ) {
			return new \WP_Error( 'temp_file_failed', \__( 'Could not create temporary file.', 'activitypub' ), array( 'status' => 500 ) );
		}

		$saved = \imagepng( $image, $tmp_file );

		// imagedestroy() is deprecated since PHP 8.5 and a no-op since 8.0.
		if ( \PHP_VERSION_ID < 80000 ) {
			\imagedestroy( $image );
		}

		if ( ! $saved ) {
			\wp_delete_file( $tmp_file );
			return new \WP_Error( 'image_write_failed', \__( 'Failed to write stats image.', 'activitypub' ), array( 'status' => 500 ) );
		}

		return $tmp_file;
	}

	/**
	 * Draw text on the image, centered on the canvas or at a specific x position.
	 *
	 * Uses TrueType rendering when a font is available, falls back to
	 * GD built-in fonts.
	 *
	 * @param resource     $image The image resource.
	 * @param string       $text  The text to draw.
	 * @param int|null     $x     The center x position, or null to center on canvas.
	 * @param int          $y     The y position.
	 * @param int|float    $size  Font size in points (TTF) or 1-5 (built-in).
	 * @param int          $color The text color.
	 * @param string|false $font  Path to TTF file, or false for built-in.
	 */
	private static function draw_text( $image, $text, $x, $y, $size, $color, $font = false ) {
		if ( $font && \function_exists( 'imagefttext' ) ) {
			$bbox       = \imageftbbox( $size, 0, $font, $text );
			$text_width = $bbox[2] - $bbox[0];
			$draw_x     = null === $x
				? (int) ( ( self::WIDTH - $text_width ) / 2 )
				: (int) ( $x - $text_width / 2 );
			\imagefttext( $image, $size, 0, $draw_x, $y, $color, $font, $text );
		} else {
			$builtin_size = \min( 5, \max( 1, (int) ( $size / 10 ) ) );
			$font_width   = \imagefontwidth( $builtin_size );
			$text_width   = $font_width * \strlen( $text );
			$draw_x       = null === $x
				? (int) ( ( self::WIDTH - $text_width ) / 2 )
				: (int) ( $x - $text_width / 2 );
			\imagestring( $image, $builtin_size, $draw_x, $y, $text, $color );
		}
	}

	/**
	 * Resolve colors from theme Global Styles or overrides.
	 *
	 * @return array Associative array with 'bg', 'fg', and 'muted' RGB arrays.
	 */
	private static function resolve_colors() {
		$bg_rgb = array( 255, 255, 255 );
		$fg_rgb = array( 17, 17, 17 );

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
			if ( ! empty( $palette[ $matches[1] ] ) ) {
				return self::parse_hex( $palette[ $matches[1] ] );
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

		$result = \sscanf( $hex, '%02x%02x%02x' );

		return ( 3 === \count( $result ) ) ? $result : false;
	}

	/**
	 * Resolve a TTF font file from the active theme or Font Library.
	 *
	 * @return string|false Path to a TTF file, or false if none found.
	 */
	private static function resolve_font() {
		$body_slug = '';
		$styles    = \wp_get_global_styles( array( 'typography' ) );
		if ( ! empty( $styles['fontFamily'] ) && \preg_match( '/--font-family--([a-z0-9-]+)/', $styles['fontFamily'], $matches ) ) {
			$body_slug = $matches[1];
		}

		$settings = \wp_get_global_settings();
		if ( ! empty( $settings['typography']['fontFamilies'] ) ) {
			$all_families = array();
			foreach ( $settings['typography']['fontFamilies'] as $families ) {
				foreach ( $families as $family ) {
					$all_families[] = $family;
				}
			}

			// Sort so the body font family is tried first.
			if ( $body_slug ) {
				\usort(
					$all_families,
					function ( $a, $b ) use ( $body_slug ) {
						return ( ( $a['slug'] ?? '' ) === $body_slug ? 0 : 1 ) - ( ( $b['slug'] ?? '' ) === $body_slug ? 0 : 1 );
					}
				);
			}

			$font = self::find_ttf_in_families( $all_families );
			if ( $font ) {
				return $font;
			}
		}

		// Try the Font Library (WP 6.5+).
		$font = self::find_ttf_in_font_library();
		if ( $font ) {
			return $font;
		}

		return false;
	}

	/**
	 * Find a TTF/OTF file in font family definitions.
	 *
	 * @param array $families The font families to search.
	 *
	 * @return string|false Path to TTF file or false.
	 */
	private static function find_ttf_in_families( $families ) {
		$theme_dir = \get_theme_root();

		foreach ( $families as $family ) {
			if ( empty( $family['fontFace'] ) ) {
				continue;
			}
			foreach ( $family['fontFace'] as $face ) {
				$src = \is_array( $face['src'] ) ? $face['src'][0] : $face['src'];

				if ( ! \preg_match( '/\.(ttf|otf)$/i', $src ) ) {
					continue;
				}

				// Resolve theme-relative paths.
				if ( 0 === \strpos( $src, 'file:./' ) ) {
					$src = \get_theme_file_path( \substr( $src, 7 ) );
				}

				// Only allow fonts within the themes directory for security.
				$real_path = \realpath( $src );
				if ( ! $real_path || 0 !== \strpos( $real_path, \realpath( $theme_dir ) ) ) {
					continue;
				}

				return $real_path;
			}
		}

		return false;
	}

	/**
	 * Find a TTF/OTF file from the WordPress Font Library.
	 *
	 * @return string|false Path to TTF file or false.
	 */
	private static function find_ttf_in_font_library() {
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

		return false;
	}
}
