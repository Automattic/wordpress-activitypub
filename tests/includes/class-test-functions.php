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
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.org' ) );
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.com' ) );
		$this->assertTrue( \Activitypub\is_self_ping( 'https://example.org/?c=123' ) );
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
	 * @param object $post     The post object.
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
				'<p>Hello World</p>' . PHP_EOL,
			),
			array(
				'Greek Excerpt',
				array(
					'post_excerpt' => 'Τι μπορεί να σου συμβεί σε μια βόλτα για να αγοράσεις μια βαλίτσα για τα ταξίδια σου; Όλα είναι πιθανά αν έχεις ανοιχτές τις "κεραίες" σου!',
				),
				'<p>Τι μπορεί να σου συμβεί σε μια βόλτα για να αγοράσεις μια βαλίτσα για τα ταξίδια σου; Όλα είναι πιθανά αν έχεις ανοιχτές τις &#8220;κεραίες&#8221; σου!</p>' . PHP_EOL,
			),
			array(
				'Content',
				array(
					'post_content' => 'Hello World',
				),
				'<p>Hello World</p>' . PHP_EOL,
			),
			array(
				'Content with more tag',
				array(
					'post_content' => 'Hello World <!--more--> More',
				),
				'<p>Hello World […]</p>' . PHP_EOL,
			),
			array(
				'Excerpt with shortcode',
				array(
					'post_excerpt' => 'Hello World [activitypub_test_shortcode]',
				),
				'<p>Hello World</p>' . PHP_EOL,
			),
			array(
				'Content with shortcode',
				array(
					'post_content' => 'Hello World [activitypub_test_shortcode]',
				),
				'<p>Hello World</p>' . PHP_EOL,
			),
			array(
				'Excerpt more than limit',
				array(
					'post_excerpt' => 'Hello World Hello World Hello World Hello World Hello World',
				),
				'<p>Hello World Hello World Hello World Hello World Hello World</p>' . PHP_EOL,
				10,
			),
			array(
				'Content more than limit',
				array(
					'post_content' => 'Hello World Hello World Hello World Hello World Hello World',
				),
				'<p>Hello […]</p>' . PHP_EOL,
				10,
			),
			array(
				'Content more than limit with more tag',
				array(
					'post_content' => 'Hello World Hello <!--more--> World Hello World Hello World Hello World',
				),
				'<p>Hello World Hello […]</p>' . PHP_EOL,
				1,
			),
			array(
				'Test HTML content',
				array(
					'post_content' => '<p>Hello World</p>',
				),
				'<p>Hello World</p>' . PHP_EOL,
			),
			array(
				'Test HTML content with anchor',
				array(
					'post_content' => 'Hello <a href="https://example.com">World</a>',
				),
				'<p>Hello World</p>' . PHP_EOL,
			),
			array(
				'Test HTML excerpt',
				array(
					'post_excerpt' => '<p>Hello World</p>',
				),
				'<p>Hello World</p>' . PHP_EOL,
			),
			array(
				'Test HTML excerpt with anchor',
				array(
					'post_excerpt' => 'Hello <a href="https://example.com">World</a>',
				),
				'<p>Hello World</p>' . PHP_EOL,
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
	}

	/**
	 * Tests follow method.
	 *
	 * @covers ::follow
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
			)
		);

		$this->assertEquals( 1, count( $outbox_items ) );
		$this->assertEquals( 'Follow', \get_post_meta( $outbox_items[0]->ID, '_activitypub_activity_type', true ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_actor );
	}

	/**
	 * Test that Update activities have the updated attribute set.
	 *
	 * @covers ::add_to_outbox
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
}
