<?php
/**
 * OAuth 2.0 Client model for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

use Activitypub\Sanitize;

use function Activitypub\get_client_ip;
use function Activitypub\resolve_public_host;

/**
 * Client class for managing OAuth 2.0 client registrations.
 *
 * Supports both manual registration and RFC 7591 dynamic client registration,
 * plus Client Identifier Metadata Documents (CIMD) where the `client_id` is
 * itself a URL hosting a metadata document.
 *
 * ## Loopback policy (RFC 8252)
 *
 * Native apps register loopback redirect URIs to receive the OAuth callback
 * on a port the app opened locally. RFC 8252 §7.3 / §8.3 cover this and
 * specifically authorise `http://127.0.0.1:{port}/{path}` (IPv4) and
 * `http://[::1]:{port}/{path}` (IPv6). `localhost` is permitted by common
 * practice, though §8.3 marks it "NOT RECOMMENDED".
 *
 * `is_loopback()` reflects that scope: it matches `127.0.0.0/8`, `::1`
 * (any spelling, normalised via `inet_pton`), `::ffff:127.x.x.x`, `localhost`,
 * and `*.localhost`. Reserved-but-not-loopback addresses such as `0.0.0.0`,
 * link-local `169.254.0.0/16`, and RFC1918 ranges are explicitly *not*
 * treated as loopback and never bypass `wp_safe_remote_get()`.
 *
 * RFC 8252's loopback allowance applies to redirect URIs only. The CIMD
 * document must be served over HTTPS from a publicly resolvable host:
 * `client_id` discovery rejects non-`https` URLs up front, and
 * `fetch_client_metadata()` resolves the host first and rejects anything
 * private or loopback before falling through to `wp_safe_remote_get()`.
 * Local development against a loopback CIMD endpoint is intentionally not
 * supported.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8252 RFC 8252 — OAuth 2.0 for Native Apps
 * @see https://datatracker.ietf.org/doc/html/rfc7591 RFC 7591 — OAuth 2.0 Dynamic Client Registration
 */
class Client {
	/**
	 * Post type for OAuth clients.
	 */
	const POST_TYPE = 'ap_oauth_client';

