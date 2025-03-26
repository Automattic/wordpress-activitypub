<?php
/**
 * Following_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;

use function Activitypub\get_context;
use function Activitypub\is_single_user;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;

/**
 * Following_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#following
 */
class Following_Controller extends Actors_Controller {
	use Collection;

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public function __construct() {
		\add_filter( 'activitypub_rest_following', array( self::class, 'default_following' ), 10, 2 );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/following',
			array(
				'args'   => array(
					'user_id' => array(
						'description' => 'The ID or username of the actor.',
						'type'        => 'string',
						'required'    => true,
						'pattern'     => '[\w\-\.]+',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => array(
						'page'     => array(
							'description' => 'Current page of the collection.',
							'type'        => 'integer',
							'minimum'     => 1,
							// No default so we can differentiate between Collection and CollectionPage requests.
						),
						'per_page' => array(
							'description' => 'Maximum number of items to be returned in result set.',
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves following list.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_following_pre' );

		$response = array(
			'@context'  => get_context(),
			'id'        => get_rest_url_by_path( \sprintf( 'actors/%d/following', $user->get__id() ) ),
			'generator' => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'actor'     => $user->get_id(),
			'type'      => 'OrderedCollection',
		);

		/**
		 * Filter the list of following urls.
		 *
		 * @param array                   $items The array of following urls.
		 * @param \Activitypub\Model\User $user  The user object.
		 */
		$items = \apply_filters( 'activitypub_rest_following', array(), $user );

		$response['totalItems']   = \is_countable( $items ) ? \count( $items ) : 0;
		$response['orderedItems'] = $items;

		$response = $this->prepare_collection_response( $response, $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Add the Blog Authors to the following list of the Blog Actor
	 * if Blog not in single mode.
	 *
	 * @param array                   $follow_list The array of following urls.
	 * @param \Activitypub\Model\User $user        The user object.
	 *
	 * @return array The array of following urls.
	 */
	public static function default_following( $follow_list, $user ) {
		if ( 0 !== $user->get__id() || is_single_user() ) {
			return $follow_list;
		}

		$users = Actors::get_collection();

		foreach ( $users as $user ) {
			$follow_list[] = $user->get_id();
		}

		return $follow_list;
	}

	/**
	 * Retrieves the following schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$item_schema = array(
			'type'   => 'string',
			'format' => 'uri',
		);

		$schema = $this->get_collection_schema( $item_schema );

		// Add following-specific properties.
		$schema['title']                   = 'following';
		$schema['properties']['actor']     = array(
			'description' => 'The actor who owns the following collection.',
			'type'        => 'string',
			'format'      => 'uri',
			'readonly'    => true,
		);
		$schema['properties']['generator'] = array(
			'description' => 'The generator of the following collection.',
			'type'        => 'string',
			'format'      => 'uri',
			'readonly'    => true,
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
