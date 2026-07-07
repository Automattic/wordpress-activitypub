<?php
/**
 * Test file for the FASP settings admin actions.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions -- base64 is the FASP wire format, not obfuscation.

namespace Activitypub\Tests\WP_Admin;

use Activitypub\Fasp\Registrations;
use Activitypub\Tests\Fasp_TestCase;
use Activitypub\WP_Admin\Fasp_Settings;

/**
 * Test class for the FASP settings admin actions.
 *
 * @group fasp
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Fasp_Settings
 */
class Test_Fasp_Settings extends Fasp_TestCase {

	/**
	 * An administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * A subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Create the fixture users.
	 *
	 * @param \WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Populate $_POST for an admin action against a registration.
	 *
	 * @param array  $registration The registration record.
	 * @param string $nonce_prefix The nonce action prefix.
	 * @param array  $extra        Additional $_POST fields.
	 */
	private function prepare_action_request( $registration, $nonce_prefix = 'fasp_registration_', $extra = array() ) {
		$_POST = \array_merge(
			array(
				'fasp_id'  => $registration['fasp_id'],
				'_wpnonce' => \wp_create_nonce( $nonce_prefix . $registration['fasp_id'] ),
			),
			$extra
		);
	}

	/**
	 * Data provider covering every admin_post action and its nonce prefix.
	 *
	 * @return array[]
	 */
	public function admin_action_provider() {
		return array(
			'approve' => array( 'approve_registration', 'fasp_registration_' ),
			'reject'  => array( 'reject_registration', 'fasp_registration_' ),
			'delete'  => array( 'delete_registration', 'fasp_registration_' ),
			'toggle'  => array( 'toggle_capability', 'fasp_capability_' ),
			'refresh' => array( 'refresh_provider_info', 'fasp_registration_' ),
		);
	}

	/**
	 * Every admin action dies for users without manage_options.
	 *
	 * @covers ::approve_registration
	 * @covers ::reject_registration
	 * @covers ::delete_registration
	 * @covers ::toggle_capability
	 * @covers ::refresh_provider_info
	 *
	 * @dataProvider admin_action_provider
	 *
	 * @param string $action       The admin action method name.
	 * @param string $nonce_prefix The nonce action prefix.
	 */
	public function test_actions_require_manage_options( $action, $nonce_prefix ) {
		\wp_set_current_user( self::$subscriber_id );

		$registration = $this->create_fasp_registration( 'pending' );
		$this->prepare_action_request( $registration, $nonce_prefix );

		$this->expectException( \WPDieException::class );

		\call_user_func( array( Fasp_Settings::class, $action ) );
	}

	/**
	 * Every admin action dies on an invalid nonce.
	 *
	 * @covers ::approve_registration
	 * @covers ::reject_registration
	 * @covers ::delete_registration
	 * @covers ::toggle_capability
	 * @covers ::refresh_provider_info
	 *
	 * @dataProvider admin_action_provider
	 *
	 * @param string $action The admin action method name.
	 */
	public function test_actions_require_valid_nonce( $action ) {
		\wp_set_current_user( self::$admin_id );

		$registration = $this->create_fasp_registration( 'pending' );
		$this->prepare_action_request( $registration );
		$_POST['_wpnonce'] = 'invalid-nonce';

		$this->expectException( \WPDieException::class );

		\call_user_func( array( Fasp_Settings::class, $action ) );
	}

	/**
	 * A nonce for one registration must not authorize actions on another.
	 *
	 * @covers ::delete_registration
	 */
	public function test_nonce_is_bound_to_registration() {
		\wp_set_current_user( self::$admin_id );

		$registration = $this->create_fasp_registration( 'approved' );
		$this->prepare_action_request( $registration );
		$_POST['_wpnonce'] = \wp_create_nonce( 'fasp_registration_other-fasp-id' );

		$this->expectException( \WPDieException::class );

		Fasp_Settings::delete_registration();
	}

	/**
	 * Approving persists the provider info into the registration record, so the
	 * settings page renders capabilities without a blocking outbound request.
	 *
	 * @covers ::approve_registration
	 */
	public function test_approve_persists_provider_info() {
		\wp_set_current_user( self::$admin_id );

		$registration  = $this->create_fasp_registration( 'pending' );
		$provider_info = array(
			'name'         => 'Test FASP',
			'capabilities' => array(
				array(
					'id'      => 'trends',
					'version' => '1.0',
				),
			),
		);

		$mock = function ( $response, $args, $url ) use ( $provider_info ) {
			if ( 'https://fasp.example.com/provider_info' !== $url ) {
				return $response;
			}

			return $this->build_signed_fasp_response( 200, \wp_json_encode( $provider_info ) );
		};
		\add_filter( 'pre_http_request', $mock, 10, 3 );

		$this->prepare_action_request( $registration );
		$location = $this->invoke_capturing_redirect( array( Fasp_Settings::class, 'approve_registration' ) );

		\remove_filter( 'pre_http_request', $mock );

		$this->assertStringContainsString( 'approved=1', $location );

		$stored = Registrations::get( $registration['fasp_id'] );
		$this->assertSame( 'approved', $stored['status'] );
		$this->assertSame( $provider_info, $stored['provider_info'], 'Approving should persist the provider info into the record.' );
	}

