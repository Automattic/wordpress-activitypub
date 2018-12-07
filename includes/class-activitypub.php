<?php
/**
 * ActivityPub Class
 *
 * @author Matthias Pfefferle
 */
class Activitypub {
	/**
	 * Return a AS2 JSON version of an author, post or page
	 *
	 * @param  string $template the path to the template object
	 *
	 * @return string the new path to the JSON template
	 */
	public static function render_json_template( $template ) {
		if ( ! is_author() && ! is_singular() ) {
			return $template;
		}

		if ( is_author() ) {
			$json_template = dirname( __FILE__ ) . '/../templates/json-author.php';
		} elseif ( is_singular() ) {
			$json_template = dirname( __FILE__ ) . '/../templates/json-post.php';
		}

		global $wp_query;

		if ( isset( $wp_query->query_vars['as2'] ) ) {
			return $json_template;
		}

		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return $template;
		}

		// interpret accept header
		$pos = stripos( $_SERVER['HTTP_ACCEPT'], ';' );
		if ( $pos ) {
			$accept_header = substr( $_SERVER['HTTP_ACCEPT'], 0, $pos );
		} else {
			$accept_header = $_SERVER['HTTP_ACCEPT'];
		}
		// accept header as an array
		$accept = explode( ',', trim( $accept_header ) );

		if (
			! in_array( 'application/activity+json', $accept, true ) &&
			! in_array( 'application/ld+json', $accept, true ) &&
			! in_array( 'application/json', $accept, true )
		) {
			return $template;
		}

		return $json_template;
	}

	/**
	 * Add the 'photos' query variable so WordPress
	 * won't mangle it.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'as2';

		return $vars;
	}

	/**
	 * Add our rewrite endpoint to permalinks and pages.
	 */
	public static function add_rewrite_endpoint() {
		add_rewrite_endpoint( 'as2', EP_AUTHORS | EP_PERMALINK | EP_PAGES );
	}
}
