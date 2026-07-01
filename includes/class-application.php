<?php
/**
 * Application class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * ActivityPub Application Class.
 *
 * The Application is not a real actor in the plugin's internal sense —
 * it cannot be followed, addressed, or interacted with. It exists only as:
 * 1. A JSON-LD document at /wp-json/activitypub/1.0/application
 * 2. A signing identity for outbound HTTP GET requests
 *
 * This class provides static utility methods for the Application actor,
 * primarily key management for HTTP Signatures.
 *
 * @since unreleased
 */
class Application {
	/**
	 * The option key for the Application key pair.
	 *
	 * @var string
	 */
	const KEYPAIR_OPTION_KEY = 'activitypub_application_keypair';

	/**
	 * The preferred username for the Application actor.
	 *
	 * @var string
	 */
	const USERNAME = 'application';

	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @since unreleased
	 */
	public static function init() {
		/*
		 * Priority 2: must run after Integration\Webfinger::add_pseudo_user_discovery (priority 1),
		 * which returns WP_Error for 'application' since it is not in the Actors collection.
		 */
		\add_filter( 'webfinger_data', array( self::class, 'add_webfinger_discovery' ), 2, 2 );
	}

	/**
	 * WebFinger discovery filter callback.
	 *
	 * @since unreleased
	 *
	 * @param array  $jrd The jrd array.
	 * @param string $uri The WebFinger resource.
	 *
	 * @return array The jrd array or Application WebFinger data.
	 */
	public static function add_webfinger_discovery( $jrd, $uri ) {
		$data = self::get_webfinger_data( $uri );

		if ( $data ) {
			return $data;
		}

		return $jrd;
	}

	/**
	 * Returns the Application actor ID (URL).
	 *
	 * @since unreleased
	 *
	 * @return string The Application ID.
	 */
	public static function get_id() {
		return get_rest_url_by_path( 'application' );
	}

	/**
	 * Returns the pretty URL for the Application actor.
	 *
	 * @since unreleased
	 *
	 * @return string The Application URL.
	 */
	public static function get_url() {
		return self::get_id();
	}

	/**
	 * Returns the WebFinger identifier for the Application.
	 *
	 * @since unreleased
	 *
	 * @return string The WebFinger identifier (e.g. application@example.com).
	 */
	public static function get_webfinger() {
		return self::USERNAME . '@' . home_host();
	}

	/**
	 * Returns the key ID for HTTP signatures.
	 *
	 * @since unreleased
	 *
	 * @return string The key ID.
	 */
	public static function get_key_id() {
		return self::get_id() . '#main-key';
	}

	/**
	 * Returns the public key PEM for the Application.
	 *
	 * @since unreleased
	 *
	 * @return string|null The public key PEM.
	 */
	public static function get_public_key() {
		$key_pair = self::get_keypair();
		return $key_pair['public_key'];
	}

	/**
	 * Returns the private key for the Application.
	 *
	 * @since unreleased
	 *
	 * @return string|null The private key.
	 */
	public static function get_private_key() {
		$key_pair = self::get_keypair();
		return $key_pair['private_key'];
	}

	/**
	 * Returns the key pair for the Application.
	 *
	 * @since unreleased
	 *
	 * @return array The key pair with 'public_key' and 'private_key'.
	 */
	public static function get_keypair() {
		$key_pair = \get_option( self::KEYPAIR_OPTION_KEY );

		if ( ! $key_pair ) {
			$key_pair = self::check_legacy_key_pair();

			if ( $key_pair ) {
				\add_option( self::KEYPAIR_OPTION_KEY, $key_pair );
				return $key_pair;
			}

			$key_pair = self::generate_key_pair();
		}

		return $key_pair;
	}

