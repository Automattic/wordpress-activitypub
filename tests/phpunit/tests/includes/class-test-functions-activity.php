<?php
/**
 * Test file for Activity Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use function Activitypub\extract_recipients_from_activity;
use function Activitypub\extract_recipients_from_activity_property;
use function Activitypub\get_activity_visibility;

/**
 * Test class for Activity Functions.
 */
class Test_Functions_Activity extends \WP_UnitTestCase {

	/**
	 * Test object_to_uri.
	 *
	 * @dataProvider object_to_uri_provider
	 * @covers \Activitypub\object_to_uri
	 *
	 * @param mixed $input  The input to test.
	 * @param mixed $output The expected output.
	 */
	public function test_object_to_uri( $input, $output ) {
		$this->assertEquals( $output, \Activitypub\object_to_uri( $input ) );
	}

	/**
	 * Data provider for test_object_to_uri.
	 *
	 * @return array[]
	 */
	public function object_to_uri_provider() {
		return array(
			array( null, null ),
			array( 'https://example.com', 'https://example.com' ),
			array( array( 'https://example.com' ), 'https://example.com' ),
			array(
				array(
					'https://example.com',
					'https://example.org',
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Link',
					'href' => 'https://example.com',
				),
				'https://example.com',
			),
			array(
				array(
					array(
						'type' => 'Link',
						'href' => 'https://example.com',
					),
					array(
						'type' => 'Link',
						'href' => 'https://example.org',
					),
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Actor',
					'id'   => 'https://example.com',
				),
				'https://example.com',
			),
			array(
				array(
					array(
						'type' => 'Actor',
						'id'   => 'https://example.com',
					),
					array(
						'type' => 'Actor',
						'id'   => 'https://example.org',
					),
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Activity',
					'id'   => 'https://example.com',
				),
				'https://example.com',
			),
		);
	}

	/**
	 * Test is_activity with array input.
	 *
	 * @covers \Activitypub\is_activity
	 *
	 * @dataProvider is_activity_data
	 *
	 * @param mixed $activity The activity object.
	 * @param bool  $expected The expected result.
	 */
	public function test_is_activity( $activity, $expected ) {
		$this->assertEquals( $expected, \Activitypub\is_activity( $activity ) );
	}

	/**
	 * Data provider for test_is_activity.
	 *
	 * @return array[]
	 */
	public function is_activity_data() {
		// Test Activity object.
		$create = new \Activitypub\Activity\Activity();
		$create->set_type( 'Create' );

		// Test Base_Object.
		$note = new \Activitypub\Activity\Base_Object();
		$note->set_type( 'Note' );

		return array(
			array( array( 'type' => 'Create' ), true ),
			array( array( 'type' => 'Update' ), true ),
			array( array( 'type' => 'Delete' ), true ),
			array( array( 'type' => 'Follow' ), true ),
			array( array( 'type' => 'Accept' ), true ),
			array( array( 'type' => 'Reject' ), true ),
			array( array( 'type' => 'Add' ), true ),
			array( array( 'type' => 'Remove' ), true ),
			array( array( 'type' => 'Like' ), true ),
			array( array( 'type' => 'Announce' ), true ),
			array( array( 'type' => 'Undo' ), true ),
			array( array( 'type' => 'Note' ), false ),
			array( array( 'type' => 'Article' ), false ),
			array( array( 'type' => 'Person' ), false ),
			array( array( 'type' => 'Image' ), false ),
			array( array( 'type' => 'Video' ), false ),
			array( array( 'type' => 'Audio' ), false ),
			array( array( 'type' => '' ), false ),
			array( array( 'type' => null ), false ),
			array( array(), false ),
			array( $create, true ),
			array( $note, false ),
			array( 'string', false ),
			array( 123, false ),
			array( true, false ),
			array( false, false ),
			array( null, false ),
			array( new \stdClass(), false ),
		);
	}

	/**
	 * Test is_activity_object with array input.
	 *
	 * @covers \Activitypub\is_activity_object
	 *
	 * @dataProvider is_activity_object_data
	 *
	 * @param mixed $activity The activity object.
	 * @param bool  $expected The expected result.
	 */
	public function test_is_activity_object( $activity, $expected ) {
		$this->assertEquals( $expected, \Activitypub\is_activity_object( $activity ) );
	}

