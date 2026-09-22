<?php
/**
 * Actor_Autocomplete_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Actor Autocomplete Controller.
 *
 * Implements the SWICG ActivityPub API actor autocomplete extension: a typeahead search over the
 * actors this server knows (its own local actors and the remote actors it has cached), returning an
 * ActivityStreams Collection of actor objects.
 *
 * @see https://swicg.github.io/activitypub-api/autocomplete
 */
class Actor_Autocomplete_Controller extends \WP_REST_Controller {
	use Verification;

	/**
	 * The JSON-LD context for the actor autocomplete extension.
	 *
	 * @var string
	 */
	const CONTEXT = 'https://swicg.github.io/activitypub-api/autocomplete';

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'actors/autocomplete';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'verify_authentication' ),
					'args'                => array(
						'q' => array(
							'description'       => 'The text typed so far.',
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Search actors matching the query.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Collection of actor objects, or WP_Error on failure.
	 */
	public function get_items( $request ) {
		$query = \trim( (string) $request->get_param( 'q' ) );

		/**
		 * Filters the minimum number of characters required before actors are searched.
		 *
		 * @param int $min_length The minimum query length. Default 2.
		 */
		$min_length = (int) \apply_filters( 'activitypub_actor_autocomplete_min_length', 2 );

		if ( \mb_strlen( $query ) < $min_length ) {
			return new \WP_Error(
				'activitypub_query_too_short',
				\sprintf(
					/* translators: %d: minimum number of characters */
					\__( 'The search query must be at least %d characters long.', 'activitypub' ),
					$min_length
				),
				array( 'status' => 400 )
			);
		}

		/**
		 * Filters the maximum number of actors returned by the autocomplete endpoint.
		 *
		 * @param int              $number  The maximum number of actors. Default 10.
		 * @param \WP_REST_Request $request The request object.
		 */
		$number = \max( 1, (int) \apply_filters( 'activitypub_actor_autocomplete_number', 10, $request ) );

		$items = array();
		$seen  = array();

		foreach ( Actors::search( $query, $number ) as $actor ) {
			$items[]                  = $actor->to_array( false );
			$seen[ $actor->get_id() ] = true;
		}

		// Only look up as many remote actors as are still needed to fill the result set.
		$remaining = $number - \count( $items );

		if ( $remaining > 0 ) {
			foreach ( Remote_Actors::search( $query, $remaining ) as $post ) {
				$actor = Remote_Actors::get_actor( $post );
				if ( \is_wp_error( $actor ) || isset( $seen[ $actor->get_id() ] ) ) {
					continue;
				}
				$items[]                  = $actor->to_array( false );
				$seen[ $actor->get_id() ] = true;
			}
		}

		$response = array(
			// JSON_LD_CONTEXT is already a context array, so merge the extension IRI in rather than nesting it.
			'@context'   => \array_merge( (array) Base_Object::JSON_LD_CONTEXT, array( self::CONTEXT ) ),
			'id'         => \add_query_arg( 'q', $query, get_rest_url_by_path( $this->rest_base ) ),
			'generator'  => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'type'       => 'Collection',
			'totalItems' => \count( $items ),
			'items'      => $items,
		);

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}
}
