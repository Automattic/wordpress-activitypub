<?php
/**
 * Collection Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Followers;
use Activitypub\Http;

/**
 * Collection Trait.
 *
 * Provides methods for handling ActivityPub Collections, including pagination
 * and type transitions between Collection and CollectionPage.
 */
trait Collection {
	/**
	 * Prepares a collection response by adding navigation links and handling pagination.
	 *
	 * Adds first, last, next, and previous page links to a collection response
	 * based on the current page and total items. Also handles the transformation
	 * between Collection and CollectionPage types.
	 *
	 * @param array            $response The collection response array.
	 * @param \WP_REST_Request $request  The request object.
	 * @return array|\WP_Error The response array with navigation links or WP_Error on invalid page.
	 */
	public function prepare_collection_response( $response, $request ) {
		$page      = $request->get_param( 'page' );
		$max_pages = \ceil( $response['totalItems'] / $request->get_param( 'per_page' ) );

		if ( $page > $max_pages ) {
			return new \WP_Error(
				'rest_post_invalid_page_number',
				'The page number requested is larger than the number of pages available.',
				array( 'status' => 400 )
			);
		}

		// No need to add links if there's only one page.
		if ( 1 >= $max_pages && null === $page ) {
			return $response;
		}

		$response['id']    = \add_query_arg( $request->get_query_params(), $response['id'] );
		$response['first'] = \add_query_arg( 'page', 1, $response['id'] );
		$response['last']  = \add_query_arg( 'page', $max_pages, $response['id'] );

		// If this is a Collection request, return early.
		if ( null === $page ) {
			// No items in Collections, only links to CollectionPages.
			unset( $response['items'], $response['orderedItems'] );

			return $response;
		}

		// Still here, so this is a Page request. Append the type.
		$response['type']  .= 'Page';
		$response['partOf'] = \remove_query_arg( 'page', $response['id'] );

		if ( $max_pages > $page ) {
			$response['next'] = \add_query_arg( 'page', $page + 1, $response['partOf'] );
		}

		if ( $page > 1 ) {
			$response['prev'] = \add_query_arg( 'page', $page - 1, $response['partOf'] );
		}

		return $response;
	}

	/**
	 * Get the schema for an ActivityPub Collection.
	 *
	 * Returns a schema definition for ActivityPub (Ordered)Collection and (Ordered)CollectionPage
	 * that controllers can use to compose their full schema by passing in their item schema.
	 *
	 * @param array $item_schema Optional. The schema for the items in the collection. Default empty array.
	 * @return array The collection schema.
	 */
	public function get_collection_schema( $item_schema = array() ) {
		$collection_schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'collection',
			'type'       => 'object',
			'properties' => array(
				'@context'     => array(
					'description' => 'The JSON-LD context of the OrderedCollection.',
					'type'        => array( 'string', 'array', 'object' ),
				),
				'id'           => array(
					'description' => 'The unique identifier for the OrderedCollection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'type'         => array(
					'description' => 'The type of the object. Either OrderedCollection or OrderedCollectionPage.',
					'type'        => 'string',
					'enum'        => array( 'Collection', 'CollectionPage', 'OrderedCollection', 'OrderedCollectionPage' ),
				),
				'totalItems'   => array(
					'description' => 'The total number of items in the collection.',
					'type'        => 'integer',
					'minimum'     => 0,
				),
				'orderedItems' => array(
					'description' => 'The ordered items in the collection.',
					'type'        => 'array',
				),
				'first'        => array(
					'description' => 'Link to the first page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'last'         => array(
					'description' => 'Link to the last page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'next'         => array(
					'description' => 'Link to the next page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'prev'         => array(
					'description' => 'Link to the previous page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'partOf'       => array(
					'description' => 'The OrderedCollection to which this OrderedCollectionPage belongs.',
					'type'        => 'string',
					'format'      => 'uri',
				),
			),
		);

		// Add the orderedItems property based on the provided item schema.
		if ( ! empty( $item_schema ) ) {
			$collection_schema['properties']['orderedItems']['items'] = $item_schema;
		}

