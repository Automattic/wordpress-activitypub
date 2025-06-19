<?php
/**
 * ActivityPub HTTP Signature class.
 *
 * This class provides methods to sign and verify HTTP requests using the ActivityPub protocol.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Custom OuterList implementation to replace Bakame\Http\StructuredFields\OuterList.
 *
 * This class provides a simplified implementation of the OuterList structured field
 * as defined in RFC 8941.
 */
class Custom_OuterList {
	/**
	 * The items in the list.
	 *
	 * @var array
	 */
	private $items = array();

	/**
	 * The parameters associated with this list.
	 *
	 * @var Custom_Parameters|null
	 */
	private $parameters = null;

	/**
	 * Create a new OuterList from an HTTP header value.
	 *
	 * @param string $http_value The HTTP header value.
	 * @return Custom_OuterList The created OuterList.
	 */
	public static function fromHttpValue( string $http_value ): Custom_OuterList {
		$instance = new self();

		// Split the list by commas, but ignore commas inside quoted strings.
		$items = self::split_list_items( $http_value );

		foreach ( $items as $item ) {
			$instance->items[] = trim( $item );
		}

		return $instance;
	}

	/**
	 * Split a list string into individual items, respecting quotes.
	 *
	 * @param string $list_string The list string to split.
	 * @return array The split items.
	 */
	private static function split_list_items( string $list_string ): array {
		$items        = array();
		$current_item = '';
		$in_quotes    = false;
		$escaped      = false;

		for ( $i = 0; $i < strlen( $list_string ); $i++ ) {
			$char = $list_string[ $i ];

			if ( $escaped ) {
				$current_item .= $char;
				$escaped       = false;
				continue;
			}

			if ( $char === '\\' ) {
				$current_item .= $char;
				$escaped       = true;
				continue;
			}

			if ( $char === '"' ) {
				$current_item .= $char;
				$in_quotes     = ! $in_quotes;
				continue;
			}

			if ( $char === ',' && ! $in_quotes ) {
				$items[]      = $current_item;
				$current_item = '';
				continue;
			}

			$current_item .= $char;
		}

		if ( $current_item !== '' ) {
			$items[] = $current_item;
		}

		return $items;
	}

	/**
	 * Convert the OuterList to an HTTP header value.
	 *
	 * @return string The HTTP header value.
	 */
	public function toHttpValue(): string {
		return implode( ', ', $this->items );
	}

	/**
	 * Check if the OuterList is empty.
	 *
	 * @return bool Whether the OuterList is empty.
	 */
	public function isEmpty(): bool {
		return empty( $this->items );
	}

	/**
	 * Check if the OuterList is not empty.
	 *
	 * @return bool Whether the OuterList is not empty.
	 */
	public function isNotEmpty(): bool {
		return ! $this->isEmpty();
	}

	/**
	 * Get the indices of the items in the OuterList.
	 *
	 * @return array The indices.
	 */
	public function indices(): array {
		return array_keys( $this->items );
	}

	/**
	 * Get an item by its index.
	 *
	 * @param int $index The index of the item.
	 * @return mixed|null The item or null if not found.
	 */
	public function getByIndex( int $index ) {
		return $this->items[ $index ] ?? null;
	}

	/**
	 * Convert the OuterList to a string.
	 *
	 * @return string The string representation.
	 */
	public function __toString(): string {
		return $this->toHttpValue();
	}

	/**
	 * Get the parameters associated with this list.
	 *
	 * @return Custom_Parameters The parameters.
	 */
	public function parameters(): Custom_Parameters {
		if ( null === $this->parameters ) {
			$this->parameters = new Custom_Parameters();
		}
		return $this->parameters;
	}
}

/**
 * Custom Parameters implementation to replace Bakame\Http\StructuredFields\Parameters.
 *
 * This class provides a simplified implementation of the Parameters structured field
 * as defined in RFC 8941.
 */
class Custom_Parameters {
	/**
	 * The parameters stored as key-value pairs.
	 *
	 * @var array
	 */
	private $parameters = array();

	/**
	 * Create a new Parameters instance from an HTTP header value.
	 *
	 * @param string $http_value The HTTP header value.
	 * @return Custom_Parameters The created Parameters instance.
	 */
	public static function fromHttpValue( string $http_value ): Custom_Parameters {
		$instance = new self();

		// Parse parameters from the HTTP value.
		$params = self::parse_parameters( $http_value );

		foreach ( $params as $key => $value ) {
			$instance->parameters[ $key ] = array( $key, new Custom_Item( $value ) );
		}

		return $instance;
	}

	/**
	 * Parse parameters from a string.
	 *
	 * @param string $param_string The parameter string to parse.
	 * @return array The parsed parameters as key-value pairs.
	 */
	private static function parse_parameters( string $param_string ): array {
		$params = array();
		$parts  = explode( ';', $param_string );

		// Skip the first part as it's the main value, not a parameter.
		array_shift( $parts );

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( empty( $part ) ) {
				continue;
			}

			// Split by the first equals sign.
			$pos = strpos( $part, '=' );
			if ( false === $pos ) {
				// Boolean parameter (no value).
				$params[ $part ] = true;
				continue;
			}

			$key   = trim( substr( $part, 0, $pos ) );
			$value = trim( substr( $part, $pos + 1 ) );

			// Remove quotes if present.
			if ( strlen( $value ) >= 2 && '"' === $value[0] && '"' === $value[ strlen( $value ) - 1 ] ) {
				$value = substr( $value, 1, -1 );
			}

			$params[ $key ] = $value;
		}

