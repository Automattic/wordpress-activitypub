<?php
/**
 * Event Stream Trait file.
 *
 * Provides Server-Sent Events (SSE) functionality for real-time
 * ActivityPub collection updates and proxy streaming.
 *
 * @package Activitypub
 * @see https://swicg.github.io/activitypub-api/sse
 * @since 8.1.0
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;


/**
 * Event Stream Trait.
 *
 * Provides SSE streaming capabilities for collection controllers
 * (Outbox, Inbox) and the Proxy controller.
 *
 * @since 8.1.0
 */
trait Event_Stream {

	/**
	 * Map of ActivityPub activity types to SSE event types.
	 *
	 * @see https://swicg.github.io/activitypub-api/sse
	 *
	 * @return array The event type map.
	 */
	protected static function get_event_type_map() {
		return array(
			'Create'   => 'Add',
			'Announce' => 'Add',
			'Like'     => 'Add',
			'Update'   => 'Update',
			'Delete'   => 'Delete',
			'Undo'     => 'Remove',
		);
	}

	/**
	 * Check permissions for the stream endpoint.
	 *
	 * Requires OAuth authentication with the push scope.
	 * Falls back to `access_token` query parameter for EventSource clients,
	 * since the browser EventSource API cannot send custom headers.
	 *
	 * @see https://swicg.github.io/activitypub-api/sse
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function get_stream_permissions_check( $request ) {
		// If not already OAuth-authenticated, try the access_token query parameter.
		if ( ! OAuth_Server::is_oauth_request() ) {
			$this->authenticate_from_query_param();
		}

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
	 * Authenticate from the access_token query parameter.
	 *
	 * The browser EventSource API cannot send custom headers, so SSE
	 * clients pass the OAuth token as a query parameter. This method
	 * injects it as an Authorization header and re-runs OAuth
	 * authentication so the server recognizes the request.
	 *
	 * @since 8.1.0
	 *
	 * @see https://swicg.github.io/activitypub-api/sse
	 */
	private function authenticate_from_query_param() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque auth token, must not be altered.
		if ( empty( $_GET['access_token'] ) || ! \is_string( $_GET['access_token'] ) ) {
			return;
		}

		$token_string = \wp_unslash( $_GET['access_token'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Reject tokens that are too long or contain unexpected characters.
		if ( \strlen( $token_string ) > 512 || \preg_match( '/[^A-Za-z0-9._~+\/-]/', $token_string ) ) {
			return;
		}

		// Inject as Authorization header so the OAuth server can find it.
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token_string;

		// Re-run OAuth authentication.
		OAuth_Server::authenticate_oauth( null );
	}

	/**
	 * Stream SSE events for a collection.
	 *
	 * Sends raw SSE output and calls exit.
	 *
	 * @param int    $user_id    The actor ID.
	 * @param string $collection The collection type ('outbox' or 'inbox').
	 */
	protected function stream_collection( $user_id, $collection ) {
		// Allow PHP to detect client disconnects instead of auto-terminating.
		ignore_user_abort( true );

		// Extend PHP execution time for long-lived SSE connections.
		set_time_limit( 0 );

		$this->send_sse_headers();

		// Honor Last-Event-ID for reconnecting clients (per SSE spec).
		$last_event_id = isset( $_SERVER['HTTP_LAST_EVENT_ID'] )
			? \absint( \wp_unslash( $_SERVER['HTTP_LAST_EVENT_ID'] ) )
			: 0;

		// Use Last-Event-ID if provided, otherwise start from the latest item.
		$since_id = $last_event_id ? $last_event_id : $this->get_latest_item_id( $user_id, $collection );
		$start    = time();

		$this->send_sse_comment( 'connected' );

		while ( ( time() - $start ) < 300 ) {
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

				// Re-set signal if we hit the limit, so remaining items are fetched next iteration.
				if ( count( $new_items ) >= 20 ) {
					\set_transient( $signal_key, time(), 5 * MINUTE_IN_SECONDS );
				}
			}

			$this->send_sse_comment( 'keepalive ' . \gmdate( 'c' ) );
			$this->flush_output();

			// phpcs:ignore WordPress.WP.AlternativeFunctions.sleep_sleep -- SSE long-polling requires blocking sleep.
			sleep( 5 );
		}

		$this->send_sse_comment( 'timeout' );
		$this->flush_output();

		exit;
	}

