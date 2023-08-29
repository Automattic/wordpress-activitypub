<?php

namespace Activitypub\Validator;

class Query {
	/**
	 * Validate the query parameters.
	 *
	 * @return array A list of arguments and how to validate them.
	 */
	public static function get_default_args() {
		$args = array();

		$args['page'] = array(
			'type' => 'integer',
			'default' => 1,
		);

		$args['per_page'] = array(
			'type' => 'integer',
			'default' => 20,
		);

		$args['order'] = array(
			'type'    => 'string',
			'default' => 'desc',
			'enum'    => array( 'asc', 'desc' ),
		);

		$args['context'] = array(
			'type' => 'string',
			'default' => 'simple',
			'enum' => array( 'simple', 'full' ),
		);

		return $args;
	}
}
