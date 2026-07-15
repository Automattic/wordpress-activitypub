<?php
/**
 * External Activity delivery spool.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Outbox;

/** Transport complete Activity payloads owned by companion plugins. */
class External_Delivery {
	const PROCESS_HOOK = 'activitypub_process_external_delivery';

	/** Register only the transport spool and its worker. */
	public static function init() {
		Post_Types::register_outbox_post_type();
		\add_action( self::PROCESS_HOOK, array( self::class, 'process' ), 10, 2 );
	}

	/**
	 * Queue one immutable Activity payload for explicit Inbox URLs.
	 *
	 * @param array $payload Activity payload.
	 * @param array $sender  External signing descriptor.
	 * @param array $inboxes Explicit recipient Inboxes.
	 * @param array $options Optional transport settings.
	 * @return int|\WP_Error
	 */
	public static function enqueue( $payload, $sender, $inboxes, $options = array() ) {
		unset( $options );
		if ( ! \is_array( $payload ) || ! \is_array( $sender ) || ! \is_array( $inboxes ) ) {
			return new \WP_Error( 'activitypub_external_delivery_invalid', \__( 'External delivery requires array arguments.', 'activitypub' ) );
		}
		if ( isset( $sender['private_key'] ) ) {
			return new \WP_Error( 'activitypub_external_delivery_private_key', \__( 'Private key material must not be passed to the delivery spool.', 'activitypub' ) );
		}

		$activity_uri = self::http_uri( $payload['id'] ?? '' );
		$actor_uri    = self::http_uri( $sender['actor_uri'] ?? '' );
		$key_id       = self::http_uri( $sender['key_id'] ?? '' );
		$key_ref      = \is_scalar( $sender['private_key_ref'] ?? null ) ? \sanitize_text_field( (string) $sender['private_key_ref'] ) : '';
		if ( '' === $activity_uri || '' === $actor_uri || '' === $key_id || '' === $key_ref || self::member_uri( $payload['actor'] ?? '' ) !== $actor_uri ) {
			return new \WP_Error( 'activitypub_external_delivery_sender', \__( 'The Activity or external signing descriptor is invalid.', 'activitypub' ) );
		}

		$recipients = array();
		foreach ( $inboxes as $inbox ) {
			$inbox = self::http_uri( $inbox, true );
			if ( '' === $inbox || ! \wp_http_validate_url( $inbox ) ) {
				return new \WP_Error( 'activitypub_external_delivery_inbox', \__( 'Every recipient Inbox must be a public HTTPS URL.', 'activitypub' ) );
			}
			$recipients[] = $inbox;
		}
		$recipients = \array_values( \array_unique( $recipients ) );
		if ( empty( $recipients ) ) {
			return new \WP_Error( 'activitypub_external_delivery_recipients', \__( 'At least one recipient Inbox is required.', 'activitypub' ) );
		}

		$json = \wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! \is_string( $json ) || \strlen( $json ) > MB_IN_BYTES ) {
			return new \WP_Error( 'activitypub_external_delivery_payload', \__( 'The Activity payload is invalid or exceeds one MiB.', 'activitypub' ) );
		}

		$existing = self::find( $activity_uri );
		if ( 0 < $existing ) {
			return $existing;
		}

