<?php
/**
 * Collection Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\get_rest_url_by_path;

/**
 * Collection Trait.
 *
 * Provides methods for handling ActivityPub Collections, including pagination
 * and type transitions between Collection and CollectionPage.
 */
trait Collection {
	/**
	 * The JSON-LD context for the seekItem collection extension.
	 *
	 * @see https://swicg.github.io/activitypub-api/seekitem
	 *
	 * @var string
	 */
	private $seek_item_context = 'https://purl.archive.org/socialweb/seekitem/1.0';

	/**
	 * The JSON-LD context for ActivityPub collections.
	 *
	 * @var array
	 */
	private $json_ld_context = array(
		'https://www.w3.org/ns/activitystreams',
	);

	/**
	 * Prepares a collection response by adding navigation links and handling pagination.
	 *
	 * Adds first, last, next, and previous page links to a collection response
	 * based on the current page and total items. Also handles the transformation
	 * between Collection and CollectionPage types.
	 *
	 * @param array            $response The collection response array.
	 * @param \WP_REST_Request $request  The request object.
	 *
	 * @return array|\WP_Error The response array with navigation links or WP_Error on invalid page.
	 */
	public function prepare_collection_response( $response, $request ) {
		$page      = $request->get_param( 'page' );
		$per_page  = \max( 1, \absint( $request->get_param( 'per_page' ) ) );
		$max_pages = \max( 1, \ceil( $response['totalItems'] / $per_page ) );

		if ( $page > $max_pages ) {
			return new \WP_Error(
				'rest_post_invalid_page_number',
				'The page number requested is larger than the number of pages available.',
				array( 'status' => 400 )
			);
		}

		// Set the JSON-LD context if not already set.
		if ( empty( $response['@context'] ) ) {
			// Ensure the context is the first element in the response.
			$response = array( '@context' => $this->json_ld_context ) + $response;
		}

		// The `item` seek parameter is handled before the collection is built and must not leak into navigation links.
		$query_params = $request->get_query_params();
		unset( $query_params['item'] );

		/*
		 * Advertise the seek endpoint on the Collection when the request offered an `item` argument,
		 * which is how a controller opts a route in. The seek endpoint receives the collection with
		 * its filtering arguments (order, per_page, context) but without page, so it resolves the item
		 * against the same ordering the client is traversing.
		 */
		$attributes = $request->get_attributes();
		if ( null === $page && isset( $attributes['args']['item'] ) ) {
			// A Collection request never carries a page, so the query params already describe the base collection.
			$collection_id = \add_query_arg( $query_params, $response['id'] );

			// add_query_arg() does not encode values, so encode the nested collection URL to keep its query string intact.
			$response['seekItem'] = \add_query_arg( 'collection', \rawurlencode( $collection_id ), get_rest_url_by_path( 'seek' ) );

			$context = (array) $response['@context'];
			if ( ! \in_array( $this->seek_item_context, $context, true ) ) {
				$context[]            = $this->seek_item_context;
				$response['@context'] = $context;
			}
		}

		if ( empty( $response['items'] ) && empty( $response['orderedItems'] ) ) {
			// Skip pagination metadata when items are intentionally hidden or collection is empty.
			return $response;
		}

		$response['id']    = \add_query_arg( $query_params, $response['id'] );
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
	 * Argument definition for the seek `item` parameter.
	 *
	 * @see https://swicg.github.io/activitypub-api/seekitem
	 *
	 * @since unreleased
	 *
	 * @return array The argument definition.
	 */
	public function get_seek_item_arg() {
		return array(
			'description' => 'The ActivityPub object ID of an item to seek. The response redirects to the collection page containing the item.',
			'type'        => 'string',
			'format'      => 'uri',
		);
	}

	/**
	 * Handle a seek request by redirecting to the collection page that contains the sought item.
	 *
	 * Implements the seekItem collection extension: when the `item` parameter is present, the
	 * controller's get_item_index() resolves the item's position under the exact query and
	 * visibility rules of the collection, and the response is a temporary redirect whose
	 * Location is the id of the CollectionPage containing the item. A temporary redirect is
	 * used because the collections are ordered newest-first, so items drift across pages as
	 * new items arrive. A missing-authentication failure is surfaced as 401, because it describes
	 * the request rather than any item and discloses no membership. Everything else — unknown or
	 * invisible items, and authenticated-but-not-authorized requests — collapses to the same 404,
	 * so collection membership is not leaked.
	 *
	 * @see https://swicg.github.io/activitypub-api/seekitem
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request       The request object.
	 * @param string           $collection_id The plain collection ID (URL without query arguments).
	 *
	 * @return \WP_REST_Response|\WP_Error|null Redirect response, 401/404 error, or null when this is not a seek request.
	 */
	public function maybe_seek_item( $request, $collection_id ) {
		$item = $request->get_param( 'item' );
		if ( empty( $item ) ) {
			return null;
		}

		$index = $this->get_item_index( $item, $request );

		if ( \is_wp_error( $index ) ) {
			/*
			 * Surface a missing-authentication failure (401) as is: it is a property of the request,
			 * not the item, so it discloses no membership while telling the client to authenticate.
			 * Everything else — an authenticated-but-not-authorized request, an absent item, or one
			 * hidden by the collection's visibility rules — collapses to a single 404 so the presence
			 * of a specific item can never be inferred, per the seekItem spec.
			 */
			$data   = $index->get_error_data();
			$status = \is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

			if ( 401 === $status ) {
				return $index;
			}

			$index = false;
		}

		if ( false === $index ) {
			return new \WP_Error(
				'activitypub_item_not_found',
				\__( 'The requested item could not be found in this collection.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$per_page = \max( 1, \absint( $request->get_param( 'per_page' ) ) );
		$page     = (int) \floor( $index / $per_page ) + 1;

		$query_params = $request->get_query_params();
		unset( $query_params['item'] );
		$query_params['page'] = $page;

		$response = new \WP_REST_Response( null, 307 );
		$response->header( 'Location', \add_query_arg( $query_params, $collection_id ) );

		return $response;
	}

	/**
	 * Run a callback with an additional WHERE clause appended to WP_Query's SQL.
	 *
	 * Used by get_item_index() implementations to count the items that sort before the sought
	 * item, reusing the collection's own query (and thereby its visibility rules) unchanged.
	 *
	 * @param string   $where    Prepared SQL to append to the WHERE clause.
	 * @param callable $callback Callback executing the query.
	 *
	 * @return mixed The callback return value.
	 */
	private function with_posts_where( $where, $callback ) {
		$filter = static function ( $sql ) use ( $where ) {
			return $sql . $where;
		};

		\add_filter( 'posts_where', $filter );
		$result = $callback();
		\remove_filter( 'posts_where', $filter );

		return $result;
	}

	/**
	 * Build a WHERE clause matching the posts that sort before a cursor in newest-first order.
	 *
	 * Mirrors an `orderby` of post_date then ID, both descending, so the count of matching posts is
	 * the cursor's zero-based index. Shared by the date-ordered collections (outbox, inbox).
	 *
	 * @param string $post_date The cursor post's post_date.
	 * @param int    $id        The cursor post's ID.
	 *
	 * @return string Prepared SQL to append to the WHERE clause.
	 */
	private function get_preceding_by_date_where( $post_date, $id ) {
		global $wpdb;

		return $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_date > %s OR ( {$wpdb->posts}.post_date = %s AND {$wpdb->posts}.ID > %d ) )",
			$post_date,
			$post_date,
			$id
		);
	}

	/**
	 * Get the schema for an ActivityPub Collection.
	 *
	 * Returns a schema definition for ActivityPub (Ordered)Collection and (Ordered)CollectionPage
	 * that controllers can use to compose their full schema by passing in their item schema.
	 *
	 * @param array $item_schema Optional. The schema for the items in the collection. Default empty array.
	 *
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
				'seekItem'     => array(
					'description' => 'Endpoint to resolve the collection page containing a given item.',
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
