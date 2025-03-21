<?php
/**
 * Health_Check class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Webfinger;
use Activitypub\Http;
use Activitypub\Collection\Actors;
use Activitypub\Sanitize;

use function Activitypub\is_user_disabled;

/**
 * ActivityPub Health_Check Class.
 *
 * @author Matthias Pfefferle
 */
class Health_Check {

	/**
	 * Initialize health checks.
	 */
	public static function init() {
		\add_filter( 'site_status_tests', array( self::class, 'add_tests' ) );
		\add_filter( 'debug_information', array( self::class, 'debug_information' ) );
	}

	/**
	 * Add tests to the Site Health Check.
	 *
	 * @param array $tests The test array.
	 *
	 * @return array The filtered test array.
	 */
	public static function add_tests( $tests ) {
		if ( ! is_user_disabled( get_current_user_id() ) ) {
			$tests['direct']['activitypub_test_author_url'] = array(
				'label' => \__( 'Author URL test', 'activitypub' ),
				'test'  => array( self::class, 'test_author_url' ),
			);
		}

		$tests['direct']['activitypub_test_webfinger'] = array(
			'label' => __( 'WebFinger Test', 'activitypub' ),
			'test'  => array( self::class, 'test_webfinger' ),
		);

		return $tests;
	}

	/**
	 * Author URL tests.
	 *
	 * @return array The test result.
	 */
	public static function test_author_url() {
		$result = array(
			'label'       => \__( 'Author URL accessible', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Your author URL is accessible and supports the required "Accept" header.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_author_url',
		);

		$check = self::is_author_url_accessible();

		if ( true === $check ) {
			return $result;
		}

		$result['status']         = 'critical';
		$result['label']          = \__( 'Author URL is not accessible', 'activitypub' );
		$result['badge']['color'] = 'red';
		$result['description']    = \sprintf(
			'<p>%s</p>',
			$check->get_error_message()
		);

		return $result;
	}

	/**
	 * System Cron tests.
	 *
	 * @return array The test result.
	 */
	public static function test_system_cron() {
		$result = array(
			'label'       => \__( 'System Task Scheduler configured', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\esc_html__( 'You seem to use the System Task Scheduler to process WP_Cron tasks.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_system_cron',
		);

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return $result;
		}

		$result['status']         = 'recommended';
		$result['label']          = \__( 'System Task Scheduler not configured', 'activitypub' );
		$result['badge']['color'] = 'orange';
		$result['description']    = \sprintf(
			'<p>%s</p>',
			\__( 'Enhance your WordPress site’s performance and mitigate potential heavy loads caused by plugins like ActivityPub by setting up a system cron job to run WP Cron. This ensures scheduled tasks are executed consistently and reduces the reliance on website traffic for trigger events.', 'activitypub' )
		);
		$result['actions']       .= sprintf(
			'<p><a href="%s" target="_blank" rel="noopener">%s<span class="screen-reader-text"> %s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
			esc_url( __( 'https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/', 'activitypub' ) ),
			__( 'Learn how to hook the WP-Cron into the System Task Scheduler.', 'activitypub' ),
			/* translators: Hidden accessibility text. */
			__( '(opens in a new tab)', 'activitypub' )
		);

		return $result;
	}

	/**
	 * WebFinger tests.
	 *
	 * @return array The test result.
	 */
	public static function test_webfinger() {
		$result = array(
			'label'       => \__( 'WebFinger endpoint', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Your WebFinger endpoint is accessible and returns the correct information.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_webfinger',
		);

		$check = self::is_webfinger_endpoint_accessible();

		if ( true === $check ) {
			return $result;
		}

		$result['status']         = 'critical';
		$result['label']          = \__( 'WebFinger endpoint is not accessible', 'activitypub' );
		$result['badge']['color'] = 'red';
		$result['description']    = \sprintf(
			'<p>%s</p>',
			$check->get_error_message()
		);

		return $result;
	}

	/**
	 * Check if `author_posts_url` is accessible and that request returns correct JSON.
	 *
	 * @return bool|\WP_Error True if the author URL is accessible, WP_Error otherwise.
	 */
	public static function is_author_url_accessible() {
		$actor = Actors::get_by_id( \get_current_user_id() );

		// Try to access author URL.
		$response = Http::get_remote_object( $actor->get_id() );

		if ( \is_wp_error( $response ) ) {
			return new \WP_Error(
				'author_url_not_accessible',
				\sprintf(
					// translators: %s: Author URL.
					\__(
						'Your author URL <code>%s</code> is not accessible. Please check your WordPress setup or permalink structure. If the setup seems fine, maybe check if a plugin might restrict the access.',
						'activitypub'
					),
					$actor->get_id()
				)
			);
		}

		return true;
	}

	/**
	 * Check if WebFinger endpoint is accessible and profile request returns correct JSON
	 *
	 * @return boolean|\WP_Error
	 */
	public static function is_webfinger_endpoint_accessible() {
		$user     = Actors::get_by_id( Actors::APPLICATION_USER_ID );
		$resource = $user->get_webfinger();

		$url = Webfinger::resolve( $resource );
		if ( \is_wp_error( $url ) ) {
			$allowed = array( 'code' => array() );

			$not_accessible = wp_kses(
				// translators: %s: Author URL.
				\__(
					'Your WebFinger endpoint <code>%s</code> is not accessible. Please check your WordPress setup or permalink structure.',
					'activitypub'
				),
				$allowed
			);
			$invalid_response = wp_kses(
				// translators: %s: Author URL.
				\__(
					'Your WebFinger endpoint <code>%s</code> does not return valid JSON for <code>application/jrd+json</code>.',
					'activitypub'
				),
				$allowed
			);

			$health_messages = array(
				'webfinger_url_not_accessible'   => \sprintf(
					$not_accessible,
					$url->get_error_data()['data']
				),
				'webfinger_url_invalid_response' => \sprintf(
					// translators: %s: Author URL.
					$invalid_response,
					$url->get_error_data()['data']
				),
			);
			$message         = null;
			if ( isset( $health_messages[ $url->get_error_code() ] ) ) {
				$message = $health_messages[ $url->get_error_code() ];
			}

			return new \WP_Error(
				$url->get_error_code(),
				$message,
				$url->get_error_data()
			);
		}

		return true;
	}

	/**
	 * Static function for generating site debug data when required.
	 *
	 * @param array $info The debug information to be added to the core information page.
	 *
	 * @return array The filtered information
	 */
	public static function debug_information( $info ) {
		$actor = Actors::get_by_id( \get_current_user_id() );

		$info['activitypub'] = array(
			'label'  => __( 'ActivityPub', 'activitypub' ),
			'fields' => array(
				'webfinger'  => array(
					'label'   => __( 'WebFinger Resource', 'activitypub' ),
					'value'   => Webfinger::get_user_resource( wp_get_current_user()->ID ),
					'private' => false,
				),
				'author_url' => array(
					'label'   => __( 'Author URL', 'activitypub' ),
					'value'   => $actor->get_url(),
					'private' => false,
				),
				'author_id'  => array(
					'label'   => __( 'Author ID', 'activitypub' ),
					'value'   => $actor->get_id(),
					'private' => false,
				),
			),
		);

		$consts = get_defined_constants( true );

		if ( ! isset( $consts['user'] ) ) {
			return $info;
		}

		foreach ( $consts['user'] as $key => $value ) {
			if ( ! str_starts_with( $key, 'ACTIVITYPUB_' ) ) {
				continue;
			}

			$info['activitypub']['fields'][ $key ] = array(
				'label'   => esc_attr( $key ),
				'value'   => Sanitize::constant_value( $value ),
				'private' => false,
			);
		}

		return $info;
	}
}