		$has_kses = false !== \has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			\kses_remove_filters();
		}
		$post_id = \wp_insert_post(
			array(
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'pending',
				'post_author'  => 0,
				'post_title'   => \sprintf( '[External %s] %s', \sanitize_text_field( (string) ( $payload['type'] ?? 'Activity' ) ), $activity_uri ),
				'post_content' => \wp_slash( $json ),
				// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned,WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
				'meta_input'   => array(
					'_activitypub_external_delivery'     => 1,
					'_activitypub_external_activity_uri' => $activity_uri,
					'_activitypub_external_activity_uri_hash' => \hash( 'sha256', $activity_uri ),
					'_activitypub_external_actor_uri'    => $actor_uri,
					'_activitypub_external_key_id'       => $key_id,
					'_activitypub_external_private_key_ref' => $key_ref,
					'_activitypub_external_inboxes'      => \wp_json_encode( $recipients ),
					'_activitypub_external_pending_inboxes' => \wp_json_encode( $recipients ),
					'_activitypub_external_attempt'      => 0,
					'_activitypub_external_status'       => 'queued',
				),
				// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned,WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
			),
			true
		);
		if ( $has_kses ) {
			\kses_init_filters();
		}
		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}
		\wp_schedule_single_event( \time(), self::PROCESS_HOOK, array( (int) $post_id, 1 ) );
		return (int) $post_id;
	}

	/**
	 * Process one transport spool row.
	 *
	 * @param int $post_id Transport spool post ID.
	 * @param int $attempt Current delivery attempt.
	 */
	public static function process( $post_id, $attempt = 1 ) {
		$post = \get_post( (int) $post_id );
		if ( ! $post || Outbox::POST_TYPE !== $post->post_type || ! \get_post_meta( $post->ID, '_activitypub_external_delivery', true ) ) {
			return;
		}
		$descriptor = array(
			'actor_uri'       => (string) \get_post_meta( $post->ID, '_activitypub_external_actor_uri', true ),
			'key_id'          => (string) \get_post_meta( $post->ID, '_activitypub_external_key_id', true ),
			'private_key_ref' => (string) \get_post_meta( $post->ID, '_activitypub_external_private_key_ref', true ),
		);
		/**
		 * Resolve transient signing material for one external sender.
		 *
		 * @param null|array $identity   actor_uri, key_id, private_key.
		 * @param array      $descriptor Persisted non-secret descriptor.
		 * @param int        $post_id    Transport spool post ID.
		 */
		$identity = \apply_filters( 'activitypub_resolve_external_signing_identity', null, $descriptor, $post->ID );
		if ( ! \is_array( $identity )
			|| ! \hash_equals( $descriptor['actor_uri'], (string) ( $identity['actor_uri'] ?? '' ) )
			|| ! \hash_equals( $descriptor['key_id'], (string) ( $identity['key_id'] ?? '' ) )
			|| empty( $identity['private_key'] )
		) {
			self::finish( $post->ID, 'failed', \__( 'The external signing identity could not be resolved.', 'activitypub' ) );
			return;
		}

		$pending = \json_decode( (string) \get_post_meta( $post->ID, '_activitypub_external_pending_inboxes', true ), true );
		$pending = \is_array( $pending ) ? $pending : array();
		$retry   = array();
		$errors  = array();
		foreach ( $pending as $inbox ) {
			$response = \wp_safe_remote_post(
				$inbox,
				array(
					'body'                => $post->post_content,
					'headers'             => array(
						'Accept'       => 'application/activity+json',
						'Content-Type' => 'application/activity+json',
						'Date'         => \gmdate( 'D, d M Y H:i:s T' ),
					),
					'timeout'             => 10,
					'limit_response_size' => MB_IN_BYTES,
					'redirection'         => 0,
					'data_format'         => 'body',
					'key_id'              => $identity['key_id'],
					'private_key'         => $identity['private_key'],
				)
			);
			$code     = \is_wp_error( $response ) ? 0 : (int) \wp_remote_retrieve_response_code( $response );
			if ( \is_wp_error( $response ) || \in_array( $code, Dispatcher::get_retry_error_codes(), true ) ) {
				$retry[]  = $inbox;
				$errors[] = \is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code;
			} elseif ( 200 > $code || 300 <= $code ) {
				$errors[] = 'HTTP ' . $code;
			}
		}

		\update_post_meta( $post->ID, '_activitypub_external_attempt', (int) $attempt );
		\update_post_meta( $post->ID, '_activitypub_external_pending_inboxes', \wp_json_encode( $retry ) );
		if ( ! empty( $retry ) && (int) $attempt < Dispatcher::get_retry_max_attempts() ) {
			\update_post_meta( $post->ID, '_activitypub_external_status', 'retrying' );
			\update_post_meta( $post->ID, '_activitypub_external_last_error', \implode( '; ', $errors ) );
			$next = (int) $attempt + 1;
			\wp_schedule_single_event( \time() + ( $next * $next * Dispatcher::get_retry_delay() ), self::PROCESS_HOOK, array( $post->ID, $next ) );
			return;
		}

		self::finish( $post->ID, empty( $errors ) ? 'delivered' : 'failed', \implode( '; ', $errors ) );
	}

	/**
	 * Find a transport spool row by exact Activity URI.
	 *
	 * @param string $activity_uri Canonical Activity URI.
	 * @return int
	 */
	private static function find( $activity_uri ) {
		$ids = \get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'post_status'    => array( 'pending', 'publish' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_activitypub_external_activity_uri', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $activity_uri, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return isset( $ids[0] ) ? (int) $ids[0] : 0;
	}

	/**
	 * Publish a completed spool row and retain its transport result.
	 *
	 * @param int    $post_id Transport spool post ID.
	 * @param string $status  delivered|failed.
	 * @param string $error   Last transport error.
	 */
	private static function finish( $post_id, $status, $error = '' ) {
		\update_post_meta( $post_id, '_activitypub_external_status', $status );
		\update_post_meta( $post_id, '_activitypub_external_last_error', $error );
		\wp_publish_post( $post_id );
	}

	/**
	 * Validate one absolute HTTP URI, optionally requiring HTTPS.
	 *
	 * @param mixed $value Candidate value.
	 * @param bool  $https Require HTTPS.
	 * @return string
	 */
	private static function http_uri( $value, $https = false ) {
		$uri   = \is_scalar( $value ) ? \trim( (string) $value ) : '';
		$parts = \wp_parse_url( $uri );
		if ( ! \is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		$scheme = \strtolower( (string) ( $parts['scheme'] ?? '' ) );
		return ( $https ? 'https' === $scheme : \in_array( $scheme, array( 'http', 'https' ), true ) ) ? $uri : '';
	}

	/**
	 * Resolve an ActivityStreams URI member.
	 *
	 * @param mixed $value ActivityStreams member.
	 * @return string
	 */
	private static function member_uri( $value ) {
		if ( \is_scalar( $value ) ) {
			return self::http_uri( $value );
		}
		if ( \is_array( $value ) && ! \array_is_list( $value ) ) {
			return self::member_uri( $value['id'] ?? '' );
		}
		return '';
	}
}
