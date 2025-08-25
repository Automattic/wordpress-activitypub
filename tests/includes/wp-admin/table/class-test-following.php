<?php
/**
 * Test file for Following Table.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin\Table;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Following as Following_Collection;
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
		$actor_post_id = Actors::upsert( $actor_data );

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
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
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
		$actor_post_id = Actors::upsert( $actor_data );

		// Follow the actor using the proper method.
		Following_Collection::follow( $actor_post_id, get_current_user_id() );

		// Use the real prepare_items() method.
		$this->following_table->prepare_items();

		// Verify that the icon array was processed correctly: from array to first URL.
		$this->assertEquals( 'https://example.com/storage/profile.webp', $this->following_table->items[0]['icon'] );

		// Clean up.
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		wp_delete_post( $actor_post_id, true );
	}
}
