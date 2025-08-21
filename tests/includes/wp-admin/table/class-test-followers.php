<?php
/**
 * Test file for Followers Table.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin\Table;

use Activitypub\WP_Admin\Table\Followers;
use Activitypub\Collection\Actors;

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
	 * This test simulates the prepare_items() data processing by creating
	 * a realistic item array that includes an icon URL extracted from
	 * an ActivityPub actor's icon object.
	 *
	 * @covers ::column_username
	 */
	public function test_column_username_with_icon_object() {
		// Simulate how prepare_items() processes an ActivityPub actor with icon object.
		// Real ActivityPub actor icon: {"type": "Image", "url": "..."}
		$activitypub_actor_icon = array(
			'type' => 'Image',
			'url'  => 'https://secure.gravatar.com/avatar/example?s=120&d=mm&r=g',
		);

		// Simulate the icon URL extraction from prepare_items(): object_to_uri( $actor->get_icon() )
		$extracted_icon_url = $activitypub_actor_icon['url'] ?? '';

		// Create item array as prepare_items() would.
		$item = array(
			'id'         => 123,
			'icon'       => $extracted_icon_url,
			'post_title' => 'Test User',
			'username'   => 'testuser',
			'url'        => 'https://example.com/@testuser',
			'webfinger'  => '@testuser@example.com',
			'identifier' => 'https://example.com/users/testuser',
			'modified'   => '2023-01-01 12:00:00',
		);

		// Test the column_username output.
		$result = $this->followers_table->column_username( $item );

		// Verify the icon URL was properly rendered (WordPress escapes & as &#038;).
		$this->assertStringContainsString( 'https://secure.gravatar.com/avatar/example?s=120&#038;d=mm&#038;r=g', $result );
		$this->assertStringContainsString( 'width="32" height="32"', $result );
		$this->assertStringContainsString( 'alt="testuser"', $result );
		$this->assertStringContainsString( 'loading="lazy"', $result );
		$this->assertStringContainsString( '<strong><a href="https://example.com/@testuser" target="_blank">testuser</a></strong>', $result );
	}
}