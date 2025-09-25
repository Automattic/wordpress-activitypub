<?php
/**
 * Health_Check class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Http;
use Activitypub\Sanitize;
use Activitypub\Webfinger;

use function Activitypub\user_can_activitypub;

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
	 * Count critical and recommended results.
	 *
	 * @param string $type The type of results to count.
	 *
	 * @return int|int[] The number of critical and recommended results.
	 */
	public static function count_results( $type = 'all' ) {
		$tests = self::add_tests( array() );

		// Count critical and recommended results.
		$good        = 0;
		$critical    = 0;
		$recommended = 0;

		foreach ( $tests['direct'] as $test ) {
			// Run tests.
			$result = call_user_func( $test['test'] );

			if ( 'critical' === $result['status'] ) {
				++$critical;
			}

			if ( 'recommended' === $result['status'] ) {
				++$recommended;
			}

			if ( 'good' === $result['status'] ) {
				++$good;
			}
		}

		$results = array(
			'good'        => $good,
			'critical'    => $critical,
			'recommended' => $recommended,
		);

		if ( 'all' === $type ) {
			return $results;
		}

		return $results[ $type ];
	}

	/**
	 * Add tests to the Site Health Check.
	 *
	 * @param array $tests The test array.
	 *
	 * @return array The filtered test array.
	 */
	public static function add_tests( $tests ) {
		if ( user_can_activitypub( \get_current_user_id() ) ) {
			$tests['direct']['activitypub_test_author_url'] = array(
				'label' => \__( 'Author URL Test', 'activitypub' ),
				'test'  => array( self::class, 'test_author_url' ),
			);
		}

		$tests['direct']['activitypub_test_webfinger'] = array(
			'label' => __( 'WebFinger Test', 'activitypub' ),
			'test'  => array( self::class, 'test_webfinger' ),
		);

		$tests['direct']['activitypub_test_threaded_comments'] = array(
			'label' => \__( 'Threaded Comments Test', 'activitypub' ),
			'test'  => array( self::class, 'test_threaded_comments' ),
		);

		$tests['direct']['activitypub_test_pretty_permalinks'] = array(
			'label' => \__( 'Pretty Permalinks Test', 'activitypub' ),
			'test'  => array( self::class, 'test_pretty_permalinks' ),
		);

		$tests['direct']['activitypub_check_for_captcha_plugins'] = array(
			'label' => \__( 'Check for Captcha Plugins', 'activitypub' ),
			'test'  => array( self::class, 'test_check_for_captcha_plugins' ),
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

			$data       = $url->get_error_data();
			$author_url = $resource;
			if ( isset( $data['data'] ) && \is_string( $data['data'] ) ) {
				$author_url = $data['data'];
			}

			$health_messages = array(
				'webfinger_url_not_accessible'   => \sprintf(
					$not_accessible,
					$author_url
				),
				'webfinger_url_invalid_response' => \sprintf(
					// translators: %s: Author URL.
					$invalid_response,
					$author_url
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
		$info['activitypub'] = array(
			'label'  => \__( 'ActivityPub', 'activitypub' ),
			'fields' => array(),
		);

		$actor = Actors::get_by_id( \get_current_user_id() );

		if ( $actor && ! is_wp_error( $actor ) ) {
			$info['activitypub']['fields']['webfinger'] = array(
				'label'   => \__( 'WebFinger Resource', 'activitypub' ),
				'value'   => Webfinger::get_user_resource( wp_get_current_user()->ID ),
				'private' => false,
			);

			$info['activitypub']['fields']['author_url'] = array(
				'label'   => \__( 'Author URL', 'activitypub' ),
				'value'   => $actor->get_url(),
				'private' => false,
			);

			$info['activitypub']['fields']['author_id'] = array(
				'label'   => \__( 'Author ID', 'activitypub' ),
				'value'   => $actor->get_id(),
				'private' => false,
			);
		}

		$info['activitypub']['fields']['actor_mode'] = array(
			'label'   => \__( 'Actor Mode', 'activitypub' ),
			'value'   => \esc_attr( \get_option( 'activitypub_actor_mode' ) ),
			'private' => false,
		);

		$info['activitypub']['fields']['object_type'] = array(
			'label'   => \__( 'Object Type', 'activitypub' ),
			'value'   => \esc_attr( \get_option( 'activitypub_object_type' ) ),
			'private' => false,
		);

		$info['activitypub']['fields']['post_template'] = array(
			'label'   => \__( 'Post Template', 'activitypub' ),
			'value'   => \esc_attr( \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT ) ),
			'private' => false,
		);

		$info['activitypub']['fields']['activitypub_outbox_purge_days'] = array(
			'label'   => \__( 'Outbox Retention Period', 'activitypub' ),
			'value'   => \esc_attr( (int) \get_option( 'activitypub_outbox_purge_days', 180 ) ),
			'private' => false,
		);

		$info['activitypub']['fields']['vary_header'] = array(
			'label'   => \__( 'Vary Header', 'activitypub' ),
			'value'   => \esc_attr( (int) \get_option( 'activitypub_vary_header', '1' ) ),
			'private' => false,
		);

		$info['activitypub']['fields']['content_negotiation'] = array(
			'label'   => \__( 'Content Negotiation', 'activitypub' ),
			'value'   => \esc_attr( (int) \get_option( 'activitypub_content_negotiation', '1' ) ),
			'private' => false,
		);

		$info['activitypub']['fields']['authorized_fetch'] = array(
			'label'   => \__( 'Authorized Fetch', 'activitypub' ),
			'value'   => \esc_attr( (int) \get_option( 'activitypub_authorized_fetch', '0' ) ),
			'private' => false,
		);

		$info['activitypub']['fields']['shared_inbox'] = array(
			'label'   => \__( 'Shared Inbox', 'activitypub' ),
			'value'   => \esc_attr( (int) \get_option( 'activitypub_shared_inbox', '0' ) ),
			'private' => false,
		);

		$constants = get_defined_constants( true );

		if ( ! isset( $constants['user'] ) ) {
			return $info;
		}

		foreach ( $constants['user'] as $key => $value ) {
			if ( ! str_starts_with( $key, 'ACTIVITYPUB_' ) ) {
				continue;
			}

			$info['activitypub']['fields'][ $key ] = array(
				'label'   => \esc_attr( $key ),
				'value'   => Sanitize::constant_value( $value ),
				'private' => false,
			);
		}

		return $info;
	}

	/**
	 * Threaded Comments tests.
	 *
	 * @return array The test result.
	 */
	public static function test_threaded_comments() {
		$result = array(
			'label'       => \__( 'Threaded (nested) comments enabled', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Threaded (nested) comments are enabled.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_threaded_comments',
		);

		if ( '1' !== get_option( 'thread_comments', '0' ) ) {
			$result['status']         = 'recommended';
			$result['label']          = \__( 'Threaded (nested) comments are not enabled', 'activitypub' );
			$result['badge']['color'] = 'orange';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				\__( 'This is particularly important for fediverse users, as they rely on the visual hierarchy to understand conversation threads across different platforms. Without threaded comments, it becomes much more difficult to follow discussions that span multiple platforms in the fediverse.', 'activitypub' )
			);
			$result['actions']        = sprintf(
				'<p>%s</p>',
				sprintf(
					// translators: %s: Discussion settings URL.
					\__( 'You can enable them in the <a href="%s">Discussion Settings</a>.', 'activitypub' ),
					esc_url( admin_url( 'options-discussion.php' ) )
				)
			);
		}

		return $result;
	}

	/**
	 * Pretty Permalinks tests.
	 *
	 * @return array The test result.
	 */
	public static function test_pretty_permalinks() {
		$result = array(
			'label'       => \__( 'Pretty Permalinks enabled', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Your pretty permalinks are enabled and working correctly.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_pretty_permalinks',
		);

		$permalink_structure = \get_option( 'permalink_structure' );
		if ( empty( $permalink_structure ) ) {
			$result['status']         = 'critical';
			$result['label']          = \__( 'Pretty Permalinks are not enabled.', 'activitypub' );
			$result['badge']['color'] = 'red';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Permalink settings URL. */
					\__( 'ActivityPub needs SEO-friendly URLs to work properly. Please <a href="%s">update your permalink structure</a> to an option other than Plain.', 'activitypub' ),
					esc_url( admin_url( 'options-permalink.php' ) )
				)
			);
		} elseif ( str_starts_with( $permalink_structure, '/index.php' ) ) {
			$result['status']         = 'critical';
			$result['label']          = \__( 'Your permalink structure needs to be updated for ActivityPub to work properly.', 'activitypub' );
			$result['badge']['color'] = 'red';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Permalink settings URL. */
					\__( 'Your current permalink structure includes <code>/index.php</code> which is not compatible with ActivityPub. Please <a href="%s">update your permalink settings</a> to use a standard format without <code>/index.php</code>.', 'activitypub' ),
					esc_url( admin_url( 'options-permalink.php' ) )
				)
			);
		}

		return $result;
	}

	/**
	 * Check for Captcha Plugins.
	 *
	 * @return array The test result.
	 */
	public static function test_check_for_captcha_plugins() {
		$result = array(
			'label'       => \__( 'Check for Captcha Plugins', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'No Captcha plugins were found that could interfere with ActivityPub functionality.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_check_for_captcha_plugins',
		);

		$active_plugins = (array) \get_option( 'active_plugins', array() );

		// search for the word 'captcha' in the list of active plugins.
		$captcha_plugins = array_filter(
			$active_plugins,
			function ( $plugin ) {
				return \str_contains( strtolower( $plugin ), 'captcha' );
			}
		);

		if ( ! $captcha_plugins ) {
			return $result;
		}

		// Get nice plugin names instead of file paths using WordPress built-in functions.
		$all_plugins          = \get_plugins();
		$captcha_plugin_names = array_map(
			function ( $plugin_file ) use ( $all_plugins ) {
				if ( isset( $all_plugins[ $plugin_file ]['Name'] ) ) {
					return $all_plugins[ $plugin_file ]['Name'];
				}
				return false;
			},
			$captcha_plugins
		);

		$result['status']         = 'recommended';
		$result['label']          = \__( 'Captcha plugins detected', 'activitypub' );
		$result['badge']['color'] = 'orange';
		$result['description']    = \sprintf(
			'<p>%s</p><p>%s</p>',
			\sprintf(
				/* translators: %s: List of captcha plugins. */
				\esc_html__( 'The following Captcha plugins are active and may interfere with ActivityPub functionality: %s', 'activitypub' ),
				implode( ', ', array_map( 'esc_html', array_filter( $captcha_plugin_names ) ) )
			),
			\__( 'Captcha plugins require verification for comment submissions, but some may not distinguish between regular comments and those sent via an API (such as from ActivityPub). As a result, federated comments might be blocked because they cannot provide a Captcha response. If you experience missing comments, try disabling the Captcha plugin to determine if it resolves the issue.', 'activitypub' )
		);
		$result['actions'] = \sprintf(
			'<p>%s</p>',
			\sprintf(
				// translators: %s: Plugin page URL.
				\__( 'They can be disabled from the <a href="%s">Plugin Page</a>.', 'activitypub' ),
				esc_url( admin_url( 'plugins.php?s=captcha&plugin_status=all' ) )
			)
		);

		return $result;
	}
}
