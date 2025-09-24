<?php
/**
 * Test file for Following Table.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin\Table;

use Activitypub\Collection\Following as Following_Collection;
use Activitypub\Collection\Remote_Actors;
use Activitypub\WP_Admin\Table\Following;

/**
 * Test class for Following Table.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Table\Following
 */
class Test_Following extends \WP_UnitTestCase {

	/**
	 * Following table instance.
	 *
	 * @var Following
	 */
	private $following_table;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Set up global screen mock.
		set_current_screen( 'users_page_activitypub-following-list' );

		// Set current user.
		wp_set_current_user( 1 );

		// Create following table instance.
		$this->following_table = new Following();
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
	 * Test column_username with actor having icon object using real prepare_items().
	 *
	 * This test uses Following::follow() to create a real following and uses
	 * the actual prepare_items() method to test the complete data flow from
	 * ActivityPub actor with icon object to the final column output.
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

		// Add the actor first, then follow them.
		$actor_post_id = Remote_Actors::upsert( $actor_data );

		// Follow the actor using the proper method.
		Following_Collection::follow( $actor_post_id, get_current_user_id() );

		// Use the real prepare_items() method.
		$this->following_table->prepare_items();

		// Verify we have items.
		$this->assertNotEmpty( $this->following_table->items );

		// Get the first item and test column_username.
		$item   = $this->following_table->items[0];
		$result = $this->following_table->column_username( $item );

		// Verify the icon URL was extracted from the object by object_to_uri() and properly rendered.
		$this->assertStringContainsString( 'src="https://secure.gravatar.com/avatar/example?s=120&#038;d=mm&#038;r=g"', $result );

		// Verify that the icon was processed correctly: from object to URL.
		$this->assertEquals( 'https://secure.gravatar.com/avatar/example?s=120&d=mm&r=g', $item['icon'] );