	/**
	 * Checks for legacy key pair options.
	 *
	 * @since unreleased
	 *
	 * @return array|false The key pair or false.
	 */
	private static function check_legacy_key_pair() {
		/*
		 * Generic actor key pair option (array form) used for the former application
		 * user (ID -1). Checked here so the key survives even if get_keypair() runs
		 * before migrate_application_keypair_option() has had a chance to rename it.
		 */
		$key_pair = \get_option( 'activitypub_keypair_for_-1' );

		if ( \is_array( $key_pair ) && ! empty( $key_pair['public_key'] ) && ! empty( $key_pair['private_key'] ) ) {
			return array(
				'private_key' => $key_pair['private_key'],
				'public_key'  => $key_pair['public_key'],
			);
		}

		// Even older separate key options.
		$public_key  = \get_option( 'activitypub_application_user_public_key' );
		$private_key = \get_option( 'activitypub_application_user_private_key' );

		if ( ! empty( $public_key ) && \is_string( $public_key ) && ! empty( $private_key ) && \is_string( $private_key ) ) {
			return array(
				'private_key' => $private_key,
				'public_key'  => $public_key,
			);
		}

		return false;
	}

	/**
	 * Generates a new key pair for the Application.
	 *
	 * @since unreleased
	 *
	 * @return array The key pair with 'public_key' and 'private_key'.
	 */
	private static function generate_key_pair() {
		$config = array(
			'digest_alg'       => 'sha512',
			'private_key_bits' => 2048,
			'private_key_type' => \OPENSSL_KEYTYPE_RSA,
		);

		$key         = \openssl_pkey_new( $config );
		$private_key = null;
		$detail      = array();
		if ( $key ) {
			\openssl_pkey_export( $key, $private_key );
			$detail = \openssl_pkey_get_details( $key );
		}

		// Check if keys are valid.
		if (
			empty( $private_key ) || ! \is_string( $private_key ) ||
			! isset( $detail['key'] ) || ! \is_string( $detail['key'] )
		) {
			return array(
				'private_key' => null,
				'public_key'  => null,
			);
		}

		$key_pair = array(
			'private_key' => $private_key,
			'public_key'  => $detail['key'],
		);

		\update_option( self::KEYPAIR_OPTION_KEY, $key_pair );

		return $key_pair;
	}

	/**
	 * Check if the URI matches the Application actor and return WebFinger data.
	 *
	 * @since unreleased
	 *
	 * Handles the following URI formats:
	 * - acct:application@example.com / application@example.com
	 * - http(s)://example.com/@application
	 * - http(s)://example.com/wp-json/activitypub/1.0/application
	 *
	 * @param string $uri The WebFinger resource URI.
	 *
	 * @return array|false The WebFinger profile data or false if not the Application.
	 */
	public static function get_webfinger_data( $uri ) {
		if ( ! self::is_application_resource( $uri ) ) {
			return false;
		}

		$application_id = self::get_id();

		return array(
			'subject' => sprintf( 'acct:%s', self::get_webfinger() ),
			'aliases' => array( $application_id ),
			'links'   => array(
				array(
					'rel'        => 'self',
					'type'       => 'application/activity+json',
					'href'       => $application_id,
					'properties' => array(
						'https://www.w3.org/ns/activitystreams#type' => 'Application',
					),
				),
			),
		);
	}

	/**
	 * Check if a URI refers to the Application actor.
	 *
	 * @since unreleased
	 *
	 * @param string $uri The URI to check.
	 *
	 * @return bool True if the URI refers to the Application.
	 */
	public static function is_application_resource( $uri ) {
		$identifier_and_host = Webfinger::get_identifier_and_host( $uri );

		if ( \is_wp_error( $identifier_and_host ) ) {
			return false;
		}

		list( $identifier, $host ) = $identifier_and_host;

		// The resource must point at this site.
		if ( normalize_host( $host ) !== normalize_host( home_host() ) ) {
			return false;
		}

		// URL forms: the REST actor ID or the pretty /@application profile path.
		if ( false !== \strpos( $identifier, '://' ) ) {
			$identifier = normalize_url( $identifier );

			return normalize_url( self::get_id() ) === $identifier
				|| normalize_url( \home_url( '/@' . self::USERNAME ) ) === $identifier;
		}

		// acct form: application@host.
		$identifier = \str_replace( 'acct:', '', $identifier );
		$username   = \substr( $identifier, 0, \strrpos( $identifier, '@' ) );

		return self::USERNAME === $username;
	}
}
