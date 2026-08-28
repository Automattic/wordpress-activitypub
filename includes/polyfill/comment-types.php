<?php
/**
 * Polyfill for the comment type registry proposed for WordPress core.
 *
 * Mirrors WordPress/wordpress-develop#12311 (register_comment_type, Trac #35214) and #12310
 * (default excluded comment types, Trac #65537). Every function is guarded, so the moment core
 * ships one of them the core version wins and the copy here is skipped. That is the point:
 * the plugin talks to one API today, and nothing has to change when core lands.
 *
 * The same file can be dropped into Webmention and ATmosphere, which is why it carries no
 * plugin namespace and no plugin-specific behaviour.
 *
 * @package Activitypub
 * @see https://github.com/WordPress/wordpress-develop/pull/12311
 * @see https://github.com/WordPress/wordpress-develop/pull/12310
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- deliberately unprefixed: these ARE the core names.

if ( ! function_exists( 'register_comment_type' ) ) {
	/**
	 * Registers a comment type.
	 *
	 * @param string       $comment_type Comment type key. Must not exceed 20 characters.
	 * @param array|string $args         Optional. Arguments for registering a comment type.
	 *
	 * @return WP_Comment_Type|WP_Error The registered comment type object on success, WP_Error on failure.
	 */
	function register_comment_type( $comment_type, $args = array() ) {
		global $wp_comment_types;

		if ( ! is_array( $wp_comment_types ) ) {
			$wp_comment_types = array();
		}

		$args = wp_parse_args( $args );

		$comment_type = sanitize_key( $comment_type );

		if ( empty( $comment_type ) || strlen( $comment_type ) > 20 ) {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Comment type names must be between 1 and 20 characters in length.', 'activitypub' ), 'unreleased' );
			return new WP_Error( 'comment_type_length_invalid', __( 'Comment type names must be between 1 and 20 characters in length.', 'activitypub' ) );
		}

		// Built-in types cannot be re-registered; a plugin overwriting one would strip the flags core relies on.
		if ( isset( $wp_comment_types[ $comment_type ] ) && $wp_comment_types[ $comment_type ]->_builtin && empty( $args['_builtin'] ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: %s: Comment type key. */
					esc_html__( 'The "%s" comment type is a built-in type and cannot be re-registered.', 'activitypub' ),
					esc_html( $comment_type )
				),
				'unreleased'
			);
			return new WP_Error( 'comment_type_builtin', __( 'Built-in comment types cannot be re-registered.', 'activitypub' ) );
		}

		// These are aliases WP_Comment_Query expands, not types; registering one would poison type queries.
		if ( in_array( $comment_type, array( 'all', 'comments', 'pings' ), true ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: %s: Comment type key. */
					esc_html__( 'The "%s" comment type name is reserved for use by WP_Comment_Query.', 'activitypub' ),
					esc_html( $comment_type )
				),
				'unreleased'
			);
			return new WP_Error( 'comment_type_reserved', __( 'That comment type name is reserved.', 'activitypub' ) );
		}

		$comment_type_object = new WP_Comment_Type( $comment_type, $args );

		$wp_comment_types[ $comment_type ] = $comment_type_object;

		/**
		 * Fires after a comment type is registered.
		 *
		 * @param string          $comment_type        Comment type.
		 * @param WP_Comment_Type $comment_type_object Arguments used to register the comment type.
		 */
		do_action( 'registered_comment_type', $comment_type, $comment_type_object );

		return $comment_type_object;
	}
}

if ( ! function_exists( 'unregister_comment_type' ) ) {
	/**
	 * Unregisters a comment type.
	 *
	 * @param string $comment_type Comment type to unregister.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure or if the comment type does not exist.
	 */
	function unregister_comment_type( $comment_type ) {
		global $wp_comment_types;

		if ( ! comment_type_exists( $comment_type ) ) {
			return new WP_Error( 'invalid_comment_type', __( 'Invalid comment type.', 'activitypub' ) );
		}

		$comment_type_object = get_comment_type_object( $comment_type );

		if ( $comment_type_object->_builtin ) {
			return new WP_Error( 'invalid_comment_type', __( 'Unregistering a built-in comment type is not allowed.', 'activitypub' ) );
		}

		unset( $wp_comment_types[ $comment_type ] );

		/**
		 * Fires after a comment type was unregistered.
		 *
		 * @param string $comment_type Comment type key.
		 */
		do_action( 'unregistered_comment_type', $comment_type );

		return true;
	}
}

if ( ! function_exists( 'get_comment_type_object' ) ) {
	/**
	 * Retrieves a comment type object by name.
	 *
	 * @param string $comment_type The name of a registered comment type.
	 *
	 * @return WP_Comment_Type|null WP_Comment_Type object if it exists, null otherwise.
	 */
	function get_comment_type_object( $comment_type ) {
		global $wp_comment_types;

		if ( ! is_scalar( $comment_type ) || empty( $wp_comment_types[ $comment_type ] ) ) {
			return null;
		}

		return $wp_comment_types[ $comment_type ];
	}
}

