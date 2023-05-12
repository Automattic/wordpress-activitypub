<?php
namespace Activitypub\Model;

use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Post Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/
 */
class Activity {
	/**
	 * The JSON-LD context.
	 *
	 * @var array
	 */
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

	/**
	 * The published date.
	 *
	 * @var string
	 */
	private $published = '';

	/**
	 * The Activity-ID.
	 *
	 * @var string
	 */
	private $id = '';

	/**
	 * The Activity-Type.
	 *
	 * @var string
	 */
	private $type = 'Create';

	/**
	 * The Activity-Actor.
	 *
	 * @var string
	 */
	private $actor = '';

	/**
	 * The Audience.
	 *
	 * @var array
	 */
	private $to = array( 'https://www.w3.org/ns/activitystreams#Public' );

	/**
	 * The CC.
	 *
	 * @var array
	 */
	private $cc = array();

	/**
	 * The Activity-Object.
	 *
	 * @var array
	 */
	private $object = null;

	/**
	 * The Class-Constructor.
	 *
	 * @param string  $type    The Activity-Type.
	 * @param boolean $context The JSON-LD context.
	 */
	public function __construct( $type = 'Create', $context = true ) {
		if ( true !== $context ) {
			$this->context = null;
		}

		$this->type = \ucfirst( $type );
		$this->published = \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( 'now' ) );
	}

	/**
	 * Magic Getter/Setter
	 *
	 * @param string $method The method name.
	 * @param string $params The method params.
	 *
	 * @return mixed The value.
	 */
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

	/**
	 * Convert from a Post-Object.
	 *
	 * @param Post $post The Post-Object.
	 *
	 * @return void
	 */
	public function from_post( Post $post ) {
		$this->object = $post->to_array();

		if ( isset( $object['published'] ) ) {
			$this->published = $object['published'];
		}

		$path = sprintf( 'users/%d/followers', intval( $post->get_post_author() ) );
		$this->add_to( get_rest_url_by_path( $path ) );

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

	/**
	 * Convert to an Array.
	 *
	 * @return array The Array.
	 */
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
	 * @return string The JSON.
	 */
	public function to_json() {
		return \wp_json_encode( $this->to_array(), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT );
	}

	/**
	 * Convert to a Simple Array.
	 *
	 * @return string The array.
	 */
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

	/**
	 * Convert to a Simple JSON.
	 *
	 * @return string The JSON.
	 */
	public function to_simple_json() {
		return \wp_json_encode( $this->to_simple_array(), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT );
	}
}