		return $params;
	}

	/**
	 * Convert the Parameters to an HTTP header value.
	 *
	 * @return string The HTTP header value.
	 */
	public function toHttpValue(): string {
		$result = '';

		foreach ( $this->parameters as $key => $value ) {
			$param_value = $value[1]->value();

			if ( true === $param_value ) {
				// Boolean parameter.
				$result .= ';' . $key;
			} elseif ( is_string( $param_value ) && preg_match( '/[,;=\s]/', $param_value ) ) {
				// String with special characters needs quotes.
				$result .= ';' . $key . '="' . $param_value . '"';
			} else {
				$result .= ';' . $key . '=' . $param_value;
			}
		}

		return $result;
	}

	/**
	 * Check if the Parameters is empty.
	 *
	 * @return bool Whether the Parameters is empty.
	 */
	public function isEmpty(): bool {
		return empty( $this->parameters );
	}

	/**
	 * Check if the Parameters is not empty.
	 *
	 * @return bool Whether the Parameters is not empty.
	 */
	public function isNotEmpty(): bool {
		return ! $this->isEmpty();
	}

	/**
	 * Get the indices of the parameters.
	 *
	 * @return array The indices.
	 */
	public function indices(): array {
		return array_keys( $this->parameters );
	}

	/**
	 * Get a parameter by its index.
	 *
	 * @param int|string $index The index of the parameter.
	 * @return array|null The parameter or null if not found.
	 */
	public function getByIndex( $index ) {
		return $this->parameters[ $index ] ?? null;
	}

	/**
	 * Convert the Parameters to a string.
	 *
	 * @return string The string representation.
	 */
	public function __toString(): string {
		return $this->toHttpValue();
	}
}

/**
 * Custom Item implementation for use with Custom_Parameters.
 */
class Custom_Item {
	/**
	 * The value of the item.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * The parameters associated with this item.
	 *
	 * @var Custom_Parameters|null
	 */
	private $parameters = null;

	/**
	 * Constructor.
	 *
	 * @param mixed $value The value to store.
	 */
	public function __construct( $value ) {
		$this->value = $value;
	}

	/**
	 * Create a new Item from an HTTP header value.
	 *
	 * @param string $http_value The HTTP header value.
	 * @return Custom_Item The created Item.
	 */
	public static function fromHttpValue( string $http_value ): Custom_Item {
		// Parse the value and any parameters.
		$parts = explode( ';', $http_value, 2 );
		$value = trim( $parts[0] );

		// Handle quoted strings.
		if ( strlen( $value ) >= 2 && '"' === $value[0] && '"' === $value[ strlen( $value ) - 1 ] ) {
			$value = substr( $value, 1, -1 );
		}

		// Handle boolean values.
		if ( 'true' === strtolower( $value ) ) {
			$value = true;
		} elseif ( 'false' === strtolower( $value ) ) {
			$value = false;
		}

		return new self( $value );
	}

	/**
	 * Get the value of the item.
	 *
	 * @return mixed The value.
	 */
	public function value() {
		return $this->value;
	}

	/**
	 * Get the parameters associated with this item.
	 *
	 * @return Custom_Parameters The parameters.
	 */
	public function parameters() {
		if ( null === $this->parameters ) {
			$this->parameters = new Custom_Parameters();
		}
		return $this->parameters;
	}

	/**
	 * Convert the item to a string.
	 *
	 * @return string The string representation.
	 */
	public function __toString(): string {
		if ( is_bool( $this->value ) ) {
			return $this->value ? 'true' : 'false';
		}

		return (string) $this->value;
	}

	/**
	 * Convert the item to an HTTP header value.
	 *
	 * @return string The HTTP header value.
	 */
	public function toHttpValue(): string {
		return $this->__toString();
	}
}

/**
 * Custom Dictionary implementation to replace Bakame\Http\StructuredFields\Dictionary.
 *
 * This class provides a simplified implementation of the Dictionary structured field
 * as defined in RFC 8941.
 */
class Custom_Dictionary implements \ArrayAccess, \Countable, \IteratorAggregate {
	/**
	 * The items in the dictionary.
	 *
	 * @var array
	 */
	private $items = array();

	/**
	 * Create a new Dictionary from an HTTP header value.
	 *
	 * @param string $http_value The HTTP header value.
	 * @return Custom_Dictionary The created Dictionary.
	 */
	public static function fromHttpValue( string $http_value ): Custom_Dictionary {
		$instance = new self();

		// Parse dictionary items from the HTTP value.
		$items = self::parse_dictionary_items( $http_value );

		foreach ( $items as $key => $value ) {
			$instance->items[ $key ] = array( $key, new Custom_Item( $value ) );
		}

		return $instance;
	}

	/**
	 * Parse dictionary items from an HTTP header value.
	 *
	 * @param string $dict_string The dictionary string to parse.
	 * @return array The parsed items.
	 */
	private static function parse_dictionary_items( string $dict_string ): array {
		$items = array();
		$parts = self::split_dictionary_items( $dict_string );

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( empty( $part ) ) {
				continue;
			}

			// Split by the first equals sign.
			$pos = strpos( $part, '=' );
			if ( false === $pos ) {
				// Invalid dictionary item, skip.
				continue;
			}

			$key   = trim( substr( $part, 0, $pos ) );
			$value = trim( substr( $part, $pos + 1 ) );

			// Handle quoted strings.
			if ( strlen( $value ) >= 2 && '"' === $value[0] && '"' === $value[ strlen( $value ) - 1 ] ) {
				$value = substr( $value, 1, -1 );
			}

			// Handle boolean values.
			if ( 'true' === strtolower( $value ) ) {
				$value = true;
			} elseif ( 'false' === strtolower( $value ) ) {
				$value = false;
			}

			$items[ $key ] = $value;
		}

