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

		// Mock emoji imports to return a local URL.
		\add_filter( 'activitypub_pre_import_emoji', array( $this, 'mock_emoji_import' ), 10, 2 );
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		\remove_filter( 'activitypub_pre_import_emoji', array( $this, 'mock_emoji_import' ), 10 );

		parent::tear_down();
	}

	/**
	 * Mock emoji import to return a local URL.
	 *
	 * @param string|false|null $result    The import result.
	 * @param string            $emoji_url The remote emoji URL.
	 *
	 * @return string Mocked local URL based on the remote URL.
	 */
	public function mock_emoji_import( $result, $emoji_url ) {
		// Only mock emoji URLs from example.com.
		if ( false === \strpos( $emoji_url, 'example.com/emoji/' ) ) {
			return $result;
		}

		// Return a mock local URL that preserves the filename.
		$filename = \basename( $emoji_url );
		return 'http://example.org/wp-content/uploads/activitypub/emoji/example.com/' . $filename;
	}

	/**
	 * Test replacing multiple emoji in a string.
	 *
	 * @covers ::replace_custom_emoji
	 */
	public function test_replace_multiple_emoji() {
		$text = 'Hello :wave: world :earth: with :heart: many :star: emoji :rocket: here :tada:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':wave:',
					'icon' => array( 'url' => 'https://example.com/emoji/wave.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':earth:',
					'icon' => array( 'url' => 'https://example.com/emoji/earth.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':heart:',
					'icon' => array( 'url' => 'https://example.com/emoji/heart.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':star:',
					'icon' => array( 'url' => 'https://example.com/emoji/star.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':rocket:',
					'icon' => array( 'url' => 'https://example.com/emoji/rocket.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':tada:',
					'icon' => array( 'url' => 'https://example.com/emoji/tada.png' ),
				),
			),
		);

		$result = Emoji::replace_custom_emoji( $text, $activity );

		// Verify all 6 emoji are replaced.
		$this->assertStringNotContainsString( ':wave:', $result );
		$this->assertStringNotContainsString( ':earth:', $result );
		$this->assertStringNotContainsString( ':heart:', $result );
		$this->assertStringNotContainsString( ':star:', $result );
		$this->assertStringNotContainsString( ':rocket:', $result );
		$this->assertStringNotContainsString( ':tada:', $result );

		// Verify all img tags are present.
		$this->assertStringContainsString( 'wave.png', $result );
		$this->assertStringContainsString( 'earth.png', $result );
		$this->assertStringContainsString( 'heart.png', $result );
		$this->assertStringContainsString( 'star.png', $result );
		$this->assertStringContainsString( 'rocket.png', $result );
		$this->assertStringContainsString( 'tada.png', $result );

		// Verify proper img structure.
		$this->assertEquals( 6, substr_count( $result, 'class="emoji"' ) );
	}

	/**
	 * Test replacing the same emoji multiple times in a string.
	 *
	 * @covers ::replace_custom_emoji
	 */
	public function test_replace_same_emoji_multiple_times() {
		$text = ':kappa: I said :kappa: and :kappa: again :kappa:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':kappa:',
					'icon' => array( 'url' => 'https://example.com/emoji/kappa.png' ),
				),
			),
		);

		$result = Emoji::replace_custom_emoji( $text, $activity );

		// Verify no shortcodes remain.
		$this->assertStringNotContainsString( ':kappa:', $result );

		// Verify all 4 occurrences are replaced.
		$this->assertEquals( 4, substr_count( $result, 'kappa.png' ) );
		$this->assertEquals( 4, substr_count( $result, 'class="emoji"' ) );
	}

	/**
	 * Test adjacent emoji are all replaced.
	 *
	 * @covers ::replace_custom_emoji
	 */
	public function test_replace_adjacent_emoji() {
		$text = ':one::two::three::four::five:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':one:',
					'icon' => array( 'url' => 'https://example.com/emoji/one.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':two:',
					'icon' => array( 'url' => 'https://example.com/emoji/two.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':three:',
					'icon' => array( 'url' => 'https://example.com/emoji/three.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':four:',
					'icon' => array( 'url' => 'https://example.com/emoji/four.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':five:',
					'icon' => array( 'url' => 'https://example.com/emoji/five.png' ),
				),
			),
		);

		$result = Emoji::replace_custom_emoji( $text, $activity );

		// Verify all shortcodes are replaced.
		$this->assertStringNotContainsString( ':one:', $result );
		$this->assertStringNotContainsString( ':two:', $result );
		$this->assertStringNotContainsString( ':three:', $result );
		$this->assertStringNotContainsString( ':four:', $result );
		$this->assertStringNotContainsString( ':five:', $result );

		// Verify all 5 img tags are present.
		$this->assertEquals( 5, substr_count( $result, 'class="emoji"' ) );
	}

	/**
	 * Test emoji not in tags are not replaced.
	 *
	 * @covers ::replace_custom_emoji
	 */
	public function test_unknown_emoji_not_replaced() {
		$text = 'Hello :known: and :unknown:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':known:',
					'icon' => array( 'url' => 'https://example.com/emoji/known.png' ),
				),
			),
		);

		$result = Emoji::replace_custom_emoji( $text, $activity );

		// Known emoji is replaced.
		$this->assertStringNotContainsString( ':known:', $result );
		$this->assertStringContainsString( 'known.png', $result );

		// Unknown emoji remains as text.
		$this->assertStringContainsString( ':unknown:', $result );
	}

	/**
	 * Test extract_emoji_data returns correct structure.
	 *
	 * @covers ::extract_emoji_data
	 */
	public function test_extract_emoji_data() {
		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':test:',
					'icon' => array( 'url' => 'https://example.com/emoji/test.png' ),
				),
				array(
					'type'    => 'Emoji',
					'name'    => ':updated:',
					'updated' => '2025-01-15T10:30:00Z',
					'icon'    => array( 'url' => 'https://example.com/emoji/updated.png' ),
				),
				array(
					'type' => 'Hashtag',
					'name' => '#notanemoji',
				),
			),
		);

		$result = Emoji::extract_emoji_data( $activity );

		$this->assertCount( 2, $result );

		$this->assertEquals( 'https://example.com/emoji/test.png', $result[0]['url'] );
		$this->assertEquals( ':test:', $result[0]['name'] );
		$this->assertNull( $result[0]['updated'] );

		$this->assertEquals( 'https://example.com/emoji/updated.png', $result[1]['url'] );
		$this->assertEquals( ':updated:', $result[1]['name'] );
		$this->assertEquals( '2025-01-15T10:30:00Z', $result[1]['updated'] );
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
					'icon' => array( 'url' => 'https://example.com/emoji/wave.png' ),
				),
			)
		);

		$result = Emoji::replace_from_json( $text, $emoji_json );

		$this->assertStringNotContainsString( ':wave:', $result );
		$this->assertStringContainsString( 'wave.png', $result );
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
					'icon' => array( 'url' => 'https://example.com/emoji/emoji.png' ),
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
	 * Test emoji replacement is case-insensitive.
	 *
	 * Mastodon sends emoji names in lowercase in tags but may use
	 * different casing in the content (e.g., :KannaWave: vs :kannawave:).
	 *
	 * @covers ::replace_custom_emoji
	 */
	public function test_replace_emoji_case_insensitive() {
		$text = ':vmastop: :KannaWave:';

		$activity = array(
			'tag' => array(
				array(
					'type' => 'Emoji',
					'name' => ':vmastop:',
					'icon' => array( 'url' => 'https://example.com/emoji/vmastop.png' ),
				),
				array(
					'type' => 'Emoji',
					'name' => ':kannawave:', // Lowercase in tag.
					'icon' => array( 'url' => 'https://example.com/emoji/kannawave.png' ),
				),
			),
		);

		$result = Emoji::replace_custom_emoji( $text, $activity );

		// Both should be replaced despite case difference.
		$this->assertStringNotContainsString( ':vmastop:', $result );
		$this->assertStringNotContainsString( ':KannaWave:', $result );
		$this->assertStringContainsString( 'vmastop.png', $result );
		$this->assertStringContainsString( 'kannawave.png', $result );
		$this->assertEquals( 2, substr_count( $result, 'class="emoji"' ) );
	}
}
