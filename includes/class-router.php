<?php
/**
 * Router class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;

/**
 * Router class.
 */
class Router {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'init', array( self::class, 'add_rewrite_rules' ), 11 );

		\add_filter( 'template_include', array( self::class, 'render_activitypub_template' ), 99 );
		\add_action( 'template_redirect', array( self::class, 'template_redirect' ) );
		\add_filter( 'redirect_canonical', array( self::class, 'redirect_canonical' ), 10, 2 );
		\add_filter( 'redirect_canonical', array( self::class, 'no_trailing_redirect' ), 10, 2 );
		\add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );
	}

	/**
	 * Add rewrite rules.
	 */
	public static function add_rewrite_rules() {
		/*
		 * If another system needs to take precedence over the ActivityPub rewrite rules,
		 * they can define their own and will manually call the appropriate functions as required.
		 */
		if ( ACTIVITYPUB_DISABLE_REWRITES ) {
			return;
		}

		if ( ! \class_exists( 'Webfinger' ) ) {
			\add_rewrite_rule(
				'^.well-known/webfinger',
				'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger',
				'top'
			);
		}

		if ( ! \class_exists( 'Nodeinfo_Endpoint' ) && true === (bool) \get_option( 'blog_public', 1 ) ) {
			\add_rewrite_rule(
				'^.well-known/nodeinfo',
				'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo',
				'top'
			);
		}

		\add_rewrite_rule( '^@([\w\-\.]+)\/?$', 'index.php?actor=$matches[1]', 'top' );
		\add_rewrite_endpoint( 'activitypub', EP_AUTHORS | EP_PERMALINK | EP_PAGES );
	}

	/**
	 * Return a AS2 JSON version of an author, post or page.
	 *
	 * @param  string $template The path to the template object.
	 *
	 * @return string The new path to the JSON template.
	 */
	public static function render_activitypub_template( $template ) {
		if ( \wp_is_serving_rest_request() || \wp_doing_ajax() ) {
			return $template;
		}

		self::add_headers();

		if ( ! is_activitypub_request() || ! should_negotiate_content() ) {
			if ( \get_query_var( 'p' ) && Outbox::POST_TYPE === \get_post_type( \get_query_var( 'p' ) ) ) {
				\set_query_var( 'is_404', true );
				\status_header( 406 );
			}
			return $template;
		}

		if ( Tombstone::exists_local( Query::get_instance()->get_request_url() ) ) {
			\status_header( 410 );
			return ACTIVITYPUB_PLUGIN_DIR . 'templates/tombstone-json.php';
		}

		$activitypub_template = false;
		$activitypub_object   = Query::get_instance()->get_activitypub_object();

		if ( $activitypub_object ) {
			if ( \get_query_var( 'preview' ) ) {
				\define( 'ACTIVITYPUB_PREVIEW', true );

				/**
				 * Filter the template used for the ActivityPub preview.
				 *
				 * @param string $activitypub_template Absolute path to the template file.
				 */
				$activitypub_template = apply_filters( 'activitypub_preview_template', ACTIVITYPUB_PLUGIN_DIR . '/templates/post-preview.php' );
			} else {
				$activitypub_template = ACTIVITYPUB_PLUGIN_DIR . 'templates/activitypub-json.php';
			}
		}

		/*
		 * Check if the request is authorized.
		 *
		 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Primer/Authentication_Authorization#Authorized_fetch
		 * @see https://swicg.github.io/activitypub-http-signature/#authorized-fetch
		 */
		if ( $activitypub_template && use_authorized_fetch() ) {
			$verification = Signature::verify_http_signature( $_SERVER );
			if ( \is_wp_error( $verification ) ) {
				\status_header( 401 );

				// Fallback as template_loader can't return http headers.
				return $template;
			}
		}

		if ( $activitypub_template ) {
			\set_query_var( 'is_404', false );

			// Check if header already sent.
			if ( ! \headers_sent() ) {
				// Send 200 status header.
				\status_header( 200 );
			}

			return $activitypub_template;
		}

		return $template;
	}

	/**
	 * Add the 'self' link to the header.
	 */
	public static function add_headers() {
		$id = Query::get_instance()->get_activitypub_object_id();

		if ( ! $id ) {
			return;
		}

		if ( ! headers_sent() ) {
			\header( 'Link: <' . esc_url( $id ) . '>; title="ActivityPub (JSON)"; rel="alternate"; type="application/activity+json"', false );

			if ( \get_option( 'activitypub_vary_header', '1' ) ) {
				// Send Vary header for Accept header.
				\header( 'Vary: Accept', false );
			}
		}

		add_action(
			'wp_head',
			function () use ( $id ) {
				echo PHP_EOL . '<link rel="alternate" title="ActivityPub (JSON)" type="application/activity+json" href="' . esc_url( $id ) . '" />' . PHP_EOL;
			}
		);
	}

	/**
	 * Remove trailing slash from ActivityPub @username requests.
	 *
	 * @param string $redirect_url  The URL to redirect to.
	 * @param string $requested_url The requested URL.
	 *
	 * @return string $redirect_url The possibly-unslashed redirect URL.
	 */
	public static function no_trailing_redirect( $redirect_url, $requested_url ) {
		if ( get_query_var( 'actor' ) ) {
			return $requested_url;
		}

		return $redirect_url;
	}

	/**
	 * Add support for `p` and `author` query vars.
	 *
	 * @param string $redirect_url  The URL to redirect to.
	 * @param string $requested_url The requested URL.
	 *
	 * @return string $redirect_url
	 */
	public static function redirect_canonical( $redirect_url, $requested_url ) {
		if ( ! is_activitypub_request() ) {
			return $redirect_url;
		}

		$query = \wp_parse_url( $requested_url, PHP_URL_QUERY );

		if ( ! $query ) {
			return $redirect_url;
		}

		$query_params = \wp_parse_args( $query );
		unset( $query_params['activitypub'] );
		unset( $query_params['stamp'] );

		if ( 1 !== count( $query_params ) ) {
			return $redirect_url;
		}

		if ( isset( $query_params['p'] ) ) {
			return null;
		}

		if ( isset( $query_params['author'] ) ) {
			return null;
		}

		return $requested_url;
	}

	/**
	 * Custom redirects for ActivityPub requests.
	 *
	 * @return void
	 */
	public static function template_redirect() {
		global $wp_query;

		$comment_id = \get_query_var( 'c', null );

		// Check if it seems to be a comment.
		if ( $comment_id ) {
			$comment = \get_comment( $comment_id );

			// Load a 404-page if `c` is set but not valid.
			if ( ! $comment ) {
				$wp_query->set_404();
				return;
			}

			// Stop if it's not an ActivityPub comment.
			if ( is_activitypub_request() && ! is_local_comment( $comment ) ) {
				return;
			}

			\wp_safe_redirect( get_comment_link( $comment ) );
			exit;
		}

		$actor = \get_query_var( 'actor', null );
		if ( $actor ) {
			$actor = Actors::get_by_username( $actor );
			if ( ! $actor || \is_wp_error( $actor ) ) {
				$wp_query->set_404();
				return;
			}

			if ( is_activitypub_request() ) {
				return;
			}

			\wp_safe_redirect( $actor->get_url(), 301 );
			exit;
		}
	}

	/**
	 * Add the 'activitypub' query variable so WordPress won't mangle it.
	 *
	 * @param array $vars The query variables.
	 *
	 * @return array The query variables.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'activitypub';
		$vars[] = 'preview';
		$vars[] = 'author';
		$vars[] = 'actor';
		$vars[] = 'stamp';
		$vars[] = 'type';
		$vars[] = 'c';
		$vars[] = 'p';

		return $vars;
	}
}
