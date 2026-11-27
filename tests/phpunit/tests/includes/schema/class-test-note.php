<?php
/**
 * Test file for the Note schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Note;

/**
 * Test class for the Note schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Note
 */
class Test_Note extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Note( $data ) )->validate() ) );
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
		);

		return array(
			'note'           => array( $note, true ),
			'without id'     => array( \array_diff_key( $note, array( 'id' => true ) ), false ),
			'without author' => array( \array_diff_key( $note, array( 'attributedTo' => true ) ), false ),
			'author as list' => array( \array_merge( $note, array( 'attributedTo' => array( 'https://example.com/actor', 'https://example.com/channel' ) ) ), true ),
			'author no uri'  => array( \array_merge( $note, array( 'attributedTo' => 'alice' ) ), false ),
		);
	}
}
