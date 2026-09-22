<?php
/**
 * A file cache with the download and the filesystem stubbed out.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Cache\File;

require_once __DIR__ . '/class-vanishing-filesystem.php';

/**
 * Concrete cache used to exercise the shared File behavior without touching the network.
 */
class Stub_File_Cache extends File {
	/**
	 * Cache type.
	 *
	 * @return string The type.
	 */
	public static function get_type() {
		return 'test';
	}

	/**
	 * Base directory.
	 *
	 * @return string The base directory.
	 */
	public static function get_base_dir() {
		return '/activitypub/test/';
	}

	/**
	 * Context.
	 *
	 * @return string The context.
	 */
	public static function get_context() {
		return 'test';
	}

	/**
	 * Maximum dimension.
	 *
	 * @return int The maximum dimension.
	 */
	public static function get_max_dimension() {
		return 96;
	}

	/**
	 * Return the filesystem that loses the directory on its first move.
	 *
	 * @return \WP_Filesystem_Direct The filesystem.
	 */
	protected static function get_filesystem() {
		return new Vanishing_Filesystem();
	}

	/**
	 * Hand back a local file instead of downloading one.
	 *
	 * @param string $url The remote URL.
	 *
	 * @return array The file and its MIME type.
	 */
	protected static function download_and_validate( $url ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$tmp_file = \wp_tempnam( 'activitypub-test' ) . '.png';

		// A 1x1 transparent PNG.
		$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		\file_put_contents( $tmp_file, \base64_decode( $png ) );

		return array(
			'file'      => $tmp_file,
			'mime_type' => 'image/png',
		);
	}

	/**
	 * Skip image optimization, which is not what these tests are about.
	 *
	 * @param string $file_path     The file path.
	 * @param int    $max_dimension The maximum dimension.
	 *
	 * @return string The unchanged file path.
	 */
	protected static function optimize_image( $file_path, $max_dimension ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $file_path;
	}
}
