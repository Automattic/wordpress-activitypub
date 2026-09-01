<?php
/**
 * Test Remote Posts Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Cache\Media;
use Activitypub\Collection\Remote_Posts;
use Activitypub\Post_Types;

use function Activitypub\object_to_uri;

/**
 * Remote Posts Collection Test Class.
 *
 * @coversDefaultClass \Activitypub\Collection\Remote_Posts
 */
class Test_Remote_Posts extends \WP_UnitTestCase {

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		// Register required post types.
		Post_Types::register_remote_actors_post_type();
		Post_Types::register_post_post_type();

		// Initialize cache to register hooks.
		Media::init();

		// Mock HTTP requests for Remote_Actors::fetch_by_uri.
		\add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );

		// Also hook into the ActivityPub-specific filter to bypass URL validation.
		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_remote_object' ), 10, 2 );

		// Short-circuit file downloads in tests by providing pre-downloaded files.
		\add_filter( 'activitypub_pre_download_url', array( $this, 'mock_file_download' ), 10, 3 );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		\remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ) );
		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_remote_object' ) );
		\remove_filter( 'activitypub_pre_download_url', array( $this, 'mock_file_download' ) );

		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Render post content to trigger lazy media caching.
	 *
	 * Sets up global post context, renders blocks, then cleans up.
	 *
	 * @param \WP_Post $the_post The post to render.
	 */
	protected function render_post_content( $the_post ) {
		global $post;
		$post = $the_post;
		\setup_postdata( $the_post );
		\do_blocks( $the_post->post_content );
		\wp_reset_postdata();
	}

	/**
	 * Invoke the private activity_to_post() method.
	 *
	 * @param mixed $activity The activity to convert.
	 *
	 * @return array|\WP_Error The post array or WP_Error.
	 */
	protected function invoke_activity_to_post( $activity ) {
		$reflection = new \ReflectionClass( Remote_Posts::class );
		$method     = $reflection->getMethod( 'activity_to_post' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null, $activity );
	}

	/**
	 * Mock remote object fetching to bypass URL validation.
	 *
	 * @param mixed  $response      The response to return.
	 * @param string $url_or_object The URL or object being fetched.
	 * @return mixed The mocked response or null to continue.
	 */
	public function mock_remote_object( $response, $url_or_object ) {
		if ( 'https://example.com/users/testuser' === object_to_uri( $url_or_object ) ) {
			return array(
				'id'                => 'https://example.com/users/testuser',
				'type'              => 'Person',
				'name'              => 'Test Actor',
				'preferredUsername' => 'testuser',
				'summary'           => 'A test actor',
				'url'               => 'https://example.com/users/testuser',
				'inbox'             => 'https://example.com/users/testuser/inbox',
				'outbox'            => 'https://example.com/users/testuser/outbox',
			);
		}

		return $response;
	}

	/**
	 * Mock file downloads by providing pre-downloaded test files.
	 *
	 * This short-circuits the entire download process, avoiding DNS lookups
	 * and URL validation that may fail in CI environments.
	 *
	 * @param array|null $result The pre-download result.
	 * @param string     $url    The URL being downloaded.
	 * @param string     $type   The cache type.
	 * @return array|null Array with file and mime_type for test URLs, null otherwise.
	 */
	public function mock_file_download( $result, $url, $type ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required for filter signature.
		// Handle all example.com image URLs.
		if ( str_starts_with( $url, 'https://example.com/' ) && preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $url ) ) {
			// Copy test asset to temp file.
			$tmp_file = \wp_tempnam( 'test-image.jpg' );
			copy( AP_TESTS_DIR . '/data/assets/test.jpg', $tmp_file );

			return array(
				'file'      => $tmp_file,
				'mime_type' => 'image/jpeg',
			);
		}

		return $result;
	}

	/**
	 * Mock HTTP requests for remote actor fetching and attachment downloads.
	 *
	 * @param mixed  $response The response to return.
	 * @param array  $parsed_args The parsed arguments.
	 * @param string $url The URL being requested.
	 * @return mixed The mocked response or original response.
	 */
	public function mock_http_request( $response, $parsed_args, $url ) {
		if ( 'https://example.com/users/testuser' === $url ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'id'                => 'https://example.com/users/testuser',
						'type'              => 'Person',
						'name'              => 'Test Actor',
						'preferredUsername' => 'testuser',
						'summary'           => 'A test actor',
						'url'               => 'https://example.com/users/testuser',
						'inbox'             => 'https://example.com/users/testuser/inbox',
						'outbox'            => 'https://example.com/users/testuser/outbox',
					)
				),
			);
		}

		if ( 'https://nonexistent.com/users/unknown' === $url ) {
			return new \WP_Error( 'http_request_failed', 'Could not resolve host' );
		}

		// Mock attachment downloads.
		if ( 'https://example.com/image.jpg' === $url && isset( $parsed_args['filename'] ) ) {
			copy( AP_TESTS_DIR . '/data/assets/test.jpg', $parsed_args['filename'] );

			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array( 'content-type' => 'image/jpeg' ),
			);
		}

		return $response;
	}

	/**
	 * Test adding an object to the collection.
	 *
	 * @covers ::add
	 */
	public function test_add() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/123',
				'type'         => 'Note',
				'name'         => 'Test Object',
				'content'      => '<p>This is a test object content</p>',
				'summary'      => 'Test summary',
				'attributedTo' => 'https://example.com/users/testuser',
				'published'    => '2023-01-01T12:00:00Z',
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( 'Test Object', $result->post_title );
		$this->assertEquals( Remote_Posts::POST_TYPE, $result->post_type );
		$this->assertEquals( 'publish', $result->post_status );
		$this->assertEquals( 'https://example.com/objects/123', $result->guid );
	}

	/**
	 * Test that add() refuses a Create whose actor does not match the object's
	 * attributedTo. Only the actor is bound to the HTTP signature, so without this
	 * a signed Create could store a post attributed to a victim actor.
	 *
	 * @covers ::add
	 */
	public function test_add_rejects_actor_attribution_mismatch() {
		$activity = array(
			'actor'  => 'https://attacker.example/users/evil',
			'object' => array(
				'id'           => 'https://example.com/objects/spoofed',
				'type'         => 'Note',
				'name'         => 'Spoofed',
				'content'      => '<p>spoofed</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertWPError( $result, 'A Create whose actor does not match attributedTo must be rejected.' );
		$this->assertEquals( 'activitypub_create_unauthorized', $result->get_error_code() );
		$this->assertWPError( Remote_Posts::get_by_guid( 'https://example.com/objects/spoofed' ), 'No post must be created for a mismatched attribution.' );
	}

	/**
	 * Test that add() refuses a Create whose object id is on a different host than
	 * the actor. The post is cached under its own id, so an unbound id would let a
	 * signed Create cache a post under another host's object URL.
	 *
	 * @covers ::add
	 */
	public function test_add_rejects_cross_host_object_id() {
		$canonical_object_id = 'https://canonical.example/objects/123';

		$activity = array(
			'actor'  => 'https://other.example/users/other',
			'object' => array(
				'id'           => $canonical_object_id,
				'type'         => 'Note',
				'name'         => 'Fake',
				'content'      => '<p>fabricated</p>',
				'attributedTo' => 'https://other.example/users/other',
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertWPError( $result, 'A Create whose object id host differs from the actor host must be rejected.' );
		$this->assertEquals( 'activitypub_create_host_mismatch', $result->get_error_code() );
		$this->assertWPError( Remote_Posts::get_by_guid( $canonical_object_id ), 'No post must be cached under another host object id.' );
	}

	/**
	 * Test updating an existing object.
	 *
	 * @covers ::update
	 */
	public function test_update() {
		// First, create an object.
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/456',
				'type'         => 'Note',
				'name'         => 'Original Title',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$original_post = Remote_Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $original_post );

		// Now update it.
		$update_activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'      => 'https://example.com/objects/456',
				'type'    => 'Note',
				'name'    => 'Updated Title',
				'content' => '<p>Updated content</p>',
			),
		);

		$updated_post = Remote_Posts::update( $update_activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $updated_post );
		$this->assertEquals( 'Updated Title', $updated_post->post_title );
		$this->assertStringContainsString( 'Updated content', $updated_post->post_content );
		$this->assertEquals( $original_post->ID, $updated_post->ID );
	}

	/**
	 * Test that update() refuses an Update from an actor that does not own the post.
	 *
	 * Regression: update() resolved the post by object.id and overwrote it without
	 * confirming the activity actor authored it, letting a remote server overwrite
	 * another host's cached post.
	 *
	 * @covers ::update
	 */
	public function test_update_rejects_foreign_actor() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/owned',
				'type'         => 'Note',
				'name'         => 'Original Title',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$original_post = Remote_Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $original_post );

		// A different actor attempts to overwrite the post.
		$foreign_update = array(
			'actor'  => 'https://attacker.example/users/evil',
			'object' => array(
				'id'      => 'https://example.com/objects/owned',
				'type'    => 'Note',
				'name'    => 'Hacked Title',
				'content' => '<p>Hacked content</p>',
			),
		);

		$result = Remote_Posts::update( $foreign_update, 1 );

		$this->assertWPError( $result, 'A foreign actor must not be able to update the post.' );
		$this->assertEquals( 'activitypub_update_forbidden', $result->get_error_code() );
		$this->assertEquals( 'Original Title', \get_post( $original_post->ID )->post_title, 'Post must be unchanged.' );

		// The post's own actor can still update it.
		$owner_update = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'      => 'https://example.com/objects/owned',
				'type'    => 'Note',
				'name'    => 'Owner Updated Title',
				'content' => '<p>Owner updated content</p>',
			),
		);

		$updated_post = Remote_Posts::update( $owner_update, 1 );
		$this->assertInstanceOf( '\WP_Post', $updated_post );
		$this->assertEquals( 'Owner Updated Title', $updated_post->post_title, 'The post author should still be able to update.' );
	}

	/**
	 * Test updating a non-existent object.
	 *
	 * @covers ::update
	 */
	public function test_update_nonexistent() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'      => 'https://example.com/objects/nonexistent',
				'type'    => 'Note',
				'name'    => 'Updated Title',
				'content' => '<p>Updated content</p>',
			),
		);

		$result = Remote_Posts::update( $activity, 1 );

		$this->assertInstanceOf( '\WP_Error', $result );
	}

	/**
	 * Test getting an object by GUID.
	 *
	 * @covers ::get_by_guid
	 */
	public function test_get_by_guid() {
		// Create an object.
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/789',
				'type'         => 'Note',
				'name'         => 'Test Object',
				'content'      => '<p>Test content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Remote_Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $post );

		// Test retrieval.
		$retrieved_post = Remote_Posts::get_by_guid( 'https://example.com/objects/789' );

		$this->assertInstanceOf( '\WP_Post', $retrieved_post );
		$this->assertEquals( $post->ID, $retrieved_post->ID );
		$this->assertEquals( 'Test Object', $retrieved_post->post_title );
	}

	/**
	 * An object whose ID contains an ampersand must be found again by that ID.
	 *
	 * WordPress peers publish object IDs like `?p=123&foo=bar`, and WordPress escapes an
	 * explicitly passed GUID, so the round trip has to survive that escaping.
	 *
	 * @covers ::add
	 * @covers ::get_by_guid
	 */
	public function test_get_by_guid_with_ampersand() {
		$object_id = 'https://example.com/?post_type=post&p=789';

		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => $object_id,
				'type'         => 'Note',
				'name'         => 'Ampersand Object',
				'content'      => '<p>Test content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Remote_Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $post );

		$retrieved_post = Remote_Posts::get_by_guid( $object_id );

		$this->assertInstanceOf( '\WP_Post', $retrieved_post, 'An object ID containing an ampersand must be found again.' );
		$this->assertEquals( $post->ID, $retrieved_post->ID );
	}

	/**
	 * Test getting a non-existent object by GUID.
	 *
	 * @covers ::get_by_guid
	 */
	public function test_get_by_guid_nonexistent() {
		$result = Remote_Posts::get_by_guid( 'https://example.com/objects/nonexistent' );

		$this->assertInstanceOf( '\WP_Error', $result );
	}

	/**
	 * Test activity to post conversion.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post() {
		$activity = array(
			'id'        => 'https://example.com/objects/test',
			'type'      => 'Note',
			'name'      => 'Test Title',
			'content'   => '<p>Test content with <strong>HTML</strong></p>',
			'summary'   => 'Test summary',
			'published' => '2023-01-01T12:00:00Z',
		);

		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Remote_Posts::class );
		$method     = $reflection->getMethod( 'activity_to_post' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		try {
			$result = $method->invoke( null, $activity );
		} catch ( \Exception $exception ) {
			$result = $exception;
		}

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test Title', $result['post_title'] );
		$this->assertEquals( 'Test summary', $result['post_excerpt'] );
		$this->assertEquals( Remote_Posts::POST_TYPE, $result['post_type'] );
		$this->assertEquals( 'publish', $result['post_status'] );
		$this->assertEquals( 'https://example.com/objects/test', $result['guid'] );
		$this->assertStringContainsString( 'Test content', $result['post_content'] );
	}

	/**
	 * Test that a missing summary falls back to an excerpt built from the content.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_builds_excerpt_from_content() {
		$activity = array(
			'id'        => 'https://example.com/objects/no-summary',
			'type'      => 'Note',
			'content'   => '<p>Hello federated world</p>',
			'published' => '2023-01-01T12:00:00Z',
		);

		$method = new \ReflectionMethod( Remote_Posts::class, 'activity_to_post' );
		$method->setAccessible( true );
		$result = $method->invoke( null, $activity );

		$this->assertStringContainsString( 'Hello federated world', $result['post_excerpt'] );
	}

	/**
	 * Test that an empty content fallback does not read the global post's excerpt.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_does_not_leak_global_post_excerpt() {
		$local_post      = self::factory()->post->create_and_get( array( 'post_excerpt' => 'LOCAL EXCERPT' ) );
		$GLOBALS['post'] = $local_post;

		$activity = array(
			'id'        => 'https://example.com/objects/empty-content',
			'type'      => 'Note',
			'content'   => '',
			'published' => '2023-01-01T12:00:00Z',
		);

		$method = new \ReflectionMethod( Remote_Posts::class, 'activity_to_post' );
		$method->setAccessible( true );
		$result = $method->invoke( null, $activity );

		unset( $GLOBALS['post'] );

		$this->assertStringNotContainsString( 'LOCAL EXCERPT', $result['post_excerpt'] );
	}

	/**
	 * Test that a title or summary containing a bare `<` keeps its text.
	 *
	 * `wp_strip_all_tags()` hands the string to PHP's `strip_tags()`, which reads a bare
	 * `<` as the start of a tag that never closes and drops the rest with it, so
	 * "A <3 shape" becomes "A". `sanitize_text_field()` escapes the `<` first, so the
	 * text survives while real markup still goes.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_keeps_text_with_bare_less_than() {
		$activity = array(
			'id'        => 'https://example.com/objects/less-than',
			'type'      => 'Note',
			'name'      => 'A <3 shape carved in wood',
			'content'   => '<p>Test content</p>',
			'summary'   => 'Rated <3 stars by nobody',
			'published' => '2023-01-01T12:00:00Z',
		);

		$result = $this->invoke_activity_to_post( $activity );

		$this->assertStringContainsString( 'shape carved in wood', $result['post_title'], 'The title must not be truncated at the bare "<".' );
		$this->assertStringContainsString( 'stars by nobody', $result['post_excerpt'], 'The summary must not be truncated at the bare "<".' );
	}

	/**
	 * Test that backslashes in a remote title or body survive storage.
	 *
	 * `wp_insert_post()` and `wp_update_post()` unslash what they are given, so returning
	 * unslashed data means a title like `C:\Users\foo` is stored as `C:Usersfoo`.
	 *
	 * @covers ::add
	 */
	public function test_add_keeps_backslashes() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/backslash',
				'type'         => 'Note',
				'name'         => 'C:\\Users\\foo',
				'content'      => '<p>Path: C:\\Users\\foo</p>',
				'summary'      => 'About C:\\Users\\foo',
				'attributedTo' => 'https://example.com/users/testuser',
				'published'    => '2023-01-01T12:00:00Z',
			),
		);

		$post_id = Remote_Posts::add( $activity, 1 );
		$post    = \get_post( $post_id );

		$this->assertSame( 'C:\\Users\\foo', $post->post_title, 'Backslashes must survive in the title.' );
		$this->assertStringContainsString( 'C:\\Users\\foo', $post->post_content, 'Backslashes must survive in the content.' );
		$this->assertSame( 'About C:\\Users\\foo', $post->post_excerpt, 'Backslashes must survive in the excerpt.' );
	}

	/**
	 * Test that markup in a title or summary is still removed.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_strips_markup_from_plain_text_fields() {
		$activity = array(
			'id'        => 'https://example.com/objects/markup',
			'type'      => 'Note',
			'name'      => 'Title<script>alert(1)</script>',
			'content'   => '<p>Test content</p>',
			'summary'   => 'Summary<img src=x onerror="alert(1)">',
			'published' => '2023-01-01T12:00:00Z',
		);

		$result = $this->invoke_activity_to_post( $activity );

		$this->assertStringNotContainsString( '<script', $result['post_title'], 'Script tags must not survive in the title.' );
		$this->assertStringNotContainsString( 'alert(1)', $result['post_title'], 'Script bodies must not survive in the title.' );
		$this->assertStringNotContainsString( '<img', $result['post_excerpt'], 'Tags must not survive in the summary.' );
		$this->assertStringNotContainsString( 'onerror', $result['post_excerpt'], 'Event handlers must not survive in the summary.' );
	}

	/**
	 * Test activity to post conversion with invalid data.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_invalid() {
		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Remote_Posts::class );
		$method     = $reflection->getMethod( 'activity_to_post' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		try {
			$result = $method->invoke( null, 'invalid_data' );
		} catch ( \Exception $exception ) {
			$result = $exception;
		}

		$this->assertInstanceOf( '\WP_Error', $result );
	}

	/**
	 * Test activity to post conversion with minimal data.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_minimal() {
		$activity = array(
			'type'    => 'Note',
			'content' => '<p>Minimal content for excerpt generation</p>',
		);

		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Remote_Posts::class );
		$method     = $reflection->getMethod( 'activity_to_post' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		try {
			$result = $method->invoke( null, $activity );
		} catch ( \Exception $exception ) {
			$result = $exception;
		}

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['post_title'] );
		$this->assertStringContainsString( 'Minimal content', $result['post_content'] );
		$this->assertStringContainsString( 'Minimal content', $result['post_excerpt'] );
		$this->assertEquals( Remote_Posts::POST_TYPE, $result['post_type'] );
		$this->assertEquals( 'publish', $result['post_status'] );
	}

	/**
	 * Test that published timestamp is preserved when creating posts.
	 *
	 * @covers ::activity_to_post
	 * @covers ::add
	 */
	public function test_preserves_published_timestamp() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/timestamp-test',
				'type'         => 'Note',
				'name'         => 'Timestamp Test',
				'content'      => '<p>Test content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'published'    => '2023-06-15T14:30:00Z',
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( '2023-06-15 14:30:00', $result->post_date_gmt );
		$this->assertEquals( \get_date_from_gmt( '2023-06-15 14:30:00' ), $result->post_date );
	}

	/**
	 * Test that activity_to_post handles missing content gracefully.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_missing_content() {
		$activity = array(
			'type'    => 'Note',
			'name'    => 'Title Only',
			'summary' => 'Summary text',
		);

		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Remote_Posts::class );
		$method     = $reflection->getMethod( 'activity_to_post' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		try {
			$result = $method->invoke( null, $activity );
		} catch ( \Exception $exception ) {
			$result = $exception;
		}

		$this->assertIsArray( $result );
		$this->assertEquals( 'Title Only', $result['post_title'] );
		$this->assertEquals( '', $result['post_content'] );
		$this->assertEquals( 'Summary text', $result['post_excerpt'] );
	}

	/**
	 * Test adding an object with multiple recipients.
	 *
	 * @covers ::add
	 * @covers ::get_recipients
	 */
	public function test_add_with_multiple_recipients() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/multi-user',
				'type'         => 'Note',
				'name'         => 'Multi-User Post',
				'content'      => '<p>This post is for multiple users</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$result = Remote_Posts::add( $activity, array( 1, 2, 3 ) );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( 'Multi-User Post', $result->post_title );

		// Verify all recipients were added.
		$recipients = Remote_Posts::get_recipients( $result->ID );
		$this->assertCount( 3, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
	}

	/**
	 * Test adding an object with attachments.
	 *
	 * @covers ::add
	 */
	public function test_add_with_attachments() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/with-attachment',
				'type'         => 'Note',
				'name'         => 'Post with Image',
				// Real ActivityPub content includes img tags.
				'content'      => '<p>Test content</p><p><img src="https://example.com/image.jpg" alt="Test Image"/></p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Test Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( 'Post with Image', $result->post_title );

		// Verify content has the media block with original URL.
		$this->assertStringContainsString( '<!-- wp:activitypub/image', $result->post_content );
		$this->assertStringContainsString( 'https://example.com/image.jpg', $result->post_content );

		// Render the content to trigger lazy caching.
		$this->render_post_content( $result );

		// Verify file was created in activitypub directory after rendering.
		$upload_dir = \wp_upload_dir();
		$file_dir   = $upload_dir['basedir'] . Media::BASE_DIR_POSTS . $result->ID;
		$this->assertTrue( file_exists( $file_dir ), 'ActivityPub directory should exist' );

		// Verify file exists.
		$files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $files, 'One file should be created' );
	}

	/**
	 * Test attachments not in content are appended.
	 *
	 * @covers ::add
	 */
	public function test_add_appends_attachment_not_in_content() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/attachment-appended',
				'type'         => 'Note',
				'name'         => 'Post with Extra Attachment',
				// Content has no image.
				'content'      => '<p>Just text content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/extra-image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Extra Image Alt Text',
						'type'      => 'Image',
					),
				),
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );

		// Attachment should be appended to content with alt text.
		$this->assertStringContainsString( 'https://example.com/extra-image.jpg', $result->post_content );
		$this->assertStringContainsString( 'alt="Extra Image Alt Text"', $result->post_content );

		// Should be wrapped in media block.
		$this->assertStringContainsString( '<!-- wp:activitypub/image', $result->post_content );
	}

	/**
	 * Test attachments already in content are not duplicated.
	 *
	 * @covers ::add
	 */
	public function test_add_does_not_duplicate_attachment_in_content() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/no-duplicate',
				'type'         => 'Note',
				'name'         => 'No Duplicate',
				// Content already has the image.
				'content'      => '<p>Text</p><img src="https://example.com/same-image.jpg" alt="Same" />',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/same-image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Same Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );

		// Image should appear only once (not duplicated).
		// Count img tags with this src, not URL strings (URL appears in both JSON attr and img src).
		$count = \preg_match_all( '/<img[^>]+src=["\']https:\/\/example\.com\/same-image\.jpg["\']/', $result->post_content );
		$this->assertSame( 1, $count, 'Image tag should appear only once' );
	}

	/**
	 * Test updating an object with new attachments.
	 *
	 * @covers ::update
	 */
	public function test_update_with_new_attachments() {
		// Create initial post without attachments.
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/update-test',
				'type'         => 'Note',
				'name'         => 'Original Post',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$original_post = Remote_Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $original_post );

		// Verify initial recipient.
		$recipients = Remote_Posts::get_recipients( $original_post->ID );
		$this->assertCount( 1, $recipients );
		$this->assertContains( 1, $recipients );

		// Now update it with multiple new recipients.
		$update_activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'      => 'https://example.com/objects/update-test',
				'type'    => 'Note',
				'name'    => 'Updated Title',
				'content' => '<p>Updated content</p>',
			),
		);

		$updated_post = Remote_Posts::update( $update_activity, array( 2, 3, 4 ) );

		$this->assertInstanceOf( '\WP_Post', $updated_post );
		$this->assertEquals( 'Updated Title', $updated_post->post_title );

		// Verify all recipients are present (original + new ones).
		$recipients = Remote_Posts::get_recipients( $updated_post->ID );
		$this->assertCount( 4, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
		$this->assertContains( 4, $recipients );
	}

	/**
	 * Test updating with duplicate recipients doesn't create duplicates.
	 *
	 * @covers ::update
	 * @covers ::get_recipients
	 * @covers ::has_recipient
	 */
	public function test_update_prevents_duplicate_recipients() {
		// Create an object.
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/no-duplicates',
				'type'         => 'Note',
				'name'         => 'No Duplicates',
				'content'      => '<p>Test deduplication</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Remote_Posts::add( $activity, array( 1, 2 ) );
		$this->assertInstanceOf( '\WP_Post', $post );

		// Update with overlapping recipients.
		$update_activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'      => 'https://example.com/objects/no-duplicates',
				'type'    => 'Note',
				'name'    => 'Updated',
				'content' => '<p>Updated</p>',
			),
		);

		$updated_post = Remote_Posts::update( $update_activity, array( 2, 3 ) );

		$this->assertInstanceOf( '\WP_Post', $updated_post );

		// Verify no duplicates - should have 1, 2, 3 (not 2 twice).
		$recipients = Remote_Posts::get_recipients( $updated_post->ID );
		$this->assertCount( 3, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );

		// Verify has_recipient works.
		$this->assertTrue( Remote_Posts::has_recipient( $updated_post->ID, 1 ) );
		$this->assertTrue( Remote_Posts::has_recipient( $updated_post->ID, 2 ) );
		$this->assertTrue( Remote_Posts::has_recipient( $updated_post->ID, 3 ) );
		$this->assertFalse( Remote_Posts::has_recipient( $updated_post->ID, 4 ) );
	}

	/**
	 * Test adding with single recipient still works (backward compatibility).
	 *
	 * @covers ::add
	 * @covers ::get_recipients
	 */
	public function test_add_with_single_recipient_backward_compatibility() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/single-user',
				'type'         => 'Note',
				'name'         => 'Single User Post',
				'content'      => '<p>This post is for one user</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );

		// Verify single recipient was added.
		$recipients = Remote_Posts::get_recipients( $result->ID );
		$this->assertCount( 1, $recipients );
		$this->assertContains( 1, $recipients );
	}

	/**
	 * Test updating with single recipient still works (backward compatibility).
	 *
	 * @covers ::update
	 * @covers ::get_recipients
	 */
	public function test_update_with_single_recipient_backward_compatibility() {
		// Create an object.
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/single-update',
				'type'         => 'Note',
				'name'         => 'Original',
				'content'      => '<p>Original</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		Remote_Posts::add( $activity, 1 );

		// Update with single recipient.
		$update_activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'      => 'https://example.com/objects/single-update',
				'type'    => 'Note',
				'name'    => 'Updated',
				'content' => '<p>Updated</p>',
			),
		);

		$updated_post = Remote_Posts::update( $update_activity, 2 );

		$this->assertInstanceOf( '\WP_Post', $updated_post );

		// Verify both recipients are present.
		$recipients = Remote_Posts::get_recipients( $updated_post->ID );
		$this->assertCount( 2, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
	}

	/**
	 * Test add_recipient method.
	 *
	 * @covers ::add_recipient
	 * @covers ::has_recipient
	 * @covers ::get_recipients
	 */
	public function test_add_recipient() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/add-recipient',
				'type'         => 'Note',
				'name'         => 'Add Recipient Test',
				'content'      => '<p>Test add_recipient</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Remote_Posts::add( $activity, 1 );

		// Add another recipient.
		$result = Remote_Posts::add_recipient( $post->ID, 2 );
		$this->assertTrue( $result );

		// Verify recipient was added.
		$this->assertTrue( Remote_Posts::has_recipient( $post->ID, 2 ) );
		$recipients = Remote_Posts::get_recipients( $post->ID );
		$this->assertCount( 2, $recipients );

		// Adding duplicate should return true but not add again.
		$result = Remote_Posts::add_recipient( $post->ID, 2 );
		$this->assertTrue( $result );
		$recipients = Remote_Posts::get_recipients( $post->ID );
		$this->assertCount( 2, $recipients );
	}

	/**
	 * Test add_recipients method.
	 *
	 * @covers ::add_recipients
	 * @covers ::get_recipients
	 */
	public function test_add_recipients() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/add-recipients',
				'type'         => 'Note',
				'name'         => 'Add Recipients Test',
				'content'      => '<p>Test add_recipients</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Remote_Posts::add( $activity, 1 );

		// Add multiple recipients.
		Remote_Posts::add_recipients( $post->ID, array( 2, 3, 4 ) );

		// Verify all recipients were added.
		$recipients = Remote_Posts::get_recipients( $post->ID );
		$this->assertCount( 4, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
		$this->assertContains( 4, $recipients );
	}

	/**
	 * Test remove_recipient method.
	 *
	 * @covers ::remove_recipient
	 * @covers ::has_recipient
	 * @covers ::get_recipients
	 */
	public function test_remove_recipient() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/remove-recipient',
				'type'         => 'Note',
				'name'         => 'Remove Recipient Test',
				'content'      => '<p>Test remove_recipient</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Remote_Posts::add( $activity, array( 1, 2, 3 ) );

		// Remove a recipient.
		$result = Remote_Posts::remove_recipient( $post->ID, 2 );
		$this->assertTrue( $result );

		// Verify recipient was removed.
		$this->assertFalse( Remote_Posts::has_recipient( $post->ID, 2 ) );
		$recipients = Remote_Posts::get_recipients( $post->ID );
		$this->assertCount( 2, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 3, $recipients );
		$this->assertNotContains( 2, $recipients );
	}

	/**
	 * Test that add with existing post calls update instead of creating duplicate.
	 *
	 * @covers ::add
	 * @covers ::update
	 * @covers ::get_recipients
	 */
	public function test_add_existing_post_adds_recipients() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/existing-post',
				'type'         => 'Note',
				'name'         => 'Existing Post',
				'content'      => '<p>Test existing post</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		// First add.
		$post1 = Remote_Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $post1 );

		// Second add with same activity ID but different recipient.
		$post2 = Remote_Posts::add( $activity, 2 );
		$this->assertInstanceOf( '\WP_Post', $post2 );

		// Should be the same post.
		$this->assertEquals( $post1->ID, $post2->ID );

		// Should have both recipients.
		$recipients = Remote_Posts::get_recipients( $post1->ID );
		$this->assertCount( 2, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );

		// Verify only one post exists with this GUID.
		$posts = \get_posts(
			array(
				'post_type'      => Remote_Posts::POST_TYPE,
				'guid'           => 'https://example.com/objects/existing-post',
				'posts_per_page' => -1,
			)
		);
		$this->assertCount( 1, $posts );
		// Verify no attachments initially.
		$attachments = \get_attached_media( '', $post1->ID );
		$this->assertEmpty( $attachments );

		// Update with attachments.
		$update_activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'         => 'https://example.com/objects/existing-post',
				'type'       => 'Note',
				'name'       => 'Updated Post',
				'content'    => '<p>Updated content</p>',
				'attachment' => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'New Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$updated_post = Remote_Posts::update( $update_activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $updated_post );

		// Verify attachment was added to content as media block.
		$this->assertStringContainsString( '<!-- wp:activitypub/image', $updated_post->post_content );
		$this->assertStringContainsString( 'https://example.com/image.jpg', $updated_post->post_content );

		// Render to trigger lazy caching.
		$this->render_post_content( $updated_post );

		// Verify file was created after rendering.
		$upload_dir = \wp_upload_dir();
		$file_dir   = $upload_dir['basedir'] . Media::BASE_DIR_POSTS . $updated_post->ID;
		$this->assertTrue( file_exists( $file_dir ), 'ActivityPub directory should exist' );

		$files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $files, 'One file should be created' );
	}

	/**
	 * Test updating an object with changed attachments.
	 *
	 * @covers ::update
	 */
	public function test_update_with_changed_attachments() {
		// Create post with attachment.
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/change-test',
				'type'         => 'Note',
				'name'         => 'Original Post',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Original Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$original_post = Remote_Posts::add( $activity, 1 );

		// Render to trigger lazy caching.
		$this->render_post_content( $original_post );

		// Verify original file was created after rendering.
		$upload_dir = \wp_upload_dir();
		$file_dir   = $upload_dir['basedir'] . Media::BASE_DIR_POSTS . $original_post->ID;
		$this->assertTrue( file_exists( $file_dir ), 'ActivityPub directory should exist' );
		$original_files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $original_files );

		// Update with different attachment URL.
		$update_activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'         => 'https://example.com/objects/change-test',
				'type'       => 'Note',
				'name'       => 'Updated Post',
				'content'    => '<p>Updated content</p>',
				'attachment' => array(
					array(
						'url'       => 'https://example.com/new-image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'New Image',
						'type'      => 'Image',
					),
				),
			),
		);

		// Mock the new image URL.
		\add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				if ( 'https://example.com/new-image.jpg' === $url && isset( $parsed_args['filename'] ) ) {
					\copy( AP_TESTS_DIR . '/data/assets/test.jpg', $parsed_args['filename'] );

					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array( 'content-type' => 'image/jpeg' ),
					);
				}
				return $response;
			},
			11,
			3
		);

		$updated_post = Remote_Posts::update( $update_activity, 1 );

		// Render to trigger caching of new image.
		$this->render_post_content( $updated_post );

		// Verify new file was created (old file may still exist until cleanup).
		$new_files = glob( $file_dir . '/*' );
		$this->assertGreaterThanOrEqual( 1, count( $new_files ), 'At least one file should exist' );

		// Find the new file (should have different hash than original).
		$new_hashes = array_map( 'basename', $new_files );
		$old_hash   = basename( $original_files[0] );

		// With the new URL, there should be a file with a different hash.
		$different_files = array_filter(
			$new_hashes,
			function ( $hash ) use ( $old_hash ) {
				return $hash !== $old_hash;
			}
		);
		$this->assertNotEmpty( $different_files, 'New file should have different name than original' );
	}

	/**
	 * Test updating an object keeps same attachments when unchanged.
	 *
	 * @covers ::update
	 */
	public function test_update_keeps_same_attachments() {
		// Create post with attachment.
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/keep-test',
				'type'         => 'Note',
				'name'         => 'Original Post',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Test Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$original_post = Remote_Posts::add( $activity, 1 );

		// Render to trigger lazy caching.
		$this->render_post_content( $original_post );

		// Verify original file was created after rendering.
		$upload_dir = \wp_upload_dir();
		$file_dir   = $upload_dir['basedir'] . Media::BASE_DIR_POSTS . $original_post->ID;
		$this->assertTrue( file_exists( $file_dir ), 'ActivityPub directory should exist' );
		$original_files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $original_files );

		// Update with same attachment URL (just change content).
		$update_activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'         => 'https://example.com/objects/keep-test',
				'type'       => 'Note',
				'name'       => 'Updated Post',
				'content'    => '<p>Updated content</p>',
				'attachment' => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Test Image',
						'type'      => 'Image',
					),
				),
			),
		);

		Remote_Posts::update( $update_activity, 1 );

		// Verify file still exists (should not be recreated since attachment hasn't changed).
		// Note: With file-based storage, we don't detect unchanged attachments, so files get replaced.
		$new_files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $new_files, 'File should still exist after update' );
	}

	/**
	 * Test extracting hashtags from activity tags.
	 *
	 * @covers ::extract_hashtags
	 */
	public function test_extract_hashtags() {
		$tags = array(
			array(
				'type' => 'Hashtag',
				'name' => '#test',
			),
			array(
				'type' => 'Hashtag',
				'name' => '#wordpress',
			),
			array(
				'type' => 'Mention',
				'name' => '@user',
			),
		);

		$result = Remote_Posts::extract_hashtags( $tags );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		// Helper always strips # prefix.
		$this->assertContains( 'test', $result );
		$this->assertContains( 'wordpress', $result ); // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
		$this->assertNotContains( '@user', $result );
	}

	/**
	 * Test extracting hashtags without # prefix in source.
	 *
	 * @covers ::extract_hashtags
	 */
	public function test_extract_hashtags_without_prefix() {
		$tags = array(
			array(
				'type' => 'Hashtag',
				'name' => 'test',
			),
			array(
				'type' => 'Hashtag',
				'name' => 'wordpress',
			),
		);

		$result = Remote_Posts::extract_hashtags( $tags );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertContains( 'test', $result );
		$this->assertContains( 'wordpress', $result ); // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
	}

	/**
	 * Test extracting hashtags from empty array.
	 *
	 * @covers ::extract_hashtags
	 */
	public function test_extract_hashtags_from_empty_array() {
		$result = Remote_Posts::extract_hashtags( array() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test extracting hashtags from null value.
	 *
	 * @covers ::extract_hashtags
	 */
	public function test_extract_hashtags_from_null() {
		$result = Remote_Posts::extract_hashtags( null );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test extracting hashtags when tags have no name field.
	 *
	 * @covers ::extract_hashtags
	 */
	public function test_extract_hashtags_missing_name_field() {
		$tags = array(
			array(
				'type' => 'Hashtag',
			),
			array(
				'type' => 'Hashtag',
				'name' => '#valid',
			),
		);

		$result = Remote_Posts::extract_hashtags( $tags );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertContains( 'valid', $result );
	}

	/**
	 * Test extracting hashtags when tags have no type field.
	 *
	 * @covers ::extract_hashtags
	 */
	public function test_extract_hashtags_missing_type_field() {
		$tags = array(
			array(
				'name' => '#invalid',
			),
			array(
				'type' => 'Hashtag',
				'name' => '#valid',
			),
		);

		$result = Remote_Posts::extract_hashtags( $tags );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertContains( 'valid', $result );
		$this->assertNotContains( 'invalid', $result );
	}

	/**
	 * Test that hashtag removal works in activity_to_post.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_removes_hashtags() {
		$activity = array(
			'id'        => 'https://example.com/objects/hashtag-test',
			'type'      => 'Note',
			'name'      => 'Hashtag Test',
			'content'   => '<p>This is a test #test #wordpress</p>',
			'published' => '2023-01-01T12:00:00Z',
			'tag'       => array(
				array(
					'type' => 'Hashtag',
					'name' => '#test',
				),
				array(
					'type' => 'Hashtag',
					'name' => '#wordpress',
				),
			),
		);

		// Use reflection to access the private method.
		$result = $this->invoke_activity_to_post( $activity );

		$this->assertIsArray( $result );
		// Content should have hashtags removed.
		$this->assertStringNotContainsString( '#test', $result['post_content'] );
		$this->assertStringNotContainsString( '#WordPress', $result['post_content'] );
		$this->assertStringContainsString( 'This is a test', $result['post_content'] );
	}

	/**
	 * Data provider for remove_hashtags tests.
	 *
	 * @return array Test data.
	 */
	public function remove_hashtags_provider() {
		return array(
			'simple_hashtag_removal'          => array(
				'<p>This is a test #wordpress #activitypub</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => '#wordpress',
					),
					array(
						'type' => 'Hashtag',
						'name' => '#activitypub',
					),
				),
				'<p>This is a test</p>',
			),
			'hashtags_without_hash_prefix'    => array(
				'<p>Testing content #php #javascript</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'php',
					),
					array(
						'type' => 'Hashtag',
						'name' => 'javascript',
					),
				),
				'<p>Testing content</p>',
			),
			'hashtags_in_anchor_tags'         => array(
				'<p>Check out this post <a href="https://example.com/tag/wordpress">#wordpress</a> <a href="https://example.com/tag/php">#php</a></p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => '#wordpress',
					),
					array(
						'type' => 'Hashtag',
						'name' => '#php',
					),
				),
				'<p>Check out this post</p>',
			),
			'mixed_hashtags'                  => array(
				'<p>Post about coding <a href="https://example.com/tag/php">#php</a> #javascript</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'php',
					),
					array(
						'type' => 'Hashtag',
						'name' => 'javascript',
					),
				),
				'<p>Post about coding</p>',
			),
			'inline_hashtags_not_removed'     => array(
				'<p>Testing #wordpress in the middle and more text</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'wordpress',
					),
				),
				'<p>Testing #wordpress in the middle and more text</p>',
			),
			'partial_match_should_not_remove' => array(
				'<p>Testing #wordpressdevelopment in content #wordpress</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'wordpress',
					),
				),
				'<p>Testing #wordpressdevelopment in content</p>',
			),
			'empty_hashtags_array'            => array(
				'<p>Testing #wordpress #php</p>',
				array(),
				'<p>Testing #wordpress #php</p>',
			),
			'empty_content'                   => array(
				'',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'wordpress',
					),
				),
				'',
			),
			'no_matching_hashtags'            => array(
				'<p>Testing #wordpress #php</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'javascript',
					),
					array(
						'type' => 'Hashtag',
						'name' => 'python',
					),
				),
				'<p>Testing #wordpress #php</p>',
			),
			'case_insensitive_removal'        => array(
				'<p>Testing content #WordPress #PHP</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'wordpress',
					),
					array(
						'type' => 'Hashtag',
						'name' => 'php',
					),
				),
				'<p>Testing content</p>',
			),
			'trailing_hashtags_only'          => array(
				'<p>Testing #wordpress in middle #php #activitypub</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'wordpress',
					),
					array(
						'type' => 'Hashtag',
						'name' => 'php',
					),
					array(
						'type' => 'Hashtag',
						'name' => 'activitypub',
					),
				),
				'<p>Testing #wordpress in middle</p>',
			),
			'special_characters_in_hashtags'  => array(
				'<p>Testing content #c++ #.net</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'c++',
					),
					array(
						'type' => 'Hashtag',
						'name' => '.net',
					),
				),
				'<p>Testing content</p>',
			),
			'multiple_spaces_cleanup'         => array(
				'<p>Testing content #tag1    #tag2    #tag3</p>',
				array(
					array(
						'type' => 'Hashtag',
						'name' => 'tag1',
					),
					array(
						'type' => 'Hashtag',
						'name' => 'tag2',
					),
					array(
						'type' => 'Hashtag',
						'name' => 'tag3',
					),
				),
				'<p>Testing content</p>',
			),
		);
	}

	/**
	 * Test remove_hashtags with various inputs.
	 *
	 * @dataProvider remove_hashtags_provider
	 * @covers ::remove_hashtags
	 *
	 * @param string $content  Input content.
	 * @param array  $hashtags Hashtags to remove.
	 * @param string $expected Expected output.
	 */
	public function test_remove_hashtags( $content, $hashtags, $expected ) {
		$result = Remote_Posts::remove_hashtags( $content, $hashtags );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test remove_hashtags with non-array hashtags parameter.
	 *
	 * @covers ::remove_hashtags
	 */
	public function test_remove_hashtags_with_invalid_hashtags() {
		$content = '<p>Testing #WordPress #php</p>';

		// Should return original content when hashtags is not an array.
		$this->assertEquals( $content, Remote_Posts::remove_hashtags( $content, 'not-an-array' ) );
		$this->assertEquals( $content, Remote_Posts::remove_hashtags( $content, null ) );
		$this->assertEquals( $content, Remote_Posts::remove_hashtags( $content, 123 ) );
	}

	/**
	 * Test remove_hashtags preserves content structure.
	 *
	 * @covers ::remove_hashtags
	 */
	public function test_remove_hashtags_preserves_structure() {
		$content = '<p>First paragraph content</p><p>Second paragraph with <strong>bold text</strong> #php #test</p>';
		$tags    = array(
			array(
				'type' => 'Hashtag',
				'name' => 'test',
			),
			array(
				'type' => 'Hashtag',
				'name' => 'php',
			),
		);
		$result  = Remote_Posts::remove_hashtags( $content, $tags );

		// Should preserve HTML structure and remove trailing hashtags only.
		$this->assertStringContainsString( '<p>First paragraph content</p>', $result );
		$this->assertStringContainsString( '<strong>bold text</strong>', $result );
		$this->assertStringNotContainsString( '#test', $result );
		$this->assertStringNotContainsString( '#php', $result );
	}

	/**
	 * Test delete_all method deletes all posts.
	 *
	 * @covers ::delete_all
	 */
	public function test_delete_all() {
		// Create some posts.
		self::factory()->post->create_many(
			5,
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		// Verify posts were created.
		$count_before = \wp_count_posts( Remote_Posts::POST_TYPE )->publish;
		$this->assertEquals( 5, $count_before );

		// Delete all posts.
		$deleted = Remote_Posts::delete_all();

		// Clear cache to get accurate count.
		\wp_cache_delete( \_count_posts_cache_key( Remote_Posts::POST_TYPE ), 'counts' );

		// Verify all posts were deleted.
		$count_after = \wp_count_posts( Remote_Posts::POST_TYPE )->publish;
		$this->assertEquals( 0, $count_after );

		// Verify return value.
		$this->assertEquals( 5, $deleted );
	}

	/**
	 * Test delete_all method with mixed post statuses.
	 *
	 * @covers ::delete_all
	 */
	public function test_delete_all_mixed_statuses() {
		// Create posts with different statuses.
		self::factory()->post->create_many(
			3,
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create_many(
			2,
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'trash',
			)
		);

		// Delete all posts.
		$deleted = Remote_Posts::delete_all();

		// Verify all posts were deleted regardless of status.
		$remaining = \get_posts(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$this->assertEmpty( $remaining );

		// Verify return value includes all posts.
		$this->assertEquals( 6, $deleted );
	}

	/**
	 * Test purge method with more than 200 posts.
	 *
	 * @covers ::purge
	 */
	public function test_purge_more_than_200_posts() {
		// Create 20 old posts (will be deleted).
		self::factory()->post->create_many(
			20,
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-7 months' ) ),
			)
		);

		// Create 5 new posts (will be kept).
		self::factory()->post->create_many(
			5,
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 month' ) ),
			)
		);

		// Mock the count to exceed the 200-post threshold.
		$wp_count_posts_callback = function ( $counts, $type ) {
			if ( Remote_Posts::POST_TYPE === $type ) {
				$counts->publish = 225;
			}
			return $counts;
		};
		\add_filter( 'wp_count_posts', $wp_count_posts_callback, 10, 2 );

		$deleted = Remote_Posts::purge( 180 );
		\wp_cache_delete( \_count_posts_cache_key( Remote_Posts::POST_TYPE ), 'counts' );

		\remove_filter( 'wp_count_posts', $wp_count_posts_callback );

		// Assert that 20 old posts were deleted.
		$this->assertEquals( 20, $deleted );

		// Verify 5 new posts remain.
		$remaining = \get_posts(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$this->assertCount( 5, $remaining );
	}

	/**
	 * Test purge method with 200 or fewer posts.
	 *
	 * @covers ::purge
	 */
	public function test_purge_200_or_fewer_posts() {
		// Create 20 old posts.
		self::factory()->post->create_many(
			20,
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);

		$deleted = Remote_Posts::purge( 180 );
		\wp_cache_delete( \_count_posts_cache_key( Remote_Posts::POST_TYPE ), 'counts' );

		// Assert no posts were deleted (below threshold).
		$this->assertEquals( 0, $deleted );
		$this->assertEquals( 20, \wp_count_posts( Remote_Posts::POST_TYPE )->publish );
	}

	/**
	 * Test purge method preserves posts with local user comments.
	 *
	 * @covers ::purge
	 */
	public function test_purge_preserves_posts_with_comments() {
		// Create old post without comments (should be deleted).
		$post_without_comments = self::factory()->post->create(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);

		// Create old post with a local user comment (should be preserved).
		$post_with_comment = self::factory()->post->create(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);

		// Add a comment from a local user to the second post.
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_with_comment,
				'comment_content'  => 'Test comment',
				'comment_approved' => 1,
				'user_id'          => 1, // Local user comment.
			)
		);

		// Mock the count to exceed the 200-post threshold.
		$wp_count_posts_callback = function ( $counts, $type ) {
			if ( Remote_Posts::POST_TYPE === $type ) {
				$counts->publish = 225;
			}
			return $counts;
		};
		\add_filter( 'wp_count_posts', $wp_count_posts_callback, 10, 2 );

		$deleted = Remote_Posts::purge( 180 );
		\wp_cache_delete( \_count_posts_cache_key( Remote_Posts::POST_TYPE ), 'counts' );

		\remove_filter( 'wp_count_posts', $wp_count_posts_callback );

		// Assert only 1 post was deleted.
		$this->assertEquals( 1, $deleted );

		// Post without comments should be deleted.
		$this->assertNull( \get_post( $post_without_comments ) );

		// Post with local user comment should still exist.
		$this->assertNotNull( \get_post( $post_with_comment ) );
	}

	/**
	 * Test purge preserves posts with multiple local user comments.
	 *
	 * @covers ::purge
	 */
	public function test_purge_preserves_posts_with_multiple_comments() {
		// Create old post with multiple local user comments (should be preserved).
		$post_with_comments = self::factory()->post->create(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);

		// Add multiple comments from local users.
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_with_comments,
				'comment_content'  => 'First comment',
				'comment_approved' => 1,
				'user_id'          => 1, // Local user comment.
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_with_comments,
				'comment_content'  => 'Second comment',
				'comment_approved' => 1,
				'user_id'          => 1, // Local user comment.
			)
		);

		// Create old post without any interactions (should be deleted).
		$post_without_interactions = self::factory()->post->create(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);

		// Mock the count to exceed threshold.
		$wp_count_posts_callback = function ( $counts, $type ) {
			if ( Remote_Posts::POST_TYPE === $type ) {
				$counts->publish = 225;
			}
			return $counts;
		};
		\add_filter( 'wp_count_posts', $wp_count_posts_callback, 10, 2 );

		$deleted = Remote_Posts::purge( 180 );

		\remove_filter( 'wp_count_posts', $wp_count_posts_callback );

		// Only post without interactions should be deleted.
		$this->assertEquals( 1, $deleted );
		$this->assertNotNull( \get_post( $post_with_comments ) );
		$this->assertNull( \get_post( $post_without_interactions ) );
	}

	/**
	 * Test purge method with different retention days.
	 *
	 * @covers ::purge
	 */
	public function test_purge_with_different_days() {
		// Create posts older than 60 days but newer than 30 days.
		self::factory()->post->create_many(
			10,
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-45 days' ) ),
			)
		);

		// Mock the count to exceed threshold.
		$wp_count_posts_callback = function ( $counts, $type ) {
			if ( Remote_Posts::POST_TYPE === $type ) {
				$counts->publish = 225;
			}
			return $counts;
		};
		\add_filter( 'wp_count_posts', $wp_count_posts_callback, 10, 2 );

		// Purge with 60 days retention - should not delete.
		$deleted = Remote_Posts::purge( 60 );
		$this->assertEquals( 0, $deleted );

		// Purge with 30 days retention - should delete all.
		$deleted = Remote_Posts::purge( 30 );
		\wp_cache_delete( \_count_posts_cache_key( Remote_Posts::POST_TYPE ), 'counts' );

		\remove_filter( 'wp_count_posts', $wp_count_posts_callback );

		$this->assertEquals( 10, $deleted );
	}

	/**
	 * Test activity_to_post with video attachment.
	 *
	 * @covers ::add
	 */
	public function test_activity_to_post_with_video_attachment() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/video-post',
				'type'         => 'Note',
				'name'         => 'Video Post',
				'content'      => '<p>Check out this video</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/video.mp4',
						'mediaType' => 'video/mp4',
						'name'      => 'A cool video',
						'type'      => 'Document',
					),
				),
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertStringContainsString( '<!-- wp:activitypub/video', $result->post_content );
		$this->assertStringContainsString( 'https://example.com/video.mp4', $result->post_content );
		$this->assertStringContainsString( '<!-- /wp:activitypub/video -->', $result->post_content );
	}

	/**
	 * Test activity_to_post with audio attachment.
	 *
	 * @covers ::add
	 */
	public function test_activity_to_post_with_audio_attachment() {
		$activity = array(
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/audio-post',
				'type'         => 'Note',
				'name'         => 'Audio Post',
				'content'      => '<p>Listen to this</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/podcast.mp3',
						'mediaType' => 'audio/mpeg',
						'name'      => 'Episode 1',
						'type'      => 'Document',
					),
				),
			),
		);

		$result = Remote_Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertStringContainsString( '<!-- wp:activitypub/audio', $result->post_content );
		$this->assertStringContainsString( 'https://example.com/podcast.mp3', $result->post_content );
		$this->assertStringContainsString( '<!-- /wp:activitypub/audio -->', $result->post_content );
	}

	/**
	 * Test purge returns count of deleted items.
	 *
	 * @covers ::purge
	 */
	public function test_purge_returns_deleted_count() {
		// Create 15 old posts.
		self::factory()->post->create_many(
			15,
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);

		// Mock the count to exceed threshold.
		$wp_count_posts_callback = function ( $counts, $type ) {
			if ( Remote_Posts::POST_TYPE === $type ) {
				$counts->publish = 225;
			}
			return $counts;
		};
		\add_filter( 'wp_count_posts', $wp_count_posts_callback, 10, 2 );

		$deleted = Remote_Posts::purge( 180 );

		\remove_filter( 'wp_count_posts', $wp_count_posts_callback );

		// Should return exact count of deleted posts.
		$this->assertEquals( 15, $deleted );
	}
}
