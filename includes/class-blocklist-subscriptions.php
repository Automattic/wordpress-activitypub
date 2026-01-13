<?php
/**
 * Blocklist Subscriptions class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Blocklist Subscriptions class.
 *
 * Manages subscriptions to remote blocklists for automatic updates.
 * Owns all remote blocklist logic: fetching, parsing, and importing.
 */
class Blocklist_Subscriptions {

	/**
	 * Option key for storing subscriptions.
	 */
	const OPTION_KEY = 'activitypub_blocklist_subscriptions';

	/**
	 * IFTAS DNI list URL.
	 */
	const IFTAS_DNI_URL = 'https://about.iftas.org/wp-content/uploads/2025/10/iftas-dni-latest.csv';

	/**
	 * Get all subscriptions.
	 *
	 * @return array Array of URL => timestamp pairs.
	 */
	public static function get_all() {
		return \get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Add a subscription.
	 *
	 * Only adds the URL to the subscription list. Does not sync.
	 * Call sync() separately to fetch and import domains.
	 *
	 * @param string $url The blocklist URL to subscribe to.
	 * @return bool True on success, false on failure.
	 */
	public static function add( $url ) {
		$url = \sanitize_url( $url );

		if ( empty( $url ) || ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$subscriptions = self::get_all();

		// Not already subscribed.
		if ( ! isset( $subscriptions[ $url ] ) ) {
			// Add subscription with timestamp 0 (never synced).
			$subscriptions[ $url ] = 0;
			\update_option( self::OPTION_KEY, $subscriptions );
		}

		return true;
	}

	/**
	 * Remove a subscription.
	 *
	 * @param string $url The blocklist URL to unsubscribe from.
	 * @return bool True on success, false if not found.
	 */
	public static function remove( $url ) {
		$subscriptions = self::get_all();

		if ( ! isset( $subscriptions[ $url ] ) ) {
			return false;
		}

		unset( $subscriptions[ $url ] );
		\update_option( self::OPTION_KEY, $subscriptions );

		return true;
	}

	/**
	 * Sync a single subscription.
	 *
	 * Fetches the blocklist URL, parses domains, and adds new ones to the blocklist.
	 * Updates the subscription timestamp on success.
	 *
	 * @param string $url The blocklist URL to sync.
	 * @return int|false Number of domains added, or false on failure.
	 */
	public static function sync( $url ) {
		$response = \wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 5,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return false;
		}

		$response_code = \wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return false;
		}

		$body = \wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return false;
		}

		$domains = self::parse_csv_string( $body );

		if ( empty( $domains ) ) {
			return false;
		}

		// Get existing blocks and find new ones.
		$existing    = Moderation::get_site_blocks()[ Moderation::TYPE_DOMAIN ] ?? array();
		$new_domains = \array_diff( $domains, $existing );

		if ( ! empty( $new_domains ) ) {
			Moderation::add_site_blocks( Moderation::TYPE_DOMAIN, $new_domains );
		}

		// Update timestamp if this is a subscription.
		$subscriptions = self::get_all();
		if ( isset( $subscriptions[ $url ] ) ) {
			$subscriptions[ $url ] = \time();
			\update_option( self::OPTION_KEY, $subscriptions );
		}

		return \count( $new_domains );
	}

	/**
	 * Sync all subscriptions.
	 *
	 * Called by cron job.
	 */
	public static function sync_all() {
		\array_map( array( __CLASS__, 'sync' ), \array_keys( self::get_all() ) );
	}

	/**
	 * Parse CSV content from a string and extract domain names.
	 *
	 * Supports Mastodon CSV format (with #domain header) and simple
	 * one-domain-per-line format.
	 *
	 * @param string $content CSV content as a string.
	 * @return array Array of unique, valid domain names.
	 */
	public static function parse_csv_string( $content ) {
		$domains = array();

		if ( empty( $content ) ) {
			return $domains;
		}

		// Split into lines.
		$lines = \preg_split( '/\r\n|\r|\n/', $content );
		if ( empty( $lines ) ) {
			return $domains;
		}

		// Parse first line to detect format.
		$first_line = \str_getcsv( $lines[0], ',', '"', '\\' );
		$first_cell = \trim( $first_line[0] ?? '' );
		$has_header = \str_starts_with( $first_cell, '#' ) || 'domain' === \strtolower( $first_cell );

		// Find domain column index.
		$domain_index = 0;
		if ( $has_header ) {
			foreach ( $first_line as $i => $col ) {
				$col = \ltrim( \strtolower( \trim( $col ) ), '#' );
				if ( 'domain' === $col ) {
					$domain_index = $i;
					break;
				}
			}
			// Remove header from lines.
			\array_shift( $lines );
		}

		// Process each line.
		foreach ( $lines as $line ) {
			$row    = \str_getcsv( $line, ',', '"', '\\' );
			$domain = \trim( $row[ $domain_index ] ?? '' );

			// Skip empty lines and comments.
			if ( empty( $domain ) || \str_starts_with( $domain, '#' ) ) {
				continue;
			}

			if ( self::is_valid_domain( $domain ) ) {
				$domains[] = \strtolower( $domain );
			}
		}

		return \array_unique( $domains );
	}

	/**
	 * Validate a domain name.
	 *
	 * @param string $domain The domain to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_domain( $domain ) {
		// Must contain at least one dot (filter_var would accept "localhost").
		if ( ! \str_contains( $domain, '.' ) ) {
			return false;
		}

		return (bool) \filter_var( $domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME );
	}
}