	/**
	 * Data provider for test_is_activity_object.
	 *
	 * @return array[][]
	 */
	public function is_activity_object_data() {
		// Test Activity object.
		$create = new \Activitypub\Activity\Activity();
		$create->set_type( 'Create' );

		// Test Base_Object.
		$note = new \Activitypub\Activity\Base_Object();
		$note->set_type( 'Note' );

		return array(
			array( array( 'type' => 'Article' ), true ),
			array( array( 'type' => 'Image' ), true ),
			array( array( 'type' => 'Video' ), true ),
			array( array( 'type' => 'Audio' ), true ),
			array( array( 'type' => '' ), false ),
			array( array( 'type' => null ), false ),
			array( array(), false ),
			array( $create, false ),
			array( $note, true ),
			array( 'string', false ),
			array( 123, false ),
			array( true, false ),
			array( false, false ),
			array( null, false ),
			array( new \stdClass(), false ),
		);
	}

	/**
	 * Test is_activity with invalid input.
	 *
	 * @covers \Activitypub\is_activity
	 */
	public function test_is_activity_with_invalid_input() {
		$invalid_inputs = array(
			'string',
			123,
			true,
			false,
			null,
			new \stdClass(),
		);

		foreach ( $invalid_inputs as $input ) {
			$this->assertFalse(
				\Activitypub\is_activity( $input ),
				sprintf( 'Input of type %s should be invalid', gettype( $input ) )
			);
		}
	}

