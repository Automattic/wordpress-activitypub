<?php
/**
 * Generic Object.
 *
 * @package Activitypub
 */

namespace Activitypub\Activity;

use function Activitypub\camel_to_snake_case;
use function Activitypub\snake_to_camel_case;

/**
 * Generic Object.
 *
 * This class is used to create Generic Objects.
 * It is used to create objects that might be unknown by the plugin but
 * conform to the ActivityStreams vocabulary.
 *
 * @since 5.3.0
 *
 * @method string|null                   get_actor()              Gets one or more entities that performed or are expected to perform the activity.
 * @method string[]|null                 get_also_known_as()      Gets the also known as property of the object.
 * @method string|null                   get_attributed_to()      Gets the entity attributed as the original author.
 * @method array[]|null                  get_attachment()         Gets the attachment property of the object.
 * @method string[]|null                 get_cc()                 Gets the secondary recipients of the object.
 * @method string|null                   get_content()            Gets the content property of the object.
 * @method string[]|null                 get_content_map()        Gets the content map property of the object.
 * @method string[]|null                 get_endpoints()          Gets the endpoint property of the object.
 * @method string[]|null                 get_icon()               Gets the icon property of the object.
 * @method string|null                   get_id()                 Gets the object's unique global identifier.
 * @method string[]|null                 get_image()              Gets the image property of the object.
 * @method string[]|string|null          get_in_reply_to()        Gets the objects this object is in reply to.
 * @method string|null                   get_inbox()              Gets the inbox property of the object.
 * @method array|null                    get_interaction_policy() Gets the interaction policy property of the object.
 * @method string|null                   get_name()               Gets the natural language name of the object.
 * @method string[]|null                 get_name_map()           Gets the name map property of the object.
 * @method Base_Object|string|array|null get_object()             Gets the direct object of the activity.
 * @method string|string[]|null          get_origin()             Gets the origin property of the object.
 * @method string|null                   get_preferred_username() Gets the preferred username of the object.
 * @method string|null                   get_published()          Gets the date and time the object was published in ISO 8601 format.
 * @method string|null                   get_result()             Gets the result property of the object.
 * @method bool|null                     get_sensitive()          Gets the sensitive property of the object.
 * @method string|null                   get_summary()            Gets the natural language summary of the object.
 * @method string[]|null                 get_summary_map()        Gets the summary map property of the object.
 * @method string|string[]|null          get_target()             Gets the target property of the object.
 * @method array[]|null                  get_tag()                Gets the tag property of the object.
 * @method string[]|string|null          get_to()                 Gets the primary recipients of the object.
 * @method string                        get_type()               Gets the type of the object.
 * @method string|null                   get_updated()            Gets the date and time the object was updated in ISO 8601 format.
 * @method string|null                   get_url()                Gets the URL of the object.
 *
 * @method string|string[] add_cc( string|array $cc ) Adds one or more entities to the secondary audience of the object.
 * @method string|string[] add_to( string|array $to ) Adds one or more entities to the primary audience of the object.
 *
 * @method Base_Object set_actor( string|array $actor )                  Sets one or more entities that performed the activity.
 * @method Base_Object set_attachment( array $attachment )               Sets the attachment property of the object.
 * @method Base_Object set_attributed_to( string $attributed_to )        Sets the entity attributed as the original author.
 * @method Base_Object set_cc( array|string $cc )                        Sets the secondary recipients of the object.
 * @method Base_Object set_content( string $content )                    Sets the content property of the object.
 * @method Base_Object set_content_map( array $content_map )             Sets the content property of the object.
 * @method Base_Object set_dcterms( array $dcterms )                     Sets the Dublin Core terms property of the object.
 * @method Base_Object set_icon( array $icon )                           Sets the icon property of the object.
 * @method Base_Object set_id( string $id )                              Sets the object's unique global identifier.
 * @method Base_Object set_image( array $image )                         Sets the image property of the object.
 * @method Base_Object set_in_reply_to( string|string[] $in_reply_to )   Sets the is in reply to property of the object.
 * @method Base_Object set_inbox( string $inbox )                        Sets the inbox property of the object.
 * @method Base_Object set_interaction_policy( array|null $policy )      Sets the interaction policy property of the object.
 * @method Base_Object set_name( string $name )                          Sets the natural language name of the object.
 * @method Base_Object set_name_map( array|null $name_map )              Sets the name map property of the object.
 * @method Base_Object set_object( string|array|Base_Object|null $data ) Sets the direct object of the activity.
 * @method Base_Object set_origin( string|array|null $origin )           Sets the origin property of the object.
 * @method Base_Object set_published( string|null $published )           Sets the date and time the object was published in ISO 8601 format.
 * @method Base_Object set_result( string|null $result )                 Sets the result property of the object.
 * @method Base_Object set_sensitive( bool|null $sensitive )             Sets the sensitive property of the object.
 * @method Base_Object set_summary( string $summary )                    Sets the natural language summary of the object.
 * @method Base_Object set_summary_map( array|null $summary_map )        Sets the summary property of the object.
 * @method Base_Object set_target( string|array|null $target )           Sets the target property of the object.
 * @method Base_Object set_tag( array|null $tag )                        Sets the tag property of the object.
 * @method Base_Object set_to( string|string[] $to )                     Sets the primary recipients of the object.
 * @method Base_Object set_type( string $type )                          Sets the type of the object.
 * @method Base_Object set_updated( string $updated )                    Sets the date and time the object was updated in ISO 8601 format.
 * @method Base_Object set_url( string $url )                            Sets the URL of the object.
 */
