<?php
/**
 * Test file for Post Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Post Functions.
 */
class Test_Functions_Post extends \WP_UnitTestCase {

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

		$visible_local_post_id = self::factory()->post->create();

		add_post_meta( $visible_local_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		$this->assertTrue( \Activitypub\is_post_disabled( $visible_local_post_id ) );
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
	}

	/**
	 * Test get_content_visibility defaults for old posts.
	 *
	 * @covers \Activitypub\get_content_visibility
	 */
	public function test_get_content_visibility_defaults_to_local_for_old_posts() {
		// Create a post with a date older than 1 month.
		$old_date    = gmdate( 'Y-m-d H:i:s', time() - ( 31 * DAY_IN_SECONDS ) );
		$old_post_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_title'    => 'Old Post',
				'post_content'  => 'This post is more than a month old',
				'post_status'   => 'publish',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			)
		);

		// Without explicit visibility meta, old posts should default to 'local'.
		$visibility = \Activitypub\get_content_visibility( $old_post_id );
		$this->assertEquals(
			ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
			$visibility,
			'Old posts without explicit visibility should default to local'
		);
	}

	/**
	 * Test get_content_visibility defaults for new posts.
	 *
	 * @covers \Activitypub\get_content_visibility
	 */
	public function test_get_content_visibility_defaults_to_public_for_new_posts() {
		// Create a recent post.
		$new_post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'New Post',
				'post_content' => 'This is a recent post',
				'post_status'  => 'publish',
			)
		);

		// Recent posts should default to 'public'.
		$visibility = \Activitypub\get_content_visibility( $new_post_id );
		$this->assertEquals(
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
			$visibility,
			'Recent posts without explicit visibility should default to public'
		);
	}

	/**
	 * Test get_content_visibility respects explicitly set values.
	 *
	 * @covers \Activitypub\get_content_visibility
	 */
	public function test_get_content_visibility_respects_explicit_values() {
		// Create an old post with explicit quiet_public visibility.
		$old_date    = gmdate( 'Y-m-d H:i:s', time() - ( 31 * DAY_IN_SECONDS ) );
		$old_post_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_title'    => 'Old Post with Explicit Visibility',
				'post_content'  => 'This post has explicit visibility set',
				'post_status'   => 'publish',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			)
		);
		add_post_meta( $old_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );

		// Should respect the explicit value even for old posts.
		$visibility = \Activitypub\get_content_visibility( $old_post_id );
		$this->assertEquals(
			ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC,
			$visibility,
			'Explicitly set visibility should be respected even for old posts'
		);
	}

	/**
	 * Test get_content_visibility respects federated status.
	 *
	 * @covers \Activitypub\get_content_visibility
	 */
	public function test_get_content_visibility_respects_federated_status() {
		// Create an old post that was already federated.
		$old_date    = gmdate( 'Y-m-d H:i:s', time() - ( 31 * DAY_IN_SECONDS ) );
		$old_post_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_title'    => 'Old Federated Post',
				'post_content'  => 'This post was already federated',
				'post_status'   => 'publish',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			)
		);
		// Mark it as federated.
		add_post_meta( $old_post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		// Old posts that were already federated should remain public.
		$visibility = \Activitypub\get_content_visibility( $old_post_id );
		$this->assertEquals(
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
			$visibility,
			'Old posts that were already federated should remain public'
		);
	}
}