	/**
	 * The post ID of the client.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Constructor.
	 *
	 * @param int $post_id The post ID of the client.
	 */
	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * Register a new OAuth client.
	 *
	 * @param array $data Client registration data.
	 *                    - name: Client name (required).
	 *                    - redirect_uris: Array of redirect URIs (required).
	 *                    - description: Client description (optional).
	 *                    - is_public: Whether client is public/PKCE-only (default true).
	 *                    - scopes: Allowed scopes (optional, defaults to all).
	 * @return array|\WP_Error Client credentials or error.
	 */
	public static function register( $data ) {
		$name          = $data['name'] ?? '';
		$redirect_uris = $data['redirect_uris'] ?? array();
		$description   = $data['description'] ?? '';
		$is_public     = $data['is_public'] ?? true;
		$scopes        = $data['scopes'] ?? Scope::ALL;

		// Validate required fields.
		if ( empty( $name ) ) {
			return new \WP_Error(
				'activitypub_missing_client_name',
				\__( 'Client name is required.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $redirect_uris ) ) {
			return new \WP_Error(
				'activitypub_missing_redirect_uri',
				\__( 'At least one redirect URI is required.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Validate redirect URIs.
		foreach ( $redirect_uris as $uri ) {
			if ( ! self::validate_uri_format( $uri ) ) {
				return new \WP_Error(
					'activitypub_invalid_redirect_uri',
					/* translators: %s: The invalid redirect URI */
					sprintf( \__( 'Invalid redirect URI: %s', 'activitypub' ), $uri ),
					array( 'status' => 400 )
				);
			}
		}

		// Generate client credentials.
		$client_id     = self::generate_client_id();
		$client_secret = null;

		if ( ! $is_public ) {
			$client_secret = self::generate_client_secret();
		}

		// Create the client post.
		$post_id = \wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => $description,
				'meta_input'   => array(
					'_activitypub_client_id'          => $client_id,
					'_activitypub_client_secret_hash' => $client_secret ? \wp_hash_password( $client_secret ) : '',
					'_activitypub_redirect_uris'      => array_map( array( Sanitize::class, 'redirect_uri' ), $redirect_uris ),
					'_activitypub_allowed_scopes'     => Scope::validate( $scopes ),
					'_activitypub_is_public'          => (bool) $is_public,
				),
			),
			true
		);

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = array(
			'client_id' => $client_id,
		);

		if ( $client_secret ) {
			$result['client_secret'] = $client_secret;
		}

		return $result;
	}

	/**
	 * Get client by client_id.
	 *
	 * Supports auto-discovery: if client_id is a URL and not found locally,
	 * fetches the Client ID Metadata Document (CIMD) and auto-registers.
	 *
	 * @param string $client_id The client ID.
	 * @return Client|\WP_Error The client or error.
	 */
	public static function get( $client_id ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Client lookup by ID is necessary.
		$posts = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'meta_key'    => '_activitypub_client_id',
				'meta_value'  => $client_id,
				'numberposts' => 1,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( ! empty( $posts ) ) {
			$client = new self( $posts[0]->ID );

			/*
			 * Re-discover stale auto-discovered clients that have no redirect URIs.
			 * This can happen when a previous discovery failed to parse the metadata
			 * correctly (e.g. before ActivityStreams vocabulary support was added).
			 */
			if ( $client->is_discovered() && empty( $client->get_redirect_uris() ) && self::is_discoverable_url( $client_id ) ) {
				\wp_delete_post( $posts[0]->ID, true );
				return self::discover_and_register( $client_id );
			}

			return $client;
		}

		// If client_id is a discoverable URL (HTTPS), try auto-discovery.
		if ( self::is_discoverable_url( $client_id ) ) {
			return self::discover_and_register( $client_id );
		}

		return new \WP_Error(
			'activitypub_client_not_found',
			\__( 'OAuth client not found.', 'activitypub' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Determine whether a client_id is a discoverable URL.
	 *
	 * Only HTTPS URLs are eligible. The CIMD draft requires HTTPS for
	 * production, and accepting cleartext URLs would let a network-position
	 * attacker rewrite the metadata response and inject attacker-controlled
	 * redirect URIs that preserve the same client_id.
	 *
	 * @param string $client_id The client ID to check.
	 * @return bool True if the client_id is an HTTPS URL eligible for discovery.
	 */
	private static function is_discoverable_url( $client_id ) {
		if ( ! \filter_var( $client_id, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return 'https' === \strtolower( (string) \wp_parse_url( $client_id, PHP_URL_SCHEME ) );
	}

	/**
	 * Discover client metadata from URL and auto-register.
	 *
	 * Fetches the Client ID Metadata Document (CIMD) from the client_id URL.
	 * Rate-limited via transients to prevent SSRF abuse.
	 *
	 * @param string $client_id The client ID URL.
	 * @return Client|\WP_Error The client or error.
	 */
	private static function discover_and_register( $client_id ) {
		// Rate-limit auto-discovery to prevent SSRF abuse (max 10 per minute per IP).
		$ip = get_client_ip();
		if ( '' === $ip ) {
			return new \WP_Error(
				'activitypub_rate_limited',
				\__( 'Too many client discovery requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);
		}
		$transient_key = 'ap_oauth_disc_' . \md5( $ip );
		$count         = (int) \get_transient( $transient_key );

		if ( $count >= 10 ) {
			return new \WP_Error(
				'activitypub_rate_limited',
				\__( 'Too many client discovery requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);
		}

		\set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

		$metadata = self::fetch_client_metadata( $client_id );

		if ( \is_wp_error( $metadata ) ) {
			return $metadata;
		}

		// Validate client_id is present and matches.
		// A missing client_id allows client impersonation through redirects.
		if ( empty( $metadata['client_id'] ) ) {
			return new \WP_Error(
				'activitypub_missing_client_id',
				\__( 'Client metadata must contain a client_id property.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		if ( $metadata['client_id'] !== $client_id ) {
			return new \WP_Error(
				'activitypub_client_id_mismatch',
				\__( 'Client ID in metadata does not match request.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Get redirect URIs from metadata or derive from client_id origin.
		$redirect_uris = array();
		if ( ! empty( $metadata['redirect_uris'] ) && is_array( $metadata['redirect_uris'] ) ) {
			foreach ( $metadata['redirect_uris'] as $uri ) {
				if ( ! self::validate_uri_format( $uri ) ) {
					return new \WP_Error(
						'activitypub_invalid_redirect_uri',
						/* translators: %s: The invalid redirect URI */
						\sprintf( \__( 'Invalid redirect URI: %s', 'activitypub' ), $uri ),
						array( 'status' => 400 )
					);
				}
			}
			$redirect_uris = $metadata['redirect_uris'];
		}

		// Register the discovered client.
		$name = ! empty( $metadata['client_name'] ) ? $metadata['client_name'] : $client_id;

		$post_id = \wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => '',
				'meta_input'   => array(
					'_activitypub_client_id'          => $client_id,
					'_activitypub_client_secret_hash' => '', // Public client.
					'_activitypub_redirect_uris'      => array_map( array( Sanitize::class, 'redirect_uri' ), $redirect_uris ),
					'_activitypub_allowed_scopes'     => Scope::ALL,
					'_activitypub_is_public'          => true,
					'_activitypub_discovered'         => true,
					'_activitypub_logo_uri'           => ! empty( $metadata['logo_uri'] ) ? \sanitize_url( $metadata['logo_uri'] ) : '',
					'_activitypub_client_uri'         => ! empty( $metadata['client_uri'] ) ? \sanitize_url( $metadata['client_uri'] ) : '',
				),
			),
			true
		);

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return new self( $post_id );
	}

	/**
	 * Fetch client metadata from URL.
	 *
	 * Supports both CIMD JSON format and ActivityPub Application objects.
	 *
	 * @param string $url The client ID URL to fetch.
	 * @return array|\WP_Error Metadata array or error.
	 */
	private static function fetch_client_metadata( $url ) {
		/*
		 * Resolve the host explicitly and reject private/loopback addresses.
		 * wp_safe_remote_get() also performs URL validation but has a same-host
		 * carve-out (it allows requests to the WordPress site's own host even
		 * when that host is loopback/private). The CIMD document is meant to
		 * be a publicly resolvable HTTPS URL, so close that gap up front.
		 */
		$host = \wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host || false === resolve_public_host( $host ) ) {
			return new \WP_Error(
				'activitypub_client_unsafe_host',
				\__( 'The client metadata URL host is not allowed.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$args = array(
			'timeout'     => 10,
			'headers'     => array(
				'Accept' => 'application/cimd+json, application/json, application/ld+json, application/activity+json',
			),
			'redirection' => 0, // CIMDs prohibit following redirects to prevent client impersonation.
		);

		/*
		 * Always use wp_safe_remote_get for the metadata document fetch. RFC 8252's
		 * loopback allowance applies to redirect URIs (Section 7.3), not to the
		 * client metadata document — that's expected to be a publicly resolvable
		 * HTTPS URL.
		 */
		$response = \wp_safe_remote_get( $url, $args );

		if ( \is_wp_error( $response ) ) {
			return new \WP_Error(
				'activitypub_client_fetch_failed',
				\sprintf(
					/* translators: 1: The client metadata URL, 2: The error message from the HTTP request */
					\__( 'Could not reach the application at %1$s: %2$s', 'activitypub' ),
					$url,
					$response->get_error_message()
				),
				array( 'status' => 502 )
			);
		}

		$code = \wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'activitypub_client_fetch_failed',
				\sprintf(
					/* translators: 1: The client metadata URL, 2: HTTP status code */
					\__( 'The application at %1$s returned an unexpected response (HTTP %2$d).', 'activitypub' ),
					$url,
					$code
				),
				array( 'status' => 502 )
			);
		}

		$body = \wp_remote_retrieve_body( $response );
		$data = \json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'activitypub_client_invalid_metadata',
				\__( 'Invalid client metadata format.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Normalize ActivityPub Application format to CIMD format.
		return self::normalize_client_metadata( $data );
	}

	/**
	 * Normalize client metadata from various formats to standard format.
	 *
	 * Supports:
	 * - CIMD (Client ID Metadata Document)
	 * - ActivityPub Application objects
	 *
	 * @param array $data The raw metadata.
	 * @return array Normalized metadata.
	 */
	private static function normalize_client_metadata( $data ) {
		$metadata = array(
			'client_name'   => '',
			'redirect_uris' => array(),
			'logo_uri'      => '',
			'client_uri'    => '',
		);

		// CIMD format fields.
		if ( ! empty( $data['client_id'] ) ) {
			$metadata['client_id'] = $data['client_id'];
		}
		if ( ! empty( $data['client_name'] ) ) {
			$metadata['client_name'] = $data['client_name'];
		}
		if ( ! empty( $data['redirect_uris'] ) ) {
			$metadata['redirect_uris'] = (array) $data['redirect_uris'];
		}
		if ( ! empty( $data['logo_uri'] ) ) {
			$metadata['logo_uri'] = $data['logo_uri'];
		}
		if ( ! empty( $data['client_uri'] ) ) {
			$metadata['client_uri'] = $data['client_uri'];
		}

		/*
		 * ActivityStreams vocabulary fallbacks.
		 *
		 * Client ID Metadata Documents may use ActivityStreams context
		 * (e.g. "id" instead of "client_id", "name" instead of "client_name",
		 * "redirectURI" instead of "redirect_uris"). These are used as
		 * fallbacks when the CIMD-specific fields are not present.
		 */
		if ( empty( $metadata['client_id'] ) && ! empty( $data['id'] ) ) {
			$metadata['client_id'] = $data['id'];
		}
		if ( empty( $metadata['client_name'] ) ) {
			if ( ! empty( $data['name'] ) ) {
				$metadata['client_name'] = $data['name'];
			} elseif ( ! empty( $data['preferredUsername'] ) ) {
				$metadata['client_name'] = $data['preferredUsername'];
			}
		}
		if ( empty( $metadata['redirect_uris'] ) && ! empty( $data['redirectURI'] ) ) {
			$metadata['redirect_uris'] = (array) $data['redirectURI'];
		}
		if ( empty( $metadata['logo_uri'] ) && ! empty( $data['icon'] ) ) {
			if ( is_string( $data['icon'] ) ) {
				$metadata['logo_uri'] = $data['icon'];
			} elseif ( is_array( $data['icon'] ) && ! empty( $data['icon']['url'] ) ) {
				$metadata['logo_uri'] = $data['icon']['url'];
			}
		}
		if ( empty( $metadata['client_uri'] ) && ! empty( $data['url'] ) ) {
			$metadata['client_uri'] = is_array( $data['url'] ) ? $data['url'][0] : $data['url'];
		}

		// Mark ActivityPub actor-typed clients for lenient redirect validation.
		$actor_types = array( 'Application', 'Person', 'Service', 'Group', 'Organization' );
		if ( ! empty( $data['type'] ) && in_array( $data['type'], $actor_types, true ) ) {
			$metadata['is_actor'] = true;
		}

		return $metadata;
	}

	/**
	 * Validate client credentials.
	 *
	 * @param string      $client_id     The client ID.
	 * @param string|null $client_secret The client secret (optional for public clients).
	 * @return bool True if valid.
	 */
	public static function validate( $client_id, $client_secret = null ) {
		$client = self::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			return false;
		}

		// Public clients don't need secret validation.
		if ( $client->is_public() ) {
			return true;
		}

		// Confidential clients require a valid secret.
		if ( empty( $client_secret ) ) {
			return false;
		}

		$stored_hash = \get_post_meta( $client->post_id, '_activitypub_client_secret_hash', true );

		return \wp_check_password( $client_secret, $stored_hash );
	}

	/**
	 * Check if redirect URI is valid for this client.
	 *
	 * Requires an exact match against registered redirect URIs,
	 * with RFC 8252 loopback port flexibility.
	 *
	 * Clients must have at least one registered redirect URI.
	 * Same-origin fallback is intentionally not supported to
	 * prevent open redirector vulnerabilities.
	 *
	 * @param string $redirect_uri The redirect URI to validate.
	 * @return bool True if valid.
	 */
	public function is_valid_redirect_uri( $redirect_uri ) {
		$allowed_uris = $this->get_redirect_uris();

		if ( empty( $allowed_uris ) ) {
			return false;
		}

		// Exact match first.
		if ( in_array( $redirect_uri, $allowed_uris, true ) ) {
			return true;
		}

		/*
		 * RFC 8252 Section 7.3: For loopback redirects, allow any port.
		 * Compare scheme, host, and path - ignore port for 127.0.0.1 and localhost.
		 */
		foreach ( $allowed_uris as $allowed_uri ) {
			if ( self::is_loopback_redirect_match( $allowed_uri, $redirect_uri ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if two URIs match under RFC 8252 loopback rules.
	 *
	 * For loopback addresses, the port is ignored per RFC 8252 Section 7.3.
	 *
	 * @param string $allowed_uri  The registered redirect URI.
	 * @param string $redirect_uri The requested redirect URI.
	 * @return bool True if they match under loopback rules.
	 */
	private static function is_loopback_redirect_match( $allowed_uri, $redirect_uri ) {
		$allowed_parts  = \wp_parse_url( $allowed_uri );
		$redirect_parts = \wp_parse_url( $redirect_uri );

		// Must have same scheme.
		if ( ( $allowed_parts['scheme'] ?? '' ) !== ( $redirect_parts['scheme'] ?? '' ) ) {
			return false;
		}

		$allowed_host  = $allowed_parts['host'] ?? '';
		$redirect_host = $redirect_parts['host'] ?? '';

		// Must have same host.
		if ( $allowed_host !== $redirect_host ) {
			return false;
		}

		// Only apply port flexibility for loopback addresses.
		if ( ! self::is_loopback( $allowed_host ) ) {
			// Not loopback - require exact match including port.
			return $allowed_uri === $redirect_uri;
		}

		// For loopback, compare path (ignore port).
		$allowed_path  = $allowed_parts['path'] ?? '/';
		$redirect_path = $redirect_parts['path'] ?? '/';

		return $allowed_path === $redirect_path;
	}

	/**
	 * Check if a host is a loopback address.
	 *
	 * Supports:
	 * - "localhost" (common in practice for native app development)
	 * - IPv4 loopback range 127.0.0.0/8 (RFC 1122 Section 3.2.1.3)
	 * - IPv6 loopback ::1 (RFC 4291 Section 2.5.3)
	 * - IPv4-mapped IPv6 loopback ::ffff:127.x.x.x (RFC 4291 Section 2.5.5.2)
	 *
	 * @param string $host The host to check (as returned by wp_parse_url).
	 * @return bool True if loopback.
	 */
	private static function is_loopback( $host ) {
		$host = \strtolower( $host );

		// Match "localhost" and any subdomain of localhost (RFC 6761 Section 6.3).
		if ( 'localhost' === $host || '.localhost' === \substr( $host, -\strlen( '.localhost' ) ) ) {
			return true;
		}

		// Strip brackets from IPv6 (parse_url returns "[::1]").
		$ip = \trim( $host, '[]' );

		if ( ! \filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		// IPv4 loopback 127.0.0.0/8 (RFC 1122 Section 3.2.1.3).
		if ( \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return 0 === \strpos( $ip, '127.' );
		}

		/*
		 * IPv6 loopback ::1 (RFC 4291 Section 2.5.3). Normalised via inet_pton
		 * so equivalents like 0:0:0:0:0:0:0:1 and ::0001 also match.
		 */
		$packed = \inet_pton( $ip );
		if ( false !== $packed && "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\1" === $packed ) {
			return true;
		}

		// IPv4-mapped IPv6 loopback ::ffff:127.x.x.x (RFC 4291 Section 2.5.5.2).
		return 0 === \strpos( $ip, '::ffff:127.' );
	}

	/**
	 * Get all manually registered (non-discovered) clients.
	 *
	 * @since 8.1.0
	 *
	 * @return Client[] Array of Client objects.
	 */
	public static function get_manually_registered() {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary to filter out discovered clients.
		$posts = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 100,
				'meta_query'  => array(
					'relation' => 'OR',
					array(
						'key'     => '_activitypub_discovered',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => '_activitypub_discovered',
						'value' => '',
					),
					array(
						'key'   => '_activitypub_discovered',
						'value' => '0',
					),
				),
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		return array_map(
			function ( $post ) {
				return new self( $post->ID );
			},
			$posts
		);
	}

	/**
	 * Get the post ID of the client.
	 *
	 * @since 8.1.0
	 *
	 * @return int The post ID.
	 */
	public function get_post_id() {
		return $this->post_id;
	}

	/**
	 * Get client name.
	 *
	 * @return string The client name.
	 */
	public function get_name() {
		$post = \get_post( $this->post_id );
		return $post ? $post->post_title : '';
	}

	/**
	 * Get client display name, falling back to client ID.
	 *
	 * @since 8.1.0
	 *
	 * @return string The display name.
	 */
	public function get_display_name() {
		return $this->get_name() ?: $this->get_client_id();
	}

	/**
	 * Get client description.
	 *
	 * @return string The client description.
	 */
	public function get_description() {
		$post = \get_post( $this->post_id );
		return $post ? $post->post_content : '';
	}

	/**
	 * Get client ID.
	 *
	 * @return string The client ID.
	 */
	public function get_client_id() {
		return \get_post_meta( $this->post_id, '_activitypub_client_id', true );
	}

	/**
	 * Get allowed redirect URIs.
	 *
	 * @return array The redirect URIs.
	 */
	public function get_redirect_uris() {
		$uris = \get_post_meta( $this->post_id, '_activitypub_redirect_uris', true );
		return is_array( $uris ) ? $uris : array();
	}

	/**
	 * Get allowed scopes for this client.
	 *
	 * @return array The allowed scopes.
	 */
	public function get_allowed_scopes() {
		$scopes = \get_post_meta( $this->post_id, '_activitypub_allowed_scopes', true );
		return is_array( $scopes ) ? $scopes : Scope::DEFAULT_SCOPES;
	}

	/**
	 * Get client logo URI.
	 *
	 * @return string The logo URI or empty string.
	 */
	public function get_logo_uri() {
		return \get_post_meta( $this->post_id, '_activitypub_logo_uri', true ) ?: '';
	}

	/**
	 * Get client URI (homepage).
	 *
	 * @return string The client URI or empty string.
	 */
	public function get_client_uri() {
		return \get_post_meta( $this->post_id, '_activitypub_client_uri', true ) ?: '';
	}

	/**
	 * Get a URL suitable for linking to this client.
	 *
	 * Uses client_uri (the client's homepage) rather than client_id,
	 * since the client_id URL typically serves a JSON document (CIMD)
	 * not intended for end-users.
	 *
	 * @since 8.1.0
	 *
	 * @return string A URL for the client, or empty string if none available.
	 */
	public function get_link_url() {
		$client_uri = $this->get_client_uri();

		if ( $client_uri ) {
			return $client_uri;
		}

		$redirect_uris = $this->get_redirect_uris();

		if ( ! empty( $redirect_uris ) ) {
			$scheme = \wp_parse_url( $redirect_uris[0], PHP_URL_SCHEME );
			$host   = \wp_parse_url( $redirect_uris[0], PHP_URL_HOST );

			if ( $scheme && $host ) {
				return \trailingslashit( sprintf( '%s://%s', $scheme, $host ) );
			}
		}

		return '';
	}

	/**
	 * Check if this client was auto-discovered.
	 *
	 * @return bool True if discovered.
	 */
	public function is_discovered() {
		return (bool) \get_post_meta( $this->post_id, '_activitypub_discovered', true );
	}

	/**
	 * Check if this is a public client.
	 *
	 * @return bool True if public.
	 */
	public function is_public() {
		return (bool) \get_post_meta( $this->post_id, '_activitypub_is_public', true );
	}

	/**
	 * Filter requested scopes to only those allowed for this client.
	 *
	 * @param array $requested_scopes The requested scopes.
	 * @return array Filtered scopes.
	 */
	public function filter_scopes( $requested_scopes ) {
		$allowed = $this->get_allowed_scopes();
		return array_values( array_intersect( $requested_scopes, $allowed ) );
	}

	/**
	 * Generate a unique client ID.
	 *
	 * @return string UUID v4.
	 */
	public static function generate_client_id() {
		// Generate UUID v4.
		$data    = random_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // Version 4.
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // Variant.

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Generate a client secret.
	 *
	 * @return string The client secret.
	 */
	public static function generate_client_secret() {
		return Token::generate_token( 32 );
	}

	/**
	 * Validate a redirect URI format.
	 *
	 * Supports:
	 * - https:// URIs (production)
	 * - http:// URIs (localhost only, for development)
	 * - Custom URI schemes for native apps (RFC 8252 Section 7.1)
	 *
	 * @param string $uri The URI to validate.
	 * @return bool True if valid.
	 */
	private static function validate_uri_format( $uri ) {
		/*
		 * Extract scheme manually first because wp_parse_url() returns false
		 * for some custom scheme URIs (e.g. "myapp:/callback").
		 *
		 * Note: per RFC 2396, custom scheme URIs use a single slash ("myapp:/path"),
		 * but double-slash forms ("myapp://host") are common in practice, so both
		 * are accepted.
		 */
		if ( ! preg_match( '/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $uri, $matches ) ) {
			return false;
		}

		$scheme = \strtolower( $matches[1] );
		$parsed = \wp_parse_url( $uri );

		if ( ! $parsed ) {
			// wp_parse_url fails for "scheme://" — still valid for custom schemes.
			$parsed = array( 'scheme' => $scheme );
		}

		// Block dangerous schemes (see OWASP XSS prevention).
		$blocked_schemes = array( 'javascript', 'data', 'vbscript', 'blob', 'file', 'mhtml', 'cid', 'jar', 'view-source' );
		if ( in_array( $scheme, $blocked_schemes, true ) ) {
			return false;
		}

		/*
		 * Allow http only for loopback addresses (RFC 8252 Section 8.3).
		 * Native apps use loopback redirects during the OAuth flow.
		 *
		 * Non-loopback http URIs are rejected by default but can be
		 * allowed via the activitypub_oauth_allow_http_redirect_uri filter
		 * for local development environments.
		 *
		 * @param bool   $allowed Whether to allow this http redirect URI.
		 * @param string $uri     The redirect URI being validated.
		 * @param array  $parsed  The parsed URI components.
		 */
		if ( 'http' === $scheme ) {
			if ( empty( $parsed['host'] ) ) {
				return false;
			}

			if ( self::is_loopback( $parsed['host'] ) ) {
				return true;
			}

			return (bool) \apply_filters( 'activitypub_oauth_allow_http_redirect_uri', false, $uri, $parsed );
		}

		// Allow https with any host.
		if ( 'https' === $scheme ) {
			return ! empty( $parsed['host'] );
		}

		/*
		 * Allow custom URI schemes for native/mobile apps (RFC 8252 Section 7.1).
		 * Examples: com.example.app:/oauth, myapp:/callback
		 * Custom schemes must be at least 2 characters to avoid matching
		 * Windows drive letters (e.g., "C:").
		 */
		return strlen( $scheme ) >= 2;
	}

	/**
	 * Delete all OAuth clients and their associated tokens.
	 *
	 * Used during plugin uninstall to clean up all OAuth data.
	 *
	 * @return int The number of clients deleted.
	 */
	public static function delete_all() {
		$post_ids = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array( 'any', 'trash', 'auto-draft' ),
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);

		foreach ( $post_ids as $post_id ) {
			\wp_delete_post( $post_id, true );
		}

		// Also revoke all tokens stored in user meta.
		Token::revoke_all();

		return count( $post_ids );
	}

	/**
	 * Delete a client and all its tokens.
	 *
	 * @param string $client_id The client ID to delete.
	 * @return bool True on success.
	 */
	public static function delete( $client_id ) {
		$client = self::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			return false;
		}

		/*
		 * Delete all tokens for this client (tokens are stored in user meta).
		 * Authorization codes are transient-based and auto-expire within 10 minutes,
		 * so they don't need explicit revocation here.
		 */
		Token::revoke_for_client( $client_id );

		// Delete the client.
		return (bool) \wp_delete_post( $client->post_id, true );
	}
}