		return $items;
	}

	/**
	 * Split a dictionary string into individual items, respecting quotes.
	 *
	 * @param string $dict_string The dictionary string to split.
	 * @return array The split items.
	 */
	private static function split_dictionary_items( string $dict_string ): array {
		$items        = array();
		$current_item = '';
		$in_quotes    = false;
		$escaped      = false;

		for ( $i = 0; $i < strlen( $dict_string ); $i++ ) {
			$char = $dict_string[ $i ];

			if ( $escaped ) {
				$current_item .= $char;
				$escaped       = false;
				continue;
			}

			if ( '\\' === $char ) {
				$current_item .= $char;
				$escaped       = true;
				continue;
			}

			if ( '"' === $char ) {
				$current_item .= $char;
				$in_quotes     = ! $in_quotes;
				continue;
			}

			if ( ',' === $char && ! $in_quotes ) {
				$items[]      = $current_item;
				$current_item = '';
				continue;
			}

			$current_item .= $char;
		}

		if ( ! empty( $current_item ) ) {
			$items[] = $current_item;
		}

		return $items;
	}

	/**
	 * Convert the Dictionary to an HTTP header value.
	 *
	 * @return string The HTTP header value.
	 */
	public function toHttpValue(): string {
		$parts = array();

		foreach ( $this->items as $key => $item ) {
			$value = $item[1]->value();

			if ( is_bool( $value ) ) {
				$parts[] = $key . '=' . ( $value ? 'true' : 'false' );
			} elseif ( is_string( $value ) && preg_match( '/[,;=\s]/', $value ) ) {
				$parts[] = $key . '="' . $value . '"';
			} else {
				$parts[] = $key . '=' . $value;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Check if the Dictionary is empty.
	 *
	 * @return bool Whether the Dictionary is empty.
	 */
	public function isEmpty(): bool {
		return empty( $this->items );
	}

	/**
	 * Check if the Dictionary is not empty.
	 *
	 * @return bool Whether the Dictionary is not empty.
	 */
	public function isNotEmpty(): bool {
		return ! $this->isEmpty();
	}

	/**
	 * Get the indices of the items in the Dictionary.
	 *
	 * @return array The indices.
	 */
	public function indices(): array {
		return array_keys( $this->items );
	}

	/**
	 * Get an item by its index.
	 *
	 * @param string $index The index of the item.
	 * @return array|null The item or null if not found.
	 */
	public function getByIndex( string $index ) {
		return $this->items[ $index ] ?? null;
	}

	/**
	 * Convert the Dictionary to a string.
	 *
	 * @return string The string representation.
	 */
	public function __toString(): string {
		return $this->toHttpValue();
	}

	/**
	 * Check if an offset exists.
	 *
	 * @param mixed $offset The offset to check.
	 * @return bool Whether the offset exists.
	 */
	public function offsetExists( $offset ): bool {
		return isset( $this->items[ $offset ] );
	}

	/**
	 * Get an item at the specified offset.
	 *
	 * @param mixed $offset The offset to get.
	 * @return mixed The item at the offset.
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->items[ $offset ][1] ?? null;
	}

	/**
	 * Set an item at the specified offset.
	 *
	 * @param mixed $offset The offset to set.
	 * @param mixed $value The value to set.
	 */
	public function offsetSet( $offset, $value ): void {
		if ( null === $offset ) {
			$this->items[] = array( count( $this->items ), $value );
		} else {
			$this->items[ $offset ] = array( $offset, $value );
		}
	}

	/**
	 * Unset an item at the specified offset.
	 *
	 * @param mixed $offset The offset to unset.
	 */
	public function offsetUnset( $offset ): void {
		unset( $this->items[ $offset ] );
	}

	/**
	 * Count the number of items in the Dictionary.
	 *
	 * @return int The number of items.
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Get an iterator for the Dictionary.
	 *
	 * @return \ArrayIterator An iterator for the Dictionary.
	 */
	public function getIterator(): \ArrayIterator {
		return new \ArrayIterator( $this->items );
	}
}

/**
 * Custom InnerList implementation to replace Bakame\Http\StructuredFields\InnerList.
 *
 * This class provides a simplified implementation of the InnerList structured field
 * as defined in RFC 8941.
 */
class Custom_InnerList {
	/**
	 * The items in the inner list.
	 *
	 * @var array
	 */
	private $items = array();

	/**
	 * The parameters associated with this list.
	 *
	 * @var Custom_Parameters|null
	 */
	private $parameters = null;

	/**
	 * Create a new InnerList from an HTTP header value.
	 *
	 * @param string $http_value The HTTP header value.
	 * @return Custom_InnerList The created InnerList.
	 */
	public static function fromHttpValue( string $http_value ): Custom_InnerList {
		$instance = new self();

		// Inner lists are enclosed in parentheses.
		if ( preg_match( '/^\s*\((.*)\)(.*)$/s', $http_value, $matches ) ) {
			$list_content = $matches[1];
			$params_part  = $matches[2] ?? '';

			// Split the list content by commas, respecting quotes.
			$items = self::split_list_items( $list_content );

			foreach ( $items as $index => $item ) {
				$instance->items[ $index ] = array( $index, new Custom_Item( $item ) );
			}

			// Parse parameters if present.
			if ( ! empty( $params_part ) ) {
				$instance->parameters = Custom_Parameters::fromHttpValue( $params_part );
			}
		}

		return $instance;
	}

	/**
	 * Split a list string into individual items, respecting quotes.
	 *
	 * @param string $list_string The list string to split.
	 * @return array The split items.
	 */
	private static function split_list_items( string $list_string ): array {
		$items        = array();
		$current_item = '';
		$in_quotes    = false;
		$escaped      = false;

		for ( $i = 0; $i < strlen( $list_string ); $i++ ) {
			$char = $list_string[ $i ];

			if ( $escaped ) {
				$current_item .= $char;
				$escaped       = false;
				continue;
			}

			if ( '\\' === $char ) {
				$current_item .= $char;
				$escaped       = true;
				continue;
			}

			if ( '"' === $char ) {
				$current_item .= $char;
				$in_quotes     = ! $in_quotes;
				continue;
			}

			if ( ',' === $char && ! $in_quotes ) {
				$items[]      = trim( $current_item );
				$current_item = '';
				continue;
			}

			$current_item .= $char;
		}

		if ( ! empty( $current_item ) ) {
			$items[] = trim( $current_item );
		}

		return $items;
	}

	/**
	 * Get the parameters associated with this list.
	 *
	 * @return Custom_Parameters The parameters.
	 */
	public function parameters(): Custom_Parameters {
		if ( null === $this->parameters ) {
			$this->parameters = new Custom_Parameters();
		}
		return $this->parameters;
	}

	/**
	 * Convert the InnerList to an HTTP header value.
	 *
	 * @return string The HTTP header value.
	 */
	public function toHttpValue(): string {
		$items = array();

		foreach ( $this->items as $item ) {
			$items[] = $item[1]->toHttpValue();
		}

		$result = '(' . implode( ', ', $items ) . ')';

		if ( $this->parameters && $this->parameters->isNotEmpty() ) {
			$result .= $this->parameters->toHttpValue();
		}

		return $result;
	}

	/**
	 * Check if the InnerList is empty.
	 *
	 * @return bool Whether the InnerList is empty.
	 */
	public function isEmpty(): bool {
		return empty( $this->items );
	}

	/**
	 * Check if the InnerList is not empty.
	 *
	 * @return bool Whether the InnerList is not empty.
	 */
	public function isNotEmpty(): bool {
		return ! $this->isEmpty();
	}

	/**
	 * Get the indices of the items in the InnerList.
	 *
	 * @return array The indices.
	 */
	public function indices(): array {
		return array_keys( $this->items );
	}

	/**
	 * Get an item by its index.
	 *
	 * @param int $index The index of the item.
	 * @return mixed|null The item or null if not found.
	 */
	public function getByIndex( int $index ) {
		return $this->items[ $index ] ?? null;
	}

	/**
	 * Convert the InnerList to a string.
	 *
	 * @return string The string representation.
	 */
	public function __toString(): string {
		return $this->toHttpValue();
	}
}

/**
 * ActivityPub HTTP Signature class.
 *
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
 */
class Http_Signature {

	/**
	 * The key ID used for signing.
	 *
	 * @var string
	 */
	private $key_id;

	/**
	 * The private key used for signing.
	 *
	 * @var string
	 */
	private $private_key;

	/**
	 * The public key used for verification.
	 *
	 * @var string
	 */
	private $public_key;

	/**
	 * The algorithm used for signing.
	 *
	 * @var string
	 */
	private $algorithm;

	/**
	 * The signature ID.
	 *
	 * @var string
	 */
	private $signature_id = 'sig1';

	/**
	 * The creation timestamp.
	 *
	 * @var string
	 */
	private $created = '';

	/**
	 * The expiration timestamp.
	 *
	 * @var string
	 */
	private $expires = '';

	/**
	 * The nonce value.
	 *
	 * @var string
	 */
	private $nonce = '';

	/**
	 * The tag value.
	 *
	 * @var string
	 */
	private $tag = '';

	/**
	 * Mapping of HTTP header names to their structured field types.
	 *
	 * @var array
	 */
	private $structured_field_types = array(
		'accept'                           => 'list',
		'accept-encoding'                  => 'list',
		'accept-language'                  => 'list',
		'accept-patch'                     => 'list',
		'accept-post'                      => 'list',
		'accept-ranges'                    => 'list',
		'access-control-allow-credentials' => 'item',
		'access-control-allow-headers'     => 'list',
		'access-control-allow-methods'     => 'list',
		'access-control-allow-origin'      => 'item',
		'access-control-expose-headers'    => 'list',
		'access-control-max-age'           => 'item',
		'access-control-request-headers'   => 'list',
		'access-control-request-method'    => 'item',
		'age'                              => 'item',
		'allow'                            => 'list',
		'alpn'                             => 'list',
		'alt-svc'                          => 'dictionary',
		'alt-used'                         => 'item',
		'cache-control'                    => 'dictionary',
		'cdn-loop'                         => 'list',
		'clear-site-data'                  => 'list',
		'connection'                       => 'list',
		'content-encoding'                 => 'list',
		'content-language'                 => 'list',
		'content-length'                   => 'list',
		'content-location'                 => 'url',
		'content-type'                     => 'item',
		'cookie'                           => 'cookie',
		'cross-origin-resource-policy'     => 'item',
		'date'                             => 'date',
		'dnt'                              => 'item',
		'etag'                             => 'etag',
		'expect'                           => 'dictionary',
		'expect-ct'                        => 'dictionary',
		'expires'                          => 'date',
		'host'                             => 'item',
		'if-match'                         => 'etag',
		'if-modified-since'                => 'date',
		'if-none-match'                    => 'etag',
		'if-unmodified-since'              => 'date',
		'keep-alive'                       => 'dictionary',
		'last-modified'                    => 'date',
		'location'                         => 'url',
		'max-forwards'                     => 'item',
		'origin'                           => 'item',
		'pragma'                           => 'dictionary',
		'prefer'                           => 'dictionary',
		'preference-applied'               => 'dictionary',
		'referer'                          => 'url',
		'retry-after'                      => 'item',
		'sec-websocket-extensions'         => 'list',
		'sec-websocket-protocol'           => 'list',
		'sec-websocket-version'            => 'item',
		'server-timing'                    => 'list',
		'set-cookie'                       => 'cookie',
		'surrogate-control'                => 'dictionary',
		'te'                               => 'list',
		'timing-allow-origin'              => 'list',
		'trailer'                          => 'list',
		'transfer-encoding'                => 'list',
		'upgrade-insecure-requests'        => 'item',
		'vary'                             => 'list',
		'x-content-type-options'           => 'item',
		'x-frame-options'                  => 'item',
		'x-xss-protection'                 => 'list',
	);

	/**
	 * The original request.
	 *
	 * @var \WP_REST_Request
	 */
	private $original_request;

	/**
	 * Get headers from a PSR-7 message interface.
	 *
	 * @param \WP_REST_Request $request The message interface to extract headers from.
	 * @return array Associative array of headers with lowercase keys.
	 */
	public function get_headers( $request ) {
		$headers = array();
		foreach ( $request->get_headers() as $name => $values ) {
			$headers[ strtolower( $name ) ] = implode( ', ', $values );
		}
		return $headers;
	}

	/**
	 * Sign a PSR-7 request with HTTP signatures.
	 *
	 * @param string $covered_fields   Fields to be covered by the signature.
	 * @param array  $request_args     The message interface to sign.
	 * @return array The signed message interface.
	 */
	public function sign_request( $covered_fields, $request_args ) {
		$request_args['headers'] = $this->sign( $request_args['headers'], $covered_fields, $request_args );

		foreach ( array( 'signature-input', 'signature' ) as $header ) {
		//	$request_args['headers'][ $header ] = $header;
		}

		return $request_args;
	}

	/**
	 * Verify a PSR-7 request with HTTP signatures.
	 *
	 * @param \WP_REST_Request $request          The message interface to verify.
	 * @param \WP_REST_Request $original_request Optional. Original request for context. Default null.
	 * @return bool Whether the signature is valid.
	 */
	public function verify_request( $request, $original_request = null ): bool {
		$headers = array();
		if ( $original_request ) {
			$this->set_original_request( $original_request );
		}
		foreach ( $request->get_headers() as $name => $values ) {
			$headers[ strtolower( $name ) ] = implode( ', ', $values );
		}

		/* check the body digest if it's present */

		if ( isset( $headers['content-digest'] ) ) {
			$body = (string) $request->get_body();
			if ( ! $this->is_body_digest_valid( $body, $headers['content-digest'] ) ) {
				return false;
			}
		}

		return $this->verify( $headers, $request );
	}

	/**
	 * Check if the body digest is valid.
	 *
	 * From https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Digest
	 * The algorithm used to create a digest of the message content. Only two registered digest algorithms are
	 * considered secure: sha-512 and sha-256. The insecure (legacy) registered digest algorithms
	 * are: md5, sha (SHA-1), unixsum, unixcksum, adler (ADLER32) and crc32c.
	 *
	 * @param string $body        The body content to validate.
	 * @param string $header_value The Content-Digest header value.
	 * @return bool Whether the digest is valid.
	 */
	private function is_body_digest_valid( string $body, string $header_value ): bool {
		if ( ! preg_match( '/sha-(.*?)=:(.*?):/', $header_value, $matches ) ) {
			return false;
		}
		if ( ! in_array( $matches[1], array( '256', '512' ), true ) ) {
			return false;
		}

		$algorithm = 'sha' . $matches[1];

		$expected_digest = base64_decode( $matches[2] );
		$actual_digest   = hash( $algorithm, $body, true );

		return hash_equals( $expected_digest, $actual_digest );
	}

	/**
	 * Sign HTTP headers with the configured algorithm.
	 *
	 * @param array  $headers        The headers to include in the signature.
	 * @param string $covered_fields The fields to be covered by the signature.
	 * @param array  $request_args   The message interface.
	 * @return array The signature and signature-input headers.
	 */
	public function sign( array $headers, string $covered_fields, $request_args ) {
		$signature_components = array();
		$processed_components = array();

		$dict = $this->parse_structured_dict( $covered_fields );

		if ( $dict->isNotEmpty() ) {
			$covered_structured_fields = $dict->__toString();
			$indices                   = $dict->indices();
			foreach ( $indices as $index ) {
				$member = $dict->getByIndex( $index );
				if ( ! $member ) {
					throw new \Exception( 'Index ' . $index . ' not found' );
				}
				if ( in_array( $member, $processed_components, true ) ) {
					throw new \Exception( 'Duplicate member found' );
				}

				$processed_components[] = $member;
				$signature_components[] = $this->canonicalize_component( $member, $headers, $request_args );
			}
		}

		$signature_input = $covered_structured_fields . ';keyid="'
							. $this->key_id . '";alg="' . $this->algorithm . '"'
							. ( ( $this->created ) ? ';created=' . $this->created : '' )
							. ( ( $this->expires ) ? ';expires=' . $this->expires : '' )
							. ( ( $this->nonce ) ? ';nonce="' . $this->nonce . '"' : '' )
							. ( ( $this->tag ) ? ';tag="' . $this->tag . '"' : '' );

		/**
		 * Always include @signature-params in the result.
		 */
		$signature_components[] = '"@signature-params": ' . $signature_input;

		$signature_base = implode( "\n", $signature_components );
		$signature      = $this->create_signature( $signature_base );

		$headers['signature-input'] = "$this->signature_id=$signature_input";
		$headers['signature']       = "$this->signature_id=:$signature:";

		return $headers;
	}

	public function verify( array $headers, $request ) {
		if ( ! isset( $headers['signature-input'], $headers['signature'] ) ) {
			return false;
		}
		$headers[] = 'signature-params';

		$sig_input_dict = $this->parse_structured_dict( $headers['signature-input'] );

		$signature_components = array();

		if ( $sig_input_dict->isNotEmpty() ) {
			$indices = $sig_input_dict->indices();
			foreach ( $indices as $index ) {
				list( $dict_name, $members ) = $sig_input_dict->getByIndex( $index );

				if ( $members instanceof Custom_InnerList ) {
					$components    = $members;
					$inner_indices = $members->indices();
					foreach ( $inner_indices as $inner_index ) {
						$member                               = $members->getByIndex( $inner_index );
						$signature_components[ $dict_name ][] = $this->canonicalize_component( $member, $headers, $request );
					}
				}
			}
			$top_level_params = $this->extract_parameters( $components );
			if ( isset( $top_level_params['expires'] ) ) {
				$expires = (int) $top_level_params['expires'];
				if ( $expires < time() ) {
					return false;
				}
			}
		}

		$sig_dict = $this->parse_structured_dict( $headers['signature'] );
		if ( $sig_dict->isNotEmpty() ) {
			$indices = $sig_dict->indices();
			foreach ( $indices as $index ) {
				list( $dict_name, $members ) = $sig_dict->getByIndex( $index );
				if ( $members instanceof Custom_Item ) {
					$signatures[ $dict_name ] = $members->value();
				}
				if ( $members instanceof Custom_InnerList ) {
					$inner_indices = $members->indices();
					foreach ( $inner_indices as $inner_index ) {
						$signatures[ $dict_name ][] = $members->getByIndex( $inner_index );
					}
				}
			}
		}

		foreach ( $signature_components as $dict_name => $dict_components ) {
			$named_signature_components   = $signature_components[ $dict_name ];
			$signature_params_str         = $sig_input_dict[ $dict_name ]->toHttpValue();
			$named_signature_components[] = '"@signature-params": ' . $signature_params_str;
			$signature_base               = implode( "\n", $named_signature_components );
			if ( ! isset( $sig_dict[ $dict_name ] ) ) {
				return false;
			}

			$decoded_sig = base64_decode( trim( $sig_dict[ $dict_name ]->__toString(), ':' ) );
			return $this->verify_signature( $signature_base, $decoded_sig, $params['alg'] ?? $this->algorithm );
		}
		return false;
	}

	/**
	 * Extract parameters from a structured field.
	 *
	 * @param object $field The structured field to extract parameters from.
	 * @return array Associative array of parameters.
	 */
	protected function extract_parameters( $field ) {
		$parameters   = array();
		$field_params = $field->parameters();

		if ( $field_params->isNotEmpty() ) {
			$indices = $field_params->indices();
			foreach ( $indices as $index ) {
				list( $name, $item ) = $field_params->getByIndex( $index );
				$parameters[ $name ] = $item->value();
			}
		}
		return $parameters;
	}

	/**
	 * Canonicalize a component for signature calculation.
	 *
	 * @param string $field   The field name to canonicalize.
	 * @param array  $headers The headers array.
	 * @param array  $request_args The message interface.
	 * @return string The canonicalized component string.
	 */
	private function canonicalize_component( $field, $headers, $request_args ) {
		$field = $field[1];
		$field_name = $field->value();
		$parameters = $this->extract_parameters( $field );
		if ( isset( $parameters['bs'] ) && isset( $parameters['sf'] ) ) {
			throw new \Exception( 'Cannot use both bs and sf' );
		}

		$which_request = $request_args;
		if ( isset( $parameters['req'] ) ) {
			$which_request = $this->get_original_request();
		}

		$which_headers = $headers;

		list( $name, $value ) = $this->get_field_value( $field_name, $which_request, $which_headers, $parameters );

		if ( isset( $parameters['bs'] ) ) {
			$result = $name . ';bs: ';
			$values = $which_request['headers'][ $field_name ];
			if ( ! $values ) {
				return '';
			}
			if ( ! is_array( $values ) ) {
				$values = array( $values );
			}
			foreach ( $values as $value ) {
				$value   = trim( $value );
				$result .= ':' . base64_encode( $value ) . ':, ';
			}
			return $values ? rtrim( $result, ', ' ) : $result;
		}

		if ( isset( $parameters['sf'] ) ) {
			$value = $this->apply_structured_field( $name, $value );
			return $name . ';sf: ' . $value;
		}
		if ( isset( $parameters['key'] ) ) {
			$child_name = $parameters['key'];
			$value      = $this->apply_single_key_value( $name, $child_name, $value );
			return $name . ';key="' . $child_name . '": ' . $value;
		}
		return $name . ': ' . $value;
	}

	/**
	 * Get the value for a specific field from the message interface.
	 *
	 * @param string           $field_name The name of the field to get the value for.
	 * @param \WP_REST_Request $request    The message interface to extract values from.
	 * @param array            $headers    The headers array.
	 * @param array            $parameters Additional parameters for field processing.
	 * @return array An array containing the field name and value.
	 */
	private function get_field_value( $field_name, $request, $headers, $parameters ): array {
		switch ( $field_name ) {
			case '@signature-params':
				$value = array( '', '' );
				break;
			case '@method':
				$value = array( '"@method"', strtoupper( $request->get_method() ) );
				break;
			case '@authority':
				$value = array( '"@authority"', $request->get_route()->getAuthority() );
				break;
			case '@scheme':
				$value = array( '"@scheme"', wp_parse_url( strtolower( $request->get_route() ), PHP_URL_SCHEME ) );
				break;
			case '@target-uri':
				$value = array( '"target-uri"', $request->get_route() );
				break;
			case '@request-target':
				$value = array( '"@request-target"', $request->get_route() );
				break;
			case '@path':
				$value = array( '"@path"', wp_parse_url( $request->get_route(), PHP_URL_PATH ) );
				break;
			case '@query':
				$value = array( '"@query"', wp_parse_url( $request->get_route(), PHP_URL_QUERY ) );
				break;
			case '@query-param':
				$query_param_result = $this->get_query_param( $request, $parameters );
				$value              = null !== $query_param_result ? $query_param_result : array( '', '' );
				break;
			case '@status':
				$value = array( '"@status"', '"@status": ' . $request->get_status() );
				break;
			default:
				$value = array( '"' . $field_name . '"', trim( $headers[ $field_name ] ?? '' ) );
				break;
		}

		return $value;
	}

	/**
	 * Parse a query string into an associative array.
	 *
	 * Should not use PHP's parse_str function here, as it has some issues with
	 * spaces and dots in parameter names. These are unlikely to occur, but the
	 * following function treats them as opaque strings rather than as variable
	 * names.
	 *
	 * @param string $query The query string to parse.
	 * @return array|null The parsed query parameters or null if query is not set.
	 */
	private function parse_query_string( string $query ) {
		$result = array();

		if ( ! isset( $query ) ) {
			return null;
		}

		$query_params = explode( '&', $query );
		foreach ( $query_params as $param ) {
			// The '=' character is not required and indicates a boolean true value if unset.
			$element                            = explode( '=', $param, 2 );
			$result[ urldecode( $element[0] ) ] = isset( $element[1] ) ? urldecode( $element[1] ) : '';
		}
		return $result;
	}

	/**
	 * Find one query parameter by name from the request.
	 *
	 * Find one query parameter by name (which must supplied as parameters in the
	 * (structured) covered field list).
	 *
	 * @param \WP_REST_Request $which_request The request to extract query parameters from.
	 * @param array            $parameters    The parameters containing the name of the query parameter.
	 * @return array An array containing the field name and value.
	 */
	private function get_query_param( $which_request, array $parameters ): array {
		$query_params = $which_request->get_query_params();
		if ( ! empty( $query_params ) ) {
			$field_name = $parameters['name'];
			if ( $field_name ) {
				return array( '"_' . $field_name . '_"', $query_params[ $field_name ] ? '"' . $query_params[ $field_name ] . '"' : '' );
			}
		}
		throw new \Exception( 'Query string named parameter not set' );
	}

	/**
	 * Apply structured field formatting based on the field type.
	 *
	 * Processes different HTTP header field types according to their structured format
	 * as defined in the structured_field_types property.
	 *
	 * @param string $name        The name of the field.
	 * @param string $field_value The value of the field to process.
	 * @return string The processed field value.
	 */
	private function apply_structured_field( string $name, string $field_value ): string {
		$type = $this->structured_field_types[ trim( $name, '"' ) ];
		switch ( $type ) {
			case 'list':
				$field = Custom_OuterList::fromHttpValue( $field_value );
				break;
			case 'innerlist':
				$field = Custom_InnerList::fromHttpValue( $field_value );
				break;
			case 'parameters':
				$field = Custom_Parameters::fromHttpValue( $field_value );
				break;
			case 'dictionary':
				$field = Custom_Dictionary::fromHttpValue( $field_value );
				break;
			case 'item':
				$field = Custom_Item::fromHttpValue( $field_value );
				break;
			case 'url':
				return '"' . $field_value . '"';
			case 'date':
				return '@' . strtotime( $field_value );
			case 'etag':
				$result = '';
				$list   = explode( ',', $field_value );
				foreach ( $list as $item ) {
					if ( str_starts_with( trim( $item ), 'W/' ) ) {
						$result .= substr( trim( $item ), 2 ) . '; w, ';
					} else {
						$result .= trim( $item ) . ', ';
					}
				}
				return rtrim( $result, ', ' );
			case 'cookie':
				// @TODO
			default:
				break;
		}
		if ( ! $field ) {
			return '';
		}
		return $field->toHttpValue();
	}

	/**
	 * Apply a single key value from a dictionary field.
	 *
	 * Extracts a specific key's value from a dictionary-type structured field.
	 *
	 * @param string $name        The name of the field.
	 * @param string $key         The key to extract from the dictionary.
	 * @param string $field_value The field value containing the dictionary.
	 * @return string The extracted value or empty string if not found.
	 */
	private function apply_single_key_value( string $name, string $key, string $field_value ): string {
		$type = $this->structured_field_types[ trim( $name, '"' ) ];
		if ( empty( $type ) || 'dictionary' === $type ) {
			$dictionary = Custom_Dictionary::fromHttpValue( $field_value );
			if ( $dictionary->isNotEmpty() && isset( $dictionary[ $key ] ) ) {
				return $dictionary[ $key ]->toHttpValue();
			}
		}
		return '';
	}

	/**
	 * Create a signature for the provided data using the configured algorithm.
	 *
	 * @param string $data The data to sign.
	 * @return string The base64-encoded signature.
	 * @throws \RuntimeException When an unsupported algorithm is specified.
	 */
	private function create_signature( string $data ): string {
		switch ( $this->algorithm ) {
			case 'rsa-sha256':
				return $this->rsa_sign( $data );
			case 'ed25519':
				return $this->ed25519_sign( $data );
			case 'hmac-sha256':
				return base64_encode( hash_hmac( 'sha256', $data, $this->private_key, true ) );
			default:
				throw new \RuntimeException( "Unsupported algorithm: $this->algorithm" );
		}
	}

	/**
	 * Verify a signature against the provided data using the specified algorithm.
	 *
	 * @param string $data      The data that was signed.
	 * @param string $signature The signature to verify.
	 * @param string $alg       The algorithm used for signing.
	 * @return bool Whether the signature is valid.
	 */
	private function verify_signature( string $data, string $signature, string $alg ): bool {
		switch ( $alg ) {
			case 'rsa-sha256':
				return openssl_verify( $data, $signature, $this->public_key, OPENSSL_ALGO_SHA256 ) === 1;
			case 'ed25519':
				return openssl_verify( $data, $signature, $this->public_key, 'Ed25519' ) === 1;
			case 'hmac-sha256':
				return hash_equals(
					base64_encode( hash_hmac( 'sha256', $data, $this->private_key, true ) ),
					base64_encode( $signature )
				);
			default:
				return false;
		}
	}

	/**
	 * Sign data using RSA-SHA256 algorithm.
	 *
	 * @param string $data The data to sign.
	 * @return string The base64-encoded signature.
	 * @throws \RuntimeException When RSA signing fails.
	 */
	private function rsa_sign( string $data ): string {
		if ( ! openssl_sign( $data, $signature, $this->private_key, OPENSSL_ALGO_SHA256 ) ) {
			throw new \RuntimeException( 'RSA signing failed' );
		}
		return base64_encode( $signature );
	}

	/**
	 * Sign data using Ed25519 algorithm.
	 *
	 * @param string $data The data to sign.
	 * @return string The base64-encoded signature.
	 * @throws \RuntimeException When Ed25519 signing fails.
	 */
	private function ed25519_sign( string $data ): string {
		if ( ! openssl_sign( $data, $signature, $this->private_key, 'Ed25519' ) ) {
			throw new \RuntimeException( 'Ed25519 signing failed' );
		}
		return base64_encode( $signature );
	}

	/**
	 * Parse a structured dictionary from a header value.
	 *
	 * @param string $header_value The header value to parse.
	 * @return Custom_InnerList|Custom_Dictionary The parsed structured field.
	 */
	private function parse_structured_dict( string $header_value ) {
		if ( str_starts_with( trim( $header_value ), '(' ) ) {
			return Custom_InnerList::fromHttpValue( $header_value );
		} else {
			return Custom_Dictionary::fromHttpValue( $header_value );
		}
	}

	/*
	Recommended to calculate the digest of the body and add it to
		covered headers and sign, but not required. Convenience function
		to calculate the digest.

		ex:

		$digest = $signer->create_content_digest_header($body);
		$request = $request->withHeader('Content-Digest', $digest);
	*/

	/**
	 * From https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Digest
	 * The algorithm used to create a digest of the message content. Only two registered digest algorithms are
	 * considered secure: sha-512 and sha-256. The insecure (legacy) registered digest algorithms
	 * are: md5, sha (SHA-1), unixsum, unixcksum, adler (ADLER32) and crc32c.
	 *
	 * @param string $body     The body content to create a digest for.
	 * @param string $algorithm The algorithm to use for the digest. Default 'sha-256'.
	 * @return string The Content-Digest header value.
	 */
	public function create_content_digest_header( string $body, string $algorithm = 'sha256' ): string {
		$supported_algorithms = array(
			'sha256' => 'sha-256',
			'sha512' => 'sha-512',
		);

		if ( isset( $supported_algorithms[ $algorithm ] ) ) {
			$algorithm_header_string = $supported_algorithms[ $algorithm ];
		} else {
			throw new \RuntimeException( "Unsupported algorithm: $algorithm" );
		}

		$digest = hash( $algorithm, $body, true );

		// Output as structured field.
		return $algorithm_header_string . '=:' . base64_encode( $digest ) . ':';
	}

	/**
	 * Parse an HTTP message into its components.
	 *
	 * Convenience function, probably want to use a robust PSR7 solution instead.
	 *
	 * @param string $raw The raw HTTP message.
	 * @return array An array containing the parsed HTTP message components.
	 */
	public static function parse_http_message( string $raw ): array {
		list($header_part, $body) = explode( "\r\n\r\n", $raw, 2 );
		$lines                    = explode( "\r\n", $header_part );
		$request_line             = array_shift( $lines );
		list($method, $path)      = explode( ' ', $request_line );

		$headers = array();
		foreach ( $lines as $line ) {
			[$name, $value]                         = explode( ':', $line, 2 );
			$headers[ strtolower( trim( $name ) ) ] = trim( $value );
		}

		return array(
			'method'  => $method,
			'path'    => $path,
			'headers' => $headers,
			'body'    => $body,
		);
	}

	/**
	 * Get the original request.
	 *
	 * Retrieves the original request object.
	 *
	 * @return \WP_REST_Request The original request object.
	 */
	public function get_original_request() {
		return $this->original_request;
	}

	/**
	 * Set the original request.
	 *
	 * Sets the original request object.
	 *
	 * @param \WP_REST_Request $original_request The original request object.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_original_request( $original_request ) {
		$this->original_request = $original_request;
		return $this;
	}

	/**
	 * Get the key ID.
	 *
	 * Retrieves the key ID.
	 *
	 * @return string The key ID.
	 */
	public function get_key_id(): string {
		return $this->key_id;
	}

	/**
	 * Set the key ID.
	 *
	 * Sets the key ID.
	 *
	 * @param string $key_id The key ID to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_key_id( string $key_id ): Http_Signature {
		$this->key_id = $key_id;
		return $this;
	}

	/**
	 * Get the private key.
	 *
	 * Retrieves the private key.
	 *
	 * @return string The private key.
	 */
	public function get_private_key(): string {
		return $this->private_key;
	}

	/**
	 * Set the private key.
	 *
	 * Sets the private key.
	 *
	 * @param string $private_key The private key to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_private_key( string $private_key ): Http_Signature {
		$this->private_key = $private_key;
		return $this;
	}

	/**
	 * Get the public key.
	 *
	 * Retrieves the public key.
	 *
	 * @return string The public key.
	 */
	public function get_public_key(): string {
		return $this->public_key;
	}

	/**
	 * Set the public key.
	 *
	 * Sets the public key.
	 *
	 * @param string $public_key The public key to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_public_key( string $public_key ): Http_Signature {
		$this->public_key = $public_key;
		return $this;
	}

	/**
	 * Get the algorithm.
	 *
	 * Retrieves the algorithm.
	 *
	 * @return string The algorithm.
	 */
	public function get_algorithm(): string {
		return $this->algorithm;
	}

	/**
	 * Set the algorithm.
	 *
	 * Sets the algorithm.
	 *
	 * @param string $algorithm The algorithm to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_algorithm( string $algorithm ): Http_Signature {
		$this->algorithm = $algorithm;
		return $this;
	}

	/**
	 * Get the signature ID.
	 *
	 * Retrieves the signature ID.
	 *
	 * @return string The signature ID.
	 */
	public function get_signature_id(): string {
		return $this->signature_id;
	}

	/**
	 * Set the signature ID.
	 *
	 * Sets the signature ID.
	 *
	 * @param string $signature_id The signature ID to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_signature_id( string $signature_id ): Http_Signature {
		$this->signature_id = $signature_id;
		return $this;
	}

	/**
	 * Get the creation timestamp.
	 *
	 * Retrieves the creation timestamp.
	 *
	 * @return string The creation timestamp.
	 */
	public function get_created(): string {
		return $this->created;
	}

	/**
	 * Set the creation timestamp.
	 *
	 * Sets the creation timestamp.
	 *
	 * @param string $created The creation timestamp to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_created( string $created ): Http_Signature {
		$this->created = $created;
		return $this;
	}

	/**
	 * Get the expiration timestamp.
	 *
	 * Retrieves the expiration timestamp.
	 *
	 * @return string The expiration timestamp.
	 */
	public function get_expires(): string {
		return $this->expires;
	}

	/**
	 * Set the expiration timestamp.
	 *
	 * Sets the expiration timestamp.
	 *
	 * @param string $expires The expiration timestamp to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_expires( string $expires ): Http_Signature {
		$this->expires = $expires;
		return $this;
	}

	/**
	 * Get the nonce value.
	 *
	 * Retrieves the nonce value.
	 *
	 * @return string The nonce value.
	 */
	public function get_nonce(): string {
		return $this->nonce;
	}

	/**
	 * Set the nonce value.
	 *
	 * Sets the nonce value.
	 *
	 * @param string $nonce The nonce value to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_nonce( string $nonce ): Http_Signature {
		$this->nonce = $nonce;
		return $this;
	}

	/**
	 * Get the tag value.
	 *
	 * Retrieves the tag value.
	 *
	 * @return string The tag value.
	 */
	public function get_tag(): string {
		return $this->tag;
	}

	/**
	 * Set the tag value.
	 *
	 * Sets the tag value.
	 *
	 * @param string $tag The tag value to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_tag( string $tag ): Http_Signature {
		$this->tag = $tag;
		return $this;
	}

	/**
	 * Calculate the signature base string without signing it.
	 *
	 * Can be used as a development function to peek into the internals of the exact things
	 * that were signed and/or test the internal results of data normalisation.
	 * This matches the sign() function except that it just returns the serialised string
	 * and does not sign it.
	 *
	 * @param array            $headers        The headers to include in the signature.
	 * @param string           $covered_fields The fields to be covered by the signature.
	 * @param \WP_REST_Request $request        The message interface.
	 * @return string The signature base string.
	 */
	public function calculate_signature_base( $headers, $covered_fields, $request ) {
		$signature_components = array();
		$processed_components = array();
		$dict                 = $this->parse_structured_dict( $covered_fields );

		if ( $dict->isNotEmpty() ) {
			$covered_structured_fields = $dict->__toString();
			$indices                   = $dict->indices();
			foreach ( $indices as $index ) {
				$member = $dict->getByIndex( $index );
				if ( ! $member ) {
					throw new \Exception( 'Index ' . $index . ' not found' );
				}
				if ( in_array( $member, $processed_components, true ) ) {
					throw new \Exception( 'Duplicate member found' );
				}
				$processed_components[] = $member;
				$signature_components[] = $this->canonicalize_component( $member, $headers, $request );
			}
		}

		$signature_input = $covered_structured_fields . ';keyid="' . $this->key_id . '";alg="' . $this->algorithm . '"';

		if ( $this->created ) {
			$signature_input .= ';created=' . $this->created;
		}
		if ( $this->expires ) {
			$signature_input .= ';expires=' . $this->expires;
		}
		if ( $this->nonce ) {
			$signature_input .= ';nonce="' . $this->nonce . '"';
		}
		if ( $this->tag ) {
			$signature_input .= ';tag="' . $this->tag . '"';
		}

		/**
		 * Always include @signature-params in the result.
		 */
		$signature_components[] = '"@signature-params": ' . $signature_input;

		return implode( "\n", $signature_components );
	}
}
