<?php
namespace Activitypub\Model;

use function Activitypub\get_context;

/**
 * ActivityPub Post Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/
 */
class Activity {
	private $context = array(
		'https://www.w3.org/ns/activitystreams',
		'https://w3id.org/security/v1',
		array(
			'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
			'PropertyValue' => 'schema:PropertyValue',
			'schema' => 'http://schema.org#',
			'pt' => 'https://joinpeertube.org/ns#',
			'toot' => 'http://joinmastodon.org/ns#',
			'value' => 'schema:value',
			'Hashtag' => 'as:Hashtag',
			'featured' => array(
				'@id' => 'toot:featured',
				'@type' => '@id',
			),
			'featuredTags' => array(
				'@id' => 'toot:featuredTags',
				'@type' => '@id',
			),
		),
	);
	private $published = '';
	private $id = '';
	private $type = 'Create';
	private $actor = '';
	private $to = array( 'https://www.w3.org/ns/activitystreams#Public' );
	private $cc = array();
	private $object = null;

	public function __construct( $type = 'Create', $context = true ) {
		if ( true !== $context ) {
			$this->context = null;
		}

		$this->type = \ucfirst( $type );
		$this->published = \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( 'now' ) );
	}

	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
		}

		if ( \strncasecmp( $method, 'add', 3 ) === 0 ) {
			if ( ! is_array( $this->$var ) ) {
				$this->$var = $params[0];
			}

			if ( is_array( $params[0] ) ) {
				$this->$var = array_merge( $this->$var, $params[0] );
			} else {
				array_push( $this->$var, $params[0] );
			}

			$this->$var = array_unique( $this->$var );
		}
	}

	public function from_post( Post $post ) {
		$this->object = $post->to_array();

		if ( isset( $object['published'] ) ) {
			$this->published = $object['published'];
		}

		$this->add_to( \get_rest_url( null, '/activitypub/1.0/users/' . intval( $post->get_post_author() ) . '/followers' ) );

		if ( isset( $this->object['attributedTo'] ) ) {
			$this->actor = $this->object['attributedTo'];
		}

		foreach ( $post->get_tags() as $tag ) {
			if ( 'Mention' === $tag['type'] ) {
				$this->add_cc( $tag['href'] );
			}
		}

		$type = \strtolower( $this->type );

		if ( isset( $this->object['id'] ) ) {
			$this->id = add_query_arg( 'activity', $type, $this->object['id'] );
		}
	}

	public function from_comment( $object ) {

	}

	public function to_comment() {

	}

	public function from_remote_array( $array ) {

	}

	public function to_array() {
		$array = array_filter( \get_object_vars( $this ) );

		if ( $this->context ) {
			$array = array( '@context' => $this->context ) + $array;
		}

		unset( $array['context'] );

		return $array;
	}

	/**
	 * Convert to JSON
	 *
	 * @return void
	 */
	public function to_json() {
		return \wp_json_encode( $this->to_array(), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT );
	}

	public function to_simple_array() {
		$activity = array(
			'@context' => $this->context,
			'type' => $this->type,
			'actor' => $this->actor,
			'object' => $this->object,
			'to' => $this->to,
			'cc' => $this->cc,
		);

		if ( $this->id ) {
			$activity['id'] = $this->id;
		}

		return $activity;
	}

	public function to_simple_json() {
		return \wp_json_encode( $this->to_simple_array(), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT );
	}
}
