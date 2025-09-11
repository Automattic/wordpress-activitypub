<?php
/**
 * ActivityPub Embed Handler.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Class to handle embedding ActivityPub content.
 */
class Embed {

	/**
	 * Initialize the embed handler.
	 */
	public static function init() {
		\add_filter( 'pre_oembed_result', array( self::class, 'maybe_use_activitypub_embed' ), 10, 3 );
		\add_filter( 'oembed_dataparse', array( self::class, 'handle_filtered_oembed_result' ), 11, 3 );
		\add_filter( 'oembed_request_post_id', array( self::class, 'register_fallback_hook' ) );
	}

	/**
	 * Get an ActivityPub embed HTML for a URL.
	 *
	 * @param string  $url        The URL to get the embed for.
	 * @param boolean $inline_css Whether to inline CSS. Default true.
	 *
	 * @return string|false The embed HTML or false if not found.
	 */
	public static function get_html( $url, $inline_css = true ) {
		// Try to get ActivityPub representation.
		$object = Http::get_remote_object( $url );

		if ( \is_wp_error( $object ) || ! is_activity_object( $object ) ) {
			return false;
		}

		return self::get_html_for_object( $object, $inline_css );
	}

	/**
	 * Get an ActivityPub embed HTML for an ActivityPub object.
	 *
	 * @param array   $activity_object The ActivityPub object to build the embed for.
	 * @param boolean $inline_css      Whether to inline CSS. Default true.
	 *
	 * @return string The embed HTML.
	 */
	public static function get_html_for_object( $activity_object, $inline_css = true ) {
		$author_name = $activity_object['attributedTo'] ?? '';
		$avatar_url  = $activity_object['icon']['url'] ?? '';
		$author_url  = $author_name;

		// If we don't have an avatar URL, but we have an author URL, try to fetch it.
		if ( ! $avatar_url && $author_url ) {
			$author = Http::get_remote_object( $author_url );
			if ( ! is_wp_error( $author ) ) {
				$avatar_url  = $author['icon']['url'] ?? '';
				$author_name = $author['name'] ?? $author_name;
			}
		}

		// Create Webfinger where not found.
		if ( empty( $author['webfinger'] ) ) {
			if ( ! empty( $author['preferredUsername'] ) && ! empty( $author['url'] ) ) {
				// Construct webfinger-style identifier from username and domain.
				$domain              = \wp_parse_url( object_to_uri( $author['url'] ), PHP_URL_HOST );
				$author['webfinger'] = '@' . $author['preferredUsername'] . '@' . $domain;
			} else {
				// Fallback to URL.
				$author['webfinger'] = $author_url;
			}
		}

		$title     = $activity_object['name'] ?? '';
		$content   = $activity_object['content'] ?? '';
		$published = isset( $activity_object['published'] ) ? gmdate( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime( $activity_object['published'] ) ) : '';
		$boosts    = isset( $activity_object['shares']['totalItems'] ) ? (int) $activity_object['shares']['totalItems'] : null;
		$favorites = isset( $activity_object['likes']['totalItems'] ) ? (int) $activity_object['likes']['totalItems'] : null;

		$audio  = null;
		$images = array();
		$video  = null;
		if ( isset( $activity_object['image']['url'] ) ) {
			$images = array(
				array(
					'type' => 'Image',
					'url'  => $activity_object['image']['url'],
					'name' => $activity_object['image']['name'] ?? '',
				),
			);
		} elseif ( isset( $activity_object['attachment'] ) ) {
			foreach ( $activity_object['attachment'] as $attachment ) {
				$type = isset( $attachment['mediaType'] ) ? strtok( $attachment['mediaType'], '/' ) : strtolower( $attachment['type'] );

				switch ( $type ) {
					case 'image':
						$images[] = $attachment;
						break;
					case 'video':
						$video = $attachment;
						break 2;
					case 'audio':
						$audio = $attachment;
						break 2;
				}
			}
			$images = \array_slice( $images, 0, 4 );
		}

		ob_start();
		load_template(
			ACTIVITYPUB_PLUGIN_DIR . 'templates/embed.php',
			false,
			array(
				'audio'       => $audio,
				'author_name' => $author_name,
				'author_url'  => $author_url,
				'avatar_url'  => $avatar_url,
				'boosts'      => $boosts,
				'content'     => $content,
				'favorites'   => $favorites,
				'images'      => $images,
				'published'   => $published,
				'title'       => $title,
				'url'         => $activity_object['id'],
				'video'       => $video,
				'webfinger'   => $author['webfinger'],
			)
		);

		if ( $inline_css ) {
			// Grab the CSS.
			$css = \file_get_contents( ACTIVITYPUB_PLUGIN_DIR . 'assets/css/activitypub-embed.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			// We embed CSS directly because this may be in an iframe.
			printf( '<style>%s</style>', $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// A little light whitespace cleanup.
		return preg_replace( '/\s+/', ' ', ob_get_clean() );
	}

	/**
	 * Check if a real oEmbed result exists for the given URL.
	 *
	 * @param string $url The URL to check.
	 * @param array  $args Additional arguments passed to wp_oembed_get().
	 * @return bool True if a real oEmbed result exists, false otherwise.
	 */
	public static function has_real_oembed( $url, $args = array() ) {
		// Temporarily remove our filter to avoid infinite loops.
		\remove_filter( 'pre_oembed_result', array( self::class, 'maybe_use_activitypub_embed' ) );

		// Try to get a "real" oEmbed result. If found, it'll be cached to avoid unnecessary HTTP requests in `wp_oembed_get`.
		$oembed_result = \wp_oembed_get( $url, $args );

		// Add our filter back.
		\add_filter( 'pre_oembed_result', array( self::class, 'maybe_use_activitypub_embed' ), 10, 3 );

		return false !== $oembed_result;
	}

	/**
	 * Filter the oembed result to handle ActivityPub content when no oEmbed is found.
	 * Implementation is a bit weird because there's no way to filter on a false result, we have to use `pre_oembed_result`.
	 *
	 * @param null|string $result The UNSANITIZED (and potentially unsafe) HTML that should be used to embed.
	 * @param string      $url    The URL to the content that should be attempted to be embedded.
	 * @param array       $args   Additional arguments passed to wp_oembed_get().
	 * @return null|string         Return null to allow normal oEmbed processing, or string for ActivityPub embed.
	 */
	public static function maybe_use_activitypub_embed( $result, $url, $args ) {
		// If we already have a result, return it.
		if ( null !== $result ) {
			return $result;
		}

		// If we found a real oEmbed, return null to allow normal processing.
		if ( self::has_real_oembed( $url, $args ) ) {
			return null;
		}

		// No oEmbed found, try to get ActivityPub representation.
		$html = get_embed_html( $url );

		// If we couldn't get an ActivityPub embed either, return null to allow normal processing.
		if ( ! $html ) {
			return null;
		}

		// Return the ActivityPub embed HTML.
		return $html;
	}

	/**
	 * Handle cases where WordPress has filtered out the oEmbed result for security reasons,
	 * but we can provide a safe ActivityPub-specific markup.
	 *
	 * This runs after wp_filter_oembed_result has potentially nullified the result.
	 *
	 * @param string|false $html The returned oEmbed HTML.
	 * @param object       $data A data object result from an oEmbed provider.
	 * @param string       $url  The URL of the content to be embedded.
	 * @return string|false      The filtered oEmbed HTML or our ActivityPub embed.
	 */
	public static function handle_filtered_oembed_result( $html, $data, $url ) {
		// If we already have valid HTML, return it.
		if ( $html ) {
			return $html;
		}

		// If this isn't a rich or video type, we can't help.
		if ( ! isset( $data->type ) || ! \in_array( $data->type, array( 'rich', 'video' ), true ) ) {
			return $html;
		}

		// If there's no HTML in the data, we can't help.
		if ( empty( $data->html ) || ! \is_string( $data->html ) ) {
			return $html;
		}

		// Try to get ActivityPub representation.
		$activitypub_html = self::get_html( $url );
		if ( ! $activitypub_html ) {
			return $html;
		}

		// Return our safer ActivityPub embed HTML.
		return $activitypub_html;
	}

	/**
	 * Register the fallback hook for oEmbed requests.
	 *
	 * Avoids filtering every single API request.
	 *
	 * @param int $post_id The post ID.
	 * @return int The post ID.
	 */
	public static function register_fallback_hook( $post_id ) {
		\add_filter( 'rest_request_after_callbacks', array( self::class, 'oembed_fediverse_fallback' ), 10, 3 );

		return $post_id;
	}

	/**
	 * Fallback for oEmbed requests to the Fediverse.
	 *
	 * @param \WP_REST_Response|\WP_Error $response Result to send to the client.
	 * @param array                       $handler  Route handler used for the request.
	 * @param \WP_REST_Request            $request  Request used to generate the response.
	 *
	 * @return \WP_REST_Response|\WP_Error The response to send to the client.
	 */
	public static function oembed_fediverse_fallback( $response, $handler, $request ) {
		if ( '/oembed/1.0/proxy' !== $request->get_route() ) {
			return $response;
		}

		if ( ( is_wp_error( $response ) && 'oembed_invalid_url' === $response->get_error_code() ) || empty( $response->html ) ) {
			$url  = $request->get_param( 'url' );
			$html = self::get_html( $url );

			if ( $html ) {
				$args = $request->get_params();
				$data = (object) array(
					'provider_name' => 'ActivityPub oEmbed',
					'html'          => $html,
					'scripts'       => array(),
				);

				/** This filter is documented in wp-includes/class-wp-oembed.php */
				$data->html = apply_filters( 'oembed_result', $data->html, $url, $args );

				/** This filter is documented in wp-includes/class-wp-oembed-controller.php */
				$ttl = apply_filters( 'rest_oembed_ttl', DAY_IN_SECONDS, $url, $args );

				set_transient( 'oembed_' . md5( serialize( $args ) ), $data, $ttl ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

				$response = new \WP_REST_Response( $data );
			}
		} elseif ( ! empty( $request->get_param( 'activitypub' ) ) ) {
			/*
			 * If the 'activitypub' parameter is present, perform an additional validation step:
			 * Ensure the provided URL resolves to a valid ActivityPub object.
			 *
			 * This differs from the standard oEmbed flow, which does not explicitly validate
			 * the URL as an ActivityPub object unless the initial oEmbed lookup fails.
			 * This block is triggered for requests from the Federated Reply block, where we
			 * want to inform users whether post authors will be notified of the reply.
			 */
			$object = Http::get_remote_object( $request->get_param( 'url' ) );

			if ( \is_wp_error( $object ) || ! is_activity_object( $object ) ) {
				$response = new \WP_Error( 'oembed_invalid_url', \get_status_header_desc( 404 ), array( 'status' => 404 ) );
			}
		}

		return $response;
	}
}
