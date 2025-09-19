<?php
/**
 * Test file for Audience Trait.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Rest\Audience;

/**
 * Test class for Audience Trait.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Audience
 */
class Test_Trait_Audience extends \WP_UnitTestCase {
	/**
	 * Test class that uses the Audience trait.
	 *
	 * @var object
	 */
	private $audience_test_class;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test actor URL.
	 *
	 * @var string
	 */
	protected static $actor_url;

	/**
	 * Create fake data before tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$actor_url = get_rest_url( null, sprintf( '/activitypub/1.0/actors/%d', self::$user_id ) );

		// Grant the activitypub capability to the user.
		$user = new \WP_User( self::$user_id );
		$user->add_cap( 'activitypub' );
	}

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Create a test class that uses the Audience trait.
		$this->audience_test_class = new class() {
			use Audience;
		};
	}

	/**
	 * Data provider for visibility determination tests.
	 *
	 * @return array
	 */
	public function visibility_data_provider() {
		return array(
			// Public visibility - 'to' contains public identifier.
			array(
				'activity'    => array(
					'type' => 'Create',
					'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					'cc'   => array(),
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				'description' => 'Public visibility via to field',
			),
			// Quiet public visibility - 'cc' contains public identifier.
			array(
				'activity'    => array(
					'type' => 'Create',
					'to'   => array( 'https://example.com/user/123' ),
					'cc'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC,
				'description' => 'Quiet public visibility via cc field',
			),
			// Private visibility - no public identifiers.
			array(
				'activity'    => array(
					'type' => 'Create',
					'to'   => array( 'https://example.com/user/123' ),
					'cc'   => array( 'https://example.com/user/456' ),
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
				'description' => 'Private visibility',
			),
			// Special activity types always private - Accept.
			array(
				'activity'    => array(
					'type' => 'Accept',
					'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					'cc'   => array(),
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
				'description' => 'Accept activity always private',
			),
			// Special activity types always private - Delete.
			array(
				'activity'    => array(
					'type' => 'Delete',
					'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					'cc'   => array(),
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
				'description' => 'Delete activity always private',
			),
			// Special activity types always private - Follow.
			array(
				'activity'    => array(
					'type' => 'Follow',
					'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					'cc'   => array(),
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
				'description' => 'Follow activity always private',
			),
			// Alternative public identifier - as:Public.
			array(
				'activity'    => array(
					'type' => 'Create',
					'to'   => array( 'as:Public' ),
					'cc'   => array(),
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				'description' => 'Public visibility via as:Public identifier',
			),
			// Empty activity.
			array(
				'activity'    => array(
					'type' => 'Create',
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
				'description' => 'Empty activity defaults to private',
			),
		);
	}

	/**
	 * Test determine_visibility method.
	 *
	 * @dataProvider visibility_data_provider
	 * @covers ::determine_visibility
	 *
	 * @param array  $activity    The activity data.
	 * @param string $expected    Expected visibility level.
	 * @param string $description Test description.
	 */
	public function test_determine_visibility( $activity, $expected, $description ) {
		$result = $this->audience_test_class->determine_visibility( $activity );
		$this->assertSame( $expected, $result, $description );
	}

	/**
	 * Test determine_local_recipients method with no recipients.
	 *
	 * @covers ::determine_local_recipients
	 */
	public function test_determine_local_recipients_no_recipients() {
		$activity = array(
			'type' => 'Create',
		);

		$result = $this->audience_test_class->determine_local_recipients( $activity );
		$this->assertEmpty( $result, 'Should return empty array when no recipients' );
	}

	/**
	 * Test determine_local_recipients with external recipients only.
	 *
	 * @covers ::determine_local_recipients
	 */
	public function test_determine_local_recipients_external_only() {
		$activity = array(
			'type' => 'Create',
			'to'   => array( 'https://external.example.com/user/123' ),
			'cc'   => array( 'https://another.example.com/user/456' ),
		);

		$result = $this->audience_test_class->determine_local_recipients( $activity );
		$this->assertEmpty( $result, 'Should return empty array for external recipients only' );
	}

	/**
	 * Test determine_local_recipients with actual local actor.
	 *
	 * @covers ::determine_local_recipients
	 */
	public function test_determine_local_recipients_with_local_actor() {
		// Get the actual actor ID for the user.
		$actor    = Actors::get_by_id( self::$user_id );
		$actor_id = $actor->get_id();

		$activity = array(
			'type' => 'Create',
			'to'   => array( $actor_id ),
			'cc'   => array( 'https://external.example.com/user/123' ),
		);

		$result = $this->audience_test_class->determine_local_recipients( $activity );
		$this->assertContains( self::$user_id, $result, 'Should contain local user ID' );
		$this->assertCount( 1, $result, 'Should contain exactly one recipient' );
	}

	/**
	 * Test determine_visibility with string recipients instead of arrays.
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_with_string_recipients() {
		$activity = array(
			'type' => 'Create',
			'to'   => 'https://www.w3.org/ns/activitystreams#Public',
			'cc'   => 'https://example.com/user/123',
		);

		$result = $this->audience_test_class->determine_visibility( $activity );
		$this->assertSame( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, $result, 'Should handle string recipients' );
	}

	/**
	 * Test determine_local_recipients handles malformed actor URLs.
	 *
	 * @covers ::determine_local_recipients
	 */
	public function test_determine_local_recipients_with_malformed_urls() {
		$activity = array(
			'type' => 'Create',
			'to'   => array(
				'not-a-valid-url',
				get_home_url() . '/invalid-actor-path',
			),
			'cc'   => array(),
		);

		$result = $this->audience_test_class->determine_local_recipients( $activity );
		$this->assertEmpty( $result, 'Should handle malformed URLs gracefully' );
	}

	/**
	 * Test determine_visibility with minimal activity data.
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_with_minimal_activity() {
		$activity = array(
			'type' => 'Create',
			'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'   => array(),
		);

		$result = $this->audience_test_class->determine_visibility( $activity );
		$this->assertSame( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, $result, 'Should work with minimal activity data' );
	}
}