#[\AllowDynamicProperties]
class Generic_Object {
	/**
	 * The JSON-LD context for the object.
	 *
	 * @var array
	 */
	const JSON_LD_CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
	);

	/**
	 * The object's unique global identifier
	 *
	 * @see https://www.w3.org/TR/activitypub/#obj-id
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Magic function, to transform the object to string.
	 *
	 * @return string The object id.
	 */
	public function __toString() {
		return $this->to_string();
	}

	/**
	 * Function to transform the object to string.
	 *
	 * @return string The object id.
	 */
	public function to_string() {
		return $this->get_id();
	}

	/**
	 * Magic function to implement getter and setter.
	 *
	 * @param string $method The method name.
	 * @param string $params The method params.
	 *
	 * @return mixed
	 */
	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			if ( ! $this->has( $var ) ) {
				return null;
			}

			return $this->$var;
		}

		if ( \strncasecmp( $method, 'set', 3 ) === 0 ) {
			return $this->set( $var, $params[0] );
		}

		if ( \strncasecmp( $method, 'add', 3 ) === 0 ) {
			return $this->add( $var, $params[0] );
		}

		return null;
	}

	/**
	 * Generic getter.
	 *
	 * @param string $key The key to get.
	 *
	 * @return mixed The value.
	 */
	public function get( $key ) {
		return call_user_func( array( $this, 'get_' . $key ) );
	}

	/**
	 * Generic setter.
	 *
	 * @param string $key   The key to set.
	 * @param string $value The value to set.
	 *
	 * @return mixed The value.
	 */
	public function set( $key, $value ) {
		$this->$key = $value;

		return $this;
	}

	/**
	 * Generic adder.
	 *
	 * @param string $key   The key to set.
	 * @param mixed  $value The value to add.
	 *
	 * @return mixed|void The value.
	 */
	public function add( $key, $value ) {
		if ( empty( $value ) ) {
			return;
		}

		if ( ! isset( $this->$key ) ) {
			$this->$key = array();
		}

		if ( is_string( $this->$key ) ) {
			$this->$key = array( $this->$key );
		}

		$attributes = $this->$key;

		if ( is_array( $value ) ) {
			$attributes = array_merge( $attributes, $value );
		} else {
			$attributes[] = $value;
		}

		$this->$key = array_unique( $attributes );

		return $this->$key;
	}

	/**
	 * Check if the object has a key
	 *
	 * @param string $key The key to check.
	 *
	 * @return boolean True if the object has the key.
	 */
	public function has( $key ) {
		return property_exists( $this, $key );
	}

	/**
	 * Convert JSON input to an array.
	 *
	 * @param string $json The JSON string.
	 *
	 * @return Generic_Object|\WP_Error An Object built from the JSON string or WP_Error when it's not a JSON string.
	 */
	public static function init_from_json( $json ) {
		$array = \json_decode( $json, true );

		if ( ! is_array( $array ) ) {
			return new \WP_Error( 'invalid_json', __( 'Invalid JSON', 'activitypub' ), array( 'status' => 400 ) );
		}

		return self::init_from_array( $array );
	}

	/**
	 * Convert input array to a Base_Object.
	 *
	 * @param array $data The object array.
	 *
	 * @return Generic_Object|\WP_Error An Object built from the input array or WP_Error when it's not an array.
	 */
	public static function init_from_array( $data ) {
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_array', __( 'Invalid array', 'activitypub' ), array( 'status' => 400 ) );
		}

		$object = new static();
		$object->from_array( $data );

		return $object;
	}

	/**
	 * Convert JSON input to an array and pre-fill the object.
	 *
	 * @param array $data The array.
	 */
	public function from_array( $data ) {
		foreach ( $data as $key => $value ) {
			if ( null !== $value ) {
				$key = camel_to_snake_case( $key );
				call_user_func( array( $this, 'set_' . $key ), $value );
			}
		}
	}

	/**
	 * Convert JSON input to an array and pre-fill the object.
	 *
	 * @param string $json The JSON string.
	 */
	public function from_json( $json ) {
		$array = \json_decode( $json, true );

		$this->from_array( $array );
	}

	/**
	 * Convert Object to an array.
	 *
	 * It tries to get the object attributes if they exist
	 * and falls back to the getters. Empty values are ignored.
	 *
	 * @param bool $include_json_ld_context Whether to include the JSON-LD context. Default true.
	 *
	 * @return array An array built from the Object.
	 */
	public function to_array( $include_json_ld_context = true ) {
		$array = array();
		$vars  = get_object_vars( $this );

		foreach ( $vars as $key => $value ) {
			if ( \is_wp_error( $value ) ) {
				continue;
			}

			// Ignore all _prefixed keys.
			if ( '_' === substr( $key, 0, 1 ) ) {
				continue;
			}

			// If value is empty, try to get it from a getter.
			if ( ! $value ) {
				$value = call_user_func( array( $this, 'get_' . $key ) );
			}

			if ( is_object( $value ) ) {
				$value = $value->to_array( false );
			}

			if ( is_array( $value ) && $this->is_namespaced( $key ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					$array[ snake_to_camel_case( $key ) . ':' . snake_to_camel_case( $sub_key ) ] = $sub_value;
				}
			} elseif ( isset( $value ) ) {
				$array[ snake_to_camel_case( $key ) ] = $value;
			}
		}

		if ( $include_json_ld_context ) {
			// Get JsonLD context and move it to '@context' at the top.
			$array = array_merge( array( '@context' => $this->get_json_ld_context() ), $array );
		}

		$class = new \ReflectionClass( $this );
		$class = strtolower( $class->getShortName() );

		/**
		 * Filter the array of the ActivityPub object.
		 *
		 * @param array          $array  The array of the ActivityPub object.
		 * @param string         $class  The class of the ActivityPub object.
		 * @param string         $id     The ID of the ActivityPub object.
		 * @param Generic_Object $object The ActivityPub object.
		 *
		 * @return array The filtered array of the ActivityPub object.
		 */
		$array = \apply_filters( 'activitypub_activity_object_array', $array, $class, $this->id, $this );

		/**
		 * Filter the array of the ActivityPub object by class.
		 *
		 * @param array          $array  The array of the ActivityPub object.
		 * @param string         $id     The ID of the ActivityPub object.
		 * @param Generic_Object $object The ActivityPub object.
		 *
		 * @return array The filtered array of the ActivityPub object.
		 */
		return \apply_filters( "activitypub_activity_{$class}_object_array", $array, $this->id, $this );
	}

	/**
	 * Convert Object to JSON.
	 *
	 * @param bool $include_json_ld_context Whether to include the JSON-LD context. Default true.
	 *
	 * @return string The JSON string.
	 */
	public function to_json( $include_json_ld_context = true ) {
		$array   = $this->to_array( $include_json_ld_context );
		$options = \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES;

		/**
		 * Options to be passed to json_encode().
		 *
		 * @param int $options The current options flags.
		 */
		$options = \apply_filters( 'activitypub_json_encode_options', $options );

		return \wp_json_encode( $array, $options );
	}

	/**
	 * Returns the keys of the object vars.
	 *
	 * @return array The keys of the object vars.
	 */
	public function get_object_var_keys() {
		return \array_keys( \get_object_vars( $this ) );
	}

	/**
	 * Returns the JSON-LD context of this object.
	 *
	 * @return array $context A compacted JSON-LD context for the ActivityPub object.
	 */
	public function get_json_ld_context() {
		return static::JSON_LD_CONTEXT;
	}

	/**
	 * Checks if an attribute is in a namespace.
	 *
	 * @param string $attribute The attribute to check.
	 *
	 * @return bool Whether the attribute is namespaced.
	 */
	private function is_namespaced( $attribute ) {
		$namespaces = array();

		foreach ( static::JSON_LD_CONTEXT as $context ) {
			if ( is_array( $context ) ) {
				$namespaces = \array_merge( $namespaces, $context );
			}
		}

		return isset( $namespaces[ $attribute ] ) && \wp_http_validate_url( $namespaces[ $attribute ] );
	}
}
