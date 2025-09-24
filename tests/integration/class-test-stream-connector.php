<?php
/**
 * Test Stream Connector Integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Stream_Connector;

/**
 * Test Stream Connector Integration class.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Stream_Connector
 */
class Test_Stream_Connector extends \WP_UnitTestCase {
	/**
	 * Stream Connector instance.
	 *
	 * @var Stream_Connector
	 */
	protected $stream_connector;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test comment ID.
	 *
	 * @var int
	 */
	protected static $comment_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Test Author',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$user_id,
				'post_title'  => 'Test Post for Stream Connector',
			)
		);

		self::$comment_id = $factory->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_content' => 'Test comment for Stream Connector',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_comment( self::$comment_id, true );
		wp_delete_post( self::$post_id, true );
		wp_delete_user( self::$user_id );
	}

	/**
	 * Check if Stream plugin dependencies are available.
	 *
	 * @return bool True if Stream plugin is available, false otherwise.
	 */
	protected function is_stream_available() {
		return class_exists( 'WP_Stream\Connector' );
	}

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->stream_connector = new \Activitypub\Integration\Stream_Connector();
	}

	/**
	 * Test the Stream connector registration hook behavior.
	 *
	 * @covers \Activitypub\Integration\register_stream_connector
	 */
	public function test_stream_connector_registration() {
		$initial_classes = array( 'existing_connector' );

		// Test registration when Stream plugin is available.
		if ( $this->is_stream_available() ) {
			$result = apply_filters( 'wp_stream_connectors', $initial_classes );

			// Should have our connector added.
			$this->assertGreaterThan( count( $initial_classes ), count( $result ) );

			// Find our connector in the result.
			$activitypub_connector = null;
			foreach ( $result as $connector ) {
				if ( is_object( $connector ) && property_exists( $connector, 'name' ) && 'activitypub' === $connector->name ) {
					$activitypub_connector = $connector;
					break;
				}
			}

			$this->assertNotNull( $activitypub_connector, 'ActivityPub connector should be registered when Stream plugin is available' );
			$this->assertInstanceOf( 'Activitypub\Integration\Stream_Connector', $activitypub_connector );
		} else {
			// When Stream plugin is not available, the filter should return unchanged classes.
			$result = apply_filters( 'wp_stream_connectors', $initial_classes );
			$this->assertEquals( $initial_classes, $result );
		}
	}

	/**
	 * Test connector basic properties when Stream plugin is available.
	 *
	 * @covers ::get_label
	 * @covers ::get_context_labels
	 * @covers ::get_action_labels
	 */
	public function test_connector_properties() {
		if ( ! $this->is_stream_available() ) {
			$this->markTestSkipped( 'Stream plugin is not available.' );
		}

		// Test connector name.
		$this->assertEquals( 'activitypub', $this->stream_connector->name );

		// Test actions array.
		$expected_actions = array(
			'activitypub_handled_follow',
			'activitypub_sent_to_inbox',
			'activitypub_outbox_processing_complete',
			'activitypub_outbox_processing_batch_complete',
		);
		$this->assertEquals( $expected_actions, $this->stream_connector->actions );

		// Test label.
		$this->assertEquals( 'ActivityPub', $this->stream_connector->get_label() );

		// Test context labels.
		$this->assertEquals( array(), $this->stream_connector->get_context_labels() );

		// Test action labels.
		$expected_action_labels = array(
			'processed' => 'Processed',
		);
		$this->assertEquals( $expected_action_labels, $this->stream_connector->get_action_labels() );
	}

	/**
	 * Test action_links method with various scenarios.
	 *
	 * @dataProvider action_links_provider
	 * @covers ::action_links
	 *
	 * @param string $action         The record action.
	 * @param array  $meta_data      The meta data to set on the record.
	 * @param int    $expected_count The expected number of links.
	 * @param string $description    Description of the test case.
	 */
	public function test_action_links( $action, $meta_data, $expected_count, $description ) {
		if ( ! $this->is_stream_available() ) {
			$this->markTestSkipped( 'Stream plugin is not available.' );
		}
		// Create a mock record.
		$record         = $this->createMock( '\WP_Stream\Record' );
		$record->action = $action;

		// Mock the get_meta method.
		$record->method( 'get_meta' )->willReturnCallback(
			function ( $key ) use ( $meta_data ) {
				return isset( $meta_data[ $key ] ) ? $meta_data[ $key ] : '';
			}
		);

		$links  = array( 'existing_link' => 'http://example.com' );
		$result = $this->stream_connector->action_links( $links, $record );

		$this->assertCount( $expected_count, $result, $description );
		$this->assertArrayHasKey( 'existing_link', $result, 'Should preserve existing links' );
	}

	/**
	 * Data provider for action_links tests.
	 *
	 * @return array Test cases.
	 */
	public function action_links_provider() {
		return array(
			'processed_with_error'   => array(
				'processed',
				array(
					'error' => wp_json_encode( array( 'message' => 'Test error' ) ),
					'debug' => '',
				),
				2, // Existing + error.
				'Should add error link for processed action with error',
			),
			'processed_with_debug'   => array(
				'processed',
				array(
					'error' => '',
					'debug' => wp_json_encode( array( 'test' => 'debug data' ) ),
				),
				2, // Existing + debug.
				'Should add debug link for processed action with debug',
			),
			'processed_with_both'    => array(
				'processed',
				array(
					'error' => wp_json_encode( array( 'message' => 'Test error' ) ),
					'debug' => wp_json_encode( array( 'test' => 'debug data' ) ),
				),
				3, // Existing + error + debug.
				'Should add both error and debug links',
			),
			'processed_without_data' => array(
				'processed',
				array(
					'error' => '',
					'debug' => '',
				),
				1, // Only existing.
				'Should not add links when no error or debug data',
			),
			'non_processed_action'   => array(
				'other_action',
				array(
					'error' => wp_json_encode( array( 'message' => 'Test error' ) ),
				),
				1, // Only existing.
				'Should not add links for non-processed actions',
			),
		);
	}

	/**
	 * Test callback_activitypub_handled_follow method.
	 *
	 * @covers ::callback_activitypub_handled_follow
	 */
	public function test_callback_activitypub_handled_follow() {
		if ( ! $this->is_stream_available() ) {
			$this->markTestSkipped( 'Stream plugin is not available.' );
		}

		$activity = array(
			'type'   => 'Follow',
			'actor'  => 'https://example.com/actor',
			'object' => 'https://local.example.com/author/1',
		);

		$context = (object) array(
			'guid' => 'https://example.com/actor',
		);

		// Capture the log call.
		$logged_data      = null;
		$stream_connector = $this->createPartialMock( Stream_Connector::class, array( 'log' ) );
		$stream_connector->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $meta, $object_id, $context_type, $action, $user_id ) use ( &$logged_data ) {
					$logged_data = array(
						'message'      => $message,
						'meta'         => $meta,
						'object_id'    => $object_id,
						'context_type' => $context_type,
						'action'       => $action,
						'user_id'      => $user_id,
					);
				}
			);

		$stream_connector->callback_activitypub_handled_follow( $activity, self::$user_id, true, $context );

		$this->assertNotNull( $logged_data, 'Should have logged the follow event' );
		$this->assertStringContainsString( 'New Follower: https://example.com/actor', $logged_data['message'] );
		$this->assertEquals( 'notification', $logged_data['context_type'] );
		$this->assertEquals( 'follow', $logged_data['action'] );
		$this->assertEquals( self::$user_id, $logged_data['user_id'] );
		$this->assertArrayHasKey( 'activity', $logged_data['meta'] );
		$this->assertArrayHasKey( 'remote_actor', $logged_data['meta'] );
	}

	/**
	 * Test callback_activitypub_handled_follow with error context.
	 *
	 * @covers ::callback_activitypub_handled_follow
	 */
	public function test_callback_activitypub_handled_follow_with_error() {
		if ( ! $this->is_stream_available() ) {
			$this->markTestSkipped( 'Stream plugin is not available.' );
		}

		$activity = array(
			'type'   => 'Follow',
			'actor'  => 'https://example.com/actor',
			'object' => 'https://local.example.com/author/1',
		);

		$context = new \WP_Error( 'follow_error', 'Follow processing failed' );

		// Capture the log call.
		$logged_data      = null;
		$stream_connector = $this->createPartialMock( Stream_Connector::class, array( 'log' ) );
		$stream_connector->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $meta ) use ( &$logged_data ) {
					$logged_data = array(
						'message' => $message,
						'meta'    => $meta,
					);
				}
			);

		$stream_connector->callback_activitypub_handled_follow( $activity, self::$user_id, false, $context );

		$this->assertStringContainsString( 'New Follower: https://example.com/actor', $logged_data['message'] );
	}

	/**
	 * Test callback_activitypub_outbox_processing_complete method.
	 *
	 * @covers ::callback_activitypub_outbox_processing_complete
	 */
	public function test_callback_activitypub_outbox_processing_complete() {
		if ( ! $this->is_stream_available() ) {
			$this->markTestSkipped( 'Stream plugin is not available.' );
		}

		// Create a mock outbox post.
		$outbox_post_id = $this->factory->post->create(
			array(
				'post_type'  => 'ap_outbox',
				'post_title' => get_permalink( self::$post_id ),
			)
		);

		$inboxes = array( 'https://example.com/inbox' );
		$json    = '{"type":"Create","object":{"type":"Note"}}';

		// Capture the log call.
		$logged_data      = null;
		$stream_connector = $this->createPartialMock( Stream_Connector::class, array( 'log' ) );
		$stream_connector->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $meta, $object_id, $object_type, $action ) use ( &$logged_data ) {
					$logged_data = array(
						'message'     => $message,
						'meta'        => $meta,
						'object_id'   => $object_id,
						'object_type' => $object_type,
						'action'      => $action,
					);
				}
			);

		$stream_connector->callback_activitypub_outbox_processing_complete(
			$inboxes,
			$json,
			self::$user_id,
			$outbox_post_id
		);

		$this->assertNotNull( $logged_data, 'Should have logged the outbox processing complete event' );
		$this->assertStringContainsString( 'Outbox processing complete:', $logged_data['message'] );
		$this->assertEquals( 'processed', $logged_data['action'] );
		$this->assertArrayHasKey( 'debug', $logged_data['meta'] );

		// Clean up.
		wp_delete_post( $outbox_post_id, true );
	}

	/**
	 * Test callback_activitypub_outbox_processing_batch_complete method.
	 *
	 * @covers ::callback_activitypub_outbox_processing_batch_complete
	 */
	public function test_callback_activitypub_outbox_processing_batch_complete() {
		if ( ! $this->is_stream_available() ) {
			$this->markTestSkipped( 'Stream plugin is not available.' );
		}

		// Create a mock outbox post.
		$outbox_post_id = $this->factory->post->create(
			array(
				'post_type'  => 'ap_outbox',
				'post_title' => get_permalink( self::$post_id ),
			)
		);

		$inboxes    = array( 'https://example.com/inbox' );
		$json       = '{"type":"Create","object":{"type":"Note"}}';
		$batch_size = 10;
		$offset     = 0;

		// Capture the log call.
		$logged_data      = null;
		$stream_connector = $this->createPartialMock( Stream_Connector::class, array( 'log' ) );
		$stream_connector->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $meta, $object_id, $object_type, $action ) use ( &$logged_data ) {
					$logged_data = array(
						'message'     => $message,
						'meta'        => $meta,
						'object_id'   => $object_id,
						'object_type' => $object_type,
						'action'      => $action,
					);
				}
			);

		$stream_connector->callback_activitypub_outbox_processing_batch_complete(
			$inboxes,
			$json,
			self::$user_id,
			$outbox_post_id,
			$batch_size,
			$offset
		);

		$this->assertNotNull( $logged_data, 'Should have logged the batch processing complete event' );
		$this->assertStringContainsString( 'Outbox processing batch complete:', $logged_data['message'] );
		$this->assertEquals( 'processed', $logged_data['action'] );
		$this->assertArrayHasKey( 'debug', $logged_data['meta'] );

		$debug_data = json_decode( $logged_data['meta']['debug'], true );
		$this->assertEquals( $batch_size, $debug_data['batch_size'] );
		$this->assertEquals( $offset, $debug_data['offset'] );

		// Clean up.
		wp_delete_post( $outbox_post_id, true );
	}

	/**
	 * Test prepare_outbox_data_for_response method with various scenarios.
	 *
	 * @dataProvider prepare_outbox_data_provider
	 * @covers ::prepare_outbox_data_for_response
	 *
	 * @param string $post_title      The outbox post title (URL).
	 * @param array  $expected_data   The expected response data.
	 * @param string $description     Description of the test case.
	 */
	public function test_prepare_outbox_data_for_response( $post_title, $expected_data, $description ) {
		// Create a mock outbox post.
		$outbox_post = (object) array(
			'ID'         => 123,
			'post_type'  => 'ap_outbox',
			'post_title' => $post_title,
		);

		$reflection = new \ReflectionClass( Stream_Connector::class );
		$method     = $reflection->getMethod( 'prepare_outbox_data_for_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->stream_connector, $outbox_post );

		$this->assertEquals( $expected_data['type'], $result['type'], $description . ' - type' );
		$this->assertEquals( $expected_data['id'], $result['id'], $description . ' - id' );

		if ( isset( $expected_data['title'] ) ) {
			$this->assertEquals( $expected_data['title'], $result['title'], $description . ' - title' );
		}
	}

	/**
	 * Data provider for prepare_outbox_data_for_response tests.
	 *
	 * @return array Test cases.
	 */
	public function prepare_outbox_data_provider() {
		// Since data providers run before wpSetUpBeforeClass, we need to handle this differently.
		// For now, let's test the fallback behavior and known cases.
		return array(

			'application_user_url' => array(
				'http://localhost:8889/?author=-1',
				array(
					'id'    => 123, // Should fallback to outbox post ID since -1 might not be recognized.
					'type'  => 'ap_outbox', // Should fallback to outbox type.
					'title' => 'http://localhost:8889/?author=-1', // Should fallback to URL as title.
				),
				'Should handle application user URL correctly (fallback behavior)',
			),
			'unknown_url'          => array(
				'https://unknown.example.com/path',
				array(
					'id'    => 123, // The outbox post ID.
					'type'  => 'ap_outbox',
					'title' => 'https://unknown.example.com/path',
				),
				'Should fallback to outbox post data for unknown URLs',
			),
		);
	}

	/**
	 * Test prepare_outbox_data_for_response with actual post URL.
	 *
	 * @covers ::prepare_outbox_data_for_response
	 */
	public function test_prepare_outbox_data_for_response_post_url() {
		// Create a mock outbox post with actual post URL.
		$post_url    = get_permalink( self::$post_id );
		$outbox_post = (object) array(
			'ID'         => 123,
			'post_type'  => 'ap_outbox',
			'post_title' => $post_url,
		);

		$reflection = new \ReflectionClass( Stream_Connector::class );
		$method     = $reflection->getMethod( 'prepare_outbox_data_for_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->stream_connector, $outbox_post );

		// If url_to_postid works, it should return the post data, otherwise fallback to outbox data.
		if ( url_to_postid( $post_url ) === self::$post_id ) {
			$this->assertEquals( 'post', $result['type'] );
			$this->assertEquals( self::$post_id, $result['id'] );
			$this->assertEquals( 'Test Post for Stream Connector', $result['title'] );
		} else {
			// Fallback to outbox post data.
			$this->assertEquals( 'ap_outbox', $result['type'] );
			$this->assertEquals( 123, $result['id'] );
		}
	}

	/**
	 * Test prepare_outbox_data_for_response with actual comment URL.
	 *
	 * @covers ::prepare_outbox_data_for_response
	 */
	public function test_prepare_outbox_data_for_response_comment_url() {
		// Create a mock outbox post with actual comment URL.
		$comment_url = get_comment_link( self::$comment_id );
		$outbox_post = (object) array(
			'ID'         => 123,
			'post_type'  => 'ap_outbox',
			'post_title' => $comment_url,
		);

		$reflection = new \ReflectionClass( Stream_Connector::class );
		$method     = $reflection->getMethod( 'prepare_outbox_data_for_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->stream_connector, $outbox_post );

		// Check what the comment URL looks like and determine expected behavior.
		if ( \function_exists( '\Activitypub\url_to_commentid' ) ) {
			$comment_id_from_url = \Activitypub\url_to_commentid( $comment_url );
			if ( $comment_id_from_url === self::$comment_id ) {
				// Comment ID was parsed correctly.
				$this->assertEquals( 'comments', $result['type'] );
				$this->assertEquals( self::$comment_id, $result['id'] );
				$this->assertEquals( 'Test comment for Stream Connector', $result['title'] );
			} else {
				// Check if it's being parsed as a post URL instead.
				$post_id_from_url = url_to_postid( $comment_url );
				if ( $post_id_from_url === self::$post_id ) {
					// Comment URL is being parsed as post URL.
					$this->assertEquals( 'post', $result['type'] );
					$this->assertEquals( self::$post_id, $result['id'] );
				} else {
					// Fallback to outbox post data.
					$this->assertEquals( 'ap_outbox', $result['type'] );
					$this->assertEquals( 123, $result['id'] );
				}
			}
		} else {
			// Function doesn't exist, check if url_to_postid recognizes it.
			$post_id_from_url = url_to_postid( $comment_url );
			if ( $post_id_from_url === self::$post_id ) {
				// Comment URL is being parsed as post URL.
				$this->assertEquals( 'post', $result['type'] );
				$this->assertEquals( self::$post_id, $result['id'] );
			} else {
				// Fallback to outbox post data.
				$this->assertEquals( 'ap_outbox', $result['type'] );
				$this->assertEquals( 123, $result['id'] );
			}
		}
	}

	/**
	 * Test prepare_outbox_data_for_response with actual author URL.
	 *
	 * @covers ::prepare_outbox_data_for_response
	 */
	public function test_prepare_outbox_data_for_response_author_url() {
		// Create a mock outbox post with actual author URL.
		$author_url  = get_author_posts_url( self::$user_id );
		$outbox_post = (object) array(
			'ID'         => 123,
			'post_type'  => 'ap_outbox',
			'post_title' => $author_url,
		);

		$reflection = new \ReflectionClass( Stream_Connector::class );
		$method     = $reflection->getMethod( 'prepare_outbox_data_for_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->stream_connector, $outbox_post );

		// If url_to_authorid works, it should return the author data, otherwise fallback to outbox data.
		if ( \function_exists( '\Activitypub\url_to_authorid' ) ) {
			$author_id = \Activitypub\url_to_authorid( $author_url );
			if ( $author_id === self::$user_id ) {
				$this->assertEquals( 'profiles', $result['type'] );
				$this->assertEquals( self::$user_id, $result['id'] );
				$this->assertEquals( 'Test Author', $result['title'] );
			} else {
				// Fallback to outbox post data.
				$this->assertEquals( 'ap_outbox', $result['type'] );
				$this->assertEquals( 123, $result['id'] );
			}
		} else {
			// Function doesn't exist, should fallback to outbox data.
			$this->assertEquals( 'ap_outbox', $result['type'] );
			$this->assertEquals( 123, $result['id'] );
		}
	}

	/**
	 * Test prepare_outbox_data_for_response with blog user URL.
	 *
	 * @covers ::prepare_outbox_data_for_response
	 */
	public function test_prepare_outbox_data_for_response_blog_user_url() {
		// Test blog user URL - this may work differently in different environments.
		$blog_user_url = 'http://localhost:8889/?author=0';
		$outbox_post   = (object) array(
			'ID'         => 123,
			'post_type'  => 'ap_outbox',
			'post_title' => $blog_user_url,
		);

		$reflection = new \ReflectionClass( Stream_Connector::class );
		$method     = $reflection->getMethod( 'prepare_outbox_data_for_response' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->stream_connector, $outbox_post );

		// Check if url_to_authorid recognized the blog user URL.
		if ( \function_exists( '\Activitypub\url_to_authorid' ) ) {
			$author_id = \Activitypub\url_to_authorid( $blog_user_url );
			if ( 0 === $author_id ) {
				// Blog user URL was recognized correctly.
				$this->assertEquals( 'profiles', $result['type'] );
				$this->assertEquals( 0, $result['id'] );
				$this->assertEquals( 'Blog User', $result['title'] );
			} else {
				// Blog user URL was not recognized, should fallback to outbox data.
				$this->assertEquals( 'ap_outbox', $result['type'] );
				$this->assertEquals( 123, $result['id'] );
				$this->assertEquals( $blog_user_url, $result['title'] );
			}
		} else {
			// Function doesn't exist, should fallback to outbox data.
			$this->assertEquals( 'ap_outbox', $result['type'] );
			$this->assertEquals( 123, $result['id'] );
			$this->assertEquals( $blog_user_url, $result['title'] );
		}
	}

	/**
	 * Test that the Stream Connector properly extends WP_Stream\Connector.
	 *
	 * This test ensures the class hierarchy is correct.
	 */
	public function test_class_hierarchy() {
		$this->assertInstanceOf( '\WP_Stream\Connector', $this->stream_connector );
	}
}

// Mock WP_Stream\Connector if it doesn't exist.
if ( ! class_exists( 'WP_Stream\Connector' ) ) {
	// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
	/**
	 * Mock WP_Stream Connector class for testing.
	 *
	 * @package Activitypub
	 *
	 * phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
	 */
	class WP_Stream_Connector_Mock {
		/**
		 * Connector name.
		 *
		 * @var string
		 */
		public $name = '';

		/**
		 * Actions registered for this connector.
		 *
		 * @var array
		 */
		public $actions = array();

		/**
		 * Get connector label.
		 *
		 * @return string
		 */
		public function get_label() {
			return '';
		}

		/**
		 * Get context labels.
		 *
		 * @return array
		 */
		public function get_context_labels() {
			return array();
		}

		/**
		 * Get action labels.
		 *
		 * @return array
		 */
		public function get_action_labels() {
			return array();
		}

		/**
		 * Check if dependency is satisfied.
		 *
		 * @return bool
		 */
		public function is_dependency_satisfied() {
			return true;
		}

		/**
		 * Log activity.
		 *
		 * @param array $args Log arguments.
		 */
		public function log( $args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			// Mock implementation - parameter intentionally unused.
		}
	}

	// Create the namespace aliases.
	class_alias( 'Activitypub\Tests\Integration\WP_Stream_Connector_Mock', 'WP_Stream\Connector' );
}

// Mock WP_Stream\Record if it doesn't exist.
if ( ! class_exists( 'WP_Stream\Record' ) ) {
	// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
	/**
	 * Mock WP_Stream Record class for testing.
	 *
	 * @package Activitypub
	 *
	 * phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
	 */
	class WP_Stream_Record_Mock {
		/**
		 * Record action.
		 *
		 * @var string
		 */
		public $action = '';

		/**
		 * Get meta value.
		 *
		 * @param string $key     Meta key.
		 * @param bool   $single  Whether to return single value.
		 *
		 * @return string
		 */
		public function get_meta( $key, $single = false ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			// Mock implementation - parameters intentionally unused.
			return '';
		}
	}

	// Create the namespace aliases.
	class_alias( 'Activitypub\Tests\Integration\WP_Stream_Record_Mock', 'WP_Stream\Record' );
}
