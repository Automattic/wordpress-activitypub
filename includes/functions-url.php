<?php
/**
 * URL functions.
 *
 * Functions for URL manipulation.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WpOrg\Requests\Exception as Requests_Exception;
use WpOrg\Requests\IdnaEncoder;

/**
 * Get the REST URL relative to this plugin's namespace.
 *
 * @param string $path Optional. REST route path. Default ''.
 *
 * @return string REST URL relative to this plugin's namespace.
 */
function get_rest_url_by_path( $path = '' ) {
	// We'll handle the leading slash.
	$path            = \ltrim( $path, '/' );
	$namespaced_path = \sprintf( '/%s/%s', ACTIVITYPUB_REST_NAMESPACE, $path );
	return \get_rest_url( null, $namespaced_path );
}

/**
 * Normalize a URL.
 *
 * @param string $url The URL.
 *
 * @return string The normalized URL.
 */
function normalize_url( $url ) {
	// Remove ActivityPub-specific query parameters.
	$url = \remove_query_arg( array( 'activitypub', 'preview' ), $url );
	$url = \untrailingslashit( $url );
	$url = \preg_replace( '/^https?:\/\/(www\.)?/', '', $url );

	return $url;
}

/**
 * Normalize a host.
 *
 * @param string $host The host.
 *
 * @return string The normalized host.
 */
function normalize_host( $host ) {
	return \preg_replace( '/^www\./', '', $host );
}

/**
 * Convert a host to its ASCII form.
 *
 * An internationalized host is converted to its IDNA A-label (`café.example` becomes
 * `xn--caf-dma.example`), which is the form `acct:` URIs and DNS use. ASCII hosts, IPv6
 * literals included, are returned as they are.
 *
 * @since unreleased
 *
 * @param string $host The host.
 *
 * @return string The host in ASCII form, or the host as it is if it cannot be converted.
 */
function to_ascii_host( $host ) {
	$host = (string) $host;

	if ( ! \preg_match( '/[^\x00-\x7F]/', $host ) ) {
		return $host;
	}

	// UTS #46 maps equivalent spellings (case, width, decomposed accents) to the same A-label.
	if ( \function_exists( 'idn_to_ascii' ) ) {
		$ascii = \idn_to_ascii( $host, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46 );

		/*
		 * UTS #46 also maps characters away (soft hyphen, fullwidth Latin), which turns a label
		 * into a plain ASCII name that WordPress would not connect to. A label with non-ASCII
		 * characters has to stay an A-label, or the host is returned as it is.
		 */
		$labels = \preg_split( '/[.\x{3002}\x{FF0E}\x{FF61}]/u', $host );
		if ( ! $ascii || ! $labels || \count( $labels ) !== \substr_count( $ascii, '.' ) + 1 ) {
			return $host;
		}

		$ascii_labels = \explode( '.', $ascii );
		foreach ( $labels as $i => $label ) {
			if ( \preg_match( '/[^\x00-\x7F]/', $label ) && 0 !== \stripos( $ascii_labels[ $i ], 'xn--' ) ) {
				return $host;
			}
		}

		return $ascii;
	}

	// Without intl only case is folded: decomposed accents, fullwidth letters and dots like `。` give a different A-label.
	if ( \function_exists( 'mb_strtolower' ) ) {
		$host = \mb_strtolower( $host, 'UTF-8' );
	}

	try {
		return \strtolower( IdnaEncoder::encode( $host ) );
	} catch ( Requests_Exception $e ) {
		return $host;
	}
}

/**
 * Fold a host to its canonical spelling for comparison.
 *
 * Hosts are case-insensitive, IPv6 literals are bracketed in a URI but not in a stored host,
 * a trailing dot is the same fully qualified name, and an internationalized host is the same as
 * its A-label. Two spellings that differ only in those ways address the same host, so anything
 * comparing hosts has to fold them first.
 *
 * Unlike {@see normalize_host()} this is lossless: it does not strip `www.`, which is a
 * different host rather than a different spelling of one.
 *
 * @since 9.3.0
 *
 * @param string $host The host.
 *
 * @return string The folded host.
 */
function fold_host( $host ) {
	// Trimmed again after the conversion, which maps dots like `。` to `.` when the intl extension is available.
	return \rtrim( to_ascii_host( \rtrim( \trim( \strtolower( (string) $host ), '[]' ), '.' ) ), '.' );
}

/**
 * Fold an authority to the host it names.
 *
 * An authority is what a `Host` header carries: a host, plus a port whenever it is not the
 * default one, with an IPv6 literal in brackets. {@see fold_host()} expects a bare host and
 * strips brackets without understanding a port, so `[::1]:443` would fold to `::1]:443` and
 * `example.org:8080` would never match the `example.org` that {@see home_host()} returns.
 * Use this wherever an authority has to be compared against a host.
 *
 * @since 9.3.1
 *
 * @param string $authority A host, optionally bracketed and optionally with a port.
 *
 * @return string The folded host, or an empty string when the value names none.
 */
function fold_authority( $authority ) {
	// Parsed as a scheme-relative URL, which is the shape an authority already has.
	$host = \wp_parse_url( '//' . \ltrim( (string) $authority, '/' ), \PHP_URL_HOST );

	return fold_host( (string) $host );
}