		return $collection_schema;
	}

	/**
	 * Process Collection-Synchronization header if present (FEP-8fcf).
	 *
	 * This method handles the FEP-8fcf Collection Synchronization protocol for any collection type.
	 * It detects the collection type from the URL and delegates to the appropriate handler.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param array            $data    The activity data.
	 * @param int              $user_id The local user ID receiving the activity.
	 */
	protected function process_collection_synchronization( $request, $data, $user_id ) {
		// Get the Collection-Synchronization header.
		$sync_header = $request->get_header( 'collection_synchronization' );

		if ( empty( $sync_header ) ) {
			return;
		}

		// Parse the header using the generic HTTP parser.
		$params = \Activitypub\Http::parse_collection_sync_header( $sync_header );

		if ( false === $params ) {
			return;
		}

		// Ensure we have a URL parameter to determine collection type.
		if ( ! isset( $params['url'] ) ) {
			return;
		}

		// Determine the collection type from the URL.
		$collection_type = $this->detect_collection_type( $params['url'] );

		if ( ! $collection_type ) {
			// Unknown or unsupported collection type.
			return;
		}

		// Get the actor URL for validation.
		$actor_url = isset( $data['actor'] ) ? $data['actor'] : null;

		if ( ! $actor_url ) {
			return;
		}

		/**
		 * Filters whether collection synchronization should be processed for a specific collection type.
		 *
		 * Allows collection handlers to implement their own synchronization logic.
		 * Return true to indicate that synchronization was handled, false to skip.
		 *
		 * @param bool             $handled  Whether the synchronization was handled.
		 * @param string           $type     The collection type (e.g., 'followers', 'following', 'liked').
		 * @param array            $params   The parsed Collection-Synchronization header parameters.
		 * @param int              $user_id  The local user ID.
		 * @param string           $actor    The remote actor URL.
		 * @param \WP_REST_Request $request  The request object.
		 * @param array            $data     The activity data.
		 */
		$handled = \apply_filters(
			'activitypub_collection_synchronization',
			false,
			$collection_type,
			$params,
			$user_id,
			$actor_url,
			$request,
			$data
		);

		// If no handler processed it, use the default followers handler.
		if ( ! $handled && 'followers' === $collection_type ) {
			$this->process_followers_collection_sync( $params, $user_id, $actor_url );
		}
	}

	/**
	 * Detect the collection type from a URL.
	 *
	 * @param string $url The collection URL.
	 * @return string|false The collection type (e.g., 'followers', 'following', 'liked') or false if unknown.
	 */
	protected function detect_collection_type( $url ) {
		// Check for followers collection.
		if ( preg_match( '#/followers(?:-sync)?(?:\?|$)#', $url ) ) {
			return 'followers';
		}

		/**
		 * Filters the collection type detection.
		 *
		 * Allows plugins to register custom collection types for synchronization.
		 *
		 * @param string|false $type The detected collection type, or false if unknown.
		 * @param string       $url  The collection URL.
		 */
		return \apply_filters( 'activitypub_detect_collection_type', false, $url );
	}

	/**
	 * Process followers collection synchronization.
	 *
	 * @param array  $params    The parsed Collection-Synchronization header parameters.
	 * @param int    $user_id   The local user ID.
	 * @param string $actor_url The remote actor URL.
	 */
	protected function process_followers_collection_sync( $params, $user_id, $actor_url ) {
		// Validate the header parameters.
		if ( ! Http::validate_collection_sync_header_params( $params, $actor_url ) ) {
			return;
		}

		// Get our local authority.
		$our_authority = Http::get_authority( \home_url() );

		if ( ! $our_authority ) {
			return;
		}

		// Compute our local digest for this actor's followers from our instance.
		$local_digest = Followers::compute_partial_digest( $user_id, $our_authority );

		// Compare digests.
		if ( $local_digest === $params['digest'] ) {
			// Digests match, no synchronization needed.
			return;
		}

		// Digests do not match, trigger reconciliation.

		/**
		 * Action triggered when Collection-Synchronization digest mismatch is detected for followers.
		 *
		 * This allows for async processing of the reconciliation.
		 *
		 * @param int    $user_id    The local user ID.
		 * @param string $actor_url  The remote actor URL.
		 * @param array  $params     The parsed Collection-Synchronization header parameters.
		 */
		\do_action( 'activitypub_followers_sync_mismatch', $user_id, $actor_url, $params );
	}
}
