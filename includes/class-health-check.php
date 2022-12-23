<?php
namespace Activitypub;

/**
 * ActivityPub Health_Check Class
 *
 * @author Matthias Pfefferle
 */
class Health_Check {

	/**
	 * Initialize health checks
	 *
	 * @return void
	 */
	public static function init() {
		\add_filter( 'site_status_tests', array( '\Activitypub\Health_Check', 'add_tests' ) );
		\add_filter( 'debug_information', array( '\Activitypub\Health_Check', 'debug_information' ) );
	}

	public static function add_tests( $tests ) {
		$tests['direct']['activitypub_test_author_url'] = array(
			'label' => \__( 'Author URL test', 'activitypub' ),
			'test'  => array( '\Activitypub\Health_Check', 'test_author_url' ),
		);

		$tests['direct']['activitypub_test_webfinger'] = array(
			'label' => __( 'WebFinger Test', 'activitypub' ),
			'test'  => array( '\Activitypub\Health_Check', 'test_webfinger' ),
		);

		return $tests;
	}

	/**
	 * Author URL tests
	 *
	 * @return void
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
	 * WebFinger tests
	 *
	 * @return void
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
				\__( 'Your WebFinger endpoint is accessible and returns the correct informations.', 'activitypub' )
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
	 * Check if `author_posts_url` is accessible and that requerst returns correct JSON
	 *
	 * @return boolean|WP_Error
	 */
	public static function is_author_url_accessible() {
		$user = \wp_get_current_user();
		$author_url = \get_author_posts_url( $user->ID );
		$reference_author_url = self::get_author_posts_url( $user->ID, $user->user_nicename );

		// check for "author" in URL
		if ( $author_url !== $reference_author_url ) {
			return new \WP_Error(
				'author_url_not_accessible',
				\sprintf(
					// translators: %s: Author URL
					\__(
						'<p>Your author URL <code>%s</code> was replaced, this is often done by plugins.</p>',
						'activitypub'
					),
					$author_url
				)
			);
		}

		// try to access author URL
		$response = \wp_remote_get(
			$author_url,
			array(
				'headers' => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 0,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return new \WP_Error(
				'author_url_not_accessible',
				\sprintf(
					// translators: %s: Author URL
					\__(
						'<p>Your author URL <code>%s</code> is not accessible. Please check your WordPress setup or permalink structure. If the setup seems fine, maybe check if a plugin might restrict the access.</p>',
						'activitypub'
					),
					$author_url
				)
			);
		}

		$response_code = \wp_remote_retrieve_response_code( $response );

		// check for redirects
		if ( \in_array( $response_code, array( 301, 302, 307, 308 ), true ) ) {
			return new \WP_Error(
				'author_url_not_accessible',
				\sprintf(
					// translators: %s: Author URL
					\__(
						'<p>Your author URL <code>%s</code> is redirecting to another page, this is often done by SEO plugins like "Yoast SEO".</p>',
						'activitypub'
					),
					$author_url
				)
			);
		}

		// check if response is JSON
		$body = \wp_remote_retrieve_body( $response );

		if ( ! \is_string( $body ) || ! \is_array( \json_decode( $body, true ) ) ) {
			return new \WP_Error(
				'author_url_not_accessible',
				\sprintf(
					// translators: %s: Author URL
					\__(
						'<p>Your author URL <code>%s</code> does not return valid JSON for <code>application/activity+json</code>. Please check if your hosting supports alternate <code>Accept</code> headers.</p>',
						'activitypub'
					),
					$author_url
				)
			);
		}

		return true;
	}

	/**
	 * Check if WebFinger endoint is accessible and profile requerst returns correct JSON
	 *
	 * @return boolean|WP_Error
	 */
	public static function is_webfinger_endpoint_accessible() {
		$user    = \wp_get_current_user();
		$account = \Activitypub\get_webfinger_resource( $user->ID );

		$url = \Activitypub\Webfinger::resolve( $account );
		if ( \is_wp_error( $url ) ) {
			$health_messages = array(
				'webfinger_url_not_accessible' => \sprintf(
					// translators: %s: Author URL
					\__(
						'<p>Your WebFinger endpoint <code>%s</code> is not accessible. Please check your WordPress setup or permalink structure.</p>',
						'activitypub'
					),
					$url->get_error_data()
				),
				'webfinger_url_invalid_response' => \sprintf(
					// translators: %s: Author URL
					\__(
						'<p>Your WebFinger endpoint <code>%s</code> does not return valid JSON for <code>application/jrd+json</code>.</p>',
						'activitypub'
					),
					$url->get_error_data()
				),
			);
			$message = null;
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
	 * Retrieve the URL to the author page for the user with the ID provided.
	 *
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 *
	 * @param int    $author_id       Author ID.
	 * @param string $author_nicename Optional. The author's nicename (slug). Default empty.
	 *
	 * @return string The URL to the author's page.
	 */
	public static function get_author_posts_url( $author_id, $author_nicename = '' ) {
		global $wp_rewrite;
		$auth_id = (int) $author_id;
		$link = $wp_rewrite->get_author_permastruct();

		if ( empty( $link ) ) {
			$file = home_url( '/' );
			$link = $file . '?author=' . $auth_id;
		} else {
			if ( '' === $author_nicename ) {
				$user = get_userdata( $author_id );
				if ( ! empty( $user->user_nicename ) ) {
					$author_nicename = $user->user_nicename;
				}
			}
			$link = str_replace( '%author%', $author_nicename, $link );
			$link = home_url( user_trailingslashit( $link ) );
		}

		return $link;
	}

	/**
	 * Static function for generating site debug data when required.
	 *
	 * @param array $info The debug information to be added to the core information page.
	 * @return array The filtered informations
	 */
	public static function debug_information( $info ) {
		$info['activitypub'] = array(
			'label'  => __( 'ActivityPub', 'activitypub' ),
			'fields' => array(
				'webfinger' => array(
					'label'   => __( 'WebFinger Resource', 'activitypub' ),
					'value'   => \Activitypub\Webfinger::get_user_resource( wp_get_current_user()->ID ),
					'private' => true,
				),
				'author_url' => array(
					'label'   => __( 'Author URL', 'activitypub' ),
					'value'   => get_author_posts_url( wp_get_current_user()->ID ),
					'private' => true,
				),
			),
		);

		return $info;
	}
}
