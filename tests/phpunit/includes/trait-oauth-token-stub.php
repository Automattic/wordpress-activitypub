<?php
/**
 * OAuth token stub trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;

/**
 * Stands a test in for an OAuth-authenticated request.
 *
 * `Server::authenticate_oauth()` normally sets the current token during REST authentication.
 * Tests that need a request to look OAuth-authenticated, with particular scopes, set it directly
 * instead of running a full authorization flow. A trait rather than a base-class method, because
 * the test classes that need it do not share an ancestor.
 */
trait OAuth_Token_Stub {
	/**
	 * Build a stub token carrying the given scopes.
	 *
	 * @param array $scopes  Scopes the token carries.
	 * @param int   $user_id Optional. The user the token belongs to. Default 0.
	 * @return object A stub exposing the token methods the OAuth Server calls.
	 */
	protected function mock_oauth_token( $scopes, $user_id = 0 ) {
		return new class( $scopes, $user_id ) {
			/**
			 * Scopes the token carries.
			 *
			 * @var array
			 */
			private $scopes;

			/**
			 * The user the token belongs to.
			 *
			 * @var int
			 */
			private $user_id;

			/**
			 * Constructor.
			 *
			 * @param array $scopes  Scopes.
			 * @param int   $user_id User ID.
			 */
			public function __construct( $scopes, $user_id ) {
				$this->scopes  = $scopes;
				$this->user_id = $user_id;
			}

			/**
			 * Get the user the token belongs to.
			 *
			 * @return int
			 */
			public function get_user_id() {
				return $this->user_id;
			}

			/**
			 * Whether the token carries a scope.
			 *
			 * @param string $scope Scope to check.
			 * @return bool
			 */
			public function has_scope( $scope ) {
				return Scope::contains( $this->scopes, $scope );
			}
		};
	}

	/**
	 * Set the OAuth Server's current token, or clear it with null.
	 *
	 * @param object|null $token The token, or null for no OAuth session.
	 */
	protected function set_oauth_current_token( $token ) {
		$property = ( new \ReflectionClass( OAuth_Server::class ) )->getProperty( 'current_token' );
		$property->setAccessible( true );
		$property->setValue( null, $token );
	}
}
