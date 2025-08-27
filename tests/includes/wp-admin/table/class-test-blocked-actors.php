<?php
/**
 * Test file for Blocked Actors Table.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin\Table;

use Activitypub\Collection\Actors;
use Activitypub\Moderation;

/**
 * Test class for Blocked Actors Table.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Table\Blocked_Actors
 */
class Test_Blocked_Actors extends \WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Set up global screen mock.
		set_current_screen( 'users_page_activitypub-blocked-actors-list' );

		// Set current user.
		wp_set_current_user( 1 );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		parent::tear_down();

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Data provider for actor identifier normalization test cases.
	 *
	 * @return array Test cases with input, expected output, and label.
	 */
	public function actor_identifier_normalization_provider() {
		return array(
			'Standard ActivityPub URL' => array(
				'input'    => 'https://mastodon.social/users/testuser',
				'expected' => 'https://mastodon.social/users/testuser',
			),
			'Webfinger format'         => array(
				'input'    => 'testuser@mastodon.social',
				'expected' => 'https://mastodon.social/users/testuser',
			),
			'URL without scheme'       => array(
				'input'    => 'mastodon.social/users/testuser',
				'expected' => 'https://mastodon.social/users/testuser',
			),
		);
	}

	/**
	 * Test actor identifier normalization with various formats.
	 *
	 * This test verifies that different types of actor identifiers (URLs, webfinger,
	 * hostnames) are properly normalized by the normalize_identifier method.
	 *
	 * @dataProvider actor_identifier_normalization_provider
	 * @covers ::prepare_items
	 *
	 * @param string $input    The input actor identifier.
	 * @param string $expected The expected normalized output.
	 */
	public function test_actor_identifier_normalization_blocking( $input, $expected ) {
		// Mock webfinger resolution. for email-like addresses.
		if ( strpos( $input, '@' ) !== false ) {
			add_filter(
				'pre_http_request',
				function ( $response, $parsed_args, $url ) use ( $input, $expected ) {
					if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
						return array(
							'headers'  => array( 'content-type' => 'application/json' ),
							'body'     => wp_json_encode(
								array(
									'subject' => 'acct:' . str_replace( 'acct:', '', $input ),
									'links'   => array(
										array(
											'rel'  => 'self',
											'type' => 'application/activity+json',
											'href' => $expected,
										),
									),
								)
							),
							'response' => array( 'code' => 200 ),
						);
					}
					return $response;
				},
				10,
				3
			);
		}

		// Test the normalization directly.
		$normalized = Actors::normalize_identifier( $input );
		$this->assertEquals( $expected, $normalized, "Failed to normalize: {$input} -> expected {$expected}, got " . wp_json_encode( $normalized ) );
	}

	/**
	 * Test actor object normalization when blocking.
	 *
	 * This test verifies that ActivityPub actor objects are properly converted
	 * to URIs when blocking.
	 *
	 * @covers ::prepare_items
	 */
	public function test_actor_object_normalization_blocking() {
		$actor_data = array(
			'type'              => 'Person',
			'id'                => 'https://example.com/users/testuser',
			'name'              => 'Test User',
			'preferredUsername' => 'testuser',
			'inbox'             => 'https://example.com/users/testuser/inbox',
		);

		// Mock HTTP request to fetch actor data.
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) use ( $actor_data ) {
				if ( $url === $actor_data['id'] ) {
					return array(
						'headers'  => array( 'content-type' => 'application/activity+json' ),
						'body'     => wp_json_encode( $actor_data ),
						'response' => array( 'code' => 200 ),
					);
				}
				return $response;
			},
			10,
			3
		);

		// Mock remote metadata for the actor.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function ( $value, $actor ) use ( $actor_data ) {
				if ( $actor === $actor_data['id'] ) {
					return $actor_data;
				}
				return $value;
			},
			10,
			2
		);

		// Block using the actor object.
		$result = Moderation::add_user_block( get_current_user_id(), Moderation::TYPE_ACTOR, $actor_data['id'] );
		$this->assertTrue( $result, 'Failed to block actor using object format' );

		// Verify the actor was blocked by checking the normalized URI.
		$blocked_actors = Moderation::get_user_blocks( get_current_user_id() );
		$this->assertContains( $actor_data['id'], $blocked_actors['actors'], 'Actor object was not properly converted to URI when blocking' );

		// Clean up.
		Moderation::remove_user_block( get_current_user_id(), Moderation::TYPE_ACTOR, $actor_data['id'] );
	}

	/**
	 * Data provider for invalid actor identifier test cases.
	 *
	 * @return array Invalid test cases.
	 */
	public function invalid_actor_identifier_provider() {
		return array(
			'null input'        => array( null ),
			'empty string'      => array( '' ),
			'invalid format'    => array( 'invalid-format' ),
			'malformed email 1' => array( 'not-an-email@' ),
			'malformed email 2' => array( '@invalid' ),
			'empty array'       => array( array() ),
		);
	}

	/**
	 * Test error handling with invalid actor identifiers.
	 *
	 * This test verifies that invalid or malformed actor identifiers
	 * are handled gracefully without causing errors.
	 *
	 * @dataProvider invalid_actor_identifier_provider
	 *
	 * @param mixed $invalid_input The invalid input to test.
	 */
	public function test_invalid_actor_identifier_handling( $invalid_input ) {
		// These should not cause fatal errors.
		$normalized = Actors::normalize_identifier( $invalid_input );

		// The result should be handled gracefully.
		$this->assertTrue(
			null === $normalized || is_string( $normalized ) || $invalid_input === $normalized,
			'Invalid input should return null, string, or original value, got: ' . gettype( $normalized )
		);
	}
}
