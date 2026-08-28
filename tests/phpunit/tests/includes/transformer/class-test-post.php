<?php
/**
 * Test file for Post transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Activity\Base_Object;
use Activitypub\Transformer\Post;

/**
 * Test class for Post Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Post
 */
class Test_Post extends \WP_UnitTestCase {
	/**
	 * Reflection method for testing protected method.
	 *
	 * @var \ReflectionMethod
	 */
	private $reflection_method;

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'activitypub_object_type', 'wordpress-post-format' );

		// Set up reflection method.
		$reflection              = new \ReflectionClass( Post::class );
		$this->reflection_method = $reflection->getMethod( 'get_type' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$this->reflection_method->setAccessible( true );
		}
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		// Reset options after each test.
		delete_option( 'activitypub_object_type' );

		parent::tear_down();
	}

	/**
	 * Test that the get_type method returns the configured type when the option is set.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_configured_type_when_option_set() {
		update_option( 'activitypub_object_type', 'Article' );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content that is longer than the note length limit',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Article', $type );
	}

	/**
	 * Test get_type method with various scenarios.
	 *
	 * @dataProvider get_type_provider
	 * @covers ::get_type
	 *
	 * @param array  $post_data      The post data to create.
	 * @param string $post_format    The post format to set (or null).
	 * @param string $expected_type  The expected ActivityPub type.
	 * @param string $description    Description of the test case.
	 */
	public function test_get_type( $post_data, $post_format, $expected_type, $description ) {
		$post_id = self::factory()->post->create( $post_data );

		if ( $post_format ) {
			set_post_format( $post_id, $post_format );
		}

		$post = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( $expected_type, $type, $description );
	}

	/**
	 * Data provider for get_type tests.
	 *
	 * @return array Test cases with post data, post format, expected type, and description.
	 */
	public function get_type_provider() {
		$long_content = str_repeat( 'Long content. ', 100 );

		return array(
			'short_content'        => array(
				array(
					'post_title'   => 'Test Post',
					'post_content' => 'Short content',
				),
				null,
				'Article',
				'Should return Article for short content with title',
			),
			'no_title'             => array(
				array(
					'post_title'   => '',
					'post_content' => $long_content,
				),
				null,
				'Note',
				'Should return Note for posts without title',
			),
			'standard_post_format' => array(
				array(
					'post_title'   => 'Test Post',
					'post_content' => $long_content,
					'post_type'    => 'post',
				),
				'standard',
				'Article',
				'Should return Article for standard post format',
			),
			'page_post_type'       => array(
				array(
					'post_title'   => 'Test Page',
					'post_content' => $long_content,
					'post_type'    => 'page',
				),
				null,
				'Page',
				'Should return Page for page post type',
			),
			'aside_post_format'    => array(
				array(
					'post_title'   => 'Test Post',
					'post_content' => $long_content,
					'post_type'    => 'post',
				),
				'aside',
				'Note',
				'Should return Note for non-standard post format',
			),
			'default_post_format'  => array(
				array(
					'post_title'   => 'Test Post',
					'post_content' => $long_content,
					'post_type'    => 'post',
				),
				null,
				'Article',
				'Should return Article for default post format',
			),
		);
	}

	/**
	 * Test that the get_type method returns note for post type without title support.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_respects_post_type_title_support() {
		// Create custom post type without title support.
		register_post_type(
			'no_title_type',
			array(
				'public'   => true,
				'supports' => array( 'editor' ), // Explicitly exclude 'title'.
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'no_title_type',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Note', $type );

		// Clean up.
		unregister_post_type( 'no_title_type' );
	}

	/**
	 * Test that the get_type method returns article for custom post type with post format support.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_respects_post_format_support() {
		// Create custom post type without title support.
		register_post_type(
			'no_title_type',
			array(
				'public'   => true,
				'supports' => array( 'editor', 'title', 'post-formats' ), // Needs to include 'title'.
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'no_title_type',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Article', $type );

		// Clean up.
		unregister_post_type( 'no_title_type' );
	}

	/**
	 * Test that the activitypub_post_object_type filter overrides the computed type.
	 *
	 * Verifies the filter receives the computed default and the post being
	 * transformed, and that the return value replaces the computed value
	 * for downstream callers of get_type().
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_filter_overrides_computed_value() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'A Titled Post With No Format',
				'post_content' => 'Default behavior here would return Article.',
			)
		);
		$post    = get_post( $post_id );

		$received_post = null;
		$received_type = null;
		$callback      = function ( $object_type, $filter_post ) use ( &$received_type, &$received_post ) {
			$received_type = $object_type;
			$received_post = $filter_post;
			return 'Note';
		};

		\add_filter( 'activitypub_post_object_type', $callback, 10, 2 );

		try {
			$transformer = new Post( $post );
			$type        = $this->reflection_method->invoke( $transformer );
		} finally {
			\remove_filter( 'activitypub_post_object_type', $callback, 10 );
		}

		$this->assertSame( 'Article', $received_type, 'Filter should receive the computed default type.' );
		$this->assertInstanceOf( '\WP_Post', $received_post );
		$this->assertSame( $post_id, $received_post->ID, 'Filter should receive the post being transformed.' );
		$this->assertSame( 'Note', $type, 'Filtered value should replace the computed default.' );
	}

	/**
	 * Test the to_array method.
	 *
	 * @covers ::to_object
	 */
	public function test_to_object() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test',
			)
		);

		$permalink = \get_permalink( $post );

		$activitypub_post = Post::transform( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		\wp_trash_post( $post );

		$activitypub_post = Post::transform( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		$cached = \get_post_meta( $post, '_activitypub_canonical_url', true );

		$this->assertEquals( $cached, $activitypub_post->get_id() );
	}

	/**
	 * Test that to_object() omits derived fields for redacted posts.
	 *
	 * The is_redacted() gate fires for password-protected posts (regardless
	 * of any per-request cookie) and for non-publish posts outside preview
	 * mode. In either case content, summary, summaryMap, contentMap,
	 * preview, and attachments must be absent from the serialized activity.
	 *
	 * @dataProvider data_redacted_post_states
	 *
	 * @covers ::get_attachment
	 * @covers ::get_content
	 * @covers ::get_preview
	 * @covers ::get_summary
	 *
	 * @param array $post_args Post data flagged as redacted (password or non-publish status).
	 */
	public function test_redacted_post_omits_derived_fields( $post_args ) {
		/*
		 * The non-publish branch of is_redacted() is short-circuited when
		 * the ACTIVITYPUB_PREVIEW constant is defined (intentional — previews
		 * of unpublished posts must still synthesize a representation). PHP
		 * cannot unset a defined constant, so if an earlier test in the
		 * suite has defined it (e.g. the router preview-template test), this
		 * data row would assert the wrong thing. The password row is
		 * unaffected and always exercises the gate.
		 */
		if ( ! isset( $post_args['post_password'] ) && defined( 'ACTIVITYPUB_PREVIEW' ) && ACTIVITYPUB_PREVIEW ) {
			$this->markTestSkipped( 'ACTIVITYPUB_PREVIEW was defined by an earlier test; non-publish gate cannot be exercised here.' );
		}

		$defaults = array(
			'post_author'  => 1,
			'post_title'   => 'Federation_Probe',
			'post_content' => 'SHOULD-NOT-FEDERATE-BODY',
			'post_excerpt' => 'SHOULD-NOT-FEDERATE-EXCERPT',
			'post_status'  => 'publish',
		);

		$post_id = \wp_insert_post( \array_merge( $defaults, $post_args ) );

		// Attach a content warning so the to_object() override path is exercised.
		\update_post_meta( $post_id, 'activitypub_content_warning', 'SHOULD-NOT-FEDERATE-WARNING' );

		// Simulate the cookie-bypass case for password-protected posts:
		// `post_password_required()` would return false here, but the
		// transformer must still refuse.
		\add_filter( 'post_password_required', '__return_false' );

		try {
			$object = Post::transform( get_post( $post_id ) )->to_object();

			$this->assertEmpty( $object->get_content(), 'content must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_summary(), 'summary must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_preview(), 'preview must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_content_map(), 'contentMap must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_summary_map(), 'summaryMap must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_attachment(), 'attachment must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_name(), 'name must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_name_map(), 'nameMap must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_image(), 'image must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_icon(), 'icon must be omitted for a redacted post.' );
			$this->assertEmpty( $object->get_sensitive(), 'sensitive flag must not be set for a redacted post.' );
			$this->assertEmpty( $object->get_dcterms(), 'dcterms (content warning) must be omitted for a redacted post.' );
		} finally {
			\remove_filter( 'post_password_required', '__return_false' );
		}
	}

	/**
	 * Data provider: post states that should trigger is_redacted().
	 *
	 * @return array[]
	 */
	public function data_redacted_post_states() {
		return array(
			'password protected' => array( array( 'post_password' => 'fed-secret-pass' ) ),
			'draft'              => array( array( 'post_status' => 'draft' ) ),
			'pending'            => array( array( 'post_status' => 'pending' ) ),
			'private'            => array( array( 'post_status' => 'private' ) ),
		);
	}

	/**
	 * Every way of hiding a post — non-public status, password, or the AP
	 * content-visibility meta (local/private) — must serialize as a content-free
	 * Tombstone: no content, tags, @-mentions, or location, and no audience at
	 * all. Nothing body-derived can leak and no actor named only in the
	 * now-hidden content is ever addressed. The Delete still fans out: that is
	 * the dispatcher's job (see Test_Dispatcher::test_delete_dispatches_without_audience),
	 * not something the object's audience has to encode.
	 *
	 * The visibility-meta rows guard the gate alignment: the scheduler emits a
	 * Delete whenever a post is not `is_post_publicly_queryable()`, so the
	 * transformer must redact on that same predicate — not just status/password.
	 *
	 * @dataProvider data_hidden_post_states
	 *
	 * @covers ::to_object
	 *
	 * @param array $post_args Post fields that hide the post.
	 * @param array $meta      Post meta that hides the post.
	 */
	public function test_hidden_post_is_content_free_tombstone( $post_args, $meta ) {
		/*
		 * The non-publish branch is short-circuited when ACTIVITYPUB_PREVIEW is
		 * defined; the password/visibility rows are unaffected and always
		 * exercise the gate, so only skip the pure status rows in that case.
		 */
		if ( empty( $post_args['post_password'] ) && empty( $meta ) && defined( 'ACTIVITYPUB_PREVIEW' ) && ACTIVITYPUB_PREVIEW ) {
			$this->markTestSkipped( 'ACTIVITYPUB_PREVIEW was defined by an earlier test; status-only gate cannot be exercised here.' );
		}

		$mention_uri    = 'https://remote.example/users/bob';
		$mention_filter = static function () use ( $mention_uri ) {
			return array( '@bob@remote.example' => $mention_uri );
		};
		\add_filter( 'activitypub_extract_mentions', $mention_filter );

		$post_id = \wp_insert_post(
			\array_merge(
				array(
					'post_author'  => 1,
					'post_title'   => 'Hidden mention probe',
					'post_content' => 'Hello @bob@remote.example, secret body.',
					'post_status'  => 'publish',
				),
				$post_args
			)
		);

		foreach ( $meta as $key => $value ) {
			\update_post_meta( $post_id, $key, $value );
		}

		\update_post_meta( $post_id, 'geo_latitude', '52.52' );
		\update_post_meta( $post_id, 'geo_longitude', '13.405' );
		\update_post_meta( $post_id, 'geo_public', '1' );

		// Cookie-bypass case: the helper would return false, transformer must still redact.
		\add_filter( 'post_password_required', '__return_false' );

		try {
			$object = Post::transform( get_post( $post_id ) )->to_object();

			// Content-free by type.
			$this->assertSame( 'Tombstone', $object->get_type(), 'A hidden post must serialize as a Tombstone.' );
			$this->assertEmpty( $object->get_content(), 'content must be omitted for a hidden post.' );
			$this->assertEmpty( $object->get_tag(), 'tags/mentions must be omitted for a hidden post.' );
			$this->assertEmpty( $object->get_location(), 'location must be omitted for a hidden post.' );

			// The permalink is preserved so the tombstone registry can resolve it.
			$this->assertNotEmpty( $object->get_url(), 'The Tombstone must keep the permalink, not drop it.' );

			$audience = \array_merge( (array) $object->get_to(), (array) $object->get_cc() );
			// Addressed publicly so the teardown broadcasts...
			$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $audience, 'A hidden federated post is torn down publicly.' );
			// ...but never to an actor named only in the now-hidden content.
			$this->assertNotContains( $mention_uri, $audience, 'A hidden post must not address actors mentioned only in hidden content.' );
		} finally {
			\remove_filter( 'post_password_required', '__return_false' );
			\remove_filter( 'activitypub_extract_mentions', $mention_filter );
		}
	}

	/**
	 * Data provider: every way a post can be non-public, as ( post fields, post meta ).
	 *
	 * @return array[]
	 */
	public function data_hidden_post_states() {
		return array(
			'password protected' => array( array( 'post_password' => 'fed-secret-pass' ), array() ),
			'draft'              => array( array( 'post_status' => 'draft' ), array() ),
			'pending'            => array( array( 'post_status' => 'pending' ), array() ),
			'private status'     => array( array( 'post_status' => 'private' ), array() ),
			'visibility local'   => array( array(), array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ) ),
			'visibility private' => array( array(), array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE ) ),
		);
	}

	/**
	 * The happy path of the Fediverse Preview: an authorized preview of an
	 * unpublished post must render the real content, NOT a Tombstone.
	 *
	 * `is_post_publicly_queryable()` treats a draft/pending post as queryable
	 * during a `?preview=true` request from a user who can edit it, which flips
	 * `is_redacted()` to false so `to_object()` transforms the post normally.
	 * The router test only proves routing into the preview template; this asserts
	 * the transformer output the template actually renders.
	 *
	 * @covers ::to_object
	 */
	public function test_authorized_preview_of_unpublished_post_renders_real_content() {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $editor_id,
				'post_status'  => 'draft',
				'post_title'   => 'Preview Title',
				'post_content' => 'PREVIEW-VISIBLE-BODY that is long enough to be a real note.',
			)
		);

		// Authorized preview context: the editor previewing their own draft.
		// `is_post_publicly_queryable()` reads the `preview` query var (gating the
		// Tombstone), while the content getter reads WordPress's `is_preview()`
		// flag (gating draft-content rendering) — set both, as the real request does.
		\wp_set_current_user( $editor_id );
		$this->go_to( \home_url( '?p=' . $post_id . '&preview=true' ) );
		\set_query_var( 'preview', true );
		$GLOBALS['wp_query']->is_preview = true;

		try {
			$object = Post::transform( get_post( $post_id ) )->to_object();

			$this->assertNotSame( 'Tombstone', $object->get_type(), 'An authorized preview must not be redacted to a Tombstone.' );
			$this->assertStringContainsString( 'PREVIEW-VISIBLE-BODY', (string) $object->get_content(), 'The preview must render the real post content.' );
		} finally {
			\wp_set_current_user( 0 );
		}
	}

	/**
	 * Test content visibility.
	 *
	 * @covers ::to_object
	 */
	public function test_content_visibility() {
		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test content visibility',
				'post_status'  => 'publish',
			)
		);

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		$this->assertFalse( \Activitypub\is_post_disabled( $post_id ) );
		$object = Post::transform( get_post( $post_id ) )->to_object();
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_to() );

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );

		$this->assertFalse( \Activitypub\is_post_disabled( $post_id ) );
		$object = Post::transform( get_post( $post_id ) )->to_object();
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_cc() );

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );

		// The post was federated on insert, so making it local soft-deletes it:
		// to_object() yields a public Tombstone (the Delete payload that tears the
		// copy down everywhere), not a content object addressed to its new audience.
		$object = Post::transform( get_post( $post_id ) )->to_object();
		$this->assertSame( 'Tombstone', $object->get_type() );
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_to() );
	}

	/**
	 * Test different variations of Attachment parsing.
	 *
	 * @covers ::to_object
	 */
	public function test_block_attachments_with_fallback() {
		$attachment_id  = $this->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		$attachment_src = \wp_get_attachment_image_src( $attachment_id );

		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => sprintf(
					'<!-- wp:image {"id": %1$d,"sizeSlug":"large"} --><figure class="wp-block-image"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure><!-- /wp:image -->',
					$attachment_id,
					$attachment_src[0]
				),
				'post_status'  => 'publish',
			)
		);

		$object = Post::transform( get_post( $post_id ) )->to_object();

		$this->assertEquals(
			array(
				array(
					'type'      => 'Image',
					'url'       => $attachment_src[0],
					'mediaType' => 'image/jpeg',
				),
			),
			$object->get_attachment()
		);

		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => sprintf(
					'<p>this is a photo</p><p><img src="%2$s" alt="" class="wp-image-%1$d"/></p>',
					$attachment_id,
					$attachment_src[0]
				),
				'post_status'  => 'publish',
			)
		);

		$object = Post::transform( get_post( $post_id ) )->to_object();

		$this->assertEquals(
			array(
				array(
					'type'      => 'Image',
					'url'       => $attachment_src[0],
					'mediaType' => 'image/jpeg',
				),
			),
			$object->get_attachment()
		);

		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test get_media_from_blocks adds alt text to existing images.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_adds_alt_text_to_existing_images() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="test.jpg" alt="Test alt text" /></figure><!-- /wp:image -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(
				array(
					'id'  => 123,
					'alt' => '',
				),
			),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertSame( 'Test alt text', $result['image'][0]['alt'] );
		$this->assertSame( 123, $result['image'][0]['id'] );
	}

	/**
	 * Test get_attachments with zero max_media_attachments.
	 *
	 * @covers ::get_attachment
	 */
	public function test_get_attachments_with_zero_max_media_attachments() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="test.jpg" alt="Test alt text" /></figure><!-- /wp:image -->',
			)
		);

		\update_post_meta( $post_id, 'activitypub_max_image_attachments', 0 );
		$post = get_post( $post_id );

		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_attachment' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result = $method->invoke( $transformer );

		$this->assertEmpty( $result );
		$this->assertFalse( (bool) \did_filter( 'activitypub_attachment_ids' ) );

		\delete_post_meta( $post_id, 'activitypub_max_image_attachments' );

		// Create a new transformer instance to avoid cached attachment result.
		$transformer = new Post( $post );
		$result      = $method->invoke( $transformer );
		$this->assertTrue( (bool) \did_filter( 'activitypub_attachment_ids' ) );
	}

	/**
	 * Test get_media_from_blocks adds new image when none exist.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_adds_new_image() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="test.jpg" alt="Test alt text" /></figure><!-- /wp:image -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 1, $result['image'] );
		$this->assertSame( 123, $result['image'][0]['id'] );
		$this->assertSame( 'Test alt text', $result['image'][0]['alt'] );
	}

	/**
	 * Test get_media_from_blocks handles multiple blocks correctly.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_handles_multiple_blocks() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="test1.jpg" alt="Test alt 1" /></figure><!-- /wp:image --><!-- wp:image {"id":456} --><figure class="wp-block-image"><img src="test2.jpg" alt="Test alt 2" /></figure><!-- /wp:image -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 2, $result['image'] );
		$this->assertSame( 123, $result['image'][0]['id'] );
		$this->assertSame( 'Test alt 1', $result['image'][0]['alt'] );
		$this->assertSame( 456, $result['image'][1]['id'] );
		$this->assertSame( 'Test alt 2', $result['image'][1]['alt'] );
	}

	/**
	 * Test get_media_from_blocks extracts poster from video blocks.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_extracts_video_poster() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:video {"id":789} --><figure class="wp-block-video"><video controls poster="https://example.com/poster.jpg" src="https://example.com/video.mp4"></video></figure><!-- /wp:video -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 1, $result['video'] );
		$this->assertSame( 789, $result['video'][0]['id'] );
		$this->assertSame( 'https://example.com/poster.jpg', $result['video'][0]['icon'] );
	}

	/**
	 * Test get_media_from_blocks handles video blocks without poster.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_video_without_poster() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:video {"id":789} --><figure class="wp-block-video"><video controls src="https://example.com/video.mp4"></video></figure><!-- /wp:video -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 1, $result['video'] );
		$this->assertSame( 789, $result['video'][0]['id'] );
		$this->assertArrayNotHasKey( 'icon', $result['video'][0] );
	}

	/**
	 * Test get_media_from_blocks extracts the image from a Media & Text block.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_extracts_media_text_image() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:media-text {"mediaId":263,"mediaType":"image"} --><div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="https://example.com/img.png" alt="Media text alt" class="wp-image-263 size-full"/></figure><div class="wp-block-media-text__content"></div></div><!-- /wp:media-text -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 1, $result['image'], 'A Media & Text image must be extracted.' );
		$this->assertSame( 263, $result['image'][0]['id'] );
		$this->assertSame( 'Media text alt', $result['image'][0]['alt'] );
	}

	/**
	 * Test get_media_from_blocks updates alt in place when the Media & Text image
	 * was already collected, so the duplicate is not dropped and its alt lost.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_media_text_updates_existing_image_alt() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:media-text {"mediaId":263,"mediaType":"image"} --><div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="https://example.com/img.png" alt="Media text alt" class="wp-image-263"/></figure></div><!-- /wp:media-text -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		// Seed the same ID without alt, as an earlier featured-image/gallery pass would.
		$media = array(
			'image' => array( array( 'id' => 263 ) ),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 1, $result['image'], 'A duplicate Media & Text image ID must not add a second entry.' );
		$this->assertSame( 'Media text alt', $result['image'][0]['alt'], 'The existing entry must gain the Media & Text alt text.' );
	}

	/**
	 * Test get_media_from_blocks extracts the video (and its poster) from a Media
	 * & Text block.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_extracts_media_text_video() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:media-text {"mediaId":555,"mediaType":"video"} --><div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><video controls poster="https://example.com/poster.jpg" src="https://example.com/video.mp4"></video></figure><div class="wp-block-media-text__content"></div></div><!-- /wp:media-text -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 1, $result['video'], 'A Media & Text video must be extracted.' );
		$this->assertSame( 555, $result['video'][0]['id'] );
		$this->assertSame( 'https://example.com/poster.jpg', $result['video'][0]['icon'], 'The video poster must be kept as the icon.' );
		$this->assertEmpty( $result['image'], 'A Media & Text video must not be added as an image.' );
	}

	/**
	 * Test get_icon method.
	 *
	 * @covers ::get_icon
	 */
	public function test_get_icon() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content',
			)
		);
		$post    = get_post( $post_id );

		// Create test image.
		$attachment_id = $this->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );

		// Set up reflection method.
		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_icon' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Test with featured image.
		set_post_thumbnail( $post_id, $attachment_id );

		$transformer = new Post( $post );
		$icon        = $method->invoke( $transformer );

		$this->assertIsArray( $icon );
		$this->assertEquals( 'Image', $icon['type'] );
		$this->assertArrayHasKey( 'url', $icon );
		$this->assertArrayHasKey( 'mediaType', $icon );
		$this->assertEquals( get_post_mime_type( $attachment_id ), $icon['mediaType'] );

		// Test with site icon.
		delete_post_thumbnail( $post_id );
		update_option( 'site_icon', $attachment_id );

		$icon = $method->invoke( $transformer );

		$this->assertIsArray( $icon );
		$this->assertEquals( 'Image', $icon['type'] );
		$this->assertArrayHasKey( 'url', $icon );
		$this->assertArrayHasKey( 'mediaType', $icon );
		$this->assertEquals( get_post_mime_type( $attachment_id ), $icon['mediaType'] );

		// Test with alt text.
		$alt_text = 'Test Alt Text';
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		$icon = $method->invoke( $transformer );

		$this->assertIsArray( $icon );
		$this->assertEquals( 'Image', $icon['type'] );
		$this->assertArrayHasKey( 'name', $icon );
		$this->assertEquals( $alt_text, $icon['name'] );

		// Test without any images.
		delete_post_thumbnail( $post_id );
		delete_option( 'site_icon' );
		delete_post_meta( $attachment_id, '_wp_attachment_image_alt' );

		$icon = $method->invoke( $transformer );
		$this->assertNull( $icon );

		// Test with invalid image.
		set_post_thumbnail( $post_id, 99999 );
		$icon = $method->invoke( $transformer );
		$this->assertNull( $icon );
	}

	/**
	 * Saves an attachment.
	 *
	 * @param string $file      The file name to create attachment object for.
	 * @param int    $parent_id ID of the post to attach the file to.
	 * @return int|\WP_Error The attachment ID on success. The value 0 or WP_Error on failure.
	 */
	public function create_upload_object( $file, $parent_id = 0 ) {
		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$dest = dirname( $file ) . DIRECTORY_SEPARATOR . 'test-temp.jpg';
		$fs   = new \WP_Filesystem_Direct( array() );
		$fs->copy( $file, $dest );

		$file = $dest;

		$file_array = array(
			'name'     => wp_basename( $file ),
			'tmp_name' => $file,
		);

		$upload = wp_handle_sideload( $file_array, array( 'test_form' => false ) );

		$type = '';
		if ( ! empty( $upload['type'] ) ) {
			$type = $upload['type'];
		} else {
			$mime = wp_check_filetype( $upload['file'] );
			if ( $mime ) {
				$type = $mime['type'];
			}
		}

		$attachment = array(
			'post_title'     => wp_basename( $upload['file'] ),
			'post_content'   => '',
			'post_type'      => 'attachment',
			'post_parent'    => $parent_id,
			'post_mime_type' => $type,
			'guid'           => $upload['url'],
		);

		// Save the data.
		$id = wp_insert_attachment( $attachment, $upload['file'], $parent_id );
		wp_update_attachment_metadata( $id, @wp_generate_attachment_metadata( $id, $upload['file'] ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $id;
	}

	/**
	 * Test preview property generation.
	 *
	 * @covers ::get_preview
	 */
	public function test_preview_property() {
		// Create a test post of type "Article".
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'Test Article',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_status'  => 'publish',
			)
		);

		$transformer = new Post( $post );
		$preview     = $transformer->get_preview();

		// Check if the preview for an Article is correctly generated.
		$this->assertIsArray( $preview );
		$this->assertEquals( 'Note', $preview['type'] );
		$this->assertArrayHasKey( 'content', $preview );
		$this->assertNotEmpty( $preview['content'] );

		// Create a test post of type "Note" (short content).
		$note_post = self::factory()->post->create_and_get(
			array(
				'post_title'   => '',
				'post_content' => 'Short note content',
				'post_status'  => 'publish',
			)
		);

		$note_transformer = new Post( $note_post );
		$note_preview     = $note_transformer->get_preview();

		// Check if the preview for a Note is null.
		$this->assertNull( $note_preview );
	}

	/**
	 * Test get_content method.
	 *
	 * @covers ::get_content
	 */
	public function test_get_content() {
		$follow_me = '<!-- wp:activitypub/follow-me -->
<div class="wp-block-activitypub-follow-me"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Follow</a></div>
<!-- /wp:button --></div>
<!-- /wp:activitypub/follow-me -->';

		$followers = '<!-- wp:activitypub/followers -->
<div class="wp-block-activitypub-followers"><!-- wp:heading {"level":3,"placeholder":"Fediverse Followers"} -->
<h3 class="wp-block-heading">Fediverse Followers</h3>
<!-- /wp:heading --></div>
<!-- /wp:activitypub/followers -->';

		$reactions = '<!-- wp:activitypub/reactions -->
<div class="wp-block-activitypub-reactions"><!-- wp:heading {"level":3,"placeholder":"Fediverse Reactions"} -->
<h3 class="wp-block-heading">Fediverse Reactions</h3>
<!-- /wp:heading --></div>
<!-- /wp:activitypub/reactions -->';

		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => implode( PHP_EOL, array( $follow_me, $followers, $reactions ) ),
				'post_title'   => '',
			)
		);

		$object      = new Base_Object();
		$get_content = new \ReflectionMethod( Post::class, 'transform_object_properties' );

		if ( \PHP_VERSION_ID < 80100 ) {
			$get_content->setAccessible( true );
		}

		$object = $get_content->invoke( new Post( $post ), $object );

		$this->assertEmpty( $object->get_content() );
	}

	/**
	 * Test that reply blocks get transformed into mention links when they are the first block in a post.
	 *
	 * @covers ::to_object
	 * @covers ::get_content
	 */
	public function test_reply_block_transforms_to_mention_link_when_first_block() {
		// Set up a filter to intercept HTTP requests for remote objects.
		$filter_remote_object = function ( $pre, $url ) {
			if ( 'https://example.com/posts/123' === $url ) {
				return array(
					'attributedTo' => 'https://example.com/users/author',
				);
			} elseif ( 'https://example.com/users/author' === $url ) {
				return array(
					'preferredUsername' => 'author',
					'url'               => 'https://example.com/users/author',
				);
			}
			return $pre;
		};

		add_filter( 'activitypub_pre_http_get_remote_object', $filter_remote_object, 10, 2 );

		// Create a post with a reply block as the first block.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Reply Post',
				'post_content' => '<!-- wp:activitypub/reply {"url":"https://example.com/posts/123"} /-->' . PHP_EOL .
									'<!-- wp:paragraph --><p>This is a test post with a reply block first.</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
			)
		);

		// Transform the post to an ActivityPub object.
		$post   = get_post( $post_id );
		$object = Post::transform( $post )->to_object();

		// Assert that the reply block was transformed into a mention link.
		// Note: clean_html() strips class from <p> and the mention link doesn't include u-in-reply-to class.
		$this->assertStringContainsString( '<p><a rel="mention ugc" href="https://example.com/posts/123" title="@author@example.com">@author</a></p>', $object->get_content() );

		// Clean up.
		remove_filter( 'activitypub_pre_http_get_remote_object', $filter_remote_object );
	}

	/**
	 * Test that reply blocks do not get transformed into mention links when they are not the first block in a post.
	 *
	 * @covers ::to_object
	 * @covers ::get_content
	 */
	public function test_reply_block_not_transformed_when_not_first_block() {
		// Create a post with a reply block that is not the first block.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Reply Post',
				'post_content' => '<!-- wp:paragraph --><p>This is a test post with a reply block that is not first.</p><!-- /wp:paragraph -->' . PHP_EOL .
									'<!-- wp:activitypub/reply {"url":"https://example.com/posts/123"} /-->',
				'post_status'  => 'publish',
			)
		);

		// Transform the post to an ActivityPub object.
		$post   = get_post( $post_id );
		$object = Post::transform( $post )->to_object();

		// Get the content from the object.
		$content = $object->get_content();

		// Assert that the reply block was not transformed into a mention link.
		// Note: clean_html() strips target and non-allowed attributes per FEP-b2b8.
		$this->assertStringContainsString( '<div><p><a title="This post is a response to the referenced content." href="https://example.com/posts/123" class="u-in-reply-to">&#8620;example.com/posts/123</a></p></div>', $content );
	}

	/**
	 * Test that when multiple reply blocks exist, only the first one gets transformed to @-mention.
	 *
	 * @covers ::to_object
	 * @covers ::get_content
	 */
	public function test_multiple_reply_blocks_only_first_becomes_mention() {
		// Set up a filter to intercept HTTP requests for remote objects.
		$filter_remote_object = function ( $pre, $url ) {
			if ( 'https://example.com/posts/123' === $url ) {
				return array(
					'attributedTo' => 'https://example.com/users/author1',
				);
			} elseif ( 'https://example.com/users/author1' === $url ) {
				return array(
					'preferredUsername' => 'author1',
					'url'               => 'https://example.com/users/author1',
				);
			} elseif ( 'https://other.site/posts/456' === $url ) {
				return array(
					'attributedTo' => 'https://other.site/users/author2',
				);
			} elseif ( 'https://other.site/users/author2' === $url ) {
				return array(
					'preferredUsername' => 'author2',
					'url'               => 'https://other.site/users/author2',
				);
			}
			return $pre;
		};

		add_filter( 'activitypub_pre_http_get_remote_object', $filter_remote_object, 10, 2 );

		// Create a post with two reply blocks - first one should become @-mention, second should remain as link.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Multiple Reply Post',
				'post_content' => '<!-- wp:activitypub/reply {"url":"https://example.com/posts/123"} /-->' . PHP_EOL .
									'<!-- wp:paragraph --><p>This is a response to the first post, but also references another post.</p><!-- /wp:paragraph -->' . PHP_EOL .
									'<!-- wp:activitypub/reply {"url":"https://other.site/posts/456"} /-->',
				'post_status'  => 'publish',
			)
		);

		// Transform the post to an ActivityPub object.
		$post   = get_post( $post_id );
		$object = Post::transform( $post )->to_object();

		// Get the content from the object.
		$content = $object->get_content();

		// Assert that the first reply block was transformed into a mention link.
		// Note: clean_html() strips class from <p> and the mention link doesn't include u-in-reply-to class.
		$this->assertStringContainsString( '<p><a rel="mention ugc" href="https://example.com/posts/123" title="@author1@example.com">@author1</a></p>', $content );

		// Assert that the second reply block was NOT transformed into a mention link (should remain as regular reply block).
		// Note: clean_html() strips target and non-allowed attributes per FEP-b2b8.
		$this->assertStringContainsString( '<div><p><a title="This post is a response to the referenced content." href="https://other.site/posts/456" class="u-in-reply-to">&#8620;other.site/posts/456</a></p></div>', $content );

		// Clean up.
		remove_filter( 'activitypub_pre_http_get_remote_object', $filter_remote_object );
	}

	/*
	 * =========================
	 * get_interaction_policy()
	 * =========================
	 */

	/**
	 * Helper to create a published post with a fresh author.
	 *
	 * @return \WP_Post
	 */
	private function create_test_post() {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Interaction Policy Test',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_author'  => $user_id,
			)
		);
		return get_post( $post_id );
	}

	/**
	 * Test policy generation for the 'anyone' permission.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_anyone() {
		$post = $this->create_test_post();
		\update_post_meta( $post->ID, 'activitypub_interaction_policy_quote', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );

		// Quote policy is always stored to preserve user intent when global default changes.
		$stored = \get_post_meta( $post->ID, 'activitypub_interaction_policy_quote', true );
		$this->assertSame( ACTIVITYPUB_INTERACTION_POLICY_ANYONE, $stored, 'Meta value should be stored to preserve user intent.' );

		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		$this->assertIsArray( $policy, 'Policy should be array.' );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertSame(
			array(
				'automaticApproval' => 'https://www.w3.org/ns/activitystreams#Public',
				'always'            => 'https://www.w3.org/ns/activitystreams#Public',
			),
			$policy['canQuote'],
			"'anyone' permission should map to public policy."
		);
	}

	/**
	 * Test fallback to global default when no quote permission meta is set.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_no_meta_fallback() {
		$post        = $this->create_test_post();
		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		// Default global setting is 'anyone'.
		$this->assertIsArray( $policy, 'Should fall back to global default policy when no meta set.' );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertSame(
			array(
				'automaticApproval' => 'https://www.w3.org/ns/activitystreams#Public',
				'always'            => 'https://www.w3.org/ns/activitystreams#Public',
			),
			$policy['canQuote'],
			'No meta should fall back to global default (anyone) policy.'
		);
	}

	/**
	 * Test fallback to global default 'followers' when no quote permission meta is set.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_no_meta_fallback_to_global_followers() {
		\update_option( 'activitypub_default_quote_policy', ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS );

		$post        = $this->create_test_post();
		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		$this->assertIsArray( $policy, 'Should fall back to global default policy when no meta set.' );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertArrayHasKey( 'automaticApproval', $policy['canQuote'] );
		$this->assertStringContainsString( 'followers', $policy['canQuote']['automaticApproval'], 'Should use global default followers policy.' );

		\delete_option( 'activitypub_default_quote_policy' );
	}

	/**
	 * Test policy generation for the 'followers' permission.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_followers() {
		$post = $this->create_test_post();
		update_post_meta( $post->ID, 'activitypub_interaction_policy_quote', ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS );

		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		$this->assertIsArray( $policy );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertArrayHasKey( 'automaticApproval', $policy['canQuote'] );
		$this->assertStringContainsString( '/followers', $policy['canQuote']['automaticApproval'], 'Followers permission should point to followers collection.' );
	}

	/**
	 * Test policy generation for the 'me' permission across actor modes.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_me_actor_modes() {
		$post = $this->create_test_post();
		update_post_meta( $post->ID, 'activitypub_interaction_policy_quote', ACTIVITYPUB_INTERACTION_POLICY_ME );

		$actor_modes = array(
			ACTIVITYPUB_ACTOR_MODE,
			ACTIVITYPUB_BLOG_MODE,
			ACTIVITYPUB_ACTOR_AND_BLOG_MODE,
		);

		foreach ( $actor_modes as $mode ) {
			update_option( 'activitypub_actor_mode', $mode );
			$transformer = new Post( get_post( $post->ID ) ); // fresh instance.
			$policy      = $transformer->get_interaction_policy();

			$this->assertIsArray( $policy, 'Policy should be array for mode ' . $mode );
			$this->assertArrayHasKey( 'canQuote', $policy );
			$this->assertArrayHasKey( 'automaticApproval', $policy['canQuote'] );

			$auto = $policy['canQuote']['automaticApproval'];
			if ( ACTIVITYPUB_ACTOR_AND_BLOG_MODE === $mode ) {
				$this->assertIsArray( $auto, 'Actor+Blog mode should return an array of IDs.' );
				$this->assertCount( 2, $auto, 'Actor+Blog mode should supply two IDs.' );
			} else {
				$this->assertIsString( $auto, 'Single mode should return a single ID string.' );
			}
		}

		// Cleanup.
		delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Ensure invalid permission values fall back to 'anyone' policy.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_invalid_value_returns_null() {
		$post = $this->create_test_post();
		\update_post_meta( $post->ID, 'activitypub_interaction_policy_quote', 'not-a-valid-permission' );

		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		$this->assertIsArray( $policy, 'Invalid permission should fall back to anyone policy.' );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertSame(
			array(
				'automaticApproval' => 'https://www.w3.org/ns/activitystreams#Public',
				'always'            => 'https://www.w3.org/ns/activitystreams#Public',
			),
			$policy['canQuote'],
			'Invalid permission should fall back to anyone (public) policy.'
		);
	}

	/**
	 * Test get_post_content_template with various post types and reply scenarios.
	 *
	 * Tests how the template is generated for different post types (Article, Note)
	 * and reply configurations, as well as option fallback scenarios.
	 *
	 * @dataProvider wordpress_post_format_template_provider
	 * @covers ::get_post_content_template
	 *
	 * @param array       $post_data           The post data to create.
	 * @param string      $expected_template   The expected template string.
	 * @param string      $object_type         The activitypub_object_type option value.
	 * @param string|null $custom_post_content The activitypub_custom_post_content option value (null to delete).
	 * @param string      $description         Description of the test case.
	 */
	public function test_get_post_content_template_with_scenarios( $post_data, $expected_template, $object_type, $custom_post_content, $description ) {
		// Set object type.
		\update_option( 'activitypub_object_type', $object_type );

		// Set or delete custom post content option.
		if ( null === $custom_post_content ) {
			\delete_option( 'activitypub_custom_post_content' );
		} else {
			\update_option( 'activitypub_custom_post_content', $custom_post_content );
		}

		// Mock mentions extraction if the post content contains mention patterns.
		$content         = $post_data['post_content'] ?? '';
		$mentions_filter = null;
		if ( \preg_match( '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/i', $content ) ) {
			$mentions_filter = function ( $mentions, $post_content ) {
				// Extract all mention patterns from content.
				\preg_match_all( '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/i', $post_content, $all_matches );
				foreach ( $all_matches[0] as $match ) {
					$mentions[ $match ] = 'https://example.com/' . \ltrim( $match, '@' );
				}
				return $mentions;
			};
			\add_filter( 'activitypub_extract_mentions', $mentions_filter, 10, 2 );
		}

		$post = self::factory()->post->create_and_get( $post_data );

		$transformer = new Post( $post );
		$reflection  = new \ReflectionClass( Post::class );
		$method      = $reflection->getMethod( 'get_post_content_template' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$template = $method->invoke( $transformer );

		// Clean up mentions filter if it was added.
		if ( $mentions_filter ) {
			\remove_filter( 'activitypub_extract_mentions', $mentions_filter );
		}

		// All wordpress-post-format templates should contain [ap_content].
		if ( 'wordpress-post-format' === $object_type ) {
			$this->assertStringContainsString( '[ap_content]', $template, $description . ' - should contain [ap_content]' );
		}

		$this->assertSame( $expected_template, $template, $description );
	}

	/**
	 * Data provider for get_post_content_template tests with various scenarios.
	 *
	 * @return array Each test case contains:
	 *               - post_data: The post data to create
	 *               - expected_template: The expected template string
	 *               - object_type: The activitypub_object_type option value
	 *               - custom_post_content: The activitypub_custom_post_content option value (null to delete)
	 *               - description: Description of the test case
	 */
	public function wordpress_post_format_template_provider() {
		return array(
			'Article type'                => array(
				array(
					'post_title'   => 'Test Article',
					'post_content' => str_repeat( 'Long content. ', 100 ),
					'post_status'  => 'publish',
				),
				'[ap_content]',
				'wordpress-post-format',
				'[ap_title]\n\n[ap_content]',
				'wordpress-post-format should override custom template for Article type.',
			),
			'Note type without reply'     => array(
				array(
					'post_title'   => '',
					'post_content' => 'Short note',
					'post_status'  => 'publish',
				),
				'[ap_title type="html"][ap_content]',
				'wordpress-post-format',
				'[ap_title]\n\n[ap_content]',
				'wordpress-post-format should add title for Note type without reply.',
			),
			'Note type with reply block'  => array(
				array(
					'post_title'   => '',
					'post_content' => '<!-- wp:activitypub/reply {"url":"https://example.com/posts/123"} /-->' . PHP_EOL .
										'<!-- wp:paragraph --><p>This is a reply note.</p><!-- /wp:paragraph -->',
					'post_status'  => 'publish',
				),
				'[ap_content]',
				'wordpress-post-format',
				'[ap_title]\n\n[ap_content]',
				'wordpress-post-format should not add title for Note type when it is a reply.',
			),
			'Note type with mentions'     => array(
				array(
					'post_title'   => '',
					'post_content' => 'Short note mentioning @activitypub.blog@activitypub.blog',
					'post_status'  => 'publish',
				),
				'[ap_content]',
				'wordpress-post-format',
				null,
				'wordpress-post-format should not add title for Note type when it has mentions.',
			),
			'fallback_with_false_option'  => array(
				array(
					'post_title'   => 'Interaction Policy Test',
					'post_content' => 'Content',
					'post_status'  => 'publish',
				),
				ACTIVITYPUB_CUSTOM_POST_CONTENT,
				'Article',
				null,
				'False option should fall back to ACTIVITYPUB_CUSTOM_POST_CONTENT constant.',
			),
			'uses_custom_option_when_set' => array(
				array(
					'post_title'   => 'Interaction Policy Test',
					'post_content' => 'Content',
					'post_status'  => 'publish',
				),
				'[ap_title]\n\n[ap_content]\n\n[ap_hashtags]',
				'Article',
				'[ap_title]\n\n[ap_content]\n\n[ap_hashtags]',
				'Should use custom template option when set.',
			),
			'fallback_with_empty_option'  => array(
				array(
					'post_title'   => 'Interaction Policy Test',
					'post_content' => 'Content',
					'post_status'  => 'publish',
				),
				ACTIVITYPUB_CUSTOM_POST_CONTENT,
				'Article',
				'',
				'Empty activitypub_custom_post_content option should fall back to ACTIVITYPUB_CUSTOM_POST_CONTENT constant.',
			),
		);
	}

	/**
	 * Test get_location method with public geodata.
	 *
	 * @covers ::get_location
	 */
	public function test_get_location_with_public_geodata() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post with Location',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Set geodata.
		\update_post_meta( $post_id, 'geo_latitude', '52.5200' );
		\update_post_meta( $post_id, 'geo_longitude', '13.4050' );
		\update_post_meta( $post_id, 'geo_address', 'Berlin, Germany' );
		\update_post_meta( $post_id, 'geo_public', '1' );

		$post        = get_post( $post_id );
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_location' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$location = $method->invoke( $transformer );

		$this->assertIsArray( $location );
		$this->assertSame( 'Place', $location['type'] );
		$this->assertSame( 52.52, $location['latitude'] );
		$this->assertSame( 13.405, $location['longitude'] );
		$this->assertSame( 'Berlin, Germany', $location['name'] );
	}

	/**
	 * Test get_location method without geo_public set (defaults to public).
	 *
	 * @covers ::get_location
	 */
	public function test_get_location_without_geo_public_defaults_public() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post with Location',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Set geodata without geo_public.
		\update_post_meta( $post_id, 'geo_latitude', '48.8566' );
		\update_post_meta( $post_id, 'geo_longitude', '2.3522' );

		$post        = get_post( $post_id );
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_location' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$location = $method->invoke( $transformer );

		$this->assertIsArray( $location );
		$this->assertSame( 'Place', $location['type'] );
		$this->assertSame( 48.8566, $location['latitude'] );
		$this->assertSame( 2.3522, $location['longitude'] );
		$this->assertArrayNotHasKey( 'name', $location );
	}

	/**
	 * Test get_location method with private geodata (geo_public = 0).
	 *
	 * @covers ::get_location
	 */
	public function test_get_location_with_private_geodata() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post with Private Location',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Set geodata as private.
		\update_post_meta( $post_id, 'geo_latitude', '40.7128' );
		\update_post_meta( $post_id, 'geo_longitude', '-74.0060' );
		\update_post_meta( $post_id, 'geo_public', '0' );

		$post        = get_post( $post_id );
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_location' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$location = $method->invoke( $transformer );

		$this->assertNull( $location, 'Location should be null when geo_public is 0.' );
	}

	/**
	 * Test get_location method without geodata.
	 *
	 * @covers ::get_location
	 */
	public function test_get_location_without_geodata() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post without Location',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		$post        = get_post( $post_id );
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_location' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$location = $method->invoke( $transformer );

		$this->assertNull( $location, 'Location should be null when no geodata is present.' );
	}

	/**
	 * Test get_location method with zero coordinates (Equator/Prime Meridian).
	 *
	 * @covers ::get_location
	 */
	public function test_get_location_with_zero_coordinates() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post at Null Island',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Set geodata to 0,0 (Null Island - valid coordinates).
		\update_post_meta( $post_id, 'geo_latitude', '0' );
		\update_post_meta( $post_id, 'geo_longitude', '0' );

		$post        = get_post( $post_id );
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_location' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$location = $method->invoke( $transformer );

		$this->assertIsArray( $location, 'Location should not be null for coordinates 0,0' );
		$this->assertSame( 'Place', $location['type'] );
		$this->assertSame( 0.0, $location['latitude'] );
		$this->assertSame( 0.0, $location['longitude'] );
	}

	/**
	 * Test get_location method with only latitude (missing longitude).
	 *
	 * @covers ::get_location
	 */
	public function test_get_location_with_incomplete_geodata() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post with Incomplete Location',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Set only latitude.
		\update_post_meta( $post_id, 'geo_latitude', '51.5074' );

		$post        = get_post( $post_id );
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_location' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$location = $method->invoke( $transformer );

		$this->assertNull( $location, 'Location should be null when longitude is missing.' );
	}

	/**
	 * Test get_location filter.
	 *
	 * @covers ::get_location
	 */
	public function test_get_location_filter() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post with Location Filter',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Set geodata.
		\update_post_meta( $post_id, 'geo_latitude', '35.6762' );
		\update_post_meta( $post_id, 'geo_longitude', '139.6503' );

		// Add a filter to modify the location.
		$filter = function ( $place ) {
			$place['name']     = 'Tokyo, Japan';
			$place['altitude'] = 40;
			return $place;
		};
		\add_filter( 'activitypub_post_location', $filter, 10, 3 );

		$post        = get_post( $post_id );
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_location' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$location = $method->invoke( $transformer );

		$this->assertIsArray( $location );
		$this->assertSame( 'Tokyo, Japan', $location['name'] );
		$this->assertSame( 40, $location['altitude'] );

		\remove_filter( 'activitypub_post_location', $filter );
	}

	/**
	 * Test that location is included in to_object output.
	 *
	 * @covers ::to_object
	 */
	public function test_location_in_to_object() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post with Location',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Set geodata.
		\update_post_meta( $post_id, 'geo_latitude', '51.5074' );
		\update_post_meta( $post_id, 'geo_longitude', '-0.1278' );
		\update_post_meta( $post_id, 'geo_address', 'London, UK' );

		$post   = get_post( $post_id );
		$object = Post::transform( $post )->to_object();

		$location = $object->get_location();

		$this->assertIsArray( $location );
		$this->assertSame( 'Place', $location['type'] );
		$this->assertSame( 51.5074, $location['latitude'] );
		$this->assertSame( -0.1278, $location['longitude'] );
		$this->assertSame( 'London, UK', $location['name'] );
	}

	/**
	 * Test get_exif_data method returns null when no EXIF data.
	 *
	 * @covers \Activitypub\Transformer\Base::get_exif_data
	 */
	public function test_get_exif_data_returns_null_when_no_exif() {
		$attachment_id = $this->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );

		// Clear image_meta to simulate no EXIF data.
		$metadata               = \wp_get_attachment_metadata( $attachment_id );
		$metadata['image_meta'] = array();
		\wp_update_attachment_metadata( $attachment_id, $metadata );

		$post        = self::factory()->post->create_and_get();
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_exif_data' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$exif = $method->invoke( $transformer, $attachment_id );

		$this->assertNull( $exif, 'Should return null when no EXIF data is available.' );

		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test get_exif_data method returns EXIF data in FEP-ee3a format.
	 *
	 * @covers \Activitypub\Transformer\Base::get_exif_data
	 */
	public function test_get_exif_data_returns_fep_format() {
		$attachment_id = $this->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );

		// Set up mock EXIF data.
		$metadata               = \wp_get_attachment_metadata( $attachment_id );
		$metadata['image_meta'] = array(
			'created_timestamp' => 1704067200, // 2024-01-01 00:00:00 UTC.
			'shutter_speed'     => 0.01,       // 1/100.
			'aperture'          => 2.8,
			'focal_length'      => 50,
			'iso'               => 400,
			'camera'            => 'Canon EOS R5',
		);
		\wp_update_attachment_metadata( $attachment_id, $metadata );

		$post        = self::factory()->post->create_and_get();
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_exif_data' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$exif_data = $method->invoke( $transformer, $attachment_id );

		$this->assertIsArray( $exif_data, 'Should return an array.' );
		$this->assertCount( 6, $exif_data, 'Should have 6 PropertyValue objects.' );

		// Convert to associative array for easier testing.
		$exif_by_name = array();
		foreach ( $exif_data as $prop ) {
			$this->assertArrayHasKey( '@type', $prop, 'Each item should have @type.' );
			$this->assertSame( 'PropertyValue', $prop['@type'], '@type should be PropertyValue.' );
			$this->assertArrayHasKey( 'name', $prop, 'Each item should have name.' );
			$this->assertArrayHasKey( 'value', $prop, 'Each item should have value.' );
			$exif_by_name[ $prop['name'] ] = $prop['value'];
		}

		// Check FEP-ee3a field names and value formats.
		$this->assertArrayHasKey( 'DateTime', $exif_by_name, 'Should contain DateTime.' );
		$this->assertArrayHasKey( 'ExposureTime', $exif_by_name, 'Should contain ExposureTime.' );
		$this->assertArrayHasKey( 'FNumber', $exif_by_name, 'Should contain FNumber.' );
		$this->assertArrayHasKey( 'FocalLength', $exif_by_name, 'Should contain FocalLength.' );
		$this->assertArrayHasKey( 'PhotographicSensitivity', $exif_by_name, 'Should contain PhotographicSensitivity.' );
		$this->assertArrayHasKey( 'Model', $exif_by_name, 'Should contain Model.' );

		// Check value formats per FEP-ee3a.
		$this->assertSame( '2024:01:01 00:00:00', $exif_by_name['DateTime'], 'DateTime should be EXIF format.' );
		$this->assertSame( '1/100', $exif_by_name['ExposureTime'], 'ExposureTime should be fraction format.' );
		$this->assertSame( 'f/2.8', $exif_by_name['FNumber'], 'FNumber should be f/X.X format.' );
		$this->assertSame( '50', $exif_by_name['FocalLength'], 'FocalLength should be numeric string.' );
		$this->assertSame( '400', $exif_by_name['PhotographicSensitivity'], 'PhotographicSensitivity should be string.' );
		$this->assertSame( 'Canon EOS R5', $exif_by_name['Model'], 'Model should be camera name.' );

		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test get_exif_data with long exposure (>= 1 second).
	 *
	 * @covers \Activitypub\Transformer\Base::get_exif_data
	 */
	public function test_get_exif_data_long_exposure() {
		$attachment_id = $this->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );

		// Set up mock EXIF data with long exposure.
		$metadata               = \wp_get_attachment_metadata( $attachment_id );
		$metadata['image_meta'] = array(
			'shutter_speed' => 2.5, // 2.5 seconds.
		);
		\wp_update_attachment_metadata( $attachment_id, $metadata );

		$post        = self::factory()->post->create_and_get();
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_exif_data' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$exif_data = $method->invoke( $transformer, $attachment_id );

		$this->assertCount( 1, $exif_data, 'Should have 1 PropertyValue object.' );
		$this->assertSame( 'ExposureTime', $exif_data[0]['name'], 'Should be ExposureTime.' );
		$this->assertSame( '2.5', $exif_data[0]['value'], 'Long exposure should be shown as seconds.' );

		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test that EXIF data is included in image attachments.
	 *
	 * @covers \Activitypub\Transformer\Base::transform_attachment
	 */
	public function test_transform_attachment_includes_exif() {
		$attachment_id  = $this->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		$attachment_src = \wp_get_attachment_image_src( $attachment_id );

		// Set up mock EXIF data.
		$metadata               = \wp_get_attachment_metadata( $attachment_id );
		$metadata['image_meta'] = array(
			'iso'    => 800,
			'camera' => 'Nikon Z6',
		);
		\wp_update_attachment_metadata( $attachment_id, $metadata );

		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => sprintf(
					'<!-- wp:image {"id": %1$d,"sizeSlug":"large"} --><figure class="wp-block-image"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure><!-- /wp:image -->',
					$attachment_id,
					$attachment_src[0]
				),
				'post_status'  => 'publish',
			)
		);

		$object      = Post::transform( get_post( $post_id ) )->to_object();
		$attachments = $object->get_attachment();

		$this->assertCount( 1, $attachments, 'Should have one attachment.' );
		$this->assertArrayHasKey( 'exifData', $attachments[0], 'Attachment should include exifData array.' );
		$this->assertIsArray( $attachments[0]['exifData'], 'exifData should be an array of PropertyValue objects.' );
		$this->assertCount( 2, $attachments[0]['exifData'], 'Should have 2 PropertyValue objects.' );

		// Convert to associative array for easier testing.
		$exif_by_name = array();
		foreach ( $attachments[0]['exifData'] as $prop ) {
			$exif_by_name[ $prop['name'] ] = $prop['value'];
		}

		$this->assertArrayHasKey( 'PhotographicSensitivity', $exif_by_name, 'EXIF should include ISO.' );
		$this->assertArrayHasKey( 'Model', $exif_by_name, 'EXIF should include camera model.' );
		$this->assertSame( '800', $exif_by_name['PhotographicSensitivity'], 'ISO should be 800.' );
		$this->assertSame( 'Nikon Z6', $exif_by_name['Model'], 'Camera model should be Nikon Z6.' );

		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test activitypub_image_exif filter.
	 *
	 * @covers \Activitypub\Transformer\Base::get_exif_data
	 */
	public function test_get_exif_data_filter() {
		$attachment_id = $this->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );

		// Set up mock EXIF data.
		$metadata               = \wp_get_attachment_metadata( $attachment_id );
		$metadata['image_meta'] = array(
			'camera' => 'Test Camera',
		);
		\wp_update_attachment_metadata( $attachment_id, $metadata );

		// Add filter to extend EXIF data with a Make property.
		$filter = function ( $exif_data, $image_meta, $id ) use ( $attachment_id ) {
			$this->assertSame( $attachment_id, $id, 'Filter should receive correct attachment ID.' );
			$exif_data[] = array(
				'@type' => 'PropertyValue',
				'name'  => 'Make',
				'value' => 'Test Manufacturer',
			);
			return $exif_data;
		};
		\add_filter( 'activitypub_image_exif', $filter, 10, 3 );

		$post        = self::factory()->post->create_and_get();
		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_exif_data' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$exif_data = $method->invoke( $transformer, $attachment_id );

		$this->assertCount( 2, $exif_data, 'Should have 2 PropertyValue objects (Model + Make).' );

		// Convert to associative array for easier testing.
		$exif_by_name = array();
		foreach ( $exif_data as $prop ) {
			$exif_by_name[ $prop['name'] ] = $prop['value'];
		}

		$this->assertArrayHasKey( 'Make', $exif_by_name, 'Filter should be able to add Make property.' );
		$this->assertSame( 'Test Manufacturer', $exif_by_name['Make'], 'Filter should set Make value.' );

		\remove_filter( 'activitypub_image_exif', $filter );
		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test that duplicate attachments are filtered after activitypub_attachment_ids filter.
	 *
	 * This ensures that when plugins add attachments via the filter (like Classic Editor),
	 * duplicates are properly removed to prevent the same image appearing multiple times.
	 *
	 * @covers ::get_attachment
	 */
	public function test_duplicate_attachments_filtered_after_filter() {
		// Create an image attachment.
		$attachment_id  = $this->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		$attachment_url = \wp_get_attachment_url( $attachment_id );

		// Create a post with the image as featured image.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post with Duplicate Image',
				'post_content' => sprintf( '<p>Test content with image</p><img src="%s" />', $attachment_url ),
				'post_status'  => 'publish',
			)
		);

		// Set the same image as featured image.
		\set_post_thumbnail( $post_id, $attachment_id );

		// Add a filter that simulates Classic Editor behavior - adding attached images.
		$filter = function ( $attachments ) use ( $attachment_id ) {
			// Simulate Classic Editor adding the same attachment again.
			$attachments[] = array( 'id' => $attachment_id );
			return $attachments;
		};
		\add_filter( 'activitypub_attachment_ids', $filter, 10, 1 );

		$post   = get_post( $post_id );
		$object = Post::transform( $post )->to_object();

		// Get the attachments.
		$attachments = $object->get_attachment();

		// Remove the filter.
		\remove_filter( 'activitypub_attachment_ids', $filter );

		// Clean up.
		\delete_post_thumbnail( $post_id );
		\wp_delete_attachment( $attachment_id, true );

		// There should be only ONE attachment, not duplicates.
		$this->assertCount( 1, $attachments, 'Duplicate attachments should be filtered out' );
		$this->assertSame( $attachment_url, $attachments[0]['url'] );
	}

	/**
	 * Test to_tombstone returns a Tombstone object with correct type.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_returns_tombstone_type() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post for Tombstone',
				'post_content' => 'Content for tombstone test',
				'post_status'  => 'publish',
			)
		);

		$post        = get_post( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();

		$this->assertInstanceOf( Base_Object::class, $tombstone );
		$this->assertSame( 'Tombstone', $tombstone->get_type() );
	}

	/**
	 * Test to_tombstone includes the original post ID.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_includes_id() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post for Tombstone ID',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		$post        = get_post( $post_id );
		$permalink   = \get_permalink( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();

		$this->assertSame( $permalink, $tombstone->get_id() );
	}

	/**
	 * Test to_tombstone includes formerType from original post.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_includes_former_type() {
		// Create an Article type post (long content with title).
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Article for Tombstone',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_status'  => 'publish',
			)
		);

		$post        = get_post( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();

		$this->assertSame( 'Article', $tombstone->get_former_type() );
	}

	/**
	 * Test to_tombstone includes formerType for Note.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_includes_former_type_note() {
		// Create a Note type post (no title).
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => '',
				'post_content' => 'Short note content',
				'post_status'  => 'publish',
			)
		);

		$post        = get_post( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();

		$this->assertSame( 'Note', $tombstone->get_former_type() );
	}

	/**
	 * Test to_tombstone includes deleted timestamp when meta is set.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_includes_deleted_timestamp_when_meta_set() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post for Tombstone Timestamp',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Set the deleted timestamp meta.
		$deleted_time = time();
		\update_post_meta( $post_id, 'activitypub_deleted_at', $deleted_time );

		$post        = get_post( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();

		$deleted = $tombstone->get_deleted();
		$this->assertNotNull( $deleted );
		// Check it's a valid timestamp format.
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $deleted );
	}

	/**
	 * Test to_tombstone does not include deleted timestamp without meta.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_no_deleted_timestamp_without_meta() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post for Tombstone No Timestamp',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		$post        = get_post( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();

		// Without the meta, deleted should be null.
		$this->assertNull( $tombstone->get_deleted() );
	}

	/**
	 * Test to_tombstone includes published timestamp in array output.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_includes_published() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post for Tombstone Timestamps',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		$post        = get_post( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();
		$array       = $tombstone->to_array();

		// Published should be in the array output.
		$this->assertArrayHasKey( 'published', $array );
		// Check it's a valid timestamp format.
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['published'] );
	}

	/**
	 * Test to_tombstone for trashed post preserves cached canonical URL.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_trashed_post_uses_cached_url() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post for Trash',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		$post      = get_post( $post_id );
		$permalink = \get_permalink( $post_id );

		// First transform to cache the URL.
		$transformer = new Post( $post );
		$transformer->to_object();

		// Now trash the post.
		\wp_trash_post( $post_id );

		// Get the trashed post.
		$trashed_post = get_post( $post_id );
		$transformer  = new Post( $trashed_post );
		$tombstone    = $transformer->to_tombstone();

		// The cached URL should be used, not the trashed permalink.
		$this->assertSame( $permalink, $tombstone->get_id() );
	}

	/**
	 * Test to_tombstone to_array output has correct structure.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_to_array_structure() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post for Tombstone Array',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_status'  => 'publish',
			)
		);

		// Set the deleted timestamp meta to include deleted in output.
		\update_post_meta( $post_id, 'activitypub_deleted_at', time() );

		$post        = get_post( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();
		$array       = $tombstone->to_array();

		$this->assertArrayHasKey( '@context', $array );
		$this->assertArrayHasKey( 'type', $array );
		$this->assertArrayHasKey( 'id', $array );
		$this->assertArrayHasKey( 'formerType', $array );
		$this->assertArrayHasKey( 'deleted', $array );

		$this->assertSame( 'Tombstone', $array['type'] );
		$this->assertSame( 'Article', $array['formerType'] );
	}

	/**
	 * Test to_tombstone to_array without deleted meta.
	 *
	 * @covers ::to_tombstone
	 */
	public function test_to_tombstone_to_array_without_deleted() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post for Tombstone Array No Deleted',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_status'  => 'publish',
			)
		);

		$post        = get_post( $post_id );
		$transformer = new Post( $post );
		$tombstone   = $transformer->to_tombstone();
		$array       = $tombstone->to_array();

		$this->assertArrayHasKey( '@context', $array );
		$this->assertArrayHasKey( 'type', $array );
		$this->assertArrayHasKey( 'id', $array );
		$this->assertArrayHasKey( 'formerType', $array );
		// Without meta, deleted should not be in array.
		$this->assertArrayNotHasKey( 'deleted', $array );

		$this->assertSame( 'Tombstone', $array['type'] );
		$this->assertSame( 'Article', $array['formerType'] );
	}
}
