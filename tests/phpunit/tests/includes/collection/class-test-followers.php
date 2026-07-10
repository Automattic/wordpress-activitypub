<?php
/**
 * Test file for Activitypub Followers.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;

/**
 * Test class for Activitypub Followers.
 *
 * @coversDefaultClass \Activitypub\Collection\Followers
 */
class Test_Followers extends \WP_UnitTestCase {

	/**
	 * Actors.
	 *
	 * @var array[]
	 */
	public static $actors = array(
		'username@example.org' => array(
			'id'                => 'https://example.org/users/username',
			'type'              => 'Person',
			'url'               => 'https://example.org/users/username',
			'inbox'             => 'https://example.org/users/username/inbox',
			'name'              => 'username',
			'preferredUsername' => 'username',
			'endpoints'         => array( 'sharedInbox' => 'https://example.org/sharedInbox' ),
		),
		'jon@example.com'      => array(
			'id'                => 'https://example.com/author/jon',
			'type'              => 'Person',
			'url'               => 'https://example.com/author/jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
			'endpoints'         => array( 'sharedInbox' => 'https://example.org/sharedInbox' ),
		),
		'doe@example.org'      => array(
			'id'                => 'https://example.org/author/doe',
			'type'              => 'Person',
			'url'               => 'https://example.org/author/doe',
			'inbox'             => 'https://example.org/author/doe/inbox',
			'name'              => 'doe',
			'preferredUsername' => 'doe',
		),
		'sally@example.org'    => array(
			'id'                => 'http://sally.example.org',
			'type'              => 'Person',
			'url'               => 'http://sally.example.org',
			'inbox'             => 'http://sally.example.org/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
		),
		'12345@example.com'    => array(
			'id'                => 'https://12345.example.com',
			'type'              => 'Person',
			'url'               => 'https://12345.example.com',
			'inbox'             => 'https://12345.example.com/inbox',
			'name'              => '12345',
			'preferredUsername' => '12345',
		),
		'user2@example.com'    => array(
			'id'                => 'https://user2.example.com',
			'type'              => 'Person',
			'url'               => 'https://user2.example.com',
			'inbox'             => 'https://user2.example.com/inbox',
			'name'              => 'úser2',
			'preferredUsername' => 'user2',
			'summary'           => 'father since 04\24', // @ticket https://github.com/Automattic/wordpress-activitypub/pull/1373
		),
		'error@example.com'    => array(
			'url'               => 'https://error.example.com',
			'name'              => 'error',
			'preferredUsername' => 'error',
		),
	);

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		\add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
		_delete_all_posts();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	/**
	 * Tests get_followers.
	 *
	 * @covers ::get_followers
	 */
	public function test_get_followers() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		foreach ( $followers as $follower ) {
			Followers::add( 1, $follower );
		}

		$db_followers = Followers::get_many( 1 );

		$this->assertEquals( 3, \count( $db_followers ) );

		$db_followers = array_map(
			function ( $item ) {
				return $item->guid;
			},
			$db_followers
		);

