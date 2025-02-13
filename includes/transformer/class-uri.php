<?php
/**
 * URI Transformer Class.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Http;

/**
 * URI Transformer Class.
 *
 * @package Activitypub
 */
class Uri extends Json {
	/**
	 * Base constructor.
	 *
	 * @param WP_Post|WP_Comment|Base_Object|string|array|WP_Term $item The item that should be transformed.
	 */
	public function __construct( $item ) {
		$response = Http::get_remote_object( $item );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		parent::__construct( $response );
	}
}
