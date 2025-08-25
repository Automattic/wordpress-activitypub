<?php
/**
 * Test file for Followers Table.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin\Table;

use Activitypub\WP_Admin\Table\Followers;
use Activitypub\Collection\Followers as Follower_Collection;

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
		Follower_Collection::add_follower( get_current_user_id(), $actor_url );

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

		// Clean up.
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}
}
