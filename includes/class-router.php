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

		\add_action( 'send_headers', array( self::class, 'add_headers' ) );
		\add_filter( 'template_include', array( self::class, 'render_activitypub_template' ), 99 );
		\add_action( 'template_redirect', array( self::class, 'template_redirect' ) );
		\add_filter( 'redirect_canonical', array( self::class, 'redirect_canonical' ), 10, 2 );
		\add_filter( 'redirect_canonical', array( self::class, 'no_trailing_redirect' ), 10, 2 );
		\add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );

		\add_action( 'parse_query', array( self::class, 'fix_is_home_check' ) );
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

		\add_rewrite_rule(
			'^authorize_interaction/?$',
			'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions',
			'top'
		);

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

		// Authorization Server Metadata (RFC 8414).
		\add_rewrite_rule(
			'^.well-known/oauth-authorization-server',
			'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorization-server-metadata',
			'top'
		);

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

		if ( ! is_activitypub_request() || ! should_negotiate_content() ) {
			$is_outbox_item = \get_query_var( 'p' ) && Outbox::POST_TYPE === \get_post_type( \get_query_var( 'p' ) );
			$is_preflight   = isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'];

			if ( $is_outbox_item && $is_preflight ) {
				/*
				 * CORS preflight: override WordPress 404 so the browser
				 * accepts the preflight response (must be 2xx).
				 */
				\status_header( 200 );
			} elseif ( $is_outbox_item ) {
				// Return 406 for non-ActivityPub requests to outbox items since they only support ActivityPub requests.
				\set_query_var( 'is_404', true );
				\status_header( 406 );
			}

			return $template;
		}

		$activitypub_object = Query::get_instance()->get_activitypub_object();
		$queried_object     = Query::get_instance()->get_queried_object();

		/*
		 * Serve the Tombstone for a deleted object — but not while an authorized
		 * user is previewing it. During an editor `?preview=true` request,
		 * `is_post_publicly_queryable()` treats a draft or pending post as
		 * queryable only for a user who can edit it, so the author can still use
		 * the Fediverse Preview on a post they just soft-deleted (which is
		 * otherwise already in the tombstone registry).
		 *
		 * The bypass is scoped to that preview request on purpose. A normal
		 * ActivityPub fetch (no `preview`) of a tombstoned URL always gets the
		 * Tombstone — even if the URL now resolves to a fresh public post because
		 * its slug was reused — since remote servers were told that id is gone.
		 * The legitimate restore path clears the registry entry itself, via
		 * `Create::maybe_unbury()` when the re-publish Create is queued.
		 */
		$is_authorized_preview = \get_query_var( 'preview' )
			&& $queried_object instanceof \WP_Post
			&& is_post_publicly_queryable( $queried_object );

		if (
			Tombstone::exists_local( Query::get_instance()->get_request_url() )
			&& ! $is_authorized_preview
		) {
			// Set 410 Gone for permanently deleted posts, 200 OK for soft-deleted.
			if ( ! $activitypub_object ) {
				\status_header( 410 );
			}

			return ACTIVITYPUB_PLUGIN_DIR . 'templates/tombstone-json.php';
		}

		/*
		 * Refuse to expose the content-negotiated representation of a post
		 * that is no longer publicly queryable (non-public status, AP
		 * visibility flipped, post-type support removed, etc.). The
		 * lifecycle gate in `is_post_disabled()` intentionally lets such
		 * posts through the federation pipeline so a Delete can fire, but
		 * that escape hatch must not leak into front-end rendering during
		 * the window between status change and Delete delivery.
		 */
		if (
			$activitypub_object &&
			$queried_object instanceof \WP_Post &&
			'ap_outbox' !== $queried_object->post_type &&
			! is_post_publicly_queryable( $queried_object )
		) {
			return $template;
		}

		$activitypub_template = false;

		if ( $activitypub_object ) {
			if ( \get_query_var( 'preview' ) ) {
				\defined( 'ACTIVITYPUB_PREVIEW' ) || \define( 'ACTIVITYPUB_PREVIEW', true );

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

		/*
		 * Send CORS headers for resolved ActivityPub objects and outbox
		 * items. Outbox items need CORS even when the object ID doesn't
		 * resolve, because browser preflight requests don't carry the
		 * Authorization header needed to authenticate private items.
		 */
		$post_id       = \get_query_var( 'p' );
		$is_outbox_url = $post_id && Outbox::POST_TYPE === \get_post_type( $post_id );

		if ( ! \headers_sent() && ( $id || $is_outbox_url ) ) {
			\header( 'Access-Control-Allow-Origin: *' );
			\header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
			\header( 'Access-Control-Allow-Headers: Accept, Authorization, Content-Type' );
		}

		if ( ! $id ) {
			return;
		}

		if ( ! \headers_sent() ) {
			\header( 'Link: <' . esc_url( $id ) . '>; title="ActivityPub (JSON)"; rel="alternate"; type="application/activity+json"', false );

			if ( \get_option( 'activitypub_vary_header', '1' ) ) {
				// Send Vary header for Accept header.
				\header( 'Vary: Accept', false );
			}
		}

		\add_action(
			'wp_head',
			static function () use ( $id ) {
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

		/*
		 * Skip the actor branch when this looks like an actor-scoped FEP-7aa9
		 * stamp URL: numeric `actor` paired with a `stamp`. Those resolve to a
		 * FeatureAuthorization via Activitypub\Query, not via the username
		 * lookup which would 404 the numeric ID. Non-numeric actors fall
		 * through to the regular Mastodon-style profile lookup.
		 */
		$actor        = \get_query_var( 'actor', null );
		$is_stamp_url = $actor && \get_query_var( 'stamp' ) && \ctype_digit( (string) $actor );
		if ( $actor && ! $is_stamp_url ) {
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

		$term_id = \get_query_var( 'term_id', null );
		if ( $term_id ) {
			$term = \get_term( $term_id );

			// Load a 404-page if `term_id` is set but not valid.
			if ( ! $term || \is_wp_error( $term ) ) {
				$wp_query->set_404();
				return;
			}

			/**
			 * Filters the taxonomies supported for term redirects.
			 *
			 * @since 7.8.3
			 *
			 * @param array $supported_taxonomies Array of taxonomy names. Default array( 'category', 'post_tag' ).
			 */
			$supported_taxonomies = \apply_filters( 'activitypub_supported_taxonomies', array( 'category', 'post_tag' ) );

			if ( ! in_array( $term->taxonomy, $supported_taxonomies, true ) ) {
				return;
			}

			// Don't redirect for ActivityPub requests.
			if ( is_activitypub_request() ) {
				return;
			}

			$term_link = \get_term_link( $term );
			if ( ! \is_wp_error( $term_link ) ) {
				\wp_safe_redirect( $term_link, 301 );
				exit;
			}
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
		$vars[] = 'term_id';

		return $vars;
	}

	/**
	 * Optimize home page query for ActivityPub requests.
	 *
	 * Skip the database query entirely for ActivityPub requests on the home page
	 * since we only need to return the blog actor, not posts.
	 *
	 * @param \WP_Query $wp_query The WP_Query instance.
	 */
	public static function fix_is_home_check( $wp_query ) {
		if (
			$wp_query->get( 'actor' ) ||
			$wp_query->get( 'stamp' ) ||
			$wp_query->get( 'c' )
		) {
			$wp_query->is_home = false;
		}
	}
}
