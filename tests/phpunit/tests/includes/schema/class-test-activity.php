<?php
/**
 * Test file for the Activity schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Activity;

/**
 * Test class for the Activity schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Activity
 */
class Test_Activity extends \WP_UnitTestCase {

	/**
	 * Test validate.
	 *
	 * @dataProvider validate_provider
	 * @covers ::validate
	 * @covers ::get_schema
	 *
	 * @param array $data     The remote data.
	 * @param bool  $expected Whether it is valid.
	 */
	public function test_validate( $data, $expected ) {
		$this->assertSame( $expected, ! \is_wp_error( ( new Activity( $data ) )->validate() ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$follow = array(
			'id'     => 'https://example.com/follow/1',
			'type'   => 'Follow',
			'actor'  => 'https://example.com/actor',
			'object' => 'https://example.org/actor',
		);

		$note = array(
			'id'   => 'https://example.com/note/1',
			'type' => 'Note',
		);

		$link = array(
			'type' => 'Link',
			'href' => 'https://example.com/actor',
		);

		return array(
			'follow'                => array( $follow, true ),
			'without id'            => array( \array_diff_key( $follow, array( 'id' => true ) ), false ),
			'id not a uri'          => array( \array_merge( $follow, array( 'id' => 'follow-1' ) ), false ),
			'type not letters'      => array( \array_merge( $follow, array( 'type' => 'as:Follow' ) ), false ),
			'without actor'         => array( \array_diff_key( $follow, array( 'actor' => true ) ), false ),
			'actor not a uri'       => array( \array_merge( $follow, array( 'actor' => 'alice' ) ), false ),
			'actor as object'       => array( \array_merge( $follow, array( 'actor' => array( 'id' => 'https://example.com/actor' ) ) ), true ),
			'actor id not a uri'    => array( \array_merge( $follow, array( 'actor' => array( 'id' => 'alice' ) ) ), false ),
			'actor as list'         => array( \array_merge( $follow, array( 'actor' => array( 'https://example.com/actor' ) ) ), false ),
			'actor as link'         => array( \array_merge( $follow, array( 'actor' => $link ) ), true ),
			'actor as mention'      => array( \array_merge( $follow, array( 'actor' => \array_merge( $link, array( 'type' => 'Mention' ) ) ) ), true ),
			'actor link with id'    => array( \array_merge( $follow, array( 'actor' => \array_merge( $link, array( 'id' => 'https://example.com/link/1' ) ) ) ), true ),
			'actor link no href'    => array(
				\array_merge(
					$follow,
					array(
						'actor' => array(
							'type' => 'Link',
							'id'   => 'https://example.com/link/1',
						),
					)
				),
				false,
			),
			'actor href not a uri'  => array(
				\array_merge(
					$follow,
					array(
						'actor' => \array_merge(
							$link,
							array(
								'id'   => 'https://example.com/link/1',
								'href' => 'alice',
							)
						),
					)
				),
				false,
			),
			'actor link href list'  => array( \array_merge( $follow, array( 'actor' => \array_merge( $link, array( 'href' => array( 'https://example.com/actor' ) ) ) ) ), false ),
			'without object'        => array( \array_diff_key( $follow, array( 'object' => true ) ), false ),
			'object embedded'       => array( \array_merge( $follow, array( 'object' => $note ) ), true ),
			'object embedded no id' => array( \array_merge( $follow, array( 'object' => array( 'type' => 'Note' ) ) ), false ),
			'object as link'        => array( \array_merge( $follow, array( 'object' => $link ) ), true ),
			'object as list'        => array( \array_merge( $follow, array( 'object' => array( 'https://example.org/a', 'https://example.org/b' ) ) ), true ),
			'instrument not a uri'  => array( \array_merge( $follow, array( 'instrument' => 'note' ) ), false ),
			'to as string'          => array( \array_merge( $follow, array( 'to' => 'https://www.w3.org/ns/activitystreams#Public' ) ), true ),
			'to as list'            => array( \array_merge( $follow, array( 'to' => array( 'https://example.org/actor' ) ) ), true ),
			'to bare Public'        => array( \array_merge( $follow, array( 'to' => array( 'Public' ) ) ), true ),
			'to Public as string'   => array( \array_merge( $follow, array( 'to' => 'Public' ) ), true ),
			'to a word'             => array( \array_merge( $follow, array( 'to' => array( 'Everyone' ) ) ), false ),
			'bcc not a uri'         => array( \array_merge( $follow, array( 'bcc' => array( 'everyone' ) ) ), false ),
		);
	}

	/**
	 * An activity is normalized like the inbox args do: the actor as its id, the object's language maps resolved.
	 *
	 * @covers ::sanitize
	 */
	public function test_sanitize() {
		$locale = static function () {
			return 'de_DE';
		};
		\add_filter( 'locale', $locale );

		$activity = ( new Activity(
			array(
				'id'     => 'https://example.com/create/1',
				'type'   => 'Create',
				'actor'  => array( 'id' => 'https://example.com/actor' ),
				'object' => array(
					'id'         => 'https://example.com/notes/1',
					'type'       => 'Note',
					'summary'    => 'Hello',
					'summaryMap' => array( 'de' => 'Hallo' ),
				),
			)
		) )->sanitize();

		\remove_filter( 'locale', $locale );

		$this->assertSame( 'https://example.com/actor', $activity['actor'] );
		$this->assertSame( 'Hallo', $activity['object']['summary'] );
	}

	/**
	 * Normalizing only picks what was validated: what passed the schema still passes it after
	 * `sanitize()`, and a second `sanitize()` changes nothing.
	 *
	 * @dataProvider validate_provider
	 * @covers ::sanitize
	 * @covers ::validate
	 *
	 * @param array $data     The remote data.
	 * @param bool  $expected Whether it is valid.
	 */
	public function test_sanitize_keeps_valid_data_valid( $data, $expected ) {
		if ( ! $expected ) {
			$this->assertWPError( ( new Activity( $data ) )->validate() );
			return;
		}

		$sanitized = ( new Activity( $data ) )->sanitize();

		$this->assertTrue( ( new Activity( $sanitized ) )->validate() );
		$this->assertSame( $sanitized, ( new Activity( $sanitized ) )->sanitize() );
	}

	/**
	 * A Link or Mention as actor is replaced by the href it points to, which the schema checked.
	 *
	 * @covers ::sanitize
	 */
	public function test_sanitize_link_actor() {
		$activity = ( new Activity(
			array(
				'id'     => 'https://example.com/follow/1',
				'type'   => 'Follow',
				'actor'  => array(
					'type' => 'Link',
					'id'   => 'https://example.com/link/1',
					'href' => 'https://example.com/actor',
				),
				'object' => 'https://example.org/actor',
			)
		) )->sanitize();

		$this->assertSame( 'https://example.com/actor', $activity['actor'] );
	}
}
