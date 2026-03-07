<?php
/**
 * OAuth 2.0 Scope definitions for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

/**
 * Scope class for OAuth 2.0 scope management.
 *
 * Defines available scopes and provides validation methods.
 */
class Scope {
	/**
	 * Read access scope - read actor profile, collections, and objects.
	 */
	const READ = 'read';

	/**
	 * Write access scope - create activities via POST to outbox.
	 */
	const WRITE = 'write';

	/**
	 * Follow access scope - manage following relationships.
	 */
	const FOLLOW = 'follow';

	/**
	 * Push access scope - subscribe to SSE streams.
	 */
	const PUSH = 'push';

	/**
	 * Profile access scope - edit actor profile.
	 */
	const PROFILE = 'profile';

	/**
	 * All available scopes.
	 *
	 * @var array
	 */
	const ALL = array(
		self::READ,
		self::WRITE,
		self::FOLLOW,
		self::PUSH,
		self::PROFILE,
	);

	/**
	 * Human-readable descriptions for each scope.
	 *
	 * @var array
	 */
	const DESCRIPTIONS = array(
		self::READ    => 'Read actor profile, collections, and objects',
		self::WRITE   => 'Create activities via POST to outbox',
		self::FOLLOW  => 'Manage following relationships',
		self::PUSH    => 'Subscribe to real-time event streams',
		self::PROFILE => 'Edit actor profile',
	);

	/**
	 * Default scopes when none are requested.
	 *
	 * Defaults to read-only to prevent granting write access without
	 * explicit scope request (fail-closed on access control).
	 *
	 * @var array
	 */
	const DEFAULT_SCOPES = array(
		self::READ,
	);

	/**
	 * Validate and filter requested scopes.
	 *
	 * @param string|array $scopes The requested scopes (space-separated string or array).
	 * @return array Valid scopes.
	 */
	public static function validate( $scopes ) {
		if ( is_string( $scopes ) ) {
			$scopes = self::parse( $scopes );
		}

		if ( ! is_array( $scopes ) ) {
			return self::DEFAULT_SCOPES;
		}

		$valid_scopes = array_intersect( $scopes, self::ALL );

		if ( empty( $valid_scopes ) ) {
			return self::DEFAULT_SCOPES;
		}

		return array_values( $valid_scopes );
	}

	/**
	 * Parse a space-separated scope string to array.
	 *
	 * @param string $scope_string Space-separated scopes.
	 * @return array Scope array.
	 */
	public static function parse( $scope_string ) {
		if ( empty( $scope_string ) || ! is_string( $scope_string ) ) {
			return array();
		}

		$scopes = preg_split( '/\s+/', trim( $scope_string ) );

		return array_filter( array_map( 'trim', $scopes ) );
	}

	/**
	 * Convert scopes array to space-separated string.
	 *
	 * @param array $scopes The scopes array.
	 * @return string Space-separated scope string.
	 */
	public static function to_string( $scopes ) {
		if ( ! is_array( $scopes ) ) {
			return '';
		}

		return implode( ' ', $scopes );
	}

	/**
	 * Check if a scope is valid.
	 *
	 * @param string $scope The scope to check.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( $scope ) {
		return in_array( $scope, self::ALL, true );
	}

	/**
	 * Get the description for a scope.
	 *
	 * @param string $scope The scope.
	 * @return string The description or empty string if not found.
	 */
	public static function get_description( $scope ) {
		return self::DESCRIPTIONS[ $scope ] ?? '';
	}

	/**
	 * Get all scopes with their descriptions.
	 *
	 * @return array Associative array of scope => description.
	 */
	public static function get_all_with_descriptions() {
		return self::DESCRIPTIONS;
	}

	/**
	 * Check if scopes contain a specific scope.
	 *
	 * @param array  $scopes The scopes to check.
	 * @param string $scope  The scope to look for.
	 * @return bool True if the scope is present.
	 */
	public static function contains( $scopes, $scope ) {
		return is_array( $scopes ) && in_array( $scope, $scopes, true );
	}

	/**
	 * Sanitize callback for scope storage.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized scopes array.
	 */
	public static function sanitize( $value ) {
		if ( is_string( $value ) ) {
			$value = self::parse( $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		return self::validate( $value );
	}
}
