<?php
/**
 * Collection Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

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
}
