<?php
/**
 * Test file for Activitypub Emoji.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Emoji;

/**
 * Test class for Activitypub Emoji.
 *
 * @coversDefaultClass \Activitypub\Emoji
 */
class Test_Emoji extends \WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		// Mock emoji caching to return a local URL.
		\add_filter( 'activitypub_remote_media_url', array( $this, 'mock_emoji_cache' ), 10, 2 );
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		\remove_filter( 'activitypub_remote_media_url', array( $this, 'mock_emoji_cache' ), 10 );

		parent::tear_down();
	}

	/**
	 * Mock emoji caching to return a local URL.
	 *
	 * @param string $url     The remote emoji URL.
	 * @param string $context The context.
	 *
	 * @return string Mocked local URL based on the remote URL.
	 */
	public function mock_emoji_cache( $url, $context ) {
		if ( 'emoji' !== $context ) {
			return $url;
		}

		// Only mock emoji URLs from example.com.
		if ( false === \strpos( $url, 'example.com' ) ) {
			return $url;
		}

		// Return a mock local URL that preserves the filename.
		$filename = \basename( $url );
		return 'http://example.org/wp-content/uploads/activitypub/emoji/example.com/' . $filename;
	}

	/**
	 * Test wrapping multiple emoji in content.
	 *
	 * @covers ::wrap_in_content
	 */
	public function test_wrap_multiple_emoji() {
		$text = 'Hello :wave: world :earth:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':wave:',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/wave.png',
					),
				),
				array(
					'type' => 'Emoji',
					'name' => ':earth:',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/earth.png',
					),
				),
			),
		);

		$result = Emoji::wrap_in_content( $text, $activity );

		// Verify blocks are added.
		$this->assertStringContainsString( '<!-- wp:activitypub/emoji', $result );
		$this->assertStringContainsString( 'wave.png', $result );
		$this->assertStringContainsString( 'earth.png', $result );
		$this->assertStringContainsString( ':wave:', $result );
		$this->assertStringContainsString( ':earth:', $result );

		// Count the blocks.
		$this->assertEquals( 2, substr_count( $result, '<!-- wp:activitypub/emoji' ) );
	}

	/**
	 * Test wrapping same emoji multiple times.
	 *
	 * @covers ::wrap_in_content
	 */
	public function test_wrap_same_emoji_multiple_times() {
		$text = ':kappa: I said :kappa: again';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':kappa:',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/kappa.png',
					),
				),
			),
		);

		$result = Emoji::wrap_in_content( $text, $activity );

		// Verify all occurrences are wrapped.
		$this->assertEquals( 2, substr_count( $result, '<!-- wp:activitypub/emoji' ) );
	}

	/**
	 * Test emoji not in tags are not wrapped.
	 *
	 * @covers ::wrap_in_content
	 */
	public function test_unknown_emoji_not_wrapped() {
		$text = 'Hello :known: and :unknown:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':known:',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/known.png',
					),
				),
			),
		);

		$result = Emoji::wrap_in_content( $text, $activity );

		// Known emoji is wrapped.
		$this->assertStringContainsString( '<!-- wp:activitypub/emoji', $result );
		$this->assertStringContainsString( 'known.png', $result );

		// Unknown emoji remains as plain text.
		$this->assertStringContainsString( ':unknown:', $result );
		$this->assertEquals( 1, substr_count( $result, '<!-- wp:activitypub/emoji' ) );
	}

	/**
	 * Test replace_from_json works correctly.
	 *
	 * @covers ::replace_from_json
	 */
	public function test_replace_from_json() {
		$text = 'Hello :wave: world';

		$emoji_json = \wp_json_encode(
			array(
				array(
					'type' => 'Emoji',
					'name' => ':wave:',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/wave.png',
					),
				),
			)
		);

		$result = Emoji::replace_from_json( $text, $emoji_json );

		$this->assertStringNotContainsString( ':wave:', $result );
		$this->assertStringContainsString( 'wave.png', $result );
		$this->assertStringContainsString( 'class="emoji"', $result );
	}

	/**
	 * Test prepare_actor_meta extracts emoji tags.
	 *
	 * @covers ::prepare_actor_meta
	 */
	public function test_prepare_actor_meta() {
		$actor = array(
			'id'   => 'https://example.com/users/test',
			'name' => 'Test :emoji:',
			'tag'  => array(
				array(
					'type' => 'Emoji',
					'name' => ':emoji:',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/emoji.png',
					),
				),
				array(
					'type' => 'Hashtag',
					'name' => '#test',
				),
			),
		);

		$result = Emoji::prepare_actor_meta( $actor );

		$this->assertArrayHasKey( '_activitypub_emoji', $result );

		$decoded = \json_decode( $result['_activitypub_emoji'], true );
		$this->assertCount( 1, $decoded );
		$this->assertEquals( 'Emoji', $decoded[0]['type'] );
		$this->assertEquals( ':emoji:', $decoded[0]['name'] );
	}

	/**
	 * Test prepare_actor_meta returns empty for actors without emoji.
	 *
	 * @covers ::prepare_actor_meta
	 */
	public function test_prepare_actor_meta_no_emoji() {
		$actor = array(
			'id'   => 'https://example.com/users/test',
			'name' => 'Test User',
		);

		$result = Emoji::prepare_actor_meta( $actor );

		$this->assertEmpty( $result );
	}

	/**
	 * Test wrap_in_content preserves updated timestamp in block attributes.
	 *
	 * @covers ::wrap_in_content
	 */
	public function test_wrap_preserves_updated_timestamp() {
		$text = 'Hello :wave:';

		$activity = array(
			'tag' => array(
				array(
					'type'    => 'Emoji',
					'name'    => ':wave:',
					'icon'    => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/wave.png',
					),
					'updated' => '2024-01-15T12:00:00Z',
				),
			),
		);

		$result = Emoji::wrap_in_content( $text, $activity );

		$this->assertStringContainsString( '"updated":"2024-01-15T12:00:00Z"', $result );
		$this->assertStringContainsString( '"url":', $result );
	}

	/**
	 * Test wrap_in_content omits updated when not present.
	 *
	 * @covers ::wrap_in_content
	 */
	public function test_wrap_omits_updated_when_absent() {
		$text = 'Hello :wave:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':wave:',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/wave.png',
					),
				),
			),
		);

		$result = Emoji::wrap_in_content( $text, $activity );

		$this->assertStringNotContainsString( 'updated', $result );
	}

	/**
	 * Test emoji wrapping is case-insensitive.
	 *
	 * @covers ::wrap_in_content
	 */
	public function test_wrap_emoji_case_insensitive() {
		$text = ':vmastop: :KannaWave:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':vmastop:',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/vmastop.png',
					),
				),
				array(
					'type' => 'Emoji',
					'name' => ':kannawave:', // Lowercase in tag.
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/emoji/kannawave.png',
					),
				),
			),
		);

		$result = Emoji::wrap_in_content( $text, $activity );

		// Both should be wrapped despite case difference.
		$this->assertStringContainsString( 'vmastop.png', $result );
		$this->assertStringContainsString( 'kannawave.png', $result );
		$this->assertEquals( 2, substr_count( $result, '<!-- wp:activitypub/emoji' ) );
	}

	/**
	 * Test validate_emoji_src allows local emoji URLs by default.
	 *
	 * @covers ::validate_emoji_src
	 */
	public function test_validate_emoji_src_allows_local_urls() {
		$upload_dir = \wp_upload_dir();
		$local_url  = $upload_dir['baseurl'] . '/activitypub/emoji/example.com/test.png';

		$result = Emoji::validate_emoji_src( $local_url );

		$this->assertTrue( $result );
	}

	/**
	 * Test validate_emoji_src rejects remote URLs by default.
	 *
	 * @covers ::validate_emoji_src
	 */
	public function test_validate_emoji_src_rejects_remote_urls_by_default() {
		$remote_url = 'https://remote.example.com/emoji/test.png';

		$result = Emoji::validate_emoji_src( $remote_url );

		$this->assertFalse( $result );
	}

	/**
	 * Test validate_emoji_src respects activitypub_validate_emoji_src filter.
	 *
	 * @covers ::validate_emoji_src
	 */
	public function test_validate_emoji_src_respects_filter() {
		$remote_url = 'https://cdn.example.com/emoji/test.png';

		// By default, remote URL should be rejected.
		$this->assertFalse( Emoji::validate_emoji_src( $remote_url ) );

		// Add filter to allow CDN URLs.
		$allow_cdn = function ( $is_valid, $url ) {
			if ( \str_starts_with( $url, 'https://cdn.example.com/' ) ) {
				return true;
			}
			return $is_valid;
		};
		\add_filter( 'activitypub_validate_emoji_src', $allow_cdn, 10, 2 );

		// Now CDN URL should be allowed.
		$this->assertTrue( Emoji::validate_emoji_src( $remote_url ) );

		// Other remote URLs should still be rejected.
		$this->assertFalse( Emoji::validate_emoji_src( 'https://other.example.com/emoji/test.png' ) );

		\remove_filter( 'activitypub_validate_emoji_src', $allow_cdn );

		// After removing filter, CDN URL should be rejected again.
		$this->assertFalse( Emoji::validate_emoji_src( $remote_url ) );
	}
}
