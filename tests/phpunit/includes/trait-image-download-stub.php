<?php
/**
 * Image download stub trait.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * A downloaded test image, for the `activitypub_pre_download_url` filter.
 */
trait Image_Download_Stub {
	/**
	 * Copy a test image to a temporary file and describe it the way download_and_validate() does.
	 *
	 * The file is named with its format's extension, like validate_mime_type() leaves a download,
	 * because the image editor picks its decoder by extension.
	 *
	 * @param string $asset Optional. File name in tests/phpunit/data/assets. Default 'test.jpg'.
	 *
	 * @return array {
	 *     @type string $file      Path to the temporary file.
	 *     @type string $mime_type The image's MIME type.
	 * }
	 */
	protected static function image_download( $asset = 'test.jpg' ) {
		$dir  = \get_temp_dir();
		$file = $dir . \wp_unique_filename( $dir, $asset );
		\copy( AP_TESTS_DIR . '/data/assets/' . $asset, $file );

		return array(
			'file'      => $file,
			'mime_type' => \wp_check_filetype( $file )['type'],
		);
	}
}
