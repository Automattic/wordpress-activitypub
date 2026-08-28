<?php
/**
 * Test file for Jetpack integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Integration\Jetpack;

/**
 * Test class for Jetpack integration.
 *
 * @coversDefaultClass \Activitypub\Integration\Jetpack
 */
class Test_Jetpack extends \WP_UnitTestCase {
	/**
	 * Cover art the mocked podcast show returns.
	 *
	 * @var string
	 */
	const SHOW_IMAGE_URL = 'https://example.com/show.jpg';

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_content' => 'Test post content',
				'post_title'   => 'Test Post',
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Load mock Manager class for specific tests.
	 */
	private function load_mock_manager() {
		if ( ! class_exists( '\Automattic\Jetpack\Connection\Manager' ) ) {
			require_once AP_TESTS_DIR . '/data/mocks/class-manager.php';
		}
	}

	/**
	 * Load the stand-ins for the Jetpack podcast package.
	 *
	 * Loaded together so a test that only configures one of them still leaves the others in a
	 * known state, and tear_down can reset all three behind a single guard.
	 */
	private static function load_podcast_mocks() {
		require_once AP_TESTS_DIR . '/data/mocks/class-episode-block-tags.php';
		require_once AP_TESTS_DIR . '/data/mocks/class-customize-feed.php';
		require_once AP_TESTS_DIR . '/data/mocks/class-settings.php';
	}

	/**
	 * Set the block attributes the mocked episode block returns.
	 *
	 * @param array $attrs The block attributes.
	 */
	private function load_mock_episode_block_tags( $attrs = array() ) {
		self::load_podcast_mocks();

		\Automattic\Jetpack\Podcast\Feed\Episode_Block_Tags::$attrs = $attrs;
	}