	/**
	 * Approval still succeeds when the provider is unreachable; the record just
	 * carries no provider info, and the page offers a load button.
	 *
	 * @covers ::approve_registration
	 */
	public function test_approve_succeeds_when_provider_unreachable() {
		\wp_set_current_user( self::$admin_id );

		$registration = $this->create_fasp_registration( 'pending' );

		$mock = function ( $response, $args, $url ) {
			if ( 'https://fasp.example.com/provider_info' !== $url ) {
				return $response;
			}

			return new \WP_Error( 'http_request_failed', 'unreachable' );
		};
		\add_filter( 'pre_http_request', $mock, 10, 3 );

		$this->prepare_action_request( $registration );
		$location = $this->invoke_capturing_redirect( array( Fasp_Settings::class, 'approve_registration' ) );

		\remove_filter( 'pre_http_request', $mock );

		$this->assertStringContainsString( 'approved=1', $location );

		$stored = Registrations::get( $registration['fasp_id'] );
		$this->assertSame( 'approved', $stored['status'] );
		$this->assertArrayNotHasKey( 'provider_info', $stored, 'An unreachable provider leaves the record without provider info.' );
	}

	/**
	 * Refreshing replaces the stored provider info with a fresh copy.
	 *
	 * @covers ::refresh_provider_info
	 */
	public function test_refresh_replaces_stored_provider_info() {
		\wp_set_current_user( self::$admin_id );

		$registration = $this->create_fasp_registration( 'approved' );
		Registrations::set_provider_info( $registration['fasp_id'], array( 'capabilities' => array( array( 'id' => 'stale' ) ) ) );

		$fresh = array(
			'name'         => 'Test FASP',
			'capabilities' => array(
				array(
					'id'      => 'trends',
					'version' => '1.0',
				),
			),
		);

		$mock = function ( $response, $args, $url ) use ( $fresh ) {
			if ( 'https://fasp.example.com/provider_info' !== $url ) {
				return $response;
			}

			return $this->build_signed_fasp_response( 200, \wp_json_encode( $fresh ) );
		};
		\add_filter( 'pre_http_request', $mock, 10, 3 );

		$this->prepare_action_request( $registration );
		$location = $this->invoke_capturing_redirect( array( Fasp_Settings::class, 'refresh_provider_info' ) );

		\remove_filter( 'pre_http_request', $mock );

		$this->assertStringContainsString( 'highlight=' . $registration['fasp_id'], $location );
		$this->assertSame( $fresh, Registrations::get( $registration['fasp_id'] )['provider_info'] );
	}

	/**
	 * A failed refresh keeps the last-known-good copy and still highlights the card.
	 *
	 * @covers ::refresh_provider_info
	 */
	public function test_refresh_failure_keeps_last_known_info_and_highlights() {
		\wp_set_current_user( self::$admin_id );

		$registration = $this->create_fasp_registration( 'approved' );
		$last_known   = array( 'capabilities' => array( array( 'id' => 'trends', 'version' => '1.0' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		Registrations::set_provider_info( $registration['fasp_id'], $last_known );

		$mock = function ( $response, $args, $url ) {
			if ( 'https://fasp.example.com/provider_info' !== $url ) {
				return $response;
			}

			return new \WP_Error( 'http_request_failed', 'unreachable' );
		};
		\add_filter( 'pre_http_request', $mock, 10, 3 );

		$this->prepare_action_request( $registration );
		$location = $this->invoke_capturing_redirect( array( Fasp_Settings::class, 'refresh_provider_info' ) );

		\remove_filter( 'pre_http_request', $mock );

		$this->assertStringContainsString( 'error=1', $location, 'A failed refresh surfaces an error.' );
		$this->assertStringContainsString( 'highlight=' . $registration['fasp_id'], $location, 'A failed refresh still highlights the affected card.' );
		$this->assertSame( $last_known, Registrations::get( $registration['fasp_id'] )['provider_info'], 'A failed refresh keeps the last-known-good copy.' );
	}

	/**
	 * Local capability state only changes when the provider acknowledged the call.
	 *
	 * @covers ::toggle_capability
	 */
	public function test_toggle_capability_requires_provider_acknowledgement() {
		\wp_set_current_user( self::$admin_id );

		$registration = $this->create_fasp_registration( 'approved' );

		// The provider rejects the activation.
		$mock_fail = function ( $response, $args, $url ) {
			if ( 'https://fasp.example.com/capabilities/trends/1.0/activation' !== $url ) {
				return $response;
			}

			return $this->build_signed_fasp_response( 500, '' );
		};
		\add_filter( 'pre_http_request', $mock_fail, 10, 3 );

		$this->prepare_action_request(
			$registration,
			'fasp_capability_',
			array(
				'identifier' => 'trends',
				'version'    => '1.0',
				'enable'     => '1',
			)
		);
		$location = $this->invoke_capturing_redirect( array( Fasp_Settings::class, 'toggle_capability' ) );

		\remove_filter( 'pre_http_request', $mock_fail );

		$this->assertStringContainsString( 'error=1', $location );
		$this->assertFalse(
			Registrations::is_capability_enabled( $registration['fasp_id'], 'trends', '1.0' ),
			'Local state must not change when the provider did not acknowledge the call.'
		);

		// The provider acknowledges the activation.
		$mock_ok = function ( $response, $args, $url ) {
			if ( 'https://fasp.example.com/capabilities/trends/1.0/activation' !== $url ) {
				return $response;
			}

			return $this->build_signed_fasp_response( 204, '' );
		};
		\add_filter( 'pre_http_request', $mock_ok, 10, 3 );

		$this->prepare_action_request(
			$registration,
			'fasp_capability_',
			array(
				'identifier' => 'trends',
				'version'    => '1.0',
				'enable'     => '1',
			)
		);
		$location = $this->invoke_capturing_redirect( array( Fasp_Settings::class, 'toggle_capability' ) );

		\remove_filter( 'pre_http_request', $mock_ok );

		$this->assertStringContainsString( 'capability_updated=1', $location );
		$this->assertTrue( Registrations::is_capability_enabled( $registration['fasp_id'], 'trends', '1.0' ) );
	}
}