	/**
	 * Open a streaming connection to a remote SSE endpoint and relay events.
	 *
	 * Uses PHP streams directly because the WordPress HTTP API
	 * does not support streaming responses.
	 *
	 * @param string $stream_url The remote eventStream URL.
	 */
	protected function relay_remote_stream( $stream_url ) {
		ignore_user_abort( true );

		// Extend PHP execution time for long-lived SSE connections.
		set_time_limit( 0 );

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
		$stream = stream_socket_client(
			'ssl://' . $host . ':' . $port,
			$errno,
			$errstr,
			30,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! $stream ) {
			\status_header( 502 );
			\header( 'Content-Type: application/json' );
			Server::send_cors_headers();
			echo \wp_json_encode(
				array(
					'code'    => 'activitypub_proxy_connection_failed',
					'message' => \__( 'Failed to connect to the remote eventStream.', 'activitypub' ),
				)
			);
			exit;
		}

		// Send the HTTP request.
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
			\header( 'Content-Type: application/json' );
			Server::send_cors_headers();
			echo \wp_json_encode(
				array(
					'code'    => 'activitypub_proxy_stream_error',
					'message' => \__( 'The remote eventStream returned an error.', 'activitypub' ),
				)
			);
			exit;
		}

		// Send our own SSE headers and relay the remote stream.
		$this->send_sse_headers();
		$this->send_sse_comment( 'proxying ' . $host );

		$start = time();

		stream_set_timeout( $stream, 10 );

		while ( ! feof( $stream ) && ( time() - $start ) < 300 ) {
			if ( \connection_aborted() ) {
				break;
			}

			$line = fgets( $stream, 8192 );

			if ( false === $line ) {
				$meta = stream_get_meta_data( $stream );

				if ( ! empty( $meta['timed_out'] ) ) {
					$this->send_sse_comment( 'keepalive ' . \gmdate( 'c' ) );
					$this->flush_output();
					continue;
				}

				break;
			}

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
	protected function send_sse_headers() {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		\status_header( 200 );
		\header( 'Content-Type: text/event-stream' );
		\header( 'Cache-Control: no-cache, no-store' );
		\header( 'Referrer-Policy: no-referrer' );
		\header( 'X-Accel-Buffering: no' );

		// SSE exits before rest_post_dispatch, so CORS must be sent directly.
		Server::send_cors_headers();
	}

	/**
	 * Send an SSE event for a collection item.
	 *
	 * @param \WP_Post $item       The collection post item.
	 * @param string   $collection The collection type ('outbox' or 'inbox').
	 */
	protected function send_sse_event( $item, $collection ) {
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
	 * Send an SSE comment line.
	 *
	 * @param string $comment The comment text.
	 */
	protected function send_sse_comment( $comment ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol requires raw output.
		echo ': ' . $comment . "\n\n";
	}

	/**
	 * Flush all output buffers.
	 */
	protected function flush_output() {
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Get the SSE event type for a collection item.
	 *
	 * @param \WP_Post $item       The collection post item.
	 * @param string   $collection The collection type ('outbox' or 'inbox').
	 *
	 * @return string The SSE event type.
	 */
	protected function get_event_type( $item, $collection ) {
		if ( 'inbox' === $collection ) {
			return 'Add';
		}

		$activity_type  = \get_post_meta( $item->ID, '_activitypub_activity_type', true );
		$event_type_map = self::get_event_type_map();

		if ( isset( $event_type_map[ $activity_type ] ) ) {
			return $event_type_map[ $activity_type ];
		}

		return 'Add';
	}

	/**
	 * Get the activity data for a collection item.
	 *
	 * @param \WP_Post $item       The collection post item.
	 * @param string   $collection The collection type ('outbox' or 'inbox').
	 *
	 * @return array|null The activity data, or null on failure.
	 */
	protected function get_event_data( $item, $collection ) {
		if ( 'outbox' === $collection ) {
			$activity = Outbox::get_activity( $item->ID );

			if ( \is_wp_error( $activity ) ) {
				return null;
			}

			return $activity->to_array( false );
		}

		$data = \json_decode( $item->post_content, true );

		return $data ? $data : null;
	}

	/**
	 * Get the latest item ID for a collection.
	 *
	 * @param int    $user_id    The actor ID.
	 * @param string $collection The collection type ('outbox' or 'inbox').
	 *
	 * @return int The latest post ID, or 0 if empty.
	 */
	protected function get_latest_item_id( $user_id, $collection ) {
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
	 * Get the eventStream URL for a collection.
	 *
	 * @param int    $user_id    The actor ID.
	 * @param string $collection The collection type ('outbox' or 'inbox').
	 *
	 * @return string The eventStream URL.
	 */
	public function get_stream_url( $user_id, $collection ) {
		return \rest_url( sprintf( '%s/actors/%d/%s/stream', $this->namespace, $user_id, $collection ) );
	}

	/**
	 * Get new collection items since a given ID.
	 *
	 * @param int    $user_id    The actor ID.
	 * @param string $collection The collection type ('outbox' or 'inbox').
	 * @param int    $since_id   Only return items with ID greater than this.
	 *
	 * @return \WP_Post[] Array of new post items.
	 */
	protected function get_new_items( $user_id, $collection, $since_id ) {
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
}
