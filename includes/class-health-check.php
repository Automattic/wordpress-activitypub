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
			'test'        => 'test_profile_url',
		);

		$enum = self::is_profile_url_accessible();

		if ( true !== $enum ) {
			$result['status']      = 'critical';
			$result['label']       = \__( 'Profile URL is not accessible', 'activitypub' );
			$result['description'] = \sprintf(
				'<p>%s</p>',
				\__( 'Your author URL is not accessible and/or does not return valid JSON. Please check if the author URL is accessible and does not redirect to another page (often done by SEO plugins).', 'activitypub' )
			);
		}

		return $result;
	}

	public static function is_profile_url_accessible() {
		$user = \wp_get_current_user();
		$author_url = \get_author_posts_url( $user->ID );
		$reference_author_url = self::get_author_posts_url( $user->ID, $user->user_nicename );

		// check for "author" in URL
		if ( $author_url !== $reference_author_url ) {
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
}