	/**
	 * Test whether an activity is public.
	 *
	 * @dataProvider public_activity_provider
	 *
	 * @param array $data  The data.
	 * @param bool  $check The check.
	 */
	public function test_is_activity_public( $data, $check ) {
		$this->assertEquals( $check, \Activitypub\is_activity_public( $data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function public_activity_provider() {
		return array(
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'to'     => 'https://www.w3.org/ns/activitystreams#Public',
					'object' => array(),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'to'     => array(
						'https://www.w3.org/ns/activitystreams#Public',
					),
					'object' => array(),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(),
				),
				false,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'to' => 'https://www.w3.org/ns/activitystreams#Public',
					),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'to' => array(
							'https://www.w3.org/ns/activitystreams#Public',
						),
					),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'cc' => array(
							'https://www.w3.org/ns/activitystreams#Public',
						),
					),
				),
				false,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://www.w3.org/ns/activitystreams#Public',
					),
					'object' => 'https://example.com',
				),
				true,
			),
			array(
				array(
					'object' => array(
						'to' => 'https://www.w3.org/ns/activitystreams#Public',
					),
				),
				true,
			),
			array(
				array(
					'object' => array(
						'cc' => 'https://www.w3.org/ns/activitystreams#Public',
					),
				),
				true,
			),
			array(
				array(
					'object' => array(
						'monkey' => 'https://www.w3.org/ns/activitystreams#Public',
					),
				),
				true,
			),
			array(
				array(
					'to'     => 'http://www.w3.org/ns/activitystreams#Public',
					'cc'     => 'http://www.w3.org/ns/activitystreams#Public',
					'object' => '',
				),
				false,
			),
			array(
				array(
					'to'     => array( 'http://www.w3.org/ns/activitystreams#Public' ),
					'cc'     => array( 'http://www.w3.org/ns/activitystreams#Public' ),
					'object' => '',
				),
				false,
			),
			array(
				array(
					'to'     => 'as:Public',
					'cc'     => '',
					'object' => '',
				),
				true,
			),
			array(
				array(
					'to'     => '',
					'cc'     => 'as:Public',
					'object' => '',
				),
				true,
			),
			array(
				array(
					'to'     => '',
					'cc'     => 'Public',
					'object' => '',
				),
				true,
			),
		);
	}

	/**
	 * Data provider for testing extract_recipients_from_activity_property.
	 *
	 * @return array Test data sets.
	 */
	public function data_provider_extract_recipients() {
		return array(
			'simple_string_recipient'                 => array(
				'data'      => array(
					'to' => 'https://example.com/users/alice',
				),
				'attribute' => 'to',
				'expected'  => array( 'https://example.com/users/alice' ),
			),
			'array_of_recipients'                     => array(
				'data'      => array(
					'to' => array(
						'https://example.com/users/alice',
						'https://example.com/users/bob',
					),
				),
				'attribute' => 'to',
				'expected'  => array(
					'https://example.com/users/alice',
					'https://example.com/users/bob',
				),
			),
			'object_recipients_with_id'               => array(
				'data'      => array(
					'cc' => array(
						array( 'id' => 'https://example.com/users/charlie' ),
						array( 'id' => 'https://example.com/users/diana' ),
					),
				),
				'attribute' => 'cc',
				'expected'  => array(
					'https://example.com/users/charlie',
					'https://example.com/users/diana',
				),
			),
			'mixed_recipients'                        => array(
				'data'      => array(
					'bcc' => array(
						'https://example.com/users/eve',
						array( 'id' => 'https://example.com/users/frank' ),
					),
				),
				'attribute' => 'bcc',
				'expected'  => array(
					'https://example.com/users/eve',
					'https://example.com/users/frank',
				),
			),
			'recipients_in_object'                    => array(
				'data'      => array(
					'object' => array(
						'to' => 'https://example.com/users/grace',
					),
				),
				'attribute' => 'to',
				'expected'  => array( 'https://example.com/users/grace' ),
			),
			'recipients_in_both_main_and_object'      => array(
				'data'      => array(
					'to'     => 'https://example.com/users/henry',
					'object' => array(
						'to' => 'https://example.com/users/iris',
					),
				),
				'attribute' => 'to',
				'expected'  => array(
					'https://example.com/users/henry',
				),
			),
			'duplicate_recipients'                    => array(
				'data'      => array(
					'to' => array(
						'https://example.com/users/jack',
						'https://example.com/users/jack', // Duplicate.
					),
				),
				'attribute' => 'to',
				'expected'  => array( 'https://example.com/users/jack' ), // Should be unique.
			),
			'no_recipients'                           => array(
				'data'      => array(
					'cc' => array(),
				),
				'attribute' => 'to', // Different attribute.
				'expected'  => array(),
			),
			'empty_data'                              => array(
				'data'      => array(),
				'attribute' => 'to',
				'expected'  => array(),
			),
			'object_with_id'                          => array(
				'data'      => array(
					'to' => array(
						array(
							'id'   => 'https://example.com/users/kate',
							'type' => 'Person',
							'name' => 'Kate',
						),
					),
				),
				'attribute' => 'to',
				'expected'  => array(
					'https://example.com/users/kate',
				), // Should be ignored.
			),
			'public_recipients'                       => array(
				'data'      => array(
					'to' => array(
						'https://www.w3.org/ns/activitystreams#Public',
						'https://example.com/users/liam',
					),
				),
				'attribute' => 'to',
				'expected'  => array(
					'https://www.w3.org/ns/activitystreams#Public',
					'https://example.com/users/liam',
				),
			),
			'audience_attribute'                      => array(
				'data'      => array(
					'audience' => 'https://example.com/groups/followers',
				),
				'attribute' => 'audience',
				'expected'  => array( 'https://example.com/groups/followers' ),
			),
			'recipients_in_instrument_to'             => array(
				'data'      => array(
					'type'       => 'QuoteRequest',
					'actor'      => 'https://example.com/users/alice',
					'object'     => 'https://example.org/posts/123',
					'instrument' => array(
						'type' => 'Note',
						'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					),
				),
				'attribute' => 'to',
				'expected'  => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			),
			'recipients_in_instrument_cc'             => array(
				'data'      => array(
					'type'       => 'QuoteRequest',
					'actor'      => 'https://example.com/users/alice',
					'object'     => 'https://example.org/posts/123',
					'instrument' => array(
						'type' => 'Note',
						'cc'   => array( 'https://example.com/users/alice/followers' ),
					),
				),
				'attribute' => 'cc',
				'expected'  => array( 'https://example.com/users/alice/followers' ),
			),
			'activity_level_takes_precedence_over_instrument' => array(
				'data'      => array(
					'type'       => 'QuoteRequest',
					'actor'      => 'https://example.com/users/alice',
					'object'     => 'https://example.org/posts/123',
					'to'         => array( 'https://example.org/users/bob' ),
					'instrument' => array(
						'type' => 'Note',
						'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					),
				),
				'attribute' => 'to',
				'expected'  => array( 'https://example.org/users/bob' ),
			),
			'object_takes_precedence_over_instrument' => array(
				'data'      => array(
					'type'       => 'QuoteRequest',
					'actor'      => 'https://example.com/users/alice',
					'object'     => array(
						'id' => 'https://example.org/posts/123',
						'to' => array( 'https://example.org/users/charlie' ),
					),
					'instrument' => array(
						'type' => 'Note',
						'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					),
				),
				'attribute' => 'to',
				'expected'  => array( 'https://example.org/users/charlie' ),
			),
		);
	}

	/**
	 * Test extract_recipients_from_activity_property function.
	 *
	 * @dataProvider data_provider_extract_recipients
	 *
	 * @param array  $data      The activity data.
	 * @param string $attribute The attribute to extract.
	 * @param array  $expected  The expected recipients.
	 */
	public function test_extract_recipients_from_activity_property( $data, $attribute, $expected ) {
		$actual = extract_recipients_from_activity_property( $attribute, $data );

		// Sort both arrays to ensure order doesn't matter in comparison.
		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test extract_recipients_from_activity_attribute function.
	 *
	 * @dataProvider data_provider_extract_recipients
	 *
	 * @param array  $data      The activity data.
	 * @param string $attribute The attribute to extract.
	 * @param array  $expected  The expected recipients.
	 */
	public function test_extract_recipients_from_activity( $data, $attribute, $expected ) {
		$actual = extract_recipients_from_activity( $data );

		// Sort both arrays to ensure order doesn't matter in comparison.
		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test that the function returns unique recipients.
	 */
	public function test_unique_recipients() {
		$data   = array(
			'to'     => array(
				'https://example.com/users/alice',
				'https://example.com/users/alice', // Duplicate.
			),
			'object' => array(
				'to' => 'https://example.com/users/alice', // Another duplicate.
			),
		);
		$actual = extract_recipients_from_activity_property( 'to', $data );

		$this->assertSame( array( 'https://example.com/users/alice' ), $actual );
		$this->assertCount( 1, $actual, 'Should return unique recipients only.' );
	}

	/**
	 * Test that the function returns unique recipients from extract_recipients_from_activity.
	 */
	public function test_unique_recipients_from_activity() {
		$data   = array(
			'to'     => array(
				'https://example.com/users/alice',
				'https://example.com/users/alice', // Duplicate.
			),
			'object' => array(
				'to' => 'https://example.com/users/alice', // Another duplicate.
			),
		);
		$actual = extract_recipients_from_activity( $data );
		$this->assertSame( array( 'https://example.com/users/alice' ), $actual );
		$this->assertCount( 1, $actual, 'Should return unique recipients only.' );
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
			// Empty activity - no recipients means public.
			array(
				'activity'    => array(
					'type' => 'Create',
				),
				'expected'    => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				'description' => 'Empty activity (no recipients) is treated as public',
			),
		);
	}

	/**
	 * Test get_activity_visibility function.
	 *
	 * @dataProvider visibility_data_provider
	 *
	 * @param array  $activity    The activity data.
	 * @param string $expected    Expected visibility level.
	 * @param string $description Test description.
	 */
	public function test_get_activity_visibility( $activity, $expected, $description ) {
		$result = get_activity_visibility( $activity );
		$this->assertSame( $expected, $result, $description );
	}

	/**
	 * Test get_activity_visibility with minimal activity data.
	 */
	public function test_get_activity_visibility_with_minimal_activity() {
		$activity = array(
			'type' => 'Create',
			'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'   => array(),
		);

		$result = get_activity_visibility( $activity );
		$this->assertSame( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, $result, 'Should work with minimal activity data' );
	}

	/**
	 * Test is_activity_reply function with inReplyTo.
	 *
	 * @covers \Activitypub\is_activity_reply
	 */
	public function test_is_activity_reply_with_in_reply_to() {
		$activity = array(
			'type'   => 'Create',
			'object' => array(
				'type'      => 'Note',
				'content'   => 'This is a reply',
				'inReplyTo' => 'https://example.com/post/123',
			),
		);

		$this->assertTrue( \Activitypub\is_activity_reply( $activity ) );
	}

	/**
	 * Test is_activity_reply returns false for non-reply.
	 *
	 * @covers \Activitypub\is_activity_reply
	 */
	public function test_is_activity_reply_returns_false_for_non_reply() {
		$activity = array(
			'type'   => 'Create',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Just a regular post',
			),
		);

		$this->assertFalse( \Activitypub\is_activity_reply( $activity ) );
	}

	/**
	 * Test is_quote_activity function with quote property.
	 *
	 * @covers \Activitypub\is_quote_activity
	 */
	public function test_is_quote_activity_with_quote() {
		$activity = array(
			'type'   => 'Create',
			'object' => array(
				'type'    => 'Note',
				'content' => '<p class="quote-inline">RE: <a href="https://example.com/post">Post</a></p><p>My comment</p>',
				'quote'   => 'https://example.com/post',
			),
		);

		$this->assertTrue( \Activitypub\is_quote_activity( $activity ) );
	}

	/**
	 * Test is_quote_activity function with quoteUrl property.
	 *
	 * @covers \Activitypub\is_quote_activity
	 */
	public function test_is_quote_activity_with_quote_url() {
		$activity = array(
			'type'   => 'Create',
			'object' => array(
				'type'     => 'Note',
				'content'  => '<p>My comment</p>',
				'quoteUrl' => 'https://example.com/post',
			),
		);

		$this->assertTrue( \Activitypub\is_quote_activity( $activity ) );
	}

	/**
	 * Test is_quote_activity function with quoteUri property.
	 *
	 * @covers \Activitypub\is_quote_activity
	 */
	public function test_is_quote_activity_with_quote_uri() {
		$activity = array(
			'type'   => 'Create',
			'object' => array(
				'type'     => 'Note',
				'content'  => '<p>My comment</p>',
				'quoteUri' => 'https://example.com/post',
			),
		);

		$this->assertTrue( \Activitypub\is_quote_activity( $activity ) );
	}

	/**
	 * Test is_quote_activity function with _misskey_quote property.
	 *
	 * @covers \Activitypub\is_quote_activity
	 */
	public function test_is_quote_activity_with_misskey_quote() {
		$activity = array(
			'type'   => 'Create',
			'object' => array(
				'type'           => 'Note',
				'content'        => '<p>My comment</p>',
				'_misskey_quote' => 'https://example.com/post',
			),
		);

		$this->assertTrue( \Activitypub\is_quote_activity( $activity ) );
	}

	/**
	 * Test is_quote_activity returns false for non-quote.
	 *
	 * @covers \Activitypub\is_quote_activity
	 */
	public function test_is_quote_activity_returns_false_for_non_quote() {
		$activity = array(
			'type'   => 'Create',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Just a regular post',
			),
		);

		$this->assertFalse( \Activitypub\is_quote_activity( $activity ) );
	}

	/**
	 * Test that extract_recipients_from_activity correctly extracts recipients
	 * from a QuoteRequest activity where addressing is only in the instrument
	 * (the Note being quoted), matching how Mastodon sends QuoteRequests.
	 *
	 * @covers \Activitypub\extract_recipients_from_activity
	 * @covers \Activitypub\extract_recipients_from_activity_property
	 */
	public function test_extract_recipients_from_quote_request_with_instrument() {
		// QuoteRequest activities have addressing in the instrument (the quoting Note),
		// not at the activity level. This matches how Mastodon sends QuoteRequests.
		$quote_request = array(
			'@context'   => array(
				'https://www.w3.org/ns/activitystreams',
				array(
					'toot'         => 'http://joinmastodon.org/ns#',
					'QuoteRequest' => 'toot:QuoteRequest',
				),
			),
			'id'         => 'https://remote.example.com/users/alice/quote_requests/123',
			'type'       => 'QuoteRequest',
			'object'     => 'https://example.org/posts/456',
			'actor'      => 'https://remote.example.com/users/alice',
			'instrument' => array(
				'id'           => 'https://remote.example.com/users/alice/statuses/789',
				'type'         => 'Note',
				'published'    => '2025-01-14T09:26:53Z',
				'url'          => 'https://remote.example.com/@alice/789',
				'attributedTo' => 'https://remote.example.com/users/alice',
				'to'           => array(
					'https://www.w3.org/ns/activitystreams#Public',
				),
				'cc'           => array(
					'https://remote.example.com/users/alice/followers',
				),
				'content'      => '<p>A quote post</p>',
				'quote'        => 'https://example.org/posts/456',
			),
		);

		$recipients = extract_recipients_from_activity( $quote_request );

		// Should extract recipients from instrument since activity level has none.
		$this->assertContains(
			'https://www.w3.org/ns/activitystreams#Public',
			$recipients,
			'Should extract public recipient from instrument.to'
		);
		$this->assertContains(
			'https://remote.example.com/users/alice/followers',
			$recipients,
			'Should extract followers from instrument.cc'
		);
	}

	/**
	 * Test that QuoteRequest with no addressing at activity level but
	 * with addressing in instrument can be detected as public.
	 *
	 * @covers \Activitypub\is_activity_public
	 */
	public function test_quote_request_is_public_via_instrument() {
		$quote_request = array(
			'type'       => 'QuoteRequest',
			'object'     => 'https://example.org/posts/123',
			'actor'      => 'https://example.com/users/alice',
			'instrument' => array(
				'type' => 'Note',
				'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				'cc'   => array( 'https://example.com/users/alice/followers' ),
			),
		);

		$this->assertTrue(
			\Activitypub\is_activity_public( $quote_request ),
			'QuoteRequest should be detected as public when instrument.to contains public identifier'
		);
	}
}
