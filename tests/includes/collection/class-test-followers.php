<?php
/**
 * Test file for Activitypub Followers.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Followers;

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
			'url'               => 'https://example.org/users/username',
			'inbox'             => 'https://example.org/users/username/inbox',
			'name'              => 'username',
			'preferredUsername' => 'username',
		),
		'jon@example.com'      => array(
			'id'                => 'https://example.com/author/jon',
			'url'               => 'https://example.com/author/jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
		),
		'doe@example.org'      => array(
			'id'                => 'https://example.org/author/doe',
			'url'               => 'https://example.org/author/doe',
			'inbox'             => 'https://example.org/author/doe/inbox',
			'name'              => 'doe',
			'preferredUsername' => 'doe',
		),
		'sally@example.org'    => array(
			'id'                => 'http://sally.example.org',
			'url'               => 'http://sally.example.org',
			'inbox'             => 'http://sally.example.org/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
		),
		'12345@example.com'    => array(
			'id'                => 'https://12345.example.com',
			'url'               => 'https://12345.example.com',
			'inbox'             => 'https://12345.example.com/inbox',
			'name'              => '12345',
			'preferredUsername' => '12345',
		),
		'user2@example.com'    => array(
			'id'                => 'https://user2.example.com',
			'url'               => 'https://user2.example.com',
			'inbox'             => 'https://user2.example.com/inbox',
			'name'              => 'úser2',
			'preferredUsername' => 'user2',
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
			Followers::add_follower( 1, $follower );
		}

		$db_followers = Followers::get_followers( 1 );

		$this->assertEquals( 3, \count( $db_followers ) );

		$db_followers = array_map(
			function ( $item ) {
				return $item->get_id();
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
		Followers::add_follower( 1, $follower );
		Followers::add_follower( 2, $follower );
		Followers::add_follower( 2, $follower2 );

		$db_followers  = Followers::get_followers( 1 );
		$db_followers2 = Followers::get_followers( 2 );

		$this->assertStringContainsString( $follower, serialize( $db_followers ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->assertStringContainsString( $follower2, serialize( $db_followers2 ) );  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Tests add_follower error.
	 *
	 * @covers ::add_follower
	 */
	public function test_add_follower_error() {
		$follower = 'error@example.com';

		$result = Followers::add_follower( 1, $follower );

		$this->assertTrue( is_wp_error( $result ) );

		$follower2 = 'https://error.example.com';

		$result = Followers::add_follower( 1, $follower2 );

		$this->assertTrue( is_wp_error( $result ) );

		$db_followers = Followers::get_followers( 1 );

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
			Followers::add_follower( 1, $follower );
		}

		foreach ( $followers2 as $follower ) {
			Followers::add_follower( 2, $follower );
		}

		$follower = Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->get_id() );

		$follower = Followers::get_follower( 1, 'http://sally.example.org' );
		$this->assertNull( $follower );

		$follower = Followers::get_follower( 1, 'https://user2.example.com' );
		$this->assertNull( $follower );

		$follower = Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->get_id() );

		$follower2 = Followers::get_follower( 2, 'https://user2.example.com' );
		$this->assertEquals( 'https://user2.example.com', $follower2->get_id() );
		$this->assertEquals( 'úser2', $follower2->get_name() );
	}

	/**
	 * Tests delete_follower.
	 *
	 * @covers ::delete_follower
	 */
	public function test_delete_follower() {
		$followers  = array(
			'https://example.com/author/jon',
			'https://example.org/author/doe',
		);
		$followers2 = array( 'https://user2.example.com' );

		foreach ( $followers as $follower ) {
			Followers::add_follower( 1, $follower );
			Followers::add_follower( 1, $follower );
			Followers::add_follower( 1, $follower );
			Followers::add_follower( 2, $follower );
		}

		foreach ( $followers2 as $follower2 ) {
			Followers::add_follower( 2, $follower2 );
		}

		$follower = Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->get_id() );

		$followers = Followers::get_followers( 1 );
		$this->assertEquals( 2, count( $followers ) );

		$follower2 = Followers::get_follower( 2, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower2->get_id() );

		Followers::remove_follower( 1, 'https://example.com/author/jon' );

		$follower = Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertNull( $follower );

		$follower2 = Followers::get_follower( 2, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower2->get_id() );

		$followers = Followers::get_followers( 1 );
		$this->assertEquals( 1, count( $followers ) );
	}

	/**
	 * Tests get_followers_count.
	 *
	 * @covers ::get_followers_count
	 */
	public function test_get_outdated_followers() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		foreach ( $followers as $follower ) {
			Followers::add_follower( 1, $follower );
		}

		$follower = Followers::get_follower( 1, 'https://example.com/author/jon' );

		global $wpdb;

		// E.g. time one year ago.
		$time              = time() - 804800;
		$mysql_time_format = 'Y-m-d H:i:s';

		$post_modified     = gmdate( $mysql_time_format, $time );
		$post_modified_gmt = gmdate( $mysql_time_format, ( $time + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$post_id           = $follower->get__id();

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE $wpdb->posts SET post_modified = %s, post_modified_gmt = %s WHERE ID = %s",
				array(
					$post_modified,
					$post_modified_gmt,
					$post_id,
				)
			)
		);

		clean_post_cache( $post_id );

		$followers = Followers::get_outdated_followers();
		$this->assertEquals( 1, count( $followers ) );
		$this->assertEquals( 'https://example.com/author/jon', $followers[0] );
	}

	/**
	 * Tests get_faulty_followers.
	 *
	 * @covers ::get_faulty_followers
	 */
	public function test_get_faulty_followers() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		foreach ( $followers as $follower ) {
			Followers::add_follower( 1, $follower );
		}

		$follower = Followers::get_follower( 1, 'http://sally.example.org' );

		for ( $i = 1; $i <= 15; $i++ ) {
			add_post_meta( $follower->get__id(), '_activitypub_errors', 'error ' . $i );
		}

		$follower = Followers::get_follower( 1, 'http://sally.example.org' );
		$follower->count_errors();

		$followers = Followers::get_faulty_followers();

		$this->assertEquals( 1, count( $followers ) );
		$this->assertEquals( 'http://sally.example.org', $followers[0] );

		$follower->reset_errors();

		$follower = Followers::get_follower( 1, 'http://sally.example.org' );
		$follower->count_errors();

		$followers = Followers::get_faulty_followers();

		$this->assertEquals( 0, count( $followers ) );
	}

	/**
	 * Tests add_duplicate_follower.
	 *
	 * @covers ::add_follower
	 */
	public function test_add_duplicate_follower() {
		$follower = 'https://12345.example.com';

		Followers::add_follower( 1, $follower );
		Followers::add_follower( 1, $follower );
		Followers::add_follower( 1, $follower );
		Followers::add_follower( 1, $follower );
		Followers::add_follower( 1, $follower );
		Followers::add_follower( 1, $follower );

		$db_followers = Followers::get_followers( 1 );

		$this->assertStringContainsString( $follower, serialize( $db_followers ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		$follower = current( $db_followers );
		$meta     = get_post_meta( $follower->get__id(), '_activitypub_user_id', false );

		$this->assertCount( 1, $meta );
	}

	/**
	 * Tests scheduling of migration.
	 *
	 * @covers ::maybe_migrate
	 */
	public function test_migration_scheduling() {
		update_option( 'activitypub_db_version', '0.0.1' );

		\Activitypub\Migration::maybe_migrate();

		$schedule = \wp_next_scheduled( 'activitypub_migrate', array( '0.0.1' ) );
		$this->assertNotFalse( $schedule );

		// Clean up.
		delete_option( 'activitypub_db_version' );
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
	 * @covers ::maybe_migrate
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

		add_user_meta( $user_id, 'activitypub_followers', $followers, true );

		\Activitypub\Migration::migrate_from_0_17();

		$db_followers = Followers::get_followers( 1 );
		$this->assertCount( $expected_count, $db_followers );

		if ( $expected_count > 0 ) {
			// Verify each valid follower was migrated correctly.
			$db_follower_ids = array_map(
				function ( $follower ) {
					return $follower->get_id();
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
		delete_user_meta( $user_id, 'activitypub_followers' );
		remove_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'pre_get_remote_metadata_by_actor' ) );
	}

	/**
	 * Tests extract_name_from_uri.
	 *
	 * @dataProvider extract_name_from_uri_content_provider
	 *
	 * @param string $uri  The URI.
	 * @param string $name The name.
	 */
	public function test_extract_name_from_uri( $uri, $name ) {
		$follower = new \Activitypub\Model\Follower();

		$follower->set_id( $uri );

		$this->assertEquals( $name, $follower->get_name() );
	}

	/**
	 * Tests get_inboxes.
	 *
	 * @covers ::get_inboxes
	 */
	public function test_get_inboxes() {
		for ( $i = 0; $i < 30; $i++ ) {
			$meta = array(
				'id'                => 'https://example.org/users/' . $i,
				'url'               => 'https://example.org/users/' . $i,
				'inbox'             => 'https://example.org/users/' . $i . '/inbox',
				'name'              => 'user' . $i,
				'preferredUsername' => 'user' . $i,
				'publicKey'         => 'https://example.org/users/' . $i . '#main-key',
				'publicKeyPem'      => $i,
			);

			$follower = new \Activitypub\Model\Follower();
			$follower->from_array( $meta );

			$id = $follower->upsert();

			add_post_meta( $id, '_activitypub_user_id', 1 );
		}

		$inboxes = Followers::get_inboxes( 1 );

		$this->assertCount( 30, $inboxes );

		wp_cache_delete( sprintf( Followers::CACHE_KEY_INBOXES, 1 ), 'activitypub' );

		for ( $j = 0; $j < 5; $j++ ) {
			$k    = $j + 100;
			$meta = array(
				'id'                => 'https://example.org/users/' . $k,
				'url'               => 'https://example.org/users/' . $k,
				'inbox'             => 'https://example.org/users/' . $j . '/inbox',
				'name'              => 'user' . $k,
				'preferredUsername' => 'user' . $k,
				'publicKey'         => 'https://example.org/users/' . $k . '#main-key',
				'publicKeyPem'      => $k,
			);

			$follower = new \Activitypub\Model\Follower();
			$follower->from_array( $meta );

			$id = $follower->upsert();

			add_post_meta( $id, '_activitypub_user_id', 1 );
		}

		$inboxes2 = Followers::get_inboxes( 1 );

		$this->assertCount( 30, $inboxes2 );
	}

	/**
	 * Tests get_all_followers.
	 *
	 * @covers ::get_all_followers
	 */
	public function test_get_all_followers() {
		for ( $i = 0; $i < 30; $i++ ) {
			$meta = array(
				'id'                => 'https://example.org/users/' . $i,
				'url'               => 'https://example.org/users/' . $i,
				'inbox'             => 'https://example.org/users/' . $i . '/inbox',
				'name'              => 'user' . $i,
				'preferredUsername' => 'user' . $i,
				'publicKey'         => 'https://example.org/users/' . $i . '#main-key',
				'publicKeyPem'      => $i,
			);

			$follower = new \Activitypub\Model\Follower();
			$follower->from_array( $meta );

			$id = $follower->upsert();

			add_post_meta( $id, '_activitypub_user_id', 1 );
		}

		$followers = Followers::get_all_followers();

		$this->assertCount( 30, $followers );
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
