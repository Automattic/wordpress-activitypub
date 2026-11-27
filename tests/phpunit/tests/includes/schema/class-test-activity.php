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
		$this->assertSame( $expected, ! \is_wp_error( ( new Activity() )->validate( $data ) ) );
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

		return array(
			'follow'                => array( $follow, true ),
			'without id'            => array( \array_diff_key( $follow, array( 'id' => true ) ), false ),
			'id not a uri'          => array( \array_merge( $follow, array( 'id' => 'follow-1' ) ), false ),
			'type not letters'      => array( \array_merge( $follow, array( 'type' => 'as:Follow' ) ), false ),
			'without actor'         => array( \array_diff_key( $follow, array( 'actor' => true ) ), false ),
			'actor not a uri'       => array( \array_merge( $follow, array( 'actor' => 'alice' ) ), false ),
			'actor as object'       => array( \array_merge( $follow, array( 'actor' => array( 'id' => 'https://example.com/actor' ) ) ), true ),
			'without object'        => array( \array_diff_key( $follow, array( 'object' => true ) ), false ),
			'object embedded'       => array( \array_merge( $follow, array( 'object' => $note ) ), true ),
			'object embedded no id' => array( \array_merge( $follow, array( 'object' => array( 'type' => 'Note' ) ) ), false ),
			'object as list'        => array( \array_merge( $follow, array( 'object' => array( 'https://example.org/a', 'https://example.org/b' ) ) ), true ),
			'instrument not a uri'  => array( \array_merge( $follow, array( 'instrument' => 'note' ) ), false ),
			'to as string'          => array( \array_merge( $follow, array( 'to' => 'https://www.w3.org/ns/activitystreams#Public' ) ), true ),
			'to as list'            => array( \array_merge( $follow, array( 'to' => array( 'https://example.org/actor' ) ) ), true ),
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

		$activity = ( new Activity() )->sanitize(
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
		);

		\remove_filter( 'locale', $locale );

		$this->assertSame( 'https://example.com/actor', $activity['actor'] );
		$this->assertSame( 'Hallo', $activity['object']['summary'] );
	}
}