		// Clean up.
		wp_delete_post( $actor_post_id, true );
	}

	/**
	 * Test prepare_items with actor having icon array of URLs.
	 *
	 * This test verifies that when an icon field contains an array of URLs,
	 * the object_to_uri() function correctly extracts the first URL from the array.
	 *
	 * @covers ::prepare_items
	 */
	public function test_prepare_items_with_icon_array_of_urls() {
		// Mock remote metadata for the actor with icon as direct array of URLs.
		$actor_url  = 'https://example.com/users/arrayuser';
		$actor_data = array(
			'name'              => 'Array User',
			'icon'              => array(
				'url'       => array(
					'https://example.com/storage/profile.webp',
				),
				'type'      => 'Image',
				'mediaType' => 'image/webp',
			),
			'url'               => $actor_url,
			'id'                => 'https://example.com/users/arrayuser',
			'preferredUsername' => 'arrayuser',
			'inbox'             => 'https://example.com/users/arrayuser/inbox',
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

		// Add the actor first, then follow them.
		$actor_post_id = Remote_Actors::upsert( $actor_data );

		// Follow the actor using the proper method.
		Following_Collection::follow( $actor_post_id, get_current_user_id() );

		// Use the real prepare_items() method.
		$this->following_table->prepare_items();

		// Verify that the icon array was processed correctly: from array to first URL.
		$this->assertEquals( 'https://example.com/storage/profile.webp', $this->following_table->items[0]['icon'] );

		// Clean up.
		wp_delete_post( $actor_post_id, true );
	}

	/**
	 * Data provider for actor identifier normalization test cases.
	 *
	 * @return array Test cases with input and expected output.
	 */
	public function actor_identifier_normalization_provider() {
		return array(
			'Standard ActivityPub URL' => array(
				'input'    => 'https://pixelfed.social/users/photographer',
				'expected' => 'https://pixelfed.social/users/photographer',
			),
			'Webfinger format'         => array(
				'input'    => 'photographer@pixelfed.social',
				'expected' => 'https://pixelfed.social/users/photographer',
			),
			'URL without scheme'       => array(
				'input'    => 'pixelfed.social/users/photographer',
				'expected' => 'https://pixelfed.social/users/photographer',
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
		// Mock webfinger resolution for email-like addresses.
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
	 * Test actor object normalization when following actors.
	 *
	 * This test verifies that ActivityPub actor objects are properly converted
	 * to URIs when creating following relationships.
	 *
	 * @covers ::prepare_items
	 */
	public function test_actor_object_normalization_following() {
		$actor_data = array(
			'type'              => 'Person',
			'id'                => 'https://lemmy.ml/users/developer',
			'name'              => 'Object Developer',
			'preferredUsername' => 'developer',
			'inbox'             => 'https://lemmy.ml/users/developer/inbox',
			'outbox'            => 'https://lemmy.ml/users/developer/outbox',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://lemmy.ml/avatar_dev.png',
			),
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

		// Test normalization of actor object.
		$normalized_id = Remote_Actors::normalize_identifier( $actor_data );
		$this->assertEquals( $actor_data['id'], $normalized_id, 'Actor object was not properly normalized to URI' );

		// Add the actor and follow.
		$actor_post_id = Remote_Actors::upsert( $actor_data );
		Following_Collection::follow( $actor_post_id, get_current_user_id() );

		// Prepare items to test normalization.
		$this->following_table->prepare_items();

		// Verify the following relationship was created with normalized URI.
		$found = false;
		foreach ( $this->following_table->items as $item ) {
			if ( $item['identifier'] === $actor_data['id'] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Actor object was not properly converted to URI when following' );

		// Clean up.
		Following_Collection::unfollow( $actor_post_id, get_current_user_id() );
		wp_delete_post( $actor_post_id, true );
	}

	/**
	 * Data provider for complex webfinger scenarios.
	 *
	 * @return array Complex webfinger test cases.
	 */
	public function complex_webfinger_provider() {
		return array(
			'peertube platform'  => array(
				'input'     => 'artist@peertube.example.com',
				'actor_url' => 'https://peertube.example.com/accounts/artist',
			),
			'friendica platform' => array(
				'input'     => 'user-name.123@friendica.example.net',
				'actor_url' => 'https://friendica.example.net/profile/user-name.123',
			),
			'wordpress blog'     => array(
				'input'     => 'blog@wordpress.example.org',
				'actor_url' => 'https://wordpress.example.org/author/blog',
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
	public function test_complex_webfinger_scenarios_following( $input, $actor_url ) {
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

	/**
	 * Data provider for edge case scenarios.
	 *
	 * @return array Edge case test cases.
	 */
	public function edge_case_provider() {
		return array(
			'invalid email 1'     => array( 'invalid-email@' ),
			'invalid email 2'     => array( '@invalid-domain' ),
			'no at symbol'        => array( 'no-at-symbol' ),
			'multiple at symbols' => array( 'multiple@at@symbols.com' ),
			'empty string'        => array( '' ),
			'null value'          => array( null ),
			'malformed URL 1'     => array( 'http://' ),
			'malformed URL 2'     => array( 'https://' ),
			'non-http protocol'   => array( 'ftp://not-http.com/user' ),
			'empty array'         => array( array() ),
		);
	}

	/**
	 * Test edge cases and error handling in actor normalization.
	 *
	 * This test verifies that invalid or malformed actor identifiers
	 * are handled gracefully without causing errors.
	 *
	 * @dataProvider edge_case_provider
	 *
	 * @param mixed $edge_case The edge case input to test.
	 */
	public function test_actor_normalization_edge_cases( $edge_case ) {
		// These should not cause fatal errors or exceptions.
		$normalized = Remote_Actors::normalize_identifier( $edge_case );

		// The result should be handled gracefully.
		$this->assertTrue(
			null === $normalized || is_string( $normalized ) || $edge_case === $normalized,
			'Edge case should return null, string, or original value. Got: ' . gettype( $normalized ) . ' for input: ' . wp_json_encode( $edge_case )
		);
	}
}
