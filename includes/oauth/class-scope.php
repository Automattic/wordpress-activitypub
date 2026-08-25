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
	 * Scopes that are granted implicitly by holding a broader one.
	 *
	 * `write` is every write the actor can make, so it covers following and profile edits too.
	 * `follow` and `profile` exist as narrower alternatives for a client that only needs one of
	 * them, which is the point of offering them on the consent screen at all. Reading is not
	 * implied by writing; a client that wants both asks for both.
	 *
	 * @since unreleased
	 *
	 * @var array
	 */
	const IMPLIES = array(
		self::WRITE => array( self::FOLLOW, self::PROFILE ),
	);

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
	 * Scope aliases from the SWICG ActivityPub API Basic Profile, as it stood before 2026-08-04.
	 *
	 * Maps every alias the draft defined, including the `:sameorigin` variants, to the nearest
	 * scope the plugin actually has. That is coarser than the draft intended: the plugin gates a
	 * write per activity only where it has a scope for it, so `activitypub:write:follow` resolves
	 * to `follow`, while `activitypub:write:like` resolves to `write`, which also permits deleting,
	 * blocking and flagging. See {@see self::for_activity()} for what each write actually needs.
	 *
	 * @since 9.0.0
	 * @since unreleased Changed from a list to an alias-to-scope map.
	 *
	 * @var array
	 */
	const CANONICAL_ALIASES = array(
		'activitypub:read:all'                          => self::READ,
		'activitypub:read:local:all'                    => self::READ,
		'activitypub:read:me:actor'                     => self::READ,
		'activitypub:read:me:all'                       => self::READ,
		'activitypub:read:me:followers'                 => self::READ,
		'activitypub:read:me:following'                 => self::READ,
		'activitypub:read:me:inbox'                     => self::READ,
		'activitypub:read:me:liked'                     => self::READ,
		'activitypub:read:me:outbox'                    => self::READ,
		'activitypub:read:remote:all'                   => self::READ,
		'activitypub:write:accept'                      => self::WRITE,
		'activitypub:write:add'                         => self::WRITE,
		'activitypub:write:add:sameorigin'              => self::WRITE,
		'activitypub:write:all'                         => self::WRITE,
		'activitypub:write:all:sameorigin'              => self::WRITE,
		'activitypub:write:announce'                    => self::WRITE,
		'activitypub:write:announce:sameorigin'         => self::WRITE,
		'activitypub:write:block'                       => self::WRITE,
		'activitypub:write:block:sameorigin'            => self::WRITE,
		'activitypub:write:create'                      => self::WRITE,
		'activitypub:write:create:inreplyto:sameorigin' => self::WRITE,
		'activitypub:write:create:sameorigin'           => self::WRITE,
		'activitypub:write:delete'                      => self::WRITE,
		'activitypub:write:delete:inreplyto:sameorigin' => self::WRITE,
		'activitypub:write:delete:sameorigin'           => self::WRITE,
		'activitypub:write:flag'                        => self::WRITE,
		'activitypub:write:flag:sameorigin'             => self::WRITE,
		'activitypub:write:follow'                      => self::FOLLOW,
		'activitypub:write:follow:sameorigin'           => self::FOLLOW,
		'activitypub:write:like'                        => self::WRITE,
		'activitypub:write:like:sameorigin'             => self::WRITE,
		'activitypub:write:question'                    => self::WRITE,
		'activitypub:write:reject'                      => self::WRITE,
		'activitypub:write:remove'                      => self::WRITE,
		'activitypub:write:remove:sameorigin'           => self::WRITE,
		'activitypub:write:undo:all'                    => self::WRITE,
		'activitypub:write:undo:all:sameorigin'         => self::WRITE,
		'activitypub:write:undo:announce'               => self::WRITE,
		'activitypub:write:undo:announce:sameorigin'    => self::WRITE,
		'activitypub:write:undo:block'                  => self::WRITE,
		'activitypub:write:undo:block:sameorigin'       => self::WRITE,
		'activitypub:write:undo:follow'                 => self::FOLLOW,
		'activitypub:write:undo:follow:sameorigin'      => self::FOLLOW,
		'activitypub:write:undo:like'                   => self::WRITE,
		'activitypub:write:undo:like:sameorigin'        => self::WRITE,
		'activitypub:write:update'                      => self::WRITE,
		'activitypub:write:update:inreplyto:sameorigin' => self::WRITE,
		'activitypub:write:update:sameorigin'           => self::WRITE,
	);

	/**
	 * Identifier prefix for the SWICG ActivityPub API Basic Profile scopes.
	 *
	 * @since unreleased
	 */
	const CANONICAL_SCOPE_PREFIX = 'https://swicg.github.io/activitypub-api/scopes#';

	/**
	 * Scope identifiers from the SWICG ActivityPub API Basic Profile, keyed by fragment.
	 *
	 * The Basic Profile replaced its `activitypub:*` aliases with these URL identifiers on
	 * 2026-08-04; {@see self::CANONICAL_ALIASES} is kept for clients built before that.
	 *
	 * Each identifier maps to the nearest scope the plugin has, which is coarser than the spec
	 * intends: the per-collection read identifiers all resolve to `read`, and the per-action
	 * write identifiers to `write`. `follow` and `updateprofile` are the two with a scope of
	 * their own. Three identifiers are deliberately absent: `readown` and `reactown` describe data
	 * on the client's own server rather than this one, and `uploadfiles` needs a MediaUpload
	 * endpoint the plugin does not implement.
	 *
	 * @since unreleased
	 */
	const CANONICAL_SCOPES = array(
		'readall'           => self::READ,
		'readany'           => self::READ,
		'readlocal'         => self::READ,
		'readinbox'         => self::READ,
		'readoutbox'        => self::READ,
		'readfollowers'     => self::READ,
		'readfollowing'     => self::READ,
		'readliked'         => self::READ,
		'createcontent'     => self::WRITE,
		'updatecontent'     => self::WRITE,
		'deletecontent'     => self::WRITE,
		'managefollowers'   => self::WRITE,
		'managecollections' => self::WRITE,
		'like'              => self::WRITE,
		'share'             => self::WRITE,
		'block'             => self::WRITE,
		'flag'              => self::WRITE,
		'reactlocal'        => self::WRITE,
		'reactany'          => self::WRITE,
		'addressall'        => self::WRITE,
		'addresspublic'     => self::WRITE,
		'addressactor'      => self::WRITE,
		'addressfollowers'  => self::WRITE,
		'follow'            => self::FOLLOW,
		'updateprofile'     => self::PROFILE,
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
	 * Basic Profile identifiers are normalized to the plugin's internal scopes before validation.
	 *
	 * @param string|array $scopes The requested scopes (space-separated string or array).
	 * @return array Valid scopes.
	 */
	public static function validate( $scopes ) {
		if ( \is_string( $scopes ) ) {
			$scopes = self::parse( $scopes );
		}

		if ( ! \is_array( $scopes ) ) {
			return self::DEFAULT_SCOPES;
		}

		$scopes       = self::normalize( $scopes );
		$valid_scopes = \array_intersect( $scopes, self::ALL );

		if ( empty( $valid_scopes ) ) {
			return self::DEFAULT_SCOPES;
		}

		return \array_values( \array_unique( $valid_scopes ) );
	}

	/**
	 * Normalize canonical Basic Profile scope names to internal scopes.
	 *
	 * Looks each requested scope up in {@see self::CANONICAL_ALIASES}. An exact lookup, so a
	 * scope means the same thing wherever it appears in the request. Unknown values pass through
	 * unchanged so they can be filtered out by the caller.
	 *
	 * @since 9.0.0
	 *
	 * @param array $scopes Requested scope strings.
	 * @return array Normalized scope strings.
	 */
	public static function normalize( $scopes ) {
		if ( ! \is_array( $scopes ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $scopes as $scope ) {
			if ( ! \is_string( $scope ) || '' === $scope ) {
				continue;
			}

			if ( isset( self::CANONICAL_ALIASES[ $scope ] ) ) {
				$normalized[] = self::CANONICAL_ALIASES[ $scope ];
				continue;
			}

			$fragment = \substr( $scope, \strlen( self::CANONICAL_SCOPE_PREFIX ) );
			if ( 0 === \strpos( $scope, self::CANONICAL_SCOPE_PREFIX ) && isset( self::CANONICAL_SCOPES[ $fragment ] ) ) {
				$normalized[] = self::CANONICAL_SCOPES[ $fragment ];
				continue;
			}

			$normalized[] = $scope;
		}

		return $normalized;
	}

	/**
	 * Return the scope identifiers advertised in OAuth authorization-server metadata.
	 *
	 * Includes the plugin's internal scopes plus the Basic Profile identifiers, in both the
	 * pre-2026-08-04 alias form and the URI form that replaced it, so spec-aware clients can
	 * discover them.
	 *
	 * @since 9.0.0
	 * @since unreleased Also advertises the URI-form identifiers.
	 *
	 * @return array Scope identifiers.
	 */
	public static function supported() {
		$identifiers = \array_keys( self::CANONICAL_ALIASES );

		foreach ( \array_keys( self::CANONICAL_SCOPES ) as $fragment ) {
			$identifiers[] = self::CANONICAL_SCOPE_PREFIX . $fragment;
		}

		return \array_merge( self::ALL, $identifiers );
	}

	/**
	 * Parse a space-separated scope string to array.
	 *
	 * @param string $scope_string Space-separated scopes.
	 * @return array Scope array.
	 */
	public static function parse( $scope_string ) {
		if ( empty( $scope_string ) || ! \is_string( $scope_string ) ) {
			return array();
		}

		$scopes = \preg_split( '/\s+/', \trim( $scope_string ) );

		return \array_filter( \array_map( 'trim', $scopes ) );
	}

	/**
	 * Convert scopes array to space-separated string.
	 *
	 * @param array $scopes The scopes array.
	 * @return string Space-separated scope string.
	 */
	public static function to_string( $scopes ) {
		if ( ! \is_array( $scopes ) ) {
			return '';
		}

		return \implode( ' ', $scopes );
	}

	/**
	 * The scope required to post an activity to the outbox.
	 *
	 * `write` covers every one of these through {@see self::IMPLIES}; this returns the narrowest
	 * scope that is enough, so a client granted only `follow` or only `profile` can still do the
	 * thing it was granted, and nothing else.
	 *
	 * @since unreleased
	 *
	 * @param array $activity The activity being posted.
	 * @return string The required scope.
	 */
	public static function for_activity( $activity ) {
		$type = isset( $activity['type'] ) ? $activity['type'] : '';

		if ( 'Follow' === $type ) {
			return self::FOLLOW;
		}

		$object      = isset( $activity['object'] ) ? $activity['object'] : null;
		$object_type = \is_array( $object ) && isset( $object['type'] ) ? $object['type'] : '';

		// Undoing a follow is unfollowing; accepting or rejecting one is answering a follow request.
		if ( \in_array( $type, array( 'Undo', 'Accept', 'Reject' ), true ) && 'Follow' === $object_type ) {
			return self::FOLLOW;
		}

		// An Update whose object is an actor is a profile edit, not a content edit.
		if ( 'Update' === $type && \in_array( $object_type, array( 'Person', 'Service', 'Organization', 'Application', 'Group' ), true ) ) {
			return self::PROFILE;
		}

		return self::WRITE;
	}

	/**
	 * Check if a scope is valid.
	 *
	 * @param string $scope The scope to check.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( $scope ) {
		return \in_array( $scope, self::ALL, true );
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
		if ( ! \is_array( $scopes ) ) {
			$scopes = self::parse( $scopes );
		}

		if ( \in_array( $scope, $scopes, true ) ) {
			return true;
		}

		// A broader scope the client does hold may already cover the one being asked for.
		foreach ( self::IMPLIES as $granting => $implied ) {
			if ( \in_array( $granting, $scopes, true ) && \in_array( $scope, $implied, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize callback for scope storage.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized scopes array.
	 */
	public static function sanitize( $value ) {
		if ( \is_string( $value ) ) {
			$value = self::parse( $value );
		}

		if ( ! \is_array( $value ) ) {
			return array();
		}

		return self::validate( $value );
	}
}
