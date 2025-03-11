<?php
/**
 * Collection Links Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * Collection Links Trait.
 *
 * Provides methods for adding navigation links to collection responses.
 */
trait Collection_Links {
	/**
	 * Adds navigation links to a collection response.
	 *
	 * Adds first, last, next, and previous page links to a collection response
	 * based on the current page and total items.
	 *
	 * @param array            $response The collection response array.
	 * @param \WP_REST_Request $request  The request object.
	 * @return array|\WP_Error The response array with navigation links or WP_Error on invalid page.
	 */
	protected function add_collection_links( $response, $request ) {
		$page      = $request->get_param( 'page' );
		$per_page  = $request->get_param( 'per_page' );
		$max_pages = \ceil( $response['totalItems'] / $per_page );

		// No need to add links if there's only one page.
		if ( 1 >= $max_pages ) {
			return $response;
		}

		if ( $max_pages && $page > $max_pages ) {
			return new \WP_Error(
				'rest_post_invalid_page_number',
				'The page number requested is larger than the number of pages available.',
				array( 'status' => 400 )
			);
		}

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
		$response['partOf'] = $response['id'];
		$response['id']    .= '?page=' . $page;

		if ( $max_pages > $page ) {
			$response['next'] = \add_query_arg( 'page', $page + 1, $response['id'] );
		}

		if ( $page > 1 ) {
			$response['prev'] = \add_query_arg( 'page', $page - 1, $response['id'] );
		}

		return $response;
	}
}
