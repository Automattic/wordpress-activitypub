<?php
/**
 * Event Stream Controller file.
 *
 * Implements Server-Sent Events (SSE) for real-time ActivityPub collection updates.
 *
 * @package Activitypub
 * @see https://swicg.github.io/activitypub-api/sse
 * @since unreleased
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Http;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;

use function Activitypub\get_rest_url_by_path;

/**
 * Event Stream Controller.
 *
 * Provides SSE endpoints for C2S clients to subscribe to real-time updates
 * on outbox and inbox collections.
 *
 * @since unreleased
 */
class Event_Stream_Controller extends \WP_REST_Controller {
	use Verification;

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = '(?:users|actors)/(?P<user_id>[-]?\d+)/(?P<collection>outbox|inbox)/stream';

	/**
	 * SSE polling interval in seconds.
	 *
	 * @var int
	 */
	const POLL_INTERVAL = 5;

	/**
	 * Maximum SSE connection duration in seconds.
	 *
	 * @var int
	 */
	const MAX_DURATION = 300;

	/**
	 * Map of outbox activity types to SSE event types.
	 *
	 * @see https://swicg.github.io/activitypub-api/sse
	 *
	 * @var array
	 */
	const EVENT_TYPE_MAP = array(
		'Create'   => 'Add',
		'Announce' => 'Add',
		'Like'     => 'Add',
		'Update'   => 'Update',
		'Delete'   => 'Delete',
		'Undo'     => 'Remove',
	);

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args' => array(
					'user_id'    => array(
						'description'       => 'The ID of the user or actor.',
						'type'              => 'integer',
						'validate_callback' => array( $this, 'validate_user_id' ),
					),
					'collection' => array(
						'description' => 'The collection to stream (outbox or inbox).',
						'type'        => 'string',
						'enum'        => array( 'outbox', 'inbox' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/proxy/stream',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_proxy_stream' ),
					'permission_callback' => array( $this, 'get_proxy_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description'       => 'The remote object ID (URI) whose eventStream to proxy.',
							'type'              => 'string',
							'format'            => 'uri',
							'required'          => true,
							'sanitize_callback' => 'sanitize_url',
							'validate_callback' => array( $this, 'validate_proxy_url' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Validates the user_id parameter.
	 *
	 * @param mixed $user_id The user_id parameter.
	 * @return bool|\WP_Error True if the user_id is valid, WP_Error otherwise.
	 */
	public function validate_user_id( $user_id ) {
		$user = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		return true;
	}

	/**
	 * Check permissions for the SSE stream endpoint.
	 *
	 * Requires OAuth authentication with the `push` scope.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		$oauth_result = OAuth_Server::check_oauth_permission( $request, Scope::PUSH );
		if ( true !== $oauth_result ) {
			return $oauth_result;
		}

		$user_id = $request->get_param( 'user_id' );

		if ( null === $user_id ) {
			return true;
		}

		return $this->verify_owner( $request );
	}

	/**
	 * Check permissions for the proxy event stream endpoint.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function get_proxy_permissions_check( $request ) {
		return OAuth_Server::check_oauth_permission( $request, Scope::PUSH );
	}

	/**
	 * Stream SSE events for a collection.
	 *
	 * This method sends raw SSE output and calls exit — it does not
	 * return a WP_REST_Response.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function get_items( $request ) {
		$user_id    = $request->get_param( 'user_id' );
		$collection = $request->get_param( 'collection' );

		// Allow PHP to detect client disconnects instead of auto-terminating.
		ignore_user_abort( true );

		$this->send_sse_headers();

		// Honor Last-Event-ID for reconnecting clients (per SSE spec).
		$last_event_id = isset( $_SERVER['HTTP_LAST_EVENT_ID'] )
			? \absint( \wp_unslash( $_SERVER['HTTP_LAST_EVENT_ID'] ) )
			: 0;

		// Use Last-Event-ID if provided, otherwise start from the latest item.
		$since_id = $last_event_id ? $last_event_id : $this->get_latest_item_id( $user_id, $collection );
		$start    = time();

		// Send initial connected event.
		$this->send_sse_comment( 'connected' );

		while ( ( time() - $start ) < self::MAX_DURATION ) {
			if ( \connection_aborted() ) {
				break;
			}

			// Check for signal transient before querying the DB.
			$signal_key = sprintf( 'activitypub_sse_signal_%s_%s', $user_id, $collection );
			$signal     = \get_transient( $signal_key );

			if ( $signal ) {
				\delete_transient( $signal_key );

				$new_items = $this->get_new_items( $user_id, $collection, $since_id );

				foreach ( $new_items as $item ) {
					$this->send_sse_event( $item, $collection );

					if ( $item->ID > $since_id ) {
						$since_id = $item->ID;
					}
				}
			}

			// Send keepalive comment.
			$this->send_sse_comment( 'keepalive ' . \gmdate( 'c' ) );

			// Flush and sleep.
			$this->flush_output();

			// phpcs:ignore WordPress.WP.AlternativeFunctions.sleep_sleep -- SSE long-polling requires blocking sleep.
			sleep( self::POLL_INTERVAL );
		}

		$this->send_sse_comment( 'timeout' );
		$this->flush_output();

		exit;
	}

	/**
	 * Validate the proxy URL parameter.
	 *
	 * @param string $url The URL to validate.
	 * @return bool True if valid.
	 */
	public function validate_proxy_url( $url ) {
		if ( 'https' !== \wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return false;
		}

		return (bool) \wp_http_validate_url( $url );
	}

	/**
	 * Proxy a remote eventStream.
	 *
	 * Fetches the remote object to discover its eventStream URL,
	 * then opens a streaming connection and relays SSE events.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_Error|void WP_Error on failure, exits on success.
	 */
	public function get_proxy_stream( $request ) {
		$remote_id = $request->get_param( 'id' );

		// Fetch the remote object to discover its eventStream URL.
		$object = Http::get_remote_object( $remote_id );

		if ( \is_wp_error( $object ) ) {
			return new \WP_Error(
				'activitypub_proxy_fetch_failed',
				\__( 'Failed to fetch the remote object.', 'activitypub' ),
				array( 'status' => 502 )
			);
		}

		// Look for eventStream in the object itself or in its first/last/etc.
		$stream_url = isset( $object['eventStream'] ) ? $object['eventStream'] : null;

		if ( ! $stream_url ) {
			return new \WP_Error(
				'activitypub_no_event_stream',
				\__( 'The remote object does not advertise an eventStream.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->validate_proxy_url( $stream_url ) ) {
			return new \WP_Error(
				'activitypub_invalid_event_stream',
				\__( 'The remote eventStream URL is not valid.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$this->relay_remote_stream( $stream_url );
	}

	/**
	 * Open a streaming connection to a remote SSE endpoint and relay events.
	 *
	 * Uses PHP streams directly since WordPress HTTP API does not support
	 * streaming responses.
	 *
	 * @param string $stream_url The remote eventStream URL.
	 */
	private function relay_remote_stream( $stream_url ) {
		ignore_user_abort( true );

		$parsed = \wp_parse_url( $stream_url );
		$host   = $parsed['host'];
		$port   = isset( $parsed['port'] ) ? $parsed['port'] : 443;
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';

		if ( isset( $parsed['query'] ) ) {
			$path .= '?' . $parsed['query'];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_stream_socket_client -- SSE proxy requires raw streaming.
		$context = stream_context_create(
			array(
				'ssl' => array(
					'verify_peer'      => true,
					'verify_peer_name' => true,
				),
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_stream_socket_client -- SSE proxy requires raw streaming.
		$stream = @stream_socket_client(
			'ssl://' . $host . ':' . $port,
			$errno,
			$errstr,
			30,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! $stream ) {
			\status_header( 502 );
			echo \wp_json_encode(
				array(
					'code'    => 'activitypub_proxy_connection_failed',
					'message' => \__( 'Failed to connect to the remote eventStream.', 'activitypub' ),
				)
			);
			exit;
		}

		// Send HTTP request for SSE.
		$request_headers  = "GET {$path} HTTP/1.1\r\n";
		$request_headers .= "Host: {$host}\r\n";
		$request_headers .= "Accept: text/event-stream\r\n";
		$request_headers .= "Cache-Control: no-cache\r\n";
		$request_headers .= "Connection: keep-alive\r\n";
		$request_headers .= "\r\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Raw stream operation.
		fwrite( $stream, $request_headers );

		// Read and skip the HTTP response headers.
		$header_complete = false;
		$status_code     = 0;

		while ( ! feof( $stream ) ) {
			$line = fgets( $stream, 8192 );

			if ( false === $line ) {
				break;
			}

			// Parse status line.
			if ( ! $status_code && preg_match( '/^HTTP\/\d\.\d (\d{3})/', $line, $matches ) ) {
				$status_code = (int) $matches[1];
			}

			// Empty line signals end of headers.
			if ( "\r\n" === $line || "\n" === $line ) {
				$header_complete = true;
				break;
			}
		}

		if ( ! $header_complete || 200 !== $status_code ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Raw stream operation.
			fclose( $stream );
			\status_header( 502 );
			echo \wp_json_encode(
				array(
					'code'    => 'activitypub_proxy_stream_error',
					'message' => \__( 'The remote eventStream returned an error.', 'activitypub' ),
				)
			);
			exit;
		}

		// Now relay SSE: send our own SSE headers and forward the stream.
		$this->send_sse_headers();
		$this->send_sse_comment( 'proxying ' . $host );

		$start = time();

		stream_set_timeout( $stream, self::POLL_INTERVAL + 5 );

		while ( ! feof( $stream ) && ( time() - $start ) < self::MAX_DURATION ) {
			if ( \connection_aborted() ) {
				break;
			}

			$line = fgets( $stream, 8192 );

			if ( false === $line ) {
				$meta = stream_get_meta_data( $stream );
				if ( ! empty( $meta['timed_out'] ) ) {
					// Send keepalive on timeout and continue.
					$this->send_sse_comment( 'keepalive ' . \gmdate( 'c' ) );
					$this->flush_output();
					continue;
				}
				break;
			}

			// Relay the SSE line as-is from the remote server.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Relaying raw SSE protocol data.
			echo $line;
			$this->flush_output();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Raw stream operation.
		fclose( $stream );

		$this->send_sse_comment( 'proxy timeout' );
		$this->flush_output();

		exit;
	}

	/**
	 * Send SSE-specific HTTP headers.
	 */
	private function send_sse_headers() {
		// Clear any output buffers.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		\status_header( 200 );
		\header( 'Content-Type: text/event-stream' );
		\header( 'Cache-Control: no-cache' );
		\header( 'X-Accel-Buffering: no' );

		// CORS headers for browser-based clients.
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? \esc_url_raw( \wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		if ( $origin ) {
			\header( 'Access-Control-Allow-Origin: ' . $origin );
			\header( 'Access-Control-Allow-Credentials: true' );
			\header( 'Vary: Origin' );
		} else {
			\header( 'Access-Control-Allow-Origin: *' );
		}
	}

	/**
	 * Send an SSE event.
	 *
	 * @param \WP_Post $item       The outbox or inbox post item.
	 * @param string   $collection The collection type ('outbox' or 'inbox').
	 */
	private function send_sse_event( $item, $collection ) {
		$event_type = $this->get_event_type( $item, $collection );
		$data       = $this->get_event_data( $item, $collection );

		if ( ! $data ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol requires raw output.
		echo 'event: ' . $event_type . "\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol requires raw JSON output.
		echo 'data: ' . \wp_json_encode( $data ) . "\n";
		echo 'id: ' . (int) $item->ID . "\n\n";
	}

	/**
	 * Send an SSE comment (keepalive or informational).
	 *
	 * @param string $comment The comment text.
	 */
	private function send_sse_comment( $comment ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol requires raw output.
		echo ': ' . $comment . "\n\n";
	}

	/**
	 * Flush output buffers.
	 */
	private function flush_output() {
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Get the SSE event type for an item.
	 *
	 * @param \WP_Post $item       The outbox or inbox post item.
	 * @param string   $collection The collection type.
	 * @return string The SSE event type (Add, Update, or Remove).
	 */
	private function get_event_type( $item, $collection ) {
		if ( 'inbox' === $collection ) {
			// Inbox items are always additions to the collection.
			return 'Add';
		}

		$activity_type = \get_post_meta( $item->ID, '_activitypub_activity_type', true );

		if ( isset( self::EVENT_TYPE_MAP[ $activity_type ] ) ) {
			return self::EVENT_TYPE_MAP[ $activity_type ];
		}

		return 'Add';
	}

	/**
	 * Get the event data (activity JSON) for an item.
	 *
	 * @param \WP_Post $item       The outbox or inbox post item.
	 * @param string   $collection The collection type.
	 * @return array|null The activity data or null on failure.
	 */
	private function get_event_data( $item, $collection ) {
		if ( 'outbox' === $collection ) {
			$activity = Outbox::get_activity( $item->ID );

			if ( \is_wp_error( $activity ) ) {
				return null;
			}

			return $activity->to_array( false );
		}

		// Inbox items store activity JSON directly in post_content.
		$data = \json_decode( $item->post_content, true );

		return $data ? $data : null;
	}

	/**
	 * Get the latest item ID for a collection.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $collection The collection type.
	 * @return int The latest post ID or 0.
	 */
	private function get_latest_item_id( $user_id, $collection ) {
		$post_type = 'outbox' === $collection ? Outbox::POST_TYPE : Inbox::POST_TYPE;

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( 'outbox' === $collection ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = array(
				array(
					'key'   => '_activitypub_activity_actor',
					'value' => Actors::get_type_by_id( $user_id ),
				),
			);
		} else {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = array(
				array(
					'key'   => '_activitypub_user_id',
					'value' => $user_id,
				),
			);
		}

		$query = new \WP_Query( $args );

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Get new items since a given ID.
	 *
	 * Uses a `posts_where` filter to add `ID > $since_id` to the query,
	 * since WP_Query does not natively support filtering by minimum post ID.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $collection The collection type.
	 * @param int    $since_id   Only return items with ID greater than this.
	 * @return \WP_Post[] Array of new post items.
	 */
	private function get_new_items( $user_id, $collection, $since_id ) {
		$post_type = 'outbox' === $collection ? Outbox::POST_TYPE : Inbox::POST_TYPE;

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 20,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		if ( 'outbox' === $collection ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = array(
				array(
					'key'   => '_activitypub_activity_actor',
					'value' => Actors::get_type_by_id( $user_id ),
				),
			);
		} else {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = array(
				array(
					'key'   => '_activitypub_user_id',
					'value' => $user_id,
				),
			);
		}

		// Add a posts_where filter to restrict to items newer than $since_id.
		if ( $since_id > 0 ) {
			$where_filter = function ( $where ) use ( $since_id ) {
				global $wpdb;
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $since_id );
				return $where;
			};
			\add_filter( 'posts_where', $where_filter );
		}

		$query = new \WP_Query( $args );

		if ( $since_id > 0 ) {
			\remove_filter( 'posts_where', $where_filter );
		}

		return $query->posts;
	}

	/**
	 * Get the stream URL for a collection.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $collection The collection name ('outbox' or 'inbox').
	 * @return string The stream URL.
	 */
	public static function get_stream_url( $user_id, $collection ) {
		return get_rest_url_by_path( sprintf( 'actors/%d/%s/stream', $user_id, $collection ) );
	}

	/**
	 * Get the proxy event stream URL.
	 *
	 * @return string The proxy event stream URL.
	 */
	public static function get_proxy_url() {
		return get_rest_url_by_path( 'proxy/stream' );
	}
}
