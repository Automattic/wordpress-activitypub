<?php
/**
 * Test file for Followers Table.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin\Table;

use Activitypub\Collection\Followers as Follower_Collection;
use Activitypub\Collection\Remote_Actors;
use Activitypub\WP_Admin\Table\Followers;

/**
 * Test class for Followers Table.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Table\Followers
 */
class Test_Followers extends \WP_UnitTestCase {

	/**
	 * Followers table instance.
	 *
	 * @var Followers
	 */
	private $followers_table;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Set up global screen mock.
		set_current_screen( 'users_page_activitypub-followers-list' );

		// Set current user.
		wp_set_current_user( 1 );

		// Create followers table instance.
		$this->followers_table = new Followers();
	}

	/**
	 * Test column_username with actor having icon object.
	 *
	 * @covers ::column_username
	 * @covers ::prepare_items
	 */
	public function test_column_username_with_icon_object() {
		// Mock remote metadata for the actor with icon object.
		$actor_url  = 'https://example.com/users/testuser';
		$actor_data = array(
			'name'              => 'Test User',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://secure.gravatar.com/avatar/example?s=120&d=mm&r=g',
			),
			'url'               => $actor_url,
			'id'                => 'https://example.com/users/testuser',
			'preferredUsername' => 'testuser',
			'inbox'             => 'https://example.com/users/testuser/inbox',
		);

		// Mock the remote metadata call using the correct filter.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function ( $value, $actor ) use ( $actor_url, $actor_data ) {
				if ( $actor === $actor_url ) {
					return $actor_data;
				}
				return $value;
			},
			10,
			2
		);

		// Add the follower.
		Follower_Collection::add( get_current_user_id(), $actor_url );

		// Use the real prepare_items() method.
		$this->followers_table->prepare_items();

		// Verify we have items.
		$this->assertNotEmpty( $this->followers_table->items );

		// Get the first item and test column_username.
		$item   = $this->followers_table->items[0];
		$result = $this->followers_table->column_username( $item );

		// Verify the icon URL was extracted from the object by object_to_uri() and properly rendered.
		$this->assertStringContainsString( 'src="https://secure.gravatar.com/avatar/example?s=120&#038;d=mm&#038;r=g"', $result );

		// Verify that the icon was processed correctly: from object to URL.
		$this->assertEquals( 'https://secure.gravatar.com/avatar/example?s=120&d=mm&r=g', $item['icon'] );
	}

	/**
	 * Data provider for actor identifier normalization test cases.
	 *
	 * @return array Test cases with input and expected output.
	 */
	public function actor_identifier_normalization_provider() {
		return array(
			'Standard ActivityPub URL' => array(
				'input'    => 'https://mastodon.social/users/testfollower',
				'expected' => 'https://mastodon.social/users/testfollower',
			),
			'Webfinger format'         => array(
				'input'    => 'testfollower@mastodon.social',
				'expected' => 'https://mastodon.social/users/testfollower',
			),
			'URL without scheme'       => array(
				'input'    => 'mastodon.social/users/testfollower',
				'expected' => 'https://mastodon.social/users/testfollower',
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
	public function test_actor_identifier_normalization_following( $input, $expected ) {
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
		$normalized = Remote_Actors::normalize_identifier( $input );
		$this->assertEquals( $expected, $normalized, "Failed to normalize: {$input} -> expected {$expected}, got " . wp_json_encode( $normalized ) );
	}

	/**
	 * Test actor object normalization when adding followers.
	 *
	 * This test verifies that ActivityPub actor objects are properly converted
	 * to URIs when added as followers.
	 *
	 * @covers ::prepare_items
	 */
	public function test_actor_object_normalization_following() {
		$actor_data = array(
			'type'              => 'Person',
			'id'                => 'https://example.com/users/objectfollower',
			'name'              => 'Object Follower',
			'preferredUsername' => 'objectfollower',
			'inbox'             => 'https://example.com/users/objectfollower/inbox',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://example.com/avatar2.jpg',
			),
		);

		// Mock HTTP request to fetch actor data.
		add_filter(
			'pre_http_request',
			function () use ( $actor_data ) {
				return array(
					'headers'  => array( 'content-type' => 'application/activity+json' ),
					'body'     => wp_json_encode( $actor_data ),
					'response' => array( 'code' => 200 ),
				);
			}
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

		// Add follower using the actor object.
		Follower_Collection::add( get_current_user_id(), $actor_data['id'] );

		// Prepare items to test normalization.
		$this->followers_table->prepare_items();

		// Verify the follower was added with normalized URI.
		$found = false;
		foreach ( $this->followers_table->items as $item ) {
			if ( $item['identifier'] === $actor_data['id'] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Actor object was not properly converted to URI when adding follower' );

		// Clean up.
		Follower_Collection::remove( get_current_user_id(), $actor_data['id'] );
	}

	/**
	 * Data provider for complex webfinger scenarios.
	 *
	 * @return array Complex webfinger test cases.
	 */
	public function complex_webfinger_provider() {
		return array(
			'webfinger with subdomain'          => array(
				'input'     => 'follower@social.example.com',
				'actor_url' => 'https://social.example.com/users/follower',
			),
			'webfinger with special characters' => array(
				'input'     => 'test.user_123@mastodon.example.org',
				'actor_url' => 'https://mastodon.example.org/users/test.user_123',
			),
		);
	}

	/**
	 * Test complex webfinger scenarios.
	 *
	 * This test covers various webfinger edge cases and ensures proper
	 * resolution and normalization.
	 *
	 * @dataProvider complex_webfinger_provider
	 *
	 * @param string $input     The webfinger input.
	 * @param string $actor_url The expected actor URL.
	 */
	public function test_complex_webfinger_scenarios_followers( $input, $actor_url ) {
		// Mock webfinger resolution.
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) use ( $input, $actor_url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'headers'  => array( 'content-type' => 'application/json' ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:' . $input,
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => $actor_url,
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

		// Test normalization.
		$normalized = Remote_Actors::normalize_identifier( $input );
		$this->assertEquals( $actor_url, $normalized, "Failed to normalize complex webfinger: {$input}" );
	}
}
