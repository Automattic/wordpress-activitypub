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
	 * SWICG ActivityPub API Basic Profile canonical scope aliases.
	 *
	 * Advertised in OAuth metadata so Basic Profile clients can discover them,
	 * and accepted in scope requests (any `activitypub:read:*` collapses to
	 * `read`, any `activitypub:write:*` collapses to `write`). Enforcement
	 * stays coarse: there is no per-activity-type access control yet.
	 *
	 * @since 9.0.0
	 *
	 * @var array
	 */
	const CANONICAL_ALIASES = array(
		'activitypub:read:all',
		'activitypub:write:all',
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
	 * Canonical SWICG ActivityPub API Basic Profile scope names of the form
	 * `activitypub:read:*` and `activitypub:write:*` are normalized to the
	 * plugin's internal `read` and `write` scopes before validation.
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

		$scopes       = self::normalize( $scopes );
		$valid_scopes = array_intersect( $scopes, self::ALL );

		if ( empty( $valid_scopes ) ) {
			return self::DEFAULT_SCOPES;
		}

		return array_values( array_unique( $valid_scopes ) );
	}

	/**
	 * Normalize canonical Basic Profile scope names to internal scopes.
	 *
	 * Maps any `activitypub:read:*` to {@see self::READ} and any
	 * `activitypub:write:*` to {@see self::WRITE}. Unknown values pass through
	 * unchanged so they can be filtered out by the caller.
	 *
	 * @since 9.0.0
	 *
	 * @param array $scopes Requested scope strings.
	 * @return array Normalized scope strings.
	 */
	public static function normalize( $scopes ) {
		if ( ! is_array( $scopes ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $scopes as $scope ) {
			if ( ! is_string( $scope ) || '' === $scope ) {
				continue;
			}

			if ( 0 === strpos( $scope, 'activitypub:read:' ) ) {
				$normalized[] = self::READ;
				continue;
			}

			if ( 0 === strpos( $scope, 'activitypub:write:' ) ) {
				$normalized[] = self::WRITE;
				continue;
			}

			$normalized[] = $scope;
		}

		return $normalized;
	}

	/**
	 * Return the scope identifiers advertised in OAuth authorization-server metadata.
	 *
	 * Includes the plugin's internal scopes plus the SWICG Basic Profile
	 * canonical aliases so spec-aware clients can discover them.
	 *
	 * @since 9.0.0
	 *
	 * @return array Scope identifiers.
	 */
	public static function supported() {
		return array_merge( self::ALL, self::CANONICAL_ALIASES );
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
