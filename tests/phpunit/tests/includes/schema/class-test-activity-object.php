<?php
/**
 * Test file for the Activity_Object schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Activity_Object;

/**
 * Test class for the Activity_Object schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Activity_Object
 */
class Test_Activity_Object extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Activity_Object( $data ) )->validate() ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$note = array(
			'id'           => 'https://example.com/note/1',
			'type'         => 'Note',
			'attributedTo' => 'https://example.com/actor',
			'content'      => '<p>Hello</p>',
			'summary'      => null,
		);

		return array(
			'note'                    => array( $note, true ),
			'without author'          => array( \array_diff_key( $note, array( 'attributedTo' => true ) ), true ),
			'content not a string'    => array( \array_merge( $note, array( 'content' => array( 'Hello' ) ) ), false ),
			'content map'             => array( \array_merge( $note, array( 'contentMap' => array( 'en' => 'Hello' ) ) ), true ),
			'content map not strings' => array( \array_merge( $note, array( 'contentMap' => array( 'en' => array( 'Hello' ) ) ) ), false ),
			'reply'                   => array( \array_merge( $note, array( 'inReplyTo' => 'https://example.org/note/1' ) ), true ),
			'not a reply'             => array( \array_merge( $note, array( 'inReplyTo' => null ) ), true ),
			'not a quote'             => array( \array_merge( $note, array( 'quote' => null ) ), true ),
			'reply not a uri'         => array( \array_merge( $note, array( 'inReplyTo' => 'note' ) ), false ),
			'quote not a uri'         => array( \array_merge( $note, array( 'quote' => 'note' ) ), false ),
			'url as list'             => array( \array_merge( $note, array( 'url' => array( 'https://example.com/@actor/1' ) ) ), true ),
		);
	}
}
