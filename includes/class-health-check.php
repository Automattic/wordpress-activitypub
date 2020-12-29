<?php
namespace Activitypub;

/**
 * ActivityPub Health_Check Class
 *
 * @author Matthias Pfefferle
 */
class Health_Check {
	public static function init() {
		\add_filter( 'site_status_tests', array( '\Activitypub\Health_Check', 'add_tests' ) );
	}

	public static function add_tests( $tests ) {
		$tests['direct']['activitypub_test_profile_url'] = array(
			'label' => \__( 'Profile URL test', 'activitypub' ),
			'test'  => array( '\Activitypub\Health_Check', 'test_profile_url' ),
		);

		//$tests['direct']['activitypub_test_profile_url2'] = array(
		//	'label' => __( 'Profile URL Test', 'activitypub' ),
		//	'test'  => array( '\Activitypub\Health_Check', 'test_profile_url' ),
		//);

		return $tests;
	}

	public static function test_profile_url() {
		$result = array(
			'label'       => \__( 'Profile URL accessible', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Your profile URL is accessible and do not redirect to the home page.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_profile_url',
		);

		$enum = self::is_profile_url_accessible();

		if ( true !== $enum ) {
			$result['status']      = 'critical';
			$result['label']       = \__( 'Profile URL is not accessible', 'activitypub' );
			$result['description'] = \sprintf(
				'<p>%s</p>',
				\__( 'Authorization Headers are being blocked by your hosting provider. This will cause IndieAuth to fail.', 'activitypub' )
			);
		}

		return $result;
	}

	public static function is_profile_url_accessible() {
		$user = \wp_get_current_user();
		$author_url = \get_author_posts_url( $user->ID );

		// check for "author" in URL
		if ( false === \strpos( $author_url, 'author' ) ) {
			return false;
		}

		// try to access author URL
		$response = \wp_remote_get( $author_url, array( 'headers' => array( 'Accept' => 'application/activity+json' ) ) );

		if ( \is_wp_error( $response ) ) {
			return false;
		}

		// check if response is JSON
		$body = \wp_remote_retrieve_body( $response );

		if ( ! \is_string( $body ) || ! \is_array( \json_decode( $body, true ) ) ) {
			return false;
		}

		return true;
	}
}