	/**
	 * Configure the mocked podcast show and put the test post in its category.
	 *
	 * @param bool $in_category Optional. Whether the post is in the podcast category. Default true.
	 */
	private function load_mock_podcast_show( $in_category = true ) {
		self::load_podcast_mocks();

		$category_id = self::factory()->category->create( array( 'name' => 'Podcast' ) );

		\Automattic\Jetpack\Podcast\Feed\Customize_Feed::$category_id = $category_id;
		\Automattic\Jetpack\Podcast\Settings::$show_image_url         = self::SHOW_IMAGE_URL;

		if ( $in_category ) {
			\wp_set_post_categories( self::$post_id, array( $category_id ), true );
		}
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down() {
		// Remove any filters that may have been added during tests.
		\remove_filter( 'jetpack_sync_post_meta_whitelist', array( 'Activitypub\Integration\Jetpack', 'add_sync_meta' ) );
		\remove_filter( 'jetpack_sync_comment_meta_whitelist', array( 'Activitypub\Integration\Jetpack', 'add_sync_comment_meta' ) );
		\remove_filter( 'jetpack_sync_whitelisted_comment_types', array( 'Activitypub\Integration\Jetpack', 'add_comment_types' ) );
		\remove_filter( 'jetpack_json_api_comment_types', array( 'Activitypub\Integration\Jetpack', 'add_comment_types' ) );
		\remove_filter( 'jetpack_api_include_comment_types_count', array( 'Activitypub\Integration\Jetpack', 'add_comment_types' ) );
		\remove_filter( 'activitypub_following_row_actions', array( 'Activitypub\Integration\Jetpack', 'add_reader_link' ), 20 );
		\remove_filter( 'pre_option_activitypub_following_ui', array( 'Activitypub\Integration\Jetpack', 'pre_option_activitypub_following_ui' ) );
		\remove_filter( 'activitypub_attachments', array( 'Activitypub\Integration\Jetpack', 'add_podcast_attachments' ), 10 );

		// Clear the podcast mocks so they cannot leak an attachment into other tests through the filter.
		if ( class_exists( '\Automattic\Jetpack\Podcast\Feed\Episode_Block_Tags' ) ) {
			\Automattic\Jetpack\Podcast\Feed\Episode_Block_Tags::$attrs   = array();
			\Automattic\Jetpack\Podcast\Feed\Customize_Feed::$category_id = 0;
			\Automattic\Jetpack\Podcast\Settings::$show_image_url         = '';
		}

		parent::tear_down();
	}

	/**
	 * Test init method registers sync hooks without Manager class.
	 *
	 * This test must run before the Manager class is loaded to test the behavior
	 * when the class doesn't exist.
	 *
	 * @covers ::init
	 */
	public function test_a_init_registers_sync_hooks_without_manager() {
		// Verify Manager class is not yet loaded.
		$this->assertFalse( class_exists( '\Automattic\Jetpack\Connection\Manager' ), 'Manager class should not exist yet' );

		// Ensure Jetpack-specific hooks are not already registered.
		$this->assertFalse( has_filter( 'jetpack_sync_post_meta_whitelist' ) );
		$this->assertFalse( has_filter( 'activitypub_following_row_actions', array( 'Activitypub\Integration\Jetpack', 'add_reader_link' ) ) );
		$this->assertFalse( has_filter( 'pre_option_activitypub_following_ui', array( 'Activitypub\Integration\Jetpack', 'pre_option_activitypub_following_ui' ) ) );

		// Initialize Jetpack integration without Manager class loaded.
		Jetpack::init();

		// Check that sync hooks are registered regardless of Manager class.
		$this->assertTrue( has_filter( 'jetpack_sync_post_meta_whitelist' ) );
		$this->assertTrue( has_filter( 'jetpack_sync_comment_meta_whitelist' ) );
		$this->assertTrue( has_filter( 'jetpack_sync_whitelisted_comment_types' ) );
		$this->assertTrue( has_filter( 'jetpack_json_api_comment_types' ) );
		$this->assertTrue( has_filter( 'jetpack_api_include_comment_types_count' ) );

		// Following UI hooks should NOT be registered without Manager class.
		$this->assertFalse( has_filter( 'activitypub_following_row_actions', array( 'Activitypub\Integration\Jetpack', 'add_reader_link' ) ) );
		$this->assertFalse( has_filter( 'pre_option_activitypub_following_ui', array( 'Activitypub\Integration\Jetpack', 'pre_option_activitypub_following_ui' ) ) );
	}

	/**
	 * Test init method registers all hooks with Manager class available.
	 *
	 * @covers ::init
	 */
	public function test_b_init_registers_hooks_with_manager() {
		// Load mock Manager class.
		$this->load_mock_manager();

		// Ensure Jetpack-specific hooks are not already registered.
		$this->assertFalse( has_filter( 'jetpack_sync_post_meta_whitelist' ) );
		$this->assertFalse( has_filter( 'activitypub_following_row_actions', array( 'Activitypub\Integration\Jetpack', 'add_reader_link' ) ) );
		$this->assertFalse( has_filter( 'pre_option_activitypub_following_ui', array( 'Activitypub\Integration\Jetpack', 'pre_option_activitypub_following_ui' ) ) );

		// Initialize Jetpack integration with Manager class.
		Jetpack::init();

		// Check that sync hooks are registered.
		$this->assertTrue( has_filter( 'jetpack_sync_post_meta_whitelist' ) );
		$this->assertTrue( has_filter( 'jetpack_sync_comment_meta_whitelist' ) );
		$this->assertTrue( has_filter( 'jetpack_sync_whitelisted_comment_types' ) );
		$this->assertTrue( has_filter( 'jetpack_json_api_comment_types' ) );
		$this->assertTrue( has_filter( 'jetpack_api_include_comment_types_count' ) );

		// Following UI hooks should also be registered (mock Manager returns connected).
		// has_filter() returns the priority (int) when callback is found, false otherwise.
		$this->assertNotFalse( has_filter( 'activitypub_following_row_actions', array( 'Activitypub\Integration\Jetpack', 'add_reader_link' ) ) );
		$this->assertNotFalse( has_filter( 'pre_option_activitypub_following_ui', array( 'Activitypub\Integration\Jetpack', 'pre_option_activitypub_following_ui' ) ) );
	}

	/**
	 * Test that Manager class connection check works when available.
	 *
	 * @covers ::init
	 */
	public function test_c_manager_connection_check() {
		// Load mock Manager class.
		$this->load_mock_manager();

		// Test that our mock Manager class exists and works.
		$this->assertTrue( class_exists( '\Automattic\Jetpack\Connection\Manager' ), 'Mock Manager class should exist' );

		$manager = new \Automattic\Jetpack\Connection\Manager();
		$this->assertTrue( $manager->is_user_connected(), 'Mock Manager should return connected' );
	}

	/**
	 * Test add_sync_meta method adds ActivityPub meta keys.
	 *
	 * @covers ::add_sync_meta
	 */
	public function test_add_sync_meta() {
		$original_list = array( 'existing_meta_key' );

		$updated_list = Jetpack::add_sync_meta( $original_list );

		// Check that original keys are preserved.
		$this->assertContains( 'existing_meta_key', $updated_list );

		// Check that ActivityPub meta keys are added.
		$this->assertContains( Followers::FOLLOWER_META_KEY, $updated_list );
		$this->assertContains( Following::FOLLOWING_META_KEY, $updated_list );
	}

	/**
	 * Test add_sync_comment_meta method adds ActivityPub comment meta keys.
	 *
	 * @covers ::add_sync_comment_meta
	 */
	public function test_add_sync_comment_meta() {
		$original_list = array( 'existing_comment_meta' );

		$updated_list = Jetpack::add_sync_comment_meta( $original_list );

		// Check that original keys are preserved.
		$this->assertContains( 'existing_comment_meta', $updated_list );

		// Check that ActivityPub comment meta keys are added.
		$this->assertContains( 'avatar_url', $updated_list );
	}

	/**
	 * Test add_comment_types method adds ActivityPub comment types.
	 *
	 * @covers ::add_comment_types
	 */
	public function test_add_comment_types() {
		$original_types = array( 'comment', 'pingback', 'trackback' );

		$updated_types = Jetpack::add_comment_types( $original_types );

		// Check that original types are preserved.
		$this->assertContains( 'comment', $updated_types );
		$this->assertContains( 'pingback', $updated_types );
		$this->assertContains( 'trackback', $updated_types );

		// Check that ActivityPub comment types are added.
		$expected_ap_types = \get_comment_types( array( 'reaction' => true ), 'names' );
		foreach ( $expected_ap_types as $type ) {
			$this->assertContains( $type, $updated_types );
		}

		// Check that duplicates are removed.
		$this->assertEquals( $updated_types, array_unique( $updated_types ) );
	}

	/**
	 * Test pre_option_activitypub_following_ui method forces UI to be enabled.
	 *
	 * @covers ::pre_option_activitypub_following_ui
	 */
	public function test_pre_option_activitypub_following_ui() {
		$result = Jetpack::pre_option_activitypub_following_ui();

		$this->assertEquals( '1', $result );
	}

	/**
	 * Test integration with actual WordPress filters.
	 */
	public function test_filter_integration() {
		// Initialize Jetpack integration.
		Jetpack::init();

		// Test sync meta filter integration (only if not on WordPress.com).
		if ( ! defined( 'IS_WPCOM' ) ) {
			$sync_meta = apply_filters( 'jetpack_sync_post_meta_whitelist', array() );
			$this->assertContains( Followers::FOLLOWER_META_KEY, $sync_meta );
			$this->assertContains( Following::FOLLOWING_META_KEY, $sync_meta );

			// Test comment meta filter integration.
			$comment_meta = apply_filters( 'jetpack_sync_comment_meta_whitelist', array() );
			$this->assertContains( 'avatar_url', $comment_meta );

			// Test comment types filter integration.
			$comment_types     = apply_filters( 'jetpack_sync_whitelisted_comment_types', array() );
			$expected_ap_types = \get_comment_types( array( 'reaction' => true ), 'names' );
			foreach ( $expected_ap_types as $type ) {
				$this->assertContains( $type, $comment_types );
			}
		} else {
			// On WordPress.com, sync filters should not be registered.
			// Test that they are indeed not registered.
			$sync_meta = apply_filters( 'jetpack_sync_post_meta_whitelist', array() );
			$this->assertNotContains( Followers::FOLLOWER_META_KEY, $sync_meta );
			$this->assertNotContains( Following::FOLLOWING_META_KEY, $sync_meta );

			$comment_meta = apply_filters( 'jetpack_sync_comment_meta_whitelist', array() );
			$this->assertNotContains( 'avatar_url', $comment_meta );
		}

		// Test following UI filter integration - test direct method calls.
		$ui_result = Jetpack::pre_option_activitypub_following_ui();
		$this->assertEquals( '1', $ui_result );

		// Test reader link method directly.
		$test_item        = array(
			'id'         => 123,
			'status'     => 'active',
			'identifier' => 'https://example.com/feed',
		);
		$original_actions = array( 'edit' => '<a href="#">Edit</a>' );
		$updated_actions  = Jetpack::add_reader_link( $original_actions, $test_item );
		$this->assertArrayHasKey( 'reader', $updated_actions );
	}

	/**
	 * Data provider for Reader link test scenarios.
	 *
	 * @return array Test cases with different following item configurations.
	 */
	public function reader_link_data() {
		return array(
			'active following without feed ID' => array(
				'item'                    => array(
					'id'         => 123,
					'status'     => 'active',
					'identifier' => 'https://example.com/feed',
				),
				'feed_id'                 => false,
				'expected_url'            => 'https://wordpress.com/reader/feeds/lookup/https%3A%2F%2Fexample.com%2Ffeed',
				'should_have_reader_link' => true,
			),
			'active following with feed ID'    => array(
				'item'                    => array(
					'id'         => 123,
					'status'     => 'active',
					'identifier' => 'https://example.com/feed',
				),
				'feed_id'                 => 456,
				'expected_url'            => 'https://wordpress.com/reader/feeds/456',
				'should_have_reader_link' => true,
			),
			'pending following should not have reader link' => array(
				'item'                    => array(
					'id'         => 123,
					'status'     => 'pending',
					'identifier' => 'https://example.com/feed',
				),
				'feed_id'                 => 456,
				'expected_url'            => null,
				'should_have_reader_link' => false,
			),
		);
	}

	/**
	 * Test add_reader_link method adds correct Reader links.
	 *
	 * @dataProvider reader_link_data
	 * @covers ::add_reader_link
	 *
	 * @param array       $item                    The following item.
	 * @param int|false   $feed_id                 The feed ID or false.
	 * @param string|null $expected_url            Expected URL or null.
	 * @param bool        $should_have_reader_link Whether reader link should be added.
	 */
	public function test_add_reader_link( $item, $feed_id, $expected_url, $should_have_reader_link ) {
		$original_actions = array( 'edit' => '<a href="#">Edit</a>' );

		// Set up WPCOM environment if expecting WPCOM-style URL.
		$is_wpcom_test = $expected_url && strpos( $expected_url, '/reader/feeds/lookup/' ) === false;
		if ( $is_wpcom_test && ! defined( 'IS_WPCOM' ) ) {
			define( 'IS_WPCOM', true );
		}

		// Mock the feed ID meta if provided.
		$metadata_filter = null;
		if ( false !== $feed_id ) {
			$metadata_filter = function ( $value, $object_id, $meta_key ) use ( $item, $feed_id ) {
				if ( $object_id === $item['id'] && '_activitypub_actor_feed' === $meta_key ) {
					// Return as array of values (WordPress expects this format).
					return array( array( 'feed_id' => $feed_id ) );
				}
				return $value;
			};
			add_filter( 'get_post_metadata', $metadata_filter, 10, 3 );
		}

		$updated_actions = Jetpack::add_reader_link( $original_actions, $item );

		// Check that original actions are preserved.
		$this->assertArrayHasKey( 'edit', $updated_actions );

		if ( $should_have_reader_link ) {
			// Check that reader link is added.
			$this->assertArrayHasKey( 'reader', $updated_actions );
			$this->assertStringContainsString( $expected_url, $updated_actions['reader'] );
			$this->assertStringContainsString( 'View Feed', $updated_actions['reader'] );
			$this->assertStringContainsString( 'target="_blank"', $updated_actions['reader'] );
		} else {
			// Check that reader link is not added for pending items.
			$this->assertArrayNotHasKey( 'reader', $updated_actions );
		}

		// Clean up filters.
		if ( null !== $metadata_filter ) {
			\remove_filter( 'get_post_metadata', $metadata_filter );
		}
	}

	/**
	 * Transform a post and return the attachments the transformer produced.
	 *
	 * Driving the real transformer is the point: the filter runs on an already-assembled,
	 * already-capped list, and hand-built fixtures hide whether the code matches what actually
	 * arrives there.
	 *
	 * @param int $post_id The post to transform.
	 *
	 * @return array The attachments.
	 */
	private function transform_attachments( $post_id ) {
		\clean_post_cache( $post_id );

		$transformer = \Activitypub\Transformer\Factory::get_transformer( \get_post( $post_id ) );

		return $transformer->to_object()->get_attachment();
	}

	/**
	 * Attach an enclosure to a post, the way WordPress records one.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $url     The media URL.
	 */
	private function add_enclosure( $post_id, $url ) {
		\add_post_meta( $post_id, 'enclosure', $url . "\n1234\naudio/mpeg\n" );
	}

	/**
	 * The filter is registered so the transformer passes it the post.
	 *
	 * Without the second argument the callback receives null and cannot resolve an episode at all,
	 * which no assertion on the callback itself would catch.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_the_attachment_filter() {
		Jetpack::init();

		$this->assertSame( 10, \has_filter( 'activitypub_attachments', array( Jetpack::class, 'add_podcast_attachments' ) ) );
	}

	/**
	 * A Posts to Podcast episode federates the audio from its block.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_episode_block_audio_is_federated() {
		Jetpack::init();
		$this->load_mock_episode_block_tags(
			array(
				'mediaUrl'      => 'https://example.com/episode.mp3',
				'mediaType'     => 'audio',
				'mediaMimeType' => 'audio/mpeg',
				'coverArt'      => array( 'url' => 'https://example.com/cover.jpg' ),
			)
		);

		$attachments = $this->transform_attachments( self::$post_id );

		$this->assertSame( 'https://example.com/episode.mp3', $attachments[0]['url'] );
		$this->assertSame( 'Audio', $attachments[0]['type'] );
		$this->assertSame( 'audio/mpeg', $attachments[0]['mediaType'] );
		$this->assertSame( 'https://example.com/cover.jpg', $attachments[0]['icon'] );
	}

	/**
	 * An episode with no mime type omits the property rather than sending an empty one.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_episode_without_mime_type_omits_media_type() {
		Jetpack::init();
		$this->load_mock_episode_block_tags( array( 'mediaUrl' => 'https://example.com/episode.mp3' ) );

		$attachments = $this->transform_attachments( self::$post_id );

		$this->assertArrayNotHasKey( 'mediaType', $attachments[0] );
	}

	/**
	 * A media URL that sanitizes to empty (an unsafe scheme) adds no attachment.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_unsafe_media_url_is_rejected() {
		Jetpack::init();
		$this->load_mock_episode_block_tags( array( 'mediaUrl' => 'javascript:alert(1)' ) );

		$this->assertSame( array(), $this->transform_attachments( self::$post_id ) );
	}

	/**
	 * The same audio is federated once even when the enclosure and the block disagree on the scheme.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_audio_is_not_duplicated_across_schemes() {
		Jetpack::init();

		/*
		 * The audio has to be in the media library, otherwise the transformer drops it before the
		 * filter and there is nothing to deduplicate against. The block then points at the same
		 * file over https, as it does on every site that moved to https after publishing.
		 */
		$audio_id      = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/sample-audio.mp3' );
		$enclosure_url = \wp_get_attachment_url( $audio_id );

		$this->add_enclosure( self::$post_id, $enclosure_url );
		$this->load_mock_episode_block_tags( array( 'mediaUrl' => \str_replace( 'http://', 'https://', $enclosure_url ) ) );

		$attachments = $this->transform_attachments( self::$post_id );

		$this->assertCount( 1, $attachments, 'The same audio must not be attached twice.' );

		\wp_delete_attachment( $audio_id, true );
	}

