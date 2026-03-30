<?php
/**
 * Debug Class for local development.
 *
 * @package Activitypub
 */

namespace Activitypub\Development;

use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Posts;

/**
 * Debug Class.
 *
 * Exposes internal post types, taxonomies, and metadata
 * in the WordPress admin for easier debugging during
 * local development, and provides logging utilities.
 *
 * @since unreleased
 */
class Debug {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'register_post_type_args', array( self::class, 'debug_post_type' ), 10, 2 );
		\add_filter( 'register_taxonomy_args', array( self::class, 'debug_taxonomy' ), 10, 2 );
		\add_filter( 'manage_posts_columns', array( self::class, 'debug_outbox_post_type_column' ), 10, 2 );
		\add_action( 'manage_posts_custom_column', array( self::class, 'manage_posts_custom_column' ), 10, 2 );

		if ( \defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			\add_action( 'activitypub_safe_remote_post_response', array( self::class, 'log_remote_post_responses' ), 10, 2 );
			\add_action( 'activitypub_inbox', array( self::class, 'log_inbox' ), 10, 3 );
			\add_action( 'activitypub_rest_inbox_disallowed', array( self::class, 'log_inbox' ), 10, 3 );
			\add_action( 'activitypub_add_to_outbox_failed', array( self::class, 'log_outbox_error' ), 10, 4 );
			\add_action( 'activitypub_sent_to_inbox', array( self::class, 'log_sent_to_inbox' ), 10, 2 );
		}
	}

	/**
	 * Make internal post types visible in the admin UI.
	 *
	 * @param array  $args      The arguments for the post type.
	 * @param string $post_type The post type.
	 *
	 * @return array The arguments for the post type.
	 */
	public static function debug_post_type( $args, $post_type ) {
		if ( ! \in_array( $post_type, array( Outbox::POST_TYPE, Inbox::POST_TYPE, Remote_Posts::POST_TYPE ), true ) ) {
			return $args;
		}

		$args['show_ui'] = true;

		if ( Outbox::POST_TYPE === $post_type ) {
			$args['menu_icon'] = 'dashicons-upload';
		} elseif ( Inbox::POST_TYPE === $post_type ) {
			$args['menu_icon'] = 'dashicons-download';
		} elseif ( Remote_Posts::POST_TYPE === $post_type ) {
			$args['menu_icon'] = 'dashicons-media-document';
		}

		return $args;
	}

	/**
	 * Make internal taxonomies visible in the admin UI.
	 *
	 * @param array  $args     The arguments for the taxonomy.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return array The arguments for the taxonomy.
	 */
	public static function debug_taxonomy( $args, $taxonomy ) {
		if ( ! \in_array( $taxonomy, array( 'ap_object_type', 'ap_tag' ), true ) ) {
			return $args;
		}

		$args['show_ui']      = true;
		$args['show_in_menu'] = true;

		return $args;
	}

	/**
	 * Add a meta column to the inbox/outbox post list.
	 *
	 * @param array  $columns   The columns.
	 * @param string $post_type The post type.
	 *
	 * @return array The updated columns.
	 */
	public static function debug_outbox_post_type_column( $columns, $post_type ) {
		if ( ! \in_array( $post_type, array( Outbox::POST_TYPE, Inbox::POST_TYPE ), true ) ) {
			return $columns;
		}

		$columns['ap_meta'] = 'Meta';

		return $columns;
	}

	/**
	 * Render the meta column content.
	 *
	 * @param string $column_name The column name.
	 * @param int    $post_id     The post ID.
	 */
	public static function manage_posts_custom_column( $column_name, $post_id ) {
		if ( 'ap_meta' === $column_name ) {
			$meta = \get_post_meta( $post_id );
			foreach ( $meta as $key => $value ) {
				echo \esc_attr( $key ) . ': ' . \esc_html( $value[0] ) . '<br>';
			}
		}
	}

	/**
	 * Log the responses of remote post requests.
	 *
	 * @param array  $response The response from the remote server.
	 * @param string $url      The URL of the remote server.
	 */
	public static function log_remote_post_responses( $response, $url ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		\error_log( "[OUTBOX] Request to: {$url} with Response: " . \print_r( $response, true ) );
	}

	/**
	 * Log the inbox requests.
	 *
	 * @param array  $data    The Activity array.
	 * @param int    $user_id The ID of the local blog user.
	 * @param string $type    The type of the request.
	 */
	public static function log_inbox( $data, $user_id, $type ) {
		$type = \strtolower( $type );

		if ( 'delete' !== $type ) {
			$actor = $data['actor'] ?? '';
			$url   = \Activitypub\object_to_uri( $actor );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			\error_log( "[INBOX] Request from: {$url} with Activity: " . \print_r( $data, true ) );
		}
	}

	/**
	 * Log failed outbox requests.
	 *
	 * @param false|\WP_Error $error   The error object or false.
	 * @param array           $data    The Activity array.
	 * @param string          $type    The type of the request.
	 * @param int             $user_id The ID of the local blog user.
	 */
	public static function log_outbox_error( $error, $data, $type, $user_id ) {
		$error_message = \is_wp_error( $error ) ? $error->get_error_message() : 'Unknown';

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		\error_log( "[OUTBOX] Failed to add {$type}-Activity from: {$user_id} (Error: {$error_message}) with Activity: " . \print_r( $data, true ) );
	}

	/**
	 * Log Follower notifications.
	 *
	 * @param array  $result The result of the remote post request.
	 * @param string $inbox  The inbox URL.
	 */
	public static function log_sent_to_inbox( $result, $inbox ) {
		if ( \is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			\error_log( "[DISPATCHER] Failed Request to: {$inbox} with Result: " . \print_r( $result, true ) );
		}
	}

	/**
	 * Write a log entry.
	 *
	 * @param mixed $log The log entry.
	 */
	public static function write_log( $log ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		\error_log( \print_r( $log, true ) );
	}
}
