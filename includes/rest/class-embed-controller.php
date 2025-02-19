<?php
/**
 * ActivityPub Embed Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Activitypub\Http;

/**
 * Controller class for handling embed requests
 */
class Embed_Controller extends WP_REST_Controller {

	/**
	 * Cache expiration time in seconds (24 hours)
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = ACTIVITYPUB_REST_NAMESPACE;
		$this->rest_base = 'embed';
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'url' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
					),
				),
			)
		);
	}

	/**
	 * Get an embed for a URL.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$url = $request->get_param( 'url' );

		// Generate a cache key based on the URL.
		$cache_key = 'activitypub_embed_' . md5( $url );

		// Try to get the cached response.
		$cached_response = \get_transient( $cache_key );
		if ( false !== $cached_response ) {
			if ( isset( $cached_response['is_error'] ) && $cached_response['is_error'] ) {
				return new WP_Error(
					$cached_response['code'],
					$cached_response['message'],
					$cached_response['data']
				);
			}
			return \rest_ensure_response( $cached_response );
		}

		// First try WordPress's built-in oEmbed.
		$embed = \wp_oembed_get( $url );
		if ( $embed ) {
			$response = array(
				'html'     => $embed,
				'provider' => 'oembed',
			);
			\set_transient( $cache_key, $response, self::CACHE_EXPIRATION );
			return \rest_ensure_response( $response );
		}

		// Then try ActivityPub.
		$embed = Http::get_remote_object( $url );
		if ( \is_wp_error( $embed ) ) {
			// Cache the error response.
			\set_transient(
				$cache_key,
				array(
					'is_error' => true,
					'code'     => $embed->get_error_code(),
					'message'  => $embed->get_error_message(),
					'data'     => $embed->get_error_data(),
				),
				self::CACHE_EXPIRATION
			);
			return $embed;
		}

		$author_name = $embed['attributedTo'] ?? '';
		$avatar_url  = $embed['icon']['url'] ?? '';
		$author_url  = $author_name;

		// If we don't have an avatar URL but we have an author URL, try to fetch it.
		if ( ! $avatar_url && $author_url ) {
			$author = Http::get_remote_object( $author_url );

			if ( ! \is_wp_error( $author ) ) {
				if ( isset( $author['icon']['url'] ) ) {
					$avatar_url = $author['icon']['url'];
				}
				if ( isset( $author['name'] ) ) {
					$author_name = $author['name'];
				}
			}
		}

		$title     = $embed['name'] ?? '';
		$content   = $embed['content'] ?? '';
		$published = isset( $embed['published'] ) ? \gmdate( \get_option( 'date_format' ) . ', ' . \get_option( 'time_format' ), \strtotime( $embed['published'] ) ) : '';
		$boosts    = isset( $embed['shares']['totalItems'] ) ? (int) $embed['shares']['totalItems'] : 0;
		$favorites = isset( $embed['likes']['totalItems'] ) ? (int) $embed['likes']['totalItems'] : 0;

		$image = '';
		if ( isset( $embed['image']['url'] ) ) {
			$image = $embed['image']['url'];
		} elseif ( isset( $embed['attachment'] ) ) {
			foreach ( $embed['attachment'] as $attachment ) {
				if ( isset( $attachment['type'] ) && 'Document' === $attachment['type'] ) {
					$image = $attachment['url'];
					break;
				}
			}
		}

		\ob_start();
		\load_template(
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

		$response = array(
			'html'     => \ob_get_clean(),
			'provider' => 'activitypub',
		);

		// Cache the successful response.
		\set_transient( $cache_key, $response, self::CACHE_EXPIRATION );

		return \rest_ensure_response( $response );
	}
}
