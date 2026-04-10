<?php
/**
 * Cache CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use Activitypub\Cache\Avatar;
use Activitypub\Cache\Emoji;
use Activitypub\Cache\File;
use Activitypub\Cache\Media;

/**
 * Manage ActivityPub remote media cache.
 *
 * @package Activitypub
 */
class Cache_Command extends \WP_CLI_Command {

	/**
	 * Clear the remote media cache.
	 *
	 * Removes cached files from the uploads directory. By default clears all
	 * cache types, or specify --type to clear only a specific cache.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : The cache type to clear. If omitted, clears all caches.
	 * ---
	 * options:
	 *   - avatar
	 *   - media
	 *   - emoji
	 *   - all
	 * default: all
	 * ---
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear all caches
	 *     $ wp activitypub cache clear
	 *
	 *     # Clear only avatar cache
	 *     $ wp activitypub cache clear --type=avatar
	 *
	 *     # Clear emoji cache without confirmation
	 *     $ wp activitypub cache clear --type=emoji --yes
	 *
	 * @subcommand clear
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function clear( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$type = $assoc_args['type'] ?? 'all';

		$types_to_clear = array();
		if ( 'all' === $type ) {
			$types_to_clear = array( 'avatar', 'media', 'emoji' );
		} else {
			$types_to_clear = array( $type );
		}

		$type_label = 'all' === $type ? 'all cache types' : "{$type} cache";
		\WP_CLI::confirm( "Are you sure you want to clear {$type_label}?", $assoc_args );

		$total_cleared = 0;

		foreach ( $types_to_clear as $cache_type ) {
			$cleared        = $this->clear_cache_type( $cache_type );
			$total_cleared += $cleared;
			\WP_CLI::log( \sprintf( 'Cleared %d %s cache directories.', $cleared, $cache_type ) );
		}

		\WP_CLI::success( \sprintf( 'Cache cleared. Total directories removed: %d', $total_cleared ) );
	}

	/**
	 * Show cache status and statistics.
	 *
	 * Displays information about cached files including count and total size
	 * for each cache type.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show cache status
	 *     $ wp activitypub cache status
	 *
	 *     # Show status as JSON
	 *     $ wp activitypub cache status --format=json
	 *
	 * @subcommand status
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function status( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$upload_dir = \wp_upload_dir();

		$cache_types = array(
			'avatar' => array(
				'label' => 'Avatars',
				'path'  => Avatar::get_base_dir(),
			),
			'media'  => array(
				'label' => 'Post Media',
				'path'  => Media::get_base_dir(),
			),
			'emoji'  => array(
				'label' => 'Emoji',
				'path'  => Emoji::get_base_dir(),
			),
		);

		$data        = array();
		$total_files = 0;
		$total_size  = 0;

		foreach ( $cache_types as $type => $info ) {
			$dir   = $upload_dir['basedir'] . $info['path'];
			$stats = $this->get_directory_stats( $dir );

			$data[] = array(
				'type'    => $info['label'],
				'enabled' => $this->is_cache_enabled( $type ) ? 'Yes' : 'No',
				'files'   => $stats['files'],
				'size'    => \size_format( $stats['size'] ),
				'path'    => $info['path'],
			);

			$total_files += $stats['files'];
			$total_size  += $stats['size'];
		}

		// Add totals row for table format.
		if ( 'table' === ( $assoc_args['format'] ?? 'table' ) ) {
			$data[] = array(
				'type'    => '---',
				'enabled' => '---',
				'files'   => '---',
				'size'    => '---',
				'path'    => '---',
			);
			$data[] = array(
				'type'    => 'TOTAL',
				'enabled' => '',
				'files'   => $total_files,
				'size'    => \size_format( $total_size ),
				'path'    => '/activitypub/',
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		\WP_CLI\Utils\format_items( $format, $data, array( 'type', 'enabled', 'files', 'size', 'path' ) );
	}

	/**
	 * Clear a specific cache type.
	 *
	 * @param string $type The cache type (avatar, media, emoji).
	 *
	 * @return int Number of directories cleared.
	 */
	private function clear_cache_type( $type ) {
		$upload_dir = \wp_upload_dir();

		switch ( $type ) {
			case 'avatar':
				$base_dir = $upload_dir['basedir'] . Avatar::get_base_dir();
				break;
			case 'media':
				$base_dir = $upload_dir['basedir'] . Media::get_base_dir();
				break;
			case 'emoji':
				$base_dir = $upload_dir['basedir'] . Emoji::get_base_dir();
				break;
			default:
				return 0;
		}

		if ( ! \is_dir( $base_dir ) ) {
			return 0;
		}

		// Count subdirectories before clearing.
		$subdirs = \glob( $base_dir . '/*', GLOB_ONLYDIR );
		$count   = $subdirs ? \count( $subdirs ) : 0;

		// Remove all subdirectories using the cache's native delete_directory helper.
		foreach ( $subdirs as $subdir ) {
			File::delete_directory( $subdir );
		}

		// Clean up legacy avatar URL meta from previous versions.
		if ( 'avatar' === $type ) {
			\delete_metadata( 'post', 0, '_activitypub_avatar_url', '', true );
		}

		return $count;
	}

	/**
	 * Get statistics for a cache directory.
	 *
	 * @param string $dir The directory path.
	 *
	 * @return array {
	 *     Directory statistics.
	 *
	 *     @type int $files Total number of files.
	 *     @type int $size  Total size in bytes.
	 * }
	 */
	private function get_directory_stats( $dir ) {
		$stats = array(
			'files' => 0,
			'size'  => 0,
		);

		if ( ! \is_dir( $dir ) ) {
			return $stats;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				++$stats['files'];
				$stats['size'] += $file->getSize();
			}
		}

		return $stats;
	}

	/**
	 * Check if a cache type is enabled.
	 *
	 * @param string $type The cache type.
	 *
	 * @return bool True if enabled.
	 */
	private function is_cache_enabled( $type ) {
		// Check global cache enablement (includes constant and activitypub_remote_cache_enabled filter).
		if ( ! \Activitypub\Cache::is_enabled() ) {
			return false;
		}

		// Check type-specific filter.
		return (bool) \apply_filters( "activitypub_cache_{$type}_enabled", true );
	}
}
