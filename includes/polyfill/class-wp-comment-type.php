<?php
/**
 * Polyfill for the WP_Comment_Type class proposed for WordPress core.
 *
 * Mirrors WordPress/wordpress-develop#12311 (Trac #35214). Loaded only while core does not
 * ship the class, so the plugin has one comment type implementation whether or not core has
 * landed it yet. Once it lands, this file becomes dead code and can be deleted.
 *
 * @package Activitypub
 * @see https://github.com/WordPress/wordpress-develop/pull/12311
 * @see https://core.trac.wordpress.org/ticket/35214
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- deliberately unprefixed: this IS the core name.

if ( class_exists( 'WP_Comment_Type', false ) ) {
	return;
}

/**
 * Core class used for interacting with comment types.
 *
 * Kept byte-for-byte close to the core proposal, including the dynamic-property allowance:
 * core copies every registration arg onto the object, which is how plugin-specific args such
 * as `icon`, `class` or `activity_types` survive. On PHP 7.4 the attribute is parsed as a
 * comment and dynamic properties are allowed anyway.
 */
#[AllowDynamicProperties]
final class WP_Comment_Type {

	/**
	 * Comment type key.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Name of the comment type shown in the menu. Usually plural.
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Labels object for this comment type.
	 *
	 * @var stdClass
	 */
	public $labels;

	/**
	 * A short descriptive summary of what the comment type is.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Whether the comment type is meant to be shown publicly.
	 *
	 * @var bool
	 */
	public $public = true;

	/**
	 * Whether the comment type is internal and excluded from listings and counts by default.
	 *
	 * @var bool
	 */
	public $internal = false;

	/**
	 * Whether this comment type is a native or "built-in" comment type.
	 *
	 * @var bool
	 */
	public $_builtin = false; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Whether the comment type is hierarchical. Always false for now.
	 *
	 * @var bool
	 */
	public $hierarchical = false;

	/**
	 * Constructor.
	 *
	 * @param string       $comment_type Comment type key.
	 * @param array|string $args         Optional. Array or string of arguments for registering a comment type.
	 */
	public function __construct( $comment_type, $args = array() ) {
		$this->name = $comment_type;

		$this->set_props( $args );
	}

	/**
	 * Sets comment type properties.
	 *
	 * @param array|string $args Array or string of arguments for registering a comment type.
	 */
	public function set_props( $args ) {
		$args = wp_parse_args( $args );

		/**
		 * Filters the arguments for registering a comment type.
		 *
		 * @param array  $args         Array of arguments for registering a comment type.
		 * @param string $comment_type Comment type key.
		 */
		$args = apply_filters( 'register_comment_type_args', $args, $this->name );

		$comment_type = $this->name;

		/**
		 * Filters the arguments for registering a specific comment type.
		 *
		 * The dynamic portion of the filter name, `$comment_type`, refers to the comment type key.
		 *
		 * @param array  $args         Array of arguments for registering a comment type.
		 * @param string $comment_type Comment type key.
		 */
		$args = apply_filters( "register_{$comment_type}_comment_type_args", $args, $this->name );

		$defaults = array(
			'labels'      => array(),
			'description' => '',
			'public'      => true,
			'internal'    => false,
			'_builtin'    => false,
		);

		$args = array_merge( $defaults, $args );

		$args['name'] = $this->name;

		// Hierarchical comment types are not supported yet.
		$args['hierarchical'] = false;

		foreach ( $args as $property_name => $property_value ) {
			$this->$property_name = $property_value;
		}

		$this->labels = get_comment_type_labels( $this );
		$this->label  = $this->labels->name;
	}

	/**
	 * Returns the default labels for comment types.
	 *
	 * @return (string|null)[][] The default labels for comment types.
	 */
	public static function get_default_labels() {
		return array(
			'name'          => array( _x( 'Comments', 'comment type general name', 'activitypub' ), _x( 'Comments', 'comment type general name', 'activitypub' ) ),
			'singular_name' => array( _x( 'Comment', 'comment type singular name', 'activitypub' ), _x( 'Comment', 'comment type singular name', 'activitypub' ) ),
			'search_items'  => array( __( 'Search Comments', 'activitypub' ), __( 'Search Comments', 'activitypub' ) ),
			'not_found'     => array( __( 'No comments found.', 'activitypub' ), __( 'No comments found.', 'activitypub' ) ),
			'edit_item'     => array( __( 'Edit Comment', 'activitypub' ), __( 'Edit Comment', 'activitypub' ) ),
			'view_item'     => array( __( 'View Comment', 'activitypub' ), __( 'View Comment', 'activitypub' ) ),
			'all_items'     => array( __( 'All Comments', 'activitypub' ), __( 'All Comments', 'activitypub' ) ),
			'menu_name'     => array( _x( 'Comments', 'comment type general name', 'activitypub' ), _x( 'Comments', 'comment type general name', 'activitypub' ) ),
		);
	}
}
