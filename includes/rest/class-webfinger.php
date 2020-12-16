<?php
namespace Activitypub\Rest;

/**
 * ActivityPub WebFinger REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( '\Activitypub\Rest\Webfinger', 'register_routes' ) );
		\add_action( 'webfinger_user_data', array( '\Activitypub\Rest\Webfinger', 'add_webfinger_discovery' ), 10, 3 );
		//\add_action( 'webfinger_lookup', array( '\Activitypub\Rest\Webfinger', 'webfinger_lookup' ), 10, 3 );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			'activitypub/1.0', '/webfinger', array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( '\Activitypub\Rest\Webfinger', 'webfinger' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Render JRD file
	 *
	 * @param  WP_REST_Request   $request
	 * @return WP_REST_Response
	 */
	public static function webfinger( $request ) {
		$resource = $request->get_param( 'resource' );

		$matches = array();
		$matched = \preg_match( '/^acct:([^@]+)@(.+)$/', $resource, $matches );

		if ( ! $matched ) {
			return new \WP_Error( 'activitypub_unsupported_resource', \__( 'Resource is invalid', 'activitypub' ), array( 'status' => 400 ) );
		}

		$resource_identifier = $matches[1];
		$resource_host = $matches[2];

		if ( \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) !== $resource_host ) {
			return new \WP_Error( 'activitypub_wrong_host', \__( 'Resource host does not match blog host', 'activitypub' ), array( 'status' => 404 ) );
		}

		$user = \get_user_by( 'login', \esc_sql( $resource_identifier ) );

		if ( ! $user ) {
			return new \WP_Error( 'activitypub_user_not_found', \__( 'User not found', 'activitypub' ), array( 'status' => 404 ) );
		}

		$json = array(
			'subject' => $resource,
			'aliases' => array(
				\get_author_posts_url( $user->ID ),
			),
			'links' => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => \get_author_posts_url( $user->ID ),
				),
				array(
					'rel'  => 'http://webfinger.net/rel/profile-page',
					'type' => 'text/html',
					'href' => \get_author_posts_url( $user->ID ),
				),
			),
		);

		return new \WP_REST_Response( $json, 200 );
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['resource'] = array(
			'required' => true,
			'type' => 'string',
			'pattern' => '^acct:([^@]+)@(.+)$',
		);

		return $params;
	}

	/**
	 * Add WebFinger discovery links
	 *
	 * @param array   $array    the jrd array
	 * @param string  $resource the WebFinger resource
	 * @param WP_User $user     the WordPress user
	 */
	public static function add_webfinger_discovery( $array, $resource, $user ) {
		$array['links'][] = array(
			'rel'  => 'self',
			'type' => 'application/activity+json',
			'href' => \get_author_posts_url( $user->ID ),
		);

		return $array;
	}

	public static function webfinger_lookup( $webfinger ) {
		
		if ( \substr($webfinger, 0, 1) === '@' ) {
			$webfinger = substr( $webfinger, 1 );
		} 
		$url_host = \explode( '@', $webfinger );
		$webfinger_query = 'https://' . \end( $url_host ) . '/.well-known/webfinger?resource=acct%3A' . \urlencode( $webfinger );

		$response = \wp_safe_remote_get( $webfinger_query );
		if ( ! is_wp_error( $response ) ) {
			$ap_link = json_decode( $response['body'] );
			if ( isset( $ap_link->links ) ) {
				foreach ( $ap_link->links as $link ) {
					if ( !property_exists( $link, 'type' ) ) {
						continue;
					}
					if ( isset( $link->type ) && $link->type === 'application/activity+json' ) {
						$activity_profile['href'] = $link->href;
						$activity_profile['name'] = $webfinger;
					}
				}
			} else {
				$activity_profile = null;
			}
		} else {
			$activity_profile = null;
		}
		return $activity_profile;
	}
}