if ( ! function_exists( 'get_comment_types' ) ) {
	/**
	 * Retrieves a list of registered comment type names or objects.
	 *
	 * @param array|string $args     Optional. Array or string of comment type arguments to match.
	 * @param string       $output   Optional. 'names' or 'objects'. Default 'names'.
	 * @param string       $operator Optional. 'or' or 'and'. Default 'and'.
	 *
	 * @return string[]|WP_Comment_Type[] Comment type names or objects, keyed by name.
	 */
	function get_comment_types( $args = array(), $output = 'names', $operator = 'and' ) {
		global $wp_comment_types;

		$field = ( 'names' === $output ) ? 'name' : false;

		return wp_filter_object_list( (array) $wp_comment_types, $args, $operator, $field );
	}
}

if ( ! function_exists( 'comment_type_exists' ) ) {
	/**
	 * Determines whether a comment type is registered.
	 *
	 * @param string $comment_type Comment type name.
	 *
	 * @return bool Whether the comment type is registered.
	 */
	function comment_type_exists( $comment_type ) {
		return (bool) get_comment_type_object( $comment_type );
	}
}

if ( ! function_exists( 'get_comment_type_labels' ) ) {
	/**
	 * Builds an object with all comment type labels out of a comment type object.
	 *
	 * Core routes this through `_get_custom_object_labels()`, a private helper shared with post
	 * types. It is not reproduced here; the polyfill fills the same keys from the registration's
	 * `labels` array, then the defaults, so `$labels->name` and `->singular_name` read the same.
	 *
	 * @param WP_Comment_Type $comment_type_object Comment type object.
	 *
	 * @return object Object with all the labels as member variables.
	 */
	function get_comment_type_labels( $comment_type_object ) {
		$defaults = WP_Comment_Type::get_default_labels();
		$provided = (array) $comment_type_object->labels;
		$labels   = array();

		foreach ( $defaults as $key => $pair ) {
			// Index 0 is the non-hierarchical default; comment types are never hierarchical.
			$labels[ $key ] = isset( $provided[ $key ] ) ? $provided[ $key ] : $pair[0];
		}

		// A bare `label` on the registration is the plural name, the way register_post_type() treats it.
		if ( empty( $provided['name'] ) && ! empty( $comment_type_object->label ) ) {
			$labels['name']      = $comment_type_object->label;
			$labels['menu_name'] = $comment_type_object->label;
		}

		$labels         = (object) $labels;
		$default_labels = clone $labels;
		$comment_type   = $comment_type_object->name;

		/**
		 * Filters the labels of a specific comment type.
		 *
		 * The dynamic portion of the hook name, `$comment_type`, refers to the comment type key.
		 *
		 * @param object $labels Object with labels for the comment type as member variables.
		 */
		$labels = apply_filters( "comment_type_labels_{$comment_type}", $labels );

		return (object) array_merge( (array) $default_labels, (array) $labels );
	}
}

if ( ! function_exists( 'wp_get_default_excluded_comment_types' ) ) {
	/**
	 * Retrieves the comment types excluded from queries, counts and feeds by default.
	 *
	 * Seeded from every type registered as `internal`, so a plugin only has to register its
	 * private types that way; the filter remains for exceptions.
	 *
	 * @param WP_Comment_Query|null $query Optional. The comment query being built, if any.
	 *
	 * @return string[] Comment type names to exclude by default.
	 */
	function wp_get_default_excluded_comment_types( $query = null ) {
		$default_excluded_types = array( 'note' );

		if ( function_exists( 'get_comment_types' ) ) {
			$default_excluded_types = array_values(
				array_unique(
					array_merge(
						$default_excluded_types,
						get_comment_types( array( 'internal' => true ), 'names' )
					)
				)
			);
		}

		/**
		 * Filters the comment types excluded from queries, counts and feeds by default.
		 *
		 * @param string[]              $excluded_types Comment type names to exclude.
		 * @param WP_Comment_Query|null $query          The comment query being built, if any.
		 */
		$excluded_types = apply_filters( 'default_excluded_comment_types', $default_excluded_types, $query );

		$excluded_types = array_filter( (array) $excluded_types, 'is_scalar' );
		$excluded_types = array_filter(
			array_map( 'strval', $excluded_types ),
			static function ( $excluded_type ) {
				return '' !== $excluded_type;
			}
		);
		$excluded_types = array_unique( $excluded_types );

		// Strip the special type tokens so an alias cannot poison explicit-type queries.
		return array_values( array_diff( $excluded_types, array( 'all', 'comment', 'comments', 'pings' ) ) );
	}
}