	/**
	 * Adding the episode audio does not push the post over its attachment limit.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_episode_audio_respects_the_attachment_limit() {
		Jetpack::init();
		$this->load_mock_episode_block_tags( array( 'mediaUrl' => 'https://example.com/episode.mp3' ) );

		$thumbnail_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		\set_post_thumbnail( self::$post_id, $thumbnail_id );
		\update_post_meta( self::$post_id, 'activitypub_max_image_attachments', 1 );

		$attachments = $this->transform_attachments( self::$post_id );

		$this->assertCount( 1, $attachments );
		$this->assertSame( 'https://example.com/episode.mp3', $attachments[0]['url'] );

		\delete_post_meta( self::$post_id, 'activitypub_max_image_attachments' );
		\delete_post_thumbnail( self::$post_id );
		\wp_delete_attachment( $thumbnail_id, true );
	}

	/**
	 * A Jetpack Podcast episode hosted off-site federates its audio.
	 *
	 * An external enclosure has no attachment ID, so the transformer drops it before the filter
	 * runs; the integration has to add it back or the episode federates with no audio at all.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_external_enclosure_episode_is_federated() {
		Jetpack::init();
		$this->load_mock_podcast_show();
		$episode_url = \home_url( '/podcast/episode.mp3' );
		$this->add_enclosure( self::$post_id, $episode_url );

		$attachments = $this->transform_attachments( self::$post_id );

		$this->assertCount( 1, $attachments );
		$this->assertSame( $episode_url, $attachments[0]['url'] );
		$this->assertSame( 'Audio', $attachments[0]['type'] );
		$this->assertSame( 'audio/mpeg', $attachments[0]['mediaType'] );
		$this->assertSame( self::SHOW_IMAGE_URL, $attachments[0]['icon'] );
	}

	/**
	 * An episode whose audio lives in the media library gets the show artwork.
	 *
	 * The transformer stamps its own icon on every audio attachment, so the show image only lands
	 * if the integration replaces it.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_media_library_episode_gets_the_show_cover_art() {
		Jetpack::init();
		$this->load_mock_podcast_show();

		$audio_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/sample-audio.mp3' );
		$this->add_enclosure( self::$post_id, \wp_get_attachment_url( $audio_id ) );

		// The transformer covers any audio without a poster with the site icon, so there is already
		// an icon on the attachment by the time the integration sees it.
		$icon_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		\update_option( 'site_icon', $icon_id );

		$attachments = $this->transform_attachments( self::$post_id );

		$this->assertCount( 1, $attachments );
		$this->assertSame( self::SHOW_IMAGE_URL, $attachments[0]['icon'], 'The show artwork must win over the site icon.' );

		\delete_option( 'site_icon' );
		\wp_delete_attachment( $icon_id, true );
		\wp_delete_attachment( $audio_id, true );
	}

	/**
	 * An enclosure on a post outside the podcast category is not treated as an episode.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_enclosure_outside_the_podcast_category_is_not_an_episode() {
		Jetpack::init();
		$this->load_mock_podcast_show( false );
		$this->add_enclosure( self::$post_id, \home_url( '/podcast/episode.mp3' ) );

		$this->assertSame( array(), $this->transform_attachments( self::$post_id ), 'A stray enclosure must not federate as an episode.' );
	}

	/**
	 * The show artwork is applied to the episode audio only.
	 *
	 * @covers ::add_podcast_attachments
	 */
	public function test_other_audio_does_not_get_the_show_cover_art() {
		Jetpack::init();
		$this->load_mock_podcast_show();
		$this->add_enclosure( self::$post_id, \home_url( '/podcast/episode.mp3' ) );

		$other = static function ( $attachments ) {
			$attachments[] = array(
				'type'      => 'Audio',
				'url'       => 'https://example.com/voicemail.mp3',
				'mediaType' => 'audio/mpeg',
			);

			return $attachments;
		};

		// Runs before the integration, so the episode resolution sees it in the list.
		\add_filter( 'activitypub_attachments', $other, 5 );
		$attachments = $this->transform_attachments( self::$post_id );
		\remove_filter( 'activitypub_attachments', $other, 5 );

		$this->assertCount( 2, $attachments );
		$this->assertSame( 'https://example.com/voicemail.mp3', $attachments[1]['url'] );
		$this->assertArrayNotHasKey( 'icon', $attachments[1], 'Unrelated audio must not advertise the show artwork.' );
	}
}
