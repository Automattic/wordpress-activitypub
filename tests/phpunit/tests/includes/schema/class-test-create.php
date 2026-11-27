<?php
/**
 * Test file for the Create schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Create;

/**
 * Test class for the Create schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Create
 */
class Test_Create extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Create( $data ) )->validate() ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$create = array(
			'id'     => 'https://example.com/create/1',
			'type'   => 'Create',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'id'      => 'https://example.com/note/1',
				'type'    => 'Note',
				'content' => 'Hi',
			),
		);

		return array(
			'create'                 => array( $create, true ),
			'object only an id'      => array( \array_merge( $create, array( 'object' => 'https://example.com/note/1' ) ), false ),
			'object without id'      => array(
				\array_merge(
					$create,
					array(
						'object' => array(
							'type'    => 'Note',
							'content' => 'Hi',
						),
					)
				),
				false,
			),
			'object without content' => array(
				\array_merge(
					$create,
					array(
						'object' => array(
							'id'   => 'https://example.com/note/1',
							'type' => 'Note',
						),
					)
				),
				false,
			),
			'empty content'          => array(
				\array_merge(
					$create,
					array(
						'object' => array(
							'id'      => 'https://example.com/note/1',
							'type'    => 'Note',
							'content' => '',
						),
					)
				),
				true,
			),
		);
	}

	/**
	 * Set up: comment images are enabled, so image-only replies have something to show.
	 */
	public function set_up() {
		parent::set_up();

		\update_option( 'activitypub_allow_comment_images', '1' );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_allow_comment_images' );

		parent::tear_down();
	}

	/**
	 * Whether a Create of the object is valid.
	 *
	 * @param array $created The created object.
	 *
	 * @return bool Whether it is valid.
	 */
	private function is_valid( $created ) {
		$create = array(
			'id'     => 'https://example.com/create/1',
			'type'   => 'Create',
			'actor'  => 'https://example.com/actor',
			'object' => $created,
		);

		return true === ( new Create( $create ) )->validate();
	}

	/**
	 * Image-only validation runs before the signature check, so it does not check any host.
	 *
	 * @covers ::check
	 */
	public function test_image_only_validation_checks_no_hosts() {
		$count = 0;
		$track = static function ( $ports ) use ( &$count ) {
			++$count;
			return $ports;
		};
		\add_filter( 'http_allowed_safe_ports', $track );
		$valid = $this->is_valid(
			array(
				'id'         => 'https://example.com/image-reply',
				'inReplyTo'  => 'https://example.org/post',
				'attachment' => array(
					'type' => 'Image',
					'url'  => 'https://8.8.4.4:22/photo.jpg',
				),
			)
		);
		\remove_filter( 'http_allowed_safe_ports', $track );

		$this->assertTrue( $valid );
		$this->assertSame( 0, $count );
	}

	/**
	 * Missing text is allowed only when there is an image the import path can use.
	 *
	 * @dataProvider image_only_object_provider
	 * @covers ::check
	 *
	 * @param array $fields   The object fields in addition to its id.
	 * @param bool  $expected Whether the object is valid.
	 */
	public function test_validate_image_only_objects( $fields, $expected ) {
		$object = \array_merge(
			array(
				'id'        => 'https://example.com/note',
				'inReplyTo' => 'https://example.org/post',
			),
			$fields
		);

		$this->assertSame( $expected, $this->is_valid( $object ) );

		unset( $object['id'] );
		$this->assertFalse( $this->is_valid( $object ), 'Images cannot replace the required object id.' );
	}

	/**
	 * Image-only replies are rejected while comment images are disabled.
	 *
	 * @covers ::check
	 */
	public function test_image_only_object_rejected_without_comment_images() {
		\delete_option( 'activitypub_allow_comment_images' );

		$this->assertFalse(
			$this->is_valid(
				array(
					'id'        => 'https://example.com/note',
					'inReplyTo' => 'https://example.org/post',
					'image'     => 'https://example.com/photo.jpg',
				)
			)
		);
	}

	/**
	 * Image-only posts that are not replies still need text, even with comment images enabled.
	 *
	 * @covers ::check
	 */
	public function test_image_only_object_rejected_when_not_a_reply() {
		$this->assertFalse(
			$this->is_valid(
				array(
					'id'    => 'https://example.com/note',
					'image' => 'https://example.com/photo.jpg',
				)
			)
		);
	}

	/**
	 * Image-only quotes are allowed like image-only replies.
	 *
	 * @covers ::check
	 */
	public function test_image_only_quote_is_valid() {
		$this->assertTrue(
			$this->is_valid(
				array(
					'id'    => 'https://example.com/quote',
					'quote' => 'https://example.org/post',
					'image' => 'https://example.com/photo.jpg',
				)
			)
		);
	}

	/**
	 * Text, supported images, and unsupported or malformed media.
	 *
	 * @return array The object fields and validation result.
	 */
	public function image_only_object_provider() {
		$cases = array(
			'empty text'             => array( array( 'content' => '' ), true ),
			'missing text and image' => array( array(), false ),
			'Image object'           => array(
				array(
					'attachment' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/photo.jpg',
					),
				),
				true,
			),
			'image Document'         => array(
				array(
					'attachment' => array(
						array(
							'type'      => 'Document',
							'mediaType' => 'IMAGE/JPEG',
							'url'       => 'https://example.com/photo.jpg',
						),
					),
				),
				true,
			),
			'image fallback'         => array( array( 'image' => 'https://example.com/photo.jpg' ), true ),
			'single attachment URL'  => array( array( 'attachment' => 'https://example.com/photo.jpg' ), true ),
			// The host is checked when the comment is stored.
			'unsafe attachment URL'  => array( array( 'attachment' => 'http://127.0.0.1/photo.jpg' ), true ),
			'Unicode image URL'      => array(
				array(
					'attachment' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/uploads/写真.jpg',
					),
				),
				true,
			),
			'Unicode image fallback' => array( array( 'image' => 'https://example.com/uploads/写真.jpg' ), true ),
			'Audio object'           => array(
				array(
					'attachment' => array(
						'type' => 'Audio',
						'url'  => 'https://example.com/audio.mp3',
					),
				),
				false,
			),
			'Video object'           => array(
				array(
					'attachment' => array(
						'type' => 'Video',
						'url'  => 'https://example.com/video.mp4',
					),
				),
				false,
			),
			'missing image URL'      => array( array( 'attachment' => array( 'type' => 'Image' ) ), false ),
			'unsafe protocol'        => array(
				array(
					'attachment' => array(
						'type' => 'Image',
						'url'  => 'javascript:alert(1)',
					),
				),
				false,
			),
		);
		foreach ( array(
			42,
			true,
			array(
				'type' => 'Image',
				'url'  => 42,
			),
			'/photo.jpg',
			'//remote.example/photo.jpg',
			'ftp://remote.example/photo.jpg',
			'https://',
		) as $url ) {
			$cases[] = array(
				array(
					'attachment' => array(
						'type' => 'Image',
						'url'  => $url,
					),
				),
				false,
			);
		}
		// Hosts are checked when the comment is stored, not before the signature check.
		foreach ( array(
			'http://192.168.1.1/photo.jpg',
			'https://user:pass@example.com/photo.jpg',
		) as $url ) {
			$cases[] = array(
				array(
					'attachment' => array(
						'type' => 'Image',
						'url'  => $url,
					),
				),
				true,
			);
		}
		// Without a media type, anything but Audio and Video is read as an image.
		foreach ( array( 42, true, null, array( 'Image' ) ) as $type ) {
			$cases[] = array(
				array(
					'attachment' => array(
						'type' => $type,
						'url'  => 'https://example.com/photo.jpg',
					),
				),
				true,
			);
		}
		return $cases;
	}
}