		$this->assertEquals( array( 'http://sally.example.org', 'https://example.org/author/doe', 'https://example.com/author/jon' ), $db_followers );
	}

	/**
	 * Tests add_follower.
	 *
	 * @covers ::add_follower
	 */
	public function test_add_follower() {
		$follower  = 'https://12345.example.com';
		$follower2 = 'https://user2.example.com';
		Followers::add( 1, $follower );
		Followers::add( 2, $follower );
		Followers::add( 2, $follower2 );

		$db_followers  = Followers::get_many( 1 );
		$db_followers2 = Followers::get_many( 2 );

		$this->assertStringContainsString( $follower, serialize( $db_followers ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->assertStringContainsString( $follower2, serialize( $db_followers2 ) );  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Tests add_follower error.
	 *
	 * @covers ::add_follower
	 */
	public function test_add_follower_error() {
		$follower = 'error@example.net';

		$result = Followers::add( 1, $follower );

		$this->assertTrue( \is_wp_error( $result ) );

		$follower2 = 'https://error.example.net';

		$result = Followers::add( 1, $follower2 );

		$this->assertTrue( \is_wp_error( $result ) );

		$db_followers = Followers::get_many( 1 );

		$this->assertEmpty( $db_followers );
	}

	/**
	 * Tests get_follower.
	 *
	 * @covers ::get_follower
	 */
	public function test_get_follower() {
		$followers  = array( 'https://example.com/author/jon' );
		$followers2 = array( 'https://user2.example.com' );

		foreach ( $followers as $follower ) {
			Followers::add( 1, $follower );
		}

		foreach ( $followers2 as $follower ) {
			Followers::add( 2, $follower );
		}

		$follower = Followers::get_by_uri( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->guid );

		$follower = Followers::get_by_uri( 1, 'http://sally.example.org' );
		$this->assertWPError( $follower );

		$follower = Followers::get_by_uri( 1, 'https://user2.example.com' );
		$this->assertWPError( $follower );

		$follower = Followers::get_by_uri( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->guid );

		$follower2 = Followers::get_by_uri( 2, 'https://user2.example.com' );
		$this->assertEquals( 'https://user2.example.com', $follower2->guid );
		$this->assertEquals( 'úser2', Remote_Actors::get_actor( $follower2 )->get_name() );
	}

	/**
	 * Tests that removing a follower for one user does not affect another user.
	 *
	 * @covers ::remove
	 */
	public function test_delete_follower() {
		$followers  = array(
			'https://example.com/author/jon',
			'https://example.org/author/doe',
		);
		$followers2 = array( 'https://user2.example.com' );

		foreach ( $followers as $follower ) {
			Followers::add( 1, $follower );
			Followers::add( 1, $follower );
			Followers::add( 1, $follower );
			Followers::add( 2, $follower );
		}

		foreach ( $followers2 as $follower2 ) {
			Followers::add( 2, $follower2 );
		}

		$follower = Followers::get_by_uri( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->guid );

		$followers = Followers::get_many( 1 );
		$this->assertEquals( 2, count( $followers ) );

		$follower2 = Followers::get_by_uri( 2, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower2->guid );

		Followers::remove( $follower->ID, 1 );

		$follower = Followers::get_by_uri( 1, 'https://example.com/author/jon' );
		$this->assertWPError( $follower );

		$follower2 = Followers::get_by_uri( 2, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower2->guid );

		$followers = Followers::get_many( 1 );
		$this->assertEquals( 1, count( $followers ) );
	}

	/**
	 * Tests remove.
	 *
	 * @covers ::remove
	 */
	public function test_remove() {
		$followers = array(
			'https://example.com/author/jon',
			'https://example.org/author/doe',
		);

		foreach ( $followers as $follower ) {
			Followers::add( 1, $follower );
		}

		$follower = Followers::get_by_uri( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->guid );

		$followers = Followers::get_many( 1 );
		$this->assertEquals( 2, count( $followers ) );

		Followers::remove( $followers[0]->ID, 1 );

		$follower = Followers::get_by_uri( 1, $followers[0]->guid );
		$this->assertWPError( $follower );

		$followers = Followers::get_many( 1 );
		$this->assertEquals( 1, count( $followers ) );
	}

	/**
	 * Tests add_duplicate_follower.
	 *
	 * @covers ::add_follower
	 */
	public function test_add_duplicate_follower() {
		$follower = 'https://12345.example.com';

		Followers::add( 1, $follower );
		Followers::add( 1, $follower );
		Followers::add( 1, $follower );
		Followers::add( 1, $follower );
		Followers::add( 1, $follower );
		Followers::add( 1, $follower );

		$db_followers = Followers::get_many( 1 );

		$this->assertStringContainsString( $follower, serialize( $db_followers ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		$follower = current( $db_followers );
		$meta     = get_post_meta( $follower->ID, Followers::FOLLOWER_META_KEY, false );

		$this->assertCount( 1, $meta );
	}

	/**
	 * Data provider for migration test scenarios.
	 *
	 * @return array[]
	 */
	public function migration_scenarios_provider() {
		return array(
			'valid_followers' => array(
				array(
					'https://example.com/author/jon',
					'https://example.org/author/doe',
					'http://sally.example.org',
				),
				3,
			),
			'invalid_url'     => array(
				array(
					'not_a_url',
					'https://example.org/author/doe',
				),
				1,
			),
			'empty_followers' => array(
				array(),
				0,
			),
		);
	}

	/**
	 * Tests migration of followers from user meta to new format.
	 *
	 * @dataProvider migration_scenarios_provider
	 *
	 * @param array $followers      List of followers to migrate.
	 * @param int   $expected_count Expected number of successful migrations.
	 */
	public function test_migration_followers( $followers, $expected_count ) {
		$user_id = 1;

		// Mock remote metadata to avoid network calls.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function ( $pre, $actor ) {
				if ( isset( self::$actors[ $actor ] ) ) {
					return self::$actors[ $actor ];
				}
				return $pre;
			},
			10,
			2
		);

		\add_user_meta( $user_id, 'activitypub_followers', $followers, true );

		\Activitypub\Migration::migrate_from_0_17();

		$db_followers = Followers::get_many( 1 );
		$this->assertCount( $expected_count, $db_followers );

		if ( $expected_count > 0 ) {
			// Verify each valid follower was migrated correctly.
			$db_follower_ids = array_map(
				function ( $follower ) {
					return $follower->guid;
				},
				$db_followers
			);
			sort( $db_follower_ids );
			$valid_followers = array_filter(
				$followers,
				function ( $url ) {
					return filter_var( $url, FILTER_VALIDATE_URL );
				}
			);
			sort( $valid_followers );
			$this->assertEquals( $valid_followers, $db_follower_ids );
		}

		// Clean up.
		\delete_user_meta( $user_id, 'activitypub_followers' );
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'pre_get_remote_metadata_by_actor' ) );
	}

	/**
	 * Tests get_inboxes.
	 *
	 * @covers ::get_inboxes
	 */
	public function test_get_inboxes() {
		\wp_cache_delete( \sprintf( Followers::CACHE_KEY_INBOXES, 1 ), 'activitypub' );

		for ( $i = 0; $i < 30; $i++ ) {
			$meta = array(
				'id'                => 'https://example.org/users/' . $i,
				'type'              => 'Person',
				'url'               => 'https://example.org/users/' . $i,
				'inbox'             => 'https://example.org/users/' . $i . '/inbox',
				'name'              => 'user' . $i,
				'preferredUsername' => 'user' . $i,
				'publicKey'         => 'https://example.org/users/' . $i . '#main-key',
				'publicKeyPem'      => $i,
			);

			$id = Remote_Actors::upsert( $meta );

			\add_post_meta( $id, Followers::FOLLOWER_META_KEY, 1 );
		}

		$other_actor = Remote_Actors::upsert(
			array(
				'id'                => 'https://example.org/users/other',
				'type'              => 'Person',
				'inbox'             => 'https://example.org/users/other/inbox',
				'name'              => 'other',
				'preferredUsername' => 'other',
			)
		);
		\add_post_meta( $other_actor, Followers::FOLLOWER_META_KEY, 2 );

		$non_actor = \wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Not an actor',
			)
		);
		\add_post_meta( $non_actor, '_activitypub_inbox', 'https://example.org/not-an-actor/inbox' );
		\add_post_meta( $non_actor, Followers::FOLLOWER_META_KEY, 1 );

		$draft_actor = \wp_insert_post(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => 'Draft actor',
			)
		);
		\add_post_meta( $draft_actor, '_activitypub_inbox', 'https://example.org/draft/inbox' );
		\add_post_meta( $draft_actor, Followers::FOLLOWER_META_KEY, 1 );

		global $wpdb;
		$query_count = $wpdb->num_queries;
		$inboxes     = Followers::get_inboxes( 1 );

		$this->assertSame( 1, $wpdb->num_queries - $query_count, 'Follower inboxes should be loaded with one query.' );
		$this->assertCount( 30, $inboxes );

		wp_cache_delete( sprintf( Followers::CACHE_KEY_INBOXES, 1 ), 'activitypub' );
		wp_cache_delete( Remote_Actors::CACHE_KEY_INBOXES, 'activitypub' );

		for ( $j = 0; $j < 5; $j++ ) {
			$k    = $j + 100;
			$meta = array(
				'id'                => 'https://example.org/users/' . $k,
				'type'              => 'Person',
				'url'               => 'https://example.org/users/' . $k,
				'inbox'             => 'https://example.org/users/' . $j . '/inbox',
				'name'              => 'user' . $k,
				'preferredUsername' => 'user' . $k,
				'publicKey'         => 'https://example.org/users/' . $k . '#main-key',
				'publicKeyPem'      => $k,
			);

			$id = Remote_Actors::upsert( $meta );

			add_post_meta( $id, Followers::FOLLOWER_META_KEY, 1 );
		}

		$inboxes2 = Followers::get_inboxes( 1 );

		$this->assertCount( 30, $inboxes2 );
	}

	/**
	 * Tests get_inboxes_for_activity method.
	 *
	 * @covers ::get_inboxes_for_activity
	 */
	public function test_get_inboxes_for_activity() {
		$actor_id  = 123;
		$followers = array(
			'username@example.org',
			'jon@example.com',
			'doe@example.org',
		);

		// Create test followers.
		foreach ( $followers as $follower ) {
			$actor = self::$actors[ $follower ];
			Followers::add( $actor_id, $actor['id'] );
		}

		// Test basic retrieval.
		$inboxes = Followers::get_inboxes_for_activity(
			'{"type":"Create"}',
			$actor_id,
			50,
			0
		);

		// username and jon have sharedInbox endpoints.
		$this->assertCount( 2, $inboxes, 'Should retrieve exactly 2 inboxes.' );
		$this->assertContains( self::$actors['username@example.org']['endpoints']['sharedInbox'], $inboxes, 'Should contain first inbox.' );
		$this->assertContains( self::$actors['doe@example.org']['inbox'], $inboxes, 'Should contain second inbox.' );

		// Test pagination.
		$inboxes = Followers::get_inboxes_for_activity(
			'{"type":"Create"}',
			$actor_id,
			1,
			0
		);
		$this->assertCount( 1, $inboxes, 'Should retrieve exactly 1 inbox with batch size 1.' );

		// Test with blog user in dual mode.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		Followers::add( Actors::BLOG_USER_ID, self::$actors['sally@example.org']['id'] );

		$inboxes = Followers::get_inboxes_for_activity(
			'{"type":"Delete"}',
			$actor_id,
			50,
			0
		);
		$this->assertCount( 3, $inboxes, 'Should include blog user followers in dual mode.' );
		$this->assertContains( self::$actors['sally@example.org']['inbox'], $inboxes, 'Should contain blog user inbox.' );
	}

	/**
	 * Filters remote metadata by actor.
	 *
	 * @param array  $pre   The pre.
	 * @param string $actor The actor.
	 * @return array
	 */
	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		if ( isset( self::$actors[ $actor ] ) ) {
			return self::$actors[ $actor ];
		}
		foreach ( self::$actors as $data ) {
			if ( $data['url'] === $actor ) {
				return $data;
			}
		}
		return $pre;
	}

	/**
	 * Data provider for test_extract_name_from_uri.
	 *
	 * @return array[]
	 */
	public function extract_name_from_uri_content_provider() {
		return array(
			array( 'https://example.com/@user', 'user' ),
			array( 'https://example.com/@user/', 'user' ),
			array( 'https://example.com/users/user', 'user' ),
			array( 'https://example.com/users/user/', 'user' ),
			array( 'https://example.com/@user?as=asasas', 'user' ),
			array( 'https://example.com/@user#asass', 'user' ),
			array( '@user@example.com', 'user' ),
			array( 'acct:user@example.com', 'user' ),
			array( 'user@example.com', 'user' ),
			array( 'https://example.com', 'https://example.com' ),
		);
	}
}
