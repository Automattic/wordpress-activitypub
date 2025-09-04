<?php
/**
 * Yoast SEO integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

/**
 * Yoast SEO integration class.
 */
class Yoast_Seo {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'site_status_tests', array( self::class, 'add_site_health_tests' ) );
	}

	/**
	 * Add Yoast-specific site health tests.
	 *
	 * @param array $tests The site health tests array.
	 *
	 * @return array The modified tests array.
	 */
	public static function add_site_health_tests( $tests ) {
		// Only add the test if attachment post type is supported by ActivityPub.
		if ( self::is_attachment_supported() ) {
			$tests['direct']['activitypub_yoast_seo_media_pages'] = array(
				'label' => \__( 'Yoast SEO Media Pages Test', 'activitypub' ),
				'test'  => array( self::class, 'test_yoast_seo_media_pages' ),
			);
		}

		return $tests;
	}

	/**
	 * Test if Yoast's "Enable media pages" setting is properly configured.
	 *
	 * @return array The test result.
	 */
	public static function test_yoast_seo_media_pages() {
		$result = array(
			'label'       => \__( 'Yoast SEO media pages are enabled', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Media pages are enabled in Yoast SEO, which allows media attachments to be federated and interacted with through ActivityPub.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_yoast_seo_media_pages',
		);

		if ( self::is_media_pages_disabled() ) {
			$result['status']         = 'recommended';
			$result['label']          = \__( 'Yoast SEO media pages should be enabled', 'activitypub' );
			$result['badge']['color'] = 'orange';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				\__( 'Yoast SEO&#8217;s &#8220;Enable media pages&#8221; setting is currently disabled. Since you have media attachments configured to be federated through ActivityPub, you should enable media pages so that media can be properly accessed and interacted with by ActivityPub clients and other federated platforms.', 'activitypub' )
			);
			$result['actions']        = \sprintf(
				'<p>%s</p>',
				\sprintf(
					// translators: %s: Yoast SEO settings URL.
					\__( 'You can enable media pages in <a href="%s">Yoast SEO > Settings > Advanced > Media pages</a>.', 'activitypub' ),
					\esc_url( \admin_url( 'admin.php?page=wpseo_page_settings#/media-pages' ) )
				)
			);
		}

		return $result;
	}

	/**
	 * Check if Yoast SEO media pages are disabled.
	 *
	 * @return bool True if media pages are disabled, false otherwise.
	 */
	public static function is_media_pages_disabled() {
		// Get Yoast SEO options.
		$yoast_options = \get_option( 'wpseo_titles' );

		if ( ! is_array( $yoast_options ) ) {
			return false;
		}

		// Check if disable-attachment is set to true (media pages disabled).
		return isset( $yoast_options['disable-attachment'] ) && true === $yoast_options['disable-attachment'];
	}

	/**
	 * Check if attachment post type is supported by ActivityPub.
	 *
	 * @return bool True if attachment is supported, false otherwise.
	 */
	private static function is_attachment_supported() {
		$supported_post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) );
		return in_array( 'attachment', $supported_post_types, true );
	}
}
