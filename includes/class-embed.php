<?php
/**
 * ActivityPub Embed Handler.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_REST_Response;

/**
 * Class to handle embedding ActivityPub content
 */
class Embed {

	/**
	 * Cache expiration time in seconds (24 hours)
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Initialize the embed handler
	 */
	public static function init() {
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'filter_oembed_response' ), 10, 3 );
	}

	/**
	 * Filter the oembed response to handle ActivityPub content
	 *
	 * @param \stdClass|WP_REST_Response $response The response data.
	 * @param array                      $handler  The handler used for the response.
	 * @param \WP_REST_Request           $request  The request object.
	 * @return \stdClass|WP_REST_Response
	 */
	public static function filter_oembed_response( $response, $handler, $request ) {
		// Only process oembed proxy requests.
		if ( '/oembed/1.0/proxy' !== $request->get_route() ) {
			return $response;
		}

		// If we already have a valid response with HTML, return it.
		if ( ! is_wp_error( $response ) ) {
			if ( $response instanceof WP_REST_Response ) {
				$data = $response->get_data();
				if ( ! empty( $data['html'] ) ) {
					return $response;
				}
			}
			if ( $response instanceof \stdClass && ! empty( $response->html ) ) {
				return $response;
			}
		}

		$url = $request->get_param( 'url' );
		if ( ! $url ) {
			return $response;
		}

		// Generate a cache key based on the URL.
		$cache_key = 'activitypub_embed_' . md5( $url );

		// Try to get the cached response.
		$cached_response = get_transient( $cache_key );
		if ( false !== $cached_response ) {
			return rest_ensure_response( $cached_response );
		}

		// Try to get ActivityPub representation.
		$object = Http::get_remote_object( $url );
		if ( is_wp_error( $object ) ) {
			return $response;
		}

		$author_name = $object['attributedTo'] ?? '';
		$avatar_url  = $object['icon']['url'] ?? '';
		$author_url  = $author_name;

		// If we don't have an avatar URL but we have an author URL, try to fetch it.
		if ( ! $avatar_url && $author_url ) {
			$author = Http::get_remote_object( $author_url );
			if ( ! is_wp_error( $author ) ) {
				$avatar_url  = $author['icon']['url'] ?? '';
				$author_name = $author['name'] ?? $author_name;
			}
		}

		$title     = $object['name'] ?? '';
		$content   = $object['content'] ?? '';
		$published = isset( $object['published'] ) ? gmdate( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime( $object['published'] ) ) : '';
		$boosts    = isset( $object['shares']['totalItems'] ) ? (int) $object['shares']['totalItems'] : 0;
		$favorites = isset( $object['likes']['totalItems'] ) ? (int) $object['likes']['totalItems'] : 0;

		$image = '';
		if ( isset( $object['image']['url'] ) ) {
			$image = $object['image']['url'];
		} elseif ( isset( $object['attachment'] ) ) {
			foreach ( $object['attachment'] as $attachment ) {
				if ( isset( $attachment['type'] ) && 'Document' === $attachment['type'] ) {
					$image = $attachment['url'];
					break;
				}
			}
		}

		ob_start();
		load_template(
			ACTIVITYPUB_PLUGIN_DIR . 'templates/reply-embed.php',
			false,
			array(
				'author_name' => $author_name,
				'author_url'  => $author_url,
				'avatar_url'  => $avatar_url,
				'published'   => $published,
				'title'       => $title,
				'content'     => $content,
				'image'       => $image,
				'boosts'      => $boosts,
				'favorites'   => $favorites,
				'url'         => $url,
			)
		);

		// Grab the CSS.
		$css = \file_get_contents( ACTIVITYPUB_PLUGIN_DIR . 'assets/css/activitypub-embed.css' ); // phpcs:ignore
		// A little light whitespace cleanup.
		$css = preg_replace( '/\s+/', ' ', $css );
		// We embed CSS directly because this may be in an iframe.
		printf( '<style>%s</style>', $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$embed_response = (object) array(
			'version'       => '1.0',
			'provider_name' => 'ActivityPub',
			'provider_url'  => 'https://activitypub.rocks/',
			'author_name'   => $author_name,
			'author_url'    => $author_url,
			'title'         => $title,
			'type'          => 'rich',
			'width'         => 600,
			'height'        => null,
			'html'          => ob_get_clean(),
		);

		// Cache the successful response.
		set_transient( $cache_key, $embed_response, self::CACHE_EXPIRATION );

		return rest_ensure_response( $embed_response );
	}
}