/**
 * Check if an authority names a host a signature may be made out to.
 *
 * A signature is bound to the host it was made out to, and the verifiers read that host from the
 * request. Only a host this site answers on may enter a signature base, or a signature made out
 * to another server would verify here when it is replayed to the same path with that server's name.
 *
 * @since unreleased
 *
 * @param string $authority A host, optionally bracketed and optionally with a port.
 *
 * @return bool True if the authority names one of this site's hosts, false otherwise.
 */
function is_signature_host( $authority ) {
	$authority = fold_authority( $authority );

	// An unparseable value folds to nothing and must not match an unparseable entry.
	if ( '' === $authority ) {
		return false;
	}

	/**
	 * Filters the hosts a signature may legitimately have been made out to.
	 *
	 * Sites reached under a name other than `home_url()`, for example behind a proxy, add it
	 * here. Write them the way they appear in a URL; they are folded before they are compared.
	 *
	 * @since 9.3.1
	 *
	 * @param string[] $hosts Hosts this site answers on.
	 */
	$hosts = \apply_filters( 'activitypub_signature_original_hosts', array( home_host() ) );

	// Cast because a callback returning one host outside an array would otherwise be a fatal rather than a non-match.
	foreach ( (array) $hosts as $host ) {
		if ( fold_authority( $host ) === $authority ) {
			return true;
		}
	}

	return false;
}

/**
 * Get the authority of a URL the way an HTTP client sends it in `Host`.
 *
 * The host, plus the port whenever it is not the scheme's default, as RFC 9110 defines `Host`
 * and as WordPress' HTTP transports send it. A signature over the host has to name this value,
 * or a server on a non-default port cannot verify it.
 *
 * @since unreleased
 *
 * @param string $url The URL.
 *
 * @return string The authority, or an empty string when the URL has no host.
 */
function get_authority( $url ) {
	$parts = \wp_parse_url( $url );

	if ( empty( $parts['host'] ) ) {
		return '';
	}

	static $default = array(
		'http'  => 80,
		'https' => 443,
	);

	$scheme = \strtolower( $parts['scheme'] ?? '' );

	if ( isset( $parts['port'] ) && ( $default[ $scheme ] ?? null ) !== (int) $parts['port'] ) {
		return $parts['host'] . ':' . (int) $parts['port'];
	}

	return $parts['host'];
}

/**
 * Check if a URL is from the same domain as the site.
 *
 * @param string $url The URL to check.
 *
 * @return boolean True if the URL is from the same domain, false otherwise.
 */
function is_same_domain( $url ) {
	$remote = \wp_parse_url( $url, PHP_URL_HOST );

	if ( ! $remote ) {
		return false;
	}

	$remote = normalize_host( $remote );
	$self   = normalize_host( home_host() );

	return $remote === $self;
}

/**
 * Retrieves the Host for the current site where the front end is accessible.
 *
 * @return string The host for the current site.
 */
function home_host() {
	return \wp_parse_url( \home_url(), PHP_URL_HOST );
}

/**
 * Get the base URL for uploads.
 *
 * @return string The upload base URL.
 */
function get_upload_baseurl() {
	/**
	 * Early filter to allow plugins to set the upload base URL.
	 *
	 * @param string|false $maybe_upload_dir The upload base URL or false if not set.
	 */
	$maybe_upload_dir = \apply_filters( 'pre_activitypub_get_upload_baseurl', false );
	if ( false !== $maybe_upload_dir ) {
		return $maybe_upload_dir;
	}

	$upload_dir = \wp_get_upload_dir();

	/**
	 * Filters the upload base URL.
	 *
	 * @param string $upload_dir The upload base URL. Default \wp_get_upload_dir()['baseurl']
	 */
	return \apply_filters( 'activitypub_get_upload_baseurl', $upload_dir['baseurl'] );
}

/**
 * Get the authority (scheme + host) from a URL.
 *
 * @param string $url The URL to parse.
 *
 * @return string|false The authority, or false on failure.
 */
function get_url_authority( $url ) {
	$parsed = \wp_parse_url( $url );

	if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
		return false;
	}

	return $parsed['scheme'] . '://' . $parsed['host'];
}

/**
 * Infer a shortname from the Actor ID or URL. Used only for fallbacks,
 * we will try to use what's supplied.
 *
 * @param string $uri The URI.
 *
 * @return string Hopefully the name of the Follower.
 */
function extract_name_from_uri( $uri ) {
	$name = $uri;

	if ( \filter_var( $name, FILTER_VALIDATE_URL ) ) {
		$name = \rtrim( $name, '/' );
		$path = \wp_parse_url( $name, PHP_URL_PATH );
		if ( $path && '/' !== $path ) {
			if ( \strpos( $name, '@' ) !== false ) {
				// Expected: https://example.com/@user (default URL pattern).
				$name = \preg_replace( '|^/@?|', '', $path );
			} else {
				// Expected: https://example.com/users/user (default ID pattern).
				$parts = \explode( '/', $path );
				$name  = \array_pop( $parts );
			}
		} else {
			$name = \wp_parse_url( $name, PHP_URL_HOST );
			$name = \str_replace( 'www.', '', $name );
		}
	} elseif (
		\is_email( $name ) ||
		\strpos( $name, 'acct' ) === 0 ||
		\strpos( $name, '@' ) === 0
	) {
		// Expected: user@example.com or acct:user@example (WebFinger).
		$name = \ltrim( $name, '@' );
		if ( \str_starts_with( $name, 'acct:' ) ) {
			$name = \substr( $name, 5 );
		}
		$parts = \explode( '@', $name );
		$name  = $parts[0];
	}

	return $name;
}
