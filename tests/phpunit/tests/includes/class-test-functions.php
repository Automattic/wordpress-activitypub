<?php
/**
 * Test file for Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Outbox;

use function Activitypub\add_to_outbox;
use function Activitypub\extract_recipients_from_activity;
use function Activitypub\extract_recipients_from_activity_property;
use function Activitypub\get_activity_visibility;

/**
 * Test class for Functions.
 */
class Test_Functions extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test',
			)
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		_delete_all_posts();
	}

	/**
	 * Test the get_remote_metadata_by_actor function.
	 *
	 * @covers \Activitypub\get_remote_metadata_by_actor
	 */
	public function test_get_remote_metadata_by_actor() {
		$metadata = \Activitypub\get_remote_metadata_by_actor( 'pfefferle@notiz.blog' );
		$this->assertEquals( 'https://notiz.blog/author/matthias-pfefferle/', $metadata['url'] );
		$this->assertEquals( 'pfefferle', $metadata['preferredUsername'] );
		$this->assertEquals( 'Matthias Pfefferle', $metadata['name'] );
	}

	/**
	 * Test object_id_to_comment.
	 *
	 * @covers \Activitypub\object_id_to_comment
	 */
	public function test_object_id_to_comment_basic() {
		$single_comment_source_id = 'https://example.com/single';
		$content                  = 'example comment that has bunch of text';
		$comment_id               = \wp_new_comment(
			array(
				'comment_post_ID'      => $this->post_id,
				'comment_author'       => 'Example User',
				'comment_author_url'   => 'https://example.com/user',
				'comment_content'      => $content,
				'comment_type'         => '',
				'comment_author_email' => '',
				'comment_parent'       => 0,
				'comment_meta'         => array(
					'source_id'  => $single_comment_source_id,
					'source_url' => 'https://example.com/123',
					'avatar_url' => 'https://example.com/icon',
					'protocol'   => 'activitypub',
				),
			),
			true
		);
		$query_result             = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertInstanceOf( \WP_Comment::class, $query_result );
		$this->assertEquals( $comment_id, $query_result->comment_ID );
		$this->assertEquals( $content, $query_result->comment_content );
	}

	/**
	 * Test object_id_to_comment with invalid source ID.
	 *
	 * @covers \Activitypub\object_id_to_comment
	 */
	public function test_object_id_to_comment_none() {
		$single_comment_source_id = 'https://example.com/none';
		$query_result             = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertFalse( $query_result );
	}

	/**
	 * Test object_id_to_comment with duplicate source ID.
	 *
	 * @covers \Activitypub\object_id_to_comment
	 */
	public function test_object_id_to_comment_duplicate() {
		$duplicate_comment_source_id = 'https://example.com/duplicate';

		add_filter( 'duplicate_comment_id', '__return_zero', 99 );
		add_filter( 'wp_is_comment_flood', '__return_false', 99 );
		for ( $i = 0; $i < 2; ++$i ) {
			\wp_new_comment(
				array(
					'comment_post_ID'      => $this->post_id,
					'comment_author'       => 'Example User',
					'comment_author_url'   => 'https://example.com/user',
					'comment_content'      => 'example comment',
					'comment_type'         => '',
					'comment_author_email' => '',
					'comment_parent'       => 0,
					'comment_meta'         => array(
						'source_id'  => $duplicate_comment_source_id,
						'source_url' => 'https://example.com/123',
						'avatar_url' => 'https://example.com/icon',
						'protocol'   => 'activitypub',
					),
				),
				true
			);
		}
		remove_filter( 'duplicate_comment_id', '__return_zero', 99 );
		remove_filter( 'wp_is_comment_flood', '__return_false', 99 );

		$query_result = \Activitypub\object_id_to_comment( $duplicate_comment_source_id );
		$this->assertInstanceOf( \WP_Comment::class, $query_result );
	}

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
	 * Test is_self_ping.
	 *
	 * @covers \Activitypub\is_self_ping
	 */
	public function test_is_self_ping() {
		$this->assertFalse( \Activitypub\is_self_ping( \home_url() ) );
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.com' ) );
		$this->assertTrue( \Activitypub\is_self_ping( \home_url( '?c=123' ) ) );
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.com/?c=123' ) );
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
	 * Test get comment ancestors.
	 *
	 * @covers \Activitypub\get_comment_ancestors
	 */
	public function test_get_comment_ancestors() {
		$comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'comment',
				'comment_content'      => 'This is a comment.',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$this->assertEquals( array(), \Activitypub\get_comment_ancestors( $comment_id ) );

		$comment_array = get_comment( $comment_id, ARRAY_A );

		$parent_comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'parent comment',
				'comment_content'      => 'This is a parent comment.',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_array['comment_parent'] = $parent_comment_id;

		wp_update_comment( $comment_array );

		$this->assertEquals( array( $parent_comment_id ), \Activitypub\get_comment_ancestors( $comment_id ) );
	}

	/**
	 * Test is_post_disabled function.
	 *
	 * @covers \Activitypub\is_post_disabled
	 */
	public function test_is_post_disabled() {
		// Test standard public post.
		$public_post_id = self::factory()->post->create();
		$this->assertFalse( \Activitypub\is_post_disabled( $public_post_id ) );

		// Test local-only post.
		$local_post_id = self::factory()->post->create();
		add_post_meta( $local_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		$this->assertTrue( \Activitypub\is_post_disabled( $local_post_id ) );

		// Test private post.
		$private_post_id = self::factory()->post->create(
			array(
				'post_status' => 'private',
			)
		);
		$this->assertTrue( \Activitypub\is_post_disabled( $private_post_id ) );

		// Test password protected post.
		$password_post_id = self::factory()->post->create(
			array(
				'post_password' => 'test123',
			)
		);
		$this->assertTrue( \Activitypub\is_post_disabled( $password_post_id ) );

		// Test unsupported post type.
		register_post_type( 'unsupported', array() );
		$unsupported_post_id = self::factory()->post->create(
			array(
				'post_type' => 'unsupported',
			)
		);
		$this->assertTrue( \Activitypub\is_post_disabled( $unsupported_post_id ) );
		unregister_post_type( 'unsupported' );

		// Test with filter.
		add_filter( 'activitypub_is_post_disabled', '__return_true' );
		$this->assertTrue( \Activitypub\is_post_disabled( $public_post_id ) );
		remove_filter( 'activitypub_is_post_disabled', '__return_true' );

		// Clean up.
		wp_delete_post( $public_post_id, true );
		wp_delete_post( $local_post_id, true );
		wp_delete_post( $private_post_id, true );
		wp_delete_post( $password_post_id, true );
	}

	/**
	 * Test is_post_disabled with private visibility.
	 *
	 * @covers \Activitypub\is_post_disabled
	 */
	public function test_is_post_disabled_private_visibility() {
		$visible_private_post_id = self::factory()->post->create();

		add_post_meta( $visible_private_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
		$this->assertTrue( \Activitypub\is_post_disabled( $visible_private_post_id ) );

		wp_delete_post( $visible_private_post_id, true );

		$visible_local_post_id = self::factory()->post->create();

		add_post_meta( $visible_local_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		$this->assertTrue( \Activitypub\is_post_disabled( $visible_local_post_id ) );

		wp_delete_post( $visible_local_post_id, true );
	}

	/**
	 * Test is_post_disabled with invalid post.
	 *
	 * @covers \Activitypub\is_post_disabled
	 */
	public function test_is_post_disabled_invalid_post() {
		$this->assertTrue( \Activitypub\is_post_disabled( 0 ) );
		$this->assertTrue( \Activitypub\is_post_disabled( null ) );
		$this->assertTrue( \Activitypub\is_post_disabled( 999999 ) );
	}

	/**
	 * Test get_masked_wp_version function.
	 *
	 * @covers \Activitypub\get_masked_wp_version
	 * @dataProvider provide_wp_versions
	 *
	 * @param string $input    The input version.
	 * @param string $expected The expected masked version.
	 */
	public function test_get_masked_wp_version( $input, $expected ) {
		global $wp_version;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_version = $input;

		$this->assertEquals(
			$expected,
			\Activitypub\get_masked_wp_version(),
			sprintf( 'Version %s should be masked to %s', $input, $expected )
		);
	}

	/**
	 * Data provider for WordPress versions.
	 *
	 * @return array[] Array of test cases.
	 */
	public function provide_wp_versions() {
		return array(
			'standard version'                   => array(
				'6.4.2',
				'6.4',
			),
			'alpha version'                      => array(
				'6.4.2-alpha',
				'6.4',
			),
			'different alpha version'            => array(
				'6.4-alpha',
				'6.4',
			),
			'alpha version with patch'           => array(
				'6.4.2-alpha-59438',
				'6.4',
			),
			'different alpha version with patch' => array(
				'6.5-alpha-59438',
				'6.5',
			),
			'beta version'                       => array(
				'6.4.2-beta1',
				'6.4',
			),
			'RC version'                         => array(
				'6.4.2-RC1',
				'6.4',
			),
			'no patch version'                   => array(
				'6.4',
				'6.4',
			),
			'triple zero'                        => array(
				'6.0.0',
				'6.0',
			),
			'double digit'                       => array(
				'10.5',
				'10.5',
			),
			'single number'                      => array(
				'6',
				'6',
			),
		);
	}

	/**
	 * Test generate_post_summary function.
	 *
	 * @covers \Activitypub\generate_post_summary
	 * @dataProvider get_post_summary_data
	 *
	 * @param string $desc     The description of the test.
	 * @param array  $post     The post object.
	 * @param string $expected The expected summary.
	 * @param int    $length   The length of the summary.
	 */
	public function test_generate_post_summary( $desc, $post, $expected, $length = 500 ) {
		\add_shortcode(
			'activitypub_test_shortcode',
			function () {
				return 'mighty short code';
			}
		);

		$post_id = \wp_insert_post( $post );

		$this->assertEquals(
			$expected,
			\Activitypub\generate_post_summary( $post_id, $length ),
			$desc
		);

		\wp_delete_post( $post_id, true );
		\remove_shortcode( 'activitypub_test_shortcode' );
	}

	/**
	 * Data provider for test_generate_post_summary.
	 *
	 * @return array[]
	 */
	public function get_post_summary_data() {
		return array(
			array(
				'Excerpt',
				array(
					'post_excerpt' => 'Hello World',
				),
				'Hello World',
			),
			array(
				'Greek Excerpt',
				array(
					'post_excerpt' => 'Τι μπορεί να σου συμβεί σε μια βόλτα για να αγοράσεις μια βαλίτσα για τα ταξίδια σου; Όλα είναι πιθανά αν έχεις ανοιχτές τις "κεραίες" σου!',
				),
				'Τι μπορεί να σου συμβεί σε μια βόλτα για να αγοράσεις μια βαλίτσα για τα ταξίδια σου; Όλα είναι πιθανά αν έχεις ανοιχτές τις "κεραίες" σου!',
			),
			array(
				'Content',
				array(
					'post_content' => 'Hello World',
				),
				'Hello World',
			),
			array(
				'Content with more tag',
				array(
					'post_content' => 'Hello World <!--more--> More',
				),
				'Hello World […]',
			),
			array(
				'Excerpt with shortcode',
				array(
					'post_excerpt' => 'Hello World [activitypub_test_shortcode]',
				),
				'Hello World',
			),
			array(
				'Content with shortcode',
				array(
					'post_content' => 'Hello World [activitypub_test_shortcode]',
				),
				'Hello World',
			),
			array(
				'Excerpt more than limit',
				array(
					'post_excerpt' => 'Hello World Hello World Hello World Hello World Hello World',
				),
				'Hello World Hello World Hello World Hello World Hello World',
				10,
			),
			array(
				'Content more than limit',
				array(
					'post_content' => 'Hello World Hello World Hello World Hello World Hello World',
				),
				'Hello […]',
				10,
			),
			array(
				'Content more than limit with more tag',
				array(
					'post_content' => 'Hello World Hello <!--more--> World Hello World Hello World Hello World',
				),
				'Hello World Hello […]',
				1,
			),
			array(
				'Test HTML content',
				array(
					'post_content' => '<p>Hello World</p>',
				),
				'Hello World',
			),
			array(
				'Test HTML content with anchor',
				array(
					'post_content' => 'Hello <a href="https://example.com">World</a>',
				),
				'Hello World',
			),
			array(
				'Test HTML excerpt',
				array(
					'post_excerpt' => '<p>Hello World</p>',
				),
				'Hello World',
			),
			array(
				'Test HTML excerpt with anchor',
				array(
					'post_excerpt' => 'Hello <a href="https://example.com">World</a>',
				),
				'Hello World',
			),
		);
	}

	/**
	 * Test get_user_id function.
	 *
	 * @covers \Activitypub\get_user_id
	 */
	public function test_get_user_id() {
		$this->assertFalse( \Activitypub\get_user_id( 90210 ) );

		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'activitypub' );

		$this->assertIsString( \Activitypub\get_user_id( $user->ID ) );

		\add_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$this->assertIsString( \Activitypub\get_user_id( $user->ID ) );

		$user->remove_cap( 'activitypub' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		$this->assertIsString( \Activitypub\get_user_id( $user->ID ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		$this->assertFalse( \Activitypub\get_user_id( $user->ID ) );
	}

	/**
	 * Tests follow method.
	 *
	 * @covers \Activitypub\follow
	 */
	public function test_follow() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$actor_array = array(
			'id'                 => 'https://example.com/users/test',
			'type'               => 'Person',
			'name'               => 'Test Follower',
			'preferred_username' => 'Follower',
			'summary'            => '<p>HTML content</p>',
			'endpoints'          => array(
				'sharedInbox' => 'https://example.com/inbox',
			),
		);

		$remote_actor = function () use ( $actor_array ) {
			return $actor_array;
		};

		\add_filter( 'activitypub_pre_http_get_remote_object', $remote_actor );

		\Activitypub\follow( 'https://example.com/users/test', $user_id );

		$outbox_items = \get_posts(
			array(
				'post_type'   => \Activitypub\Collection\Outbox::POST_TYPE,
				'post_status' => 'any',
				'author'      => $user_id,
			)
		);

		$this->assertEquals( 1, count( $outbox_items ) );
		$this->assertEquals( 'Follow', \get_post_meta( $outbox_items[0]->ID, '_activitypub_activity_type', true ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_actor );
	}

	/**
	 * Test that Update activities have the updated attribute set.
	 *
	 * @covers \Activitypub\add_to_outbox
	 */
	public function test_webfinger_support() {
		$follow = new Activity();
		$follow->set_type( 'Follow' );
		$follow->set_actor( 'https://example.com/user/1' );
		$follow->set_object( 'user1@example.com' );
		$follow->set_to( array( 'https://example.com/user/2' ) );

		$filter = function () {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'subject' => 'acct:pfefferle@example.org',
						'aliases' => array( 'https://example.org/?author=1' ),
						'links'   => array(
							array(
								'rel'  => 'self',
								'href' => 'https://example.org/?author=1',
								'type' => 'application/activity+json',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$id = add_to_outbox( $follow, null, 1 );

		$this->assertNotFalse( $id );

		\remove_filter( 'pre_http_request', $filter );

		// Get the activity from the outbox.
		$activity = Outbox::get_activity( $id );
		$this->assertNotInstanceOf( \WP_Error::class, $activity );

		$this->assertEquals( 'Follow', $activity->get_type() );
		$this->assertEquals( 'https://example.org/?author=1', get_post_meta( $id, '_activitypub_object_id', true ) );

		// Delete the Outbox item.
		wp_delete_post( $id );
	}

	/**
	 * Test normalize_url.
	 *
	 * @dataProvider data_normalize_url
	 *
	 * @covers \Activitypub\normalize_url
	 *
	 * @param string $url     The URL.
	 * @param string $expected The expected result.
	 */
	public function test_normalize_url( $url, $expected ) {
		$this->assertEquals( $expected, \Activitypub\normalize_url( $url ) );
	}

	/**
	 * Data provider for test_normalize_url.
	 *
	 * @return array[]
	 */
	public function data_normalize_url() {
		return array(
			array( 'https://example.com', 'example.com' ),
			array( 'http://example.com', 'example.com' ),
			array( 'https://example.com/path', 'example.com/path' ),
			array( 'http://example.com/path', 'example.com/path' ),
			array( 'http://example.com/path/', 'example.com/path' ),
			array( 'https://www.example.com/path/to/nowhere', 'example.com/path/to/nowhere' ),
			array( 'http://www.example.com/path/to/nowhere', 'example.com/path/to/nowhere' ),
		);
	}

	/**
	 * Test normalize_host.
	 *
	 * @dataProvider data_normalize_host
	 *
	 * @covers \Activitypub\normalize_host
	 *
	 * @param string $host     The host.
	 * @param string $expected The expected result.
	 */
	public function test_normalize_host( $host, $expected ) {
		$this->assertEquals( $expected, \Activitypub\normalize_host( $host ) );
	}

	/**
	 * Data provider for test_normalize_host.
	 *
	 * @return array[]
	 */
	public function data_normalize_host() {
		return array(
			array( 'example.com', 'example.com' ),
			array( 'www.example.com', 'example.com' ),
		);
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
			'simple_string_recipient'            => array(
				'data'      => array(
					'to' => 'https://example.com/users/alice',
				),
				'attribute' => 'to',
				'expected'  => array( 'https://example.com/users/alice' ),
			),
			'array_of_recipients'                => array(
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
			'object_recipients_with_id'          => array(
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
			'mixed_recipients'                   => array(
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
			'recipients_in_object'               => array(
				'data'      => array(
					'object' => array(
						'to' => 'https://example.com/users/grace',
					),
				),
				'attribute' => 'to',
				'expected'  => array( 'https://example.com/users/grace' ),
			),
			'recipients_in_both_main_and_object' => array(
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
			'duplicate_recipients'               => array(
				'data'      => array(
					'to' => array(
						'https://example.com/users/jack',
						'https://example.com/users/jack', // Duplicate.
					),
				),
				'attribute' => 'to',
				'expected'  => array( 'https://example.com/users/jack' ), // Should be unique.
			),
			'no_recipients'                      => array(
				'data'      => array(
					'cc' => array(),
				),
				'attribute' => 'to', // Different attribute.
				'expected'  => array(),
			),
			'empty_data'                         => array(
				'data'      => array(),
				'attribute' => 'to',
				'expected'  => array(),
			),
			'object_with_id'                     => array(
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
			'public_recipients'                  => array(
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
			'audience_attribute'                 => array(
				'data'      => array(
					'audience' => 'https://example.com/groups/followers',
				),
				'attribute' => 'audience',
				'expected'  => array( 'https://example.com/groups/followers' ),
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
		$result = \Activitypub\get_activity_visibility( $activity );
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

		$result = \Activitypub\get_activity_visibility( $activity );
		$this->assertSame( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, $result, 'Should work with minimal activity data' );
	}

	/**
	 * Data provider for camel to snake case and snake to camel case tests.
	 *
	 * @return array
	 */
	public function camel_snake_case_provider() {
		return array(
			'SimpleCamelCase'    => array( 'SimpleCamelCase', 'simple_camel_case' ),
			'camelCase'          => array( 'camelCase', 'camel_case' ),
			'XMLHttpRequest'     => array( 'XMLHttpRequest', 'x_m_l_http_request' ),
			'already_snake_case' => array( 'already_snake_case', 'already_snake_case' ),
			'with_numbers123'    => array( 'withNumbers123', 'with_numbers123' ),
			'leadingUpperCase'   => array( 'LeadingUpperCase', 'leading_upper_case' ),
			'singleletter'       => array( 'a', 'a' ),
			'emptyString'        => array( '', '' ),
			'nonStringInput'     => array( 12345, '12345' ),
			'CreateActivity'     => array( 'CreateActivity', 'create_activity' ),
			'Follow'             => array( 'Follow', 'follow' ),
			'QuoteRequest'       => array( 'QuoteRequest', 'quote_request' ),
		);
	}

	/**
	 * Test camel_to_snake_case function.
	 *
	 * @dataProvider camel_snake_case_provider
	 *
	 * @param string $original The original string.
	 * @param string $expected The expected result.
	 */
	public function test_camel_to_snake_case( $original, $expected ) {
		$this->assertSame( $expected, \Activitypub\camel_to_snake_case( $original ) );
	}

	/**
	 * Data provider for esc_hashtag tests.
	 *
	 * @return array Test cases with input and expected output.
	 */
	public function esc_hashtag_provider() {
		return array(
			'simple_word'             => array( 'test', '#test' ),
			'word_with_spaces'        => array( 'test tag', '#testTag' ),
			'multiple_spaces'         => array( 'test  multiple   spaces', '#testMultipleSpaces' ),
			'with_special_chars'      => array( 'test@tag!', '#testTag' ),
			'with_underscores'        => array( 'test_tag', '#testTag' ),
			'with_leading_hashtag'    => array( '#test', '#Test' ),
			'with_multiple_hashtags'  => array( '##test', '#Test' ),
			'with_leading_hyphen'     => array( '-test', '#Test' ),
			'with_trailing_hyphen'    => array( 'test-', '#test' ),
			'mixed_case'              => array( 'TestTag', '#TestTag' ),
			'with_numbers'            => array( 'test123', '#test123' ),
			'with_unicode'            => array( 'tëst', '#tëst' ),
			'with_unicode_spaces'     => array( 'tëst tàg', '#tëstTàg' ),
			'german_umlauts'          => array( 'über straße', '#überStraße' ),
			'japanese_characters'     => array( 'テスト', '#テスト' ),
			'arabic_characters'       => array( 'اختبار', '#اختبار' ),
			'cyrillic_characters'     => array( 'тест', '#тест' ),
			'empty_string'            => array( '', '#' ),
			'only_spaces'             => array( '   ', '#' ),
			'only_special_chars'      => array( '@!#$%', '#' ),
			'hyphenated_words'        => array( 'foo-bar-baz', '#fooBarBaz' ),
			'quotes'                  => array( "test'tag", '#testTag' ),
			'double_quotes'           => array( 'test"tag', '#testTag' ),
			'ampersand'               => array( 'test&tag', '#testTag' ),
			'html_entities'           => array( 'test&amp;tag', '#testTag' ),
			'leading_trailing_spaces' => array( '  test  ', '#Test' ),
			'multiple_hyphens'        => array( 'test--tag', '#testTag' ),
			'camelCase_preservation'  => array( 'testTag', '#testTag' ),
			'with_dots'               => array( 'test.tag', '#testTag' ),
			'with_commas'             => array( 'test,tag', '#testTag' ),
			'with_semicolons'         => array( 'test;tag', '#testTag' ),
			'with_slashes'            => array( 'test/tag', '#testTag' ),
			'with_backslashes'        => array( 'test\\tag', '#testTag' ),
			'with_parentheses'        => array( 'test(tag)', '#testTag' ),
			'with_brackets'           => array( 'test[tag]', '#testTag' ),
			'with_braces'             => array( 'test{tag}', '#testTag' ),
			'emoji_mixed'             => array( 'test 😀 tag', '#testTag' ),
			'chinese_characters'      => array( '测试 标签', '#测试标签' ),
			'korean_characters'       => array( '테스트 태그', '#테스트태그' ),
			'greek_characters'        => array( 'δοκιμή', '#δοκιμή' ),
			'hebrew_characters'       => array( 'בדיקה', '#בדיקה' ),
			'thai_characters'         => array( 'ทดสอบ', '#ทดสอบ' ),
		);
	}

	/**
	 * Test esc_hashtag function.
	 *
	 * @dataProvider esc_hashtag_provider
	 * @covers \Activitypub\esc_hashtag
	 *
	 * @param string $input    The input string.
	 * @param string $expected The expected hashtag output.
	 */
	public function test_esc_hashtag( $input, $expected ) {
		$result = \Activitypub\esc_hashtag( $input );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Test esc_hashtag filter hook.
	 *
	 * @covers \Activitypub\esc_hashtag
	 */
	public function test_esc_hashtag_filter() {
		$filter_callback = function ( $hashtag, $input ) {
			if ( 'custom' === $input ) {
				return '#CustomTag';
			}
			return $hashtag;
		};

		\add_filter( 'activitypub_esc_hashtag', $filter_callback, 10, 2 );

		$result = \Activitypub\esc_hashtag( 'custom' );
		$this->assertSame( '#CustomTag', $result );

		\remove_filter( 'activitypub_esc_hashtag', $filter_callback, 10 );
	}

	/**
	 * Test esc_hashtag with HTML special characters.
	 *
	 * @covers \Activitypub\esc_hashtag
	 */
	public function test_esc_hashtag_html_escaping() {
		$result = \Activitypub\esc_hashtag( '<script>alert("xss")</script>' );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( 'alert', $result );
		// The result should be HTML-escaped.
		$this->assertStringStartsWith( '#', $result );
	}

	/**
	 * Test esc_hashtag with quoted strings.
	 *
	 * @covers \Activitypub\esc_hashtag
	 */
	public function test_esc_hashtag_with_quotes() {
		// Test single quotes.
		$result = \Activitypub\esc_hashtag( "test's tag" );
		$this->assertSame( '#testSTag', $result );

		// Test double quotes.
		$result = \Activitypub\esc_hashtag( 'test"s tag' );
		$this->assertSame( '#testSTag', $result );

		// Test HTML entities for quotes.
		$result = \Activitypub\esc_hashtag( 'test&#039;s tag' );
		$this->assertSame( '#testSTag', $result );
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
	 * Test is_ap_post function with ap_post post type.
	 *
	 * @covers \Activitypub\is_ap_post
	 */
	public function test_is_ap_post_with_ap_post_type() {
		$ap_post_id = wp_insert_post(
			array(
				'post_type'    => 'ap_post',
				'post_title'   => 'Test AP Post',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);

		$this->assertTrue( \Activitypub\is_ap_post( $ap_post_id ), 'Should return true for ap_post post type' );
		$this->assertTrue( \Activitypub\is_ap_post( get_post( $ap_post_id ) ), 'Should return true when passed WP_Post object' );

		wp_delete_post( $ap_post_id, true );
	}

	/**
	 * Test is_ap_post function with regular post type.
	 *
	 * @covers \Activitypub\is_ap_post
	 */
	public function test_is_ap_post_with_regular_post() {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Test Regular Post',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);

		$this->assertFalse( \Activitypub\is_ap_post( $post_id ), 'Should return false for regular post' );
		$this->assertFalse( \Activitypub\is_ap_post( get_post( $post_id ) ), 'Should return false when passed WP_Post object' );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test is_ap_post function with invalid post.
	 *
	 * @covers \Activitypub\is_ap_post
	 */
	public function test_is_ap_post_with_invalid_post() {
		$this->assertFalse( \Activitypub\is_ap_post( 999999 ), 'Should return false for non-existent post ID' );
		$this->assertFalse( \Activitypub\is_ap_post( null ), 'Should return false for null' );
		$this->assertFalse( \Activitypub\is_ap_post( false ), 'Should return false for false' );
	}

	/**
	 * Test is_ap_post function with different post types.
	 *
	 * @covers \Activitypub\is_ap_post
	 */
	public function test_is_ap_post_with_various_post_types() {
		// Test with page.
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Test Page',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);
		$this->assertFalse( \Activitypub\is_ap_post( $page_id ), 'Should return false for page post type' );

		// Test with custom post type.
		register_post_type( 'custom_test_type' );
		$custom_id = wp_insert_post(
			array(
				'post_type'    => 'custom_test_type',
				'post_title'   => 'Test Custom',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);
		$this->assertFalse( \Activitypub\is_ap_post( $custom_id ), 'Should return false for custom post type' );

		wp_delete_post( $page_id, true );
		wp_delete_post( $custom_id, true );
	}
}
