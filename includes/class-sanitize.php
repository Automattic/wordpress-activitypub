<?php
/**
 * Sanitization file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Model\Blog;

/**
 * Sanitization class.
 */
class Sanitize {

	/**
	 * Elements to strip including their inner content.
	 *
	 * WordPress's wp_kses removes disallowed tags but preserves their inner text.
	 * These elements contain content that is meaningless or harmful
	 * without the surrounding tag (scripts, styles, interactive UI,
	 * embedded objects) or that browsers hide by default (dialogs,
	 * templates), so we remove them entirely before wp_kses runs.
	 *
	 * @var array<string>
	 */
	const STRIP_ELEMENTS = array(
		'script',
		'style',
		'noscript',
		'template',
		'button',
		'nav',
		'dialog',
		'form',
		'textarea',
		'select',
		'option',
		'optgroup',
		'datalist',
		'input',
		'fieldset',
		'iframe',
		'embed',
		'object',
		'canvas',
		'applet',
		'noembed',
		'noframes',
	);

	/**
	 * MathML global attributes allowed per the W3C MathML safe list.
	 *
	 * @see https://w3c.github.io/mathml-docs/mathml-safe-list
	 *
	 * @var array<string, true>
	 */
	const MATHML_GLOBAL_ATTRS = array(
		'dir'            => true,
		'displaystyle'   => true,
		'mathbackground' => true,
		'mathcolor'      => true,
		'mathsize'       => true,
		'scriptlevel'    => true,
		'intent'         => true,
		'arg'            => true,
	);

	/**
	 * Sanitize a list of URLs.
	 *
	 * @param string|array $value The value to sanitize.
	 * @return array The sanitized list of URLs.
	 */
	public static function url_list( $value ) {
		if ( ! \is_array( $value ) ) {
			$value = \explode( PHP_EOL, (string) $value );
		}

		$value = \array_filter( $value );
		$value = \array_map( 'trim', $value );
		$value = \array_map( 'sanitize_url', $value );
		$value = \array_unique( $value );

		return \array_values( $value );
	}

	/**
	 * Sanitize and normalize a list of account identifiers to ActivityPub IDs.
	 *
	 * This function processes various identifier formats, such as URLs and
	 * webfinger identifiers, and normalizes them into a consistent format.
	 *
	 * @param string|array $value The value to sanitize.
	 *
	 * @return array The sanitized and normalized list of account identifiers.
	 */
	public static function identifier_list( $value ) {
		if ( ! \is_array( $value ) ) {
			$value = \explode( PHP_EOL, (string) $value );
		}

		$value = \array_filter( $value );
		$uris  = array();

		foreach ( $value as $uri ) {
			$uri = \trim( $uri );
			$uri = \ltrim( $uri, '@' );

			if ( \is_email( $uri ) ) {
				$_uri = Webfinger::resolve( $uri );
				if ( \is_wp_error( $_uri ) ) {
					$uris[] = $uri;
					continue;
				}

				$uri = $_uri;
			}

			$uri   = \sanitize_url( $uri );
			$actor = Remote_Actors::fetch_by_uri( $uri );
			if ( \is_wp_error( $actor ) ) {
				$uris[] = $uri;
			} else {
				$uris[] = \sanitize_url( $actor->guid );
			}
		}

		return \array_values( \array_unique( $uris ) );
	}

	/**
	 * Sanitize a list of hosts.
	 *
	 * @param string $value The value to sanitize.
	 * @return string The sanitized list of hosts.
	 */
	public static function host_list( $value ) {
		$value = \explode( PHP_EOL, (string) $value );
		$value = \array_map(
			static function ( $host ) {
				$host = \trim( $host );
				$host = \strtolower( $host );
				$host = \set_url_scheme( $host );
				$host = \sanitize_url( $host, array( 'http', 'https' ) );

				// Remove protocol.
				if ( \str_contains( $host, 'http' ) ) {
					$host = \wp_parse_url( $host, PHP_URL_HOST );
				}

				return \filter_var( $host, FILTER_VALIDATE_DOMAIN );
			},
			$value
		);

		return \implode( PHP_EOL, \array_filter( $value ) );
	}

	/**
	 * Sanitize a blog identifier.
	 *
	 * @param string $value The value to sanitize.
	 * @return string The sanitized blog identifier.
	 */
	public static function blog_identifier( $value ) {
		// Hack to allow dots in the username.
		$parts     = \explode( '.', (string) $value );
		$sanitized = \array_map( 'sanitize_title', $parts );
		$sanitized = \implode( '.', $sanitized );

		if ( empty( $sanitized ) ) {
			return Blog::get_default_username();
		}

		// The 'application' identifier is reserved for the Application actor.
		if ( Application::USERNAME === $sanitized ) {
			\add_settings_error(
				'activitypub_blog_identifier',
				'activitypub_blog_identifier',
				\esc_html__( 'This name is reserved and cannot be used for the blog profile ID.', 'activitypub' )
			);

			return Blog::get_default_username();
		}

		// Check for login or nicename.
		$user = new \WP_User_Query(
			array(
				'search'         => $sanitized,
				'search_columns' => array( 'user_login', 'user_nicename' ),
				'number'         => 1,
				'hide_empty'     => true,
				'fields'         => 'ID',
			)
		);

		if ( $user->get_results() ) {
			\add_settings_error(
				'activitypub_blog_identifier',
				'activitypub_blog_identifier',
				\esc_html__( 'You cannot use an existing author&#8217;s name for the blog profile ID.', 'activitypub' )
			);

			return Blog::get_default_username();
		}

		return $sanitized;
	}

	/**
	 * Get the sanitized value of a constant.
	 *
	 * @param mixed $value The constant value.
	 *
	 * @return string The sanitized value.
	 */
	public static function constant_value( $value ) {
		if ( \is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( \is_string( $value ) ) {
			return \esc_attr( $value );
		}

		if ( \is_array( $value ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			return \print_r( $value, true );
		}

		return $value;
	}

	/**
	 * Sanitize a webfinger identifier.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string The sanitized webfinger identifier.
	 */
	public static function webfinger( $value ) {
		$value = \str_replace( 'acct:', '', $value );
		$value = \trim( $value, '@' );

		return $value;
	}

	/**
	 * Reduce text that a remote server wrote to plain text, without losing any of it.
	 *
	 * For the plain-text fields that hold remote data: post titles and summaries,
	 * attachment captions and alt text, actor and reaction display names. The result
	 * carries no markup and no HTML entities, so it is safe to escape at the point of
	 * output and readable wherever it is rendered as text.
	 *
	 * kses with an empty allowlist is the tag remover here, because neither of the
	 * shorter options survives remote input:
	 *
	 * - `wp_strip_all_tags()` hands the string to `strip_tags()`, which reads a bare `<`
	 *   as a tag that never closes and drops the rest with it, so "A <3 shape" became "A".
	 * - `sanitize_text_field()` keeps that text but strips percent-encoded octets, so
	 *   "foo%20bar" became "foobar".
	 *
	 * kses knows the difference between a tag and a stray `<`, so it removes the first
	 * and escapes the second.
	 *
	 * Nothing is decoded here, deliberately. Decoding after stripping can turn text kses
	 * left alone back into live markup, and every arrangement of decode-then-strip I tried
	 * had an encoding depth that slipped through. The output is escaped; decode it at the
	 * point of display, where the surrounding escaping makes it inert.
	 *
	 * @since unreleased
	 *
	 * @param string $text The remote-authored text.
	 *
	 * @return string The text with no markup. Entities are left escaped.
	 */
	public static function clean_remote_text( $text ) {
		// Remote JSON can hand us an array where a string was expected.
		if ( ! \is_string( $text ) ) {
			return '';
		}

		return \wp_kses( self::strip_elements( $text ), array() );
	}

	/**
	 * Remove elements whose inner text is noise on its own.
	 *
	 * Shared by {@see Sanitize::clean_html()} and {@see Sanitize::clean_remote_text()}:
	 * kses removes a tag but keeps what is inside it, so a `<script>` body would
	 * otherwise survive as visible text.
	 *
	 * @since unreleased
	 *
	 * @param string $content The content to strip.
	 *
	 * @return string The content without those elements.
	 */
	private static function strip_elements( $content ) {
		/*
		 * Strip elements whose inner content is noise (scripts, styles, interactive UI, embeds).
		 * This runs before wp_kses because wp_kses strips tags but keeps inner text,
		 * and content inside <script>, <style>, <nav>, etc. is meaningless on its own.
		 */
		$strip_pattern = \implode( '|', self::STRIP_ELEMENTS );
		$content       = \preg_replace( '@<(' . $strip_pattern . ')(?=[\\s/>])[^>]*?>.*?</\\1>@si', '', $content ) ?? '';

		// <option> and <optgroup> may omit their end tags, so also strip the text that follows an unclosed one.
		$content = \preg_replace( '@<(option|optgroup)(?=[\\s/>])[^>]*?>[^<]*@si', '', $content ) ?? '';

		// Also catch self-closing variants (e.g. <input />, <embed />).
		$content = \preg_replace( '@<(' . $strip_pattern . ')(?=[\\s/>])[^>]*?/?>@si', '', $content );

		// preg_replace() returns null if PCRE bails; an empty string is the safe reading.
		return $content ?? '';
	}

	/**
	 * Sanitize content for ActivityPub.
	 *
	 * @param string $content The content to convert.
	 *
	 * @return string The converted content.
	 */
	public static function content( $content ) {
		// Only make URLs clickable if no anchor tags exist, to avoid corrupting existing links.
		if ( false === \strpos( $content, '<a ' ) ) {
			$content = \make_clickable( $content );
		}

		$content = \wpautop( $content );
		$content = self::clean_remote_html( $content );

		return $content;
	}

	/**
	 * Sanitize HTML that was written by a remote server.
	 *
	 * Like `wp_kses_post()`, but without the `style` attribute. See
	 * {@see Sanitize::get_allowed_remote_html()} for why.
	 *
	 * Not a mirror of {@see Sanitize::clean_html()}, which cleans what we send out: that
	 * one also drops {@see Sanitize::STRIP_ELEMENTS} together with their inner text. Here
	 * kses removes the tag but keeps the text, so the body of a `<style>` or `<script>`
	 * element survives as inert visible characters, the way `wp_kses_post()` left it.
	 *
	 * @since unreleased
	 *
	 * @param string $content The content to sanitize.
	 *
	 * @return string The sanitized content.
	 */
	public static function clean_remote_html( $content ) {
		if ( ! \is_string( $content ) || '' === $content ) {
			return '';
		}

		return \wp_kses( self::strip_comments( $content ), self::get_allowed_remote_html(), \wp_allowed_protocols() );
	}

	/**
	 * Sanitize comment content that was written by a remote server.
	 *
	 * Comments get a much narrower allowlist than posts, so this is not
	 * {@see Sanitize::clean_remote_html()} with a different name. Core resolves the
	 * `pre_comment_content` context to the comment allowlist, and
	 * {@see Interactions::allowed_comment_html()} adds `p`, `br` and the strict emoji
	 * `img` back on top of it.
	 *
	 * Core only applies that allowlist itself when the request installed the
	 * `pre_comment_content` filter, and `kses_init()` installs nothing for a user with
	 * `unfiltered_html`. Calling this where the content is known to be remote keeps the
	 * guarantee from depending on who is logged in.
	 *
	 * @since unreleased
	 *
	 * @param string $content The remote comment content.
	 *
	 * @return string The sanitized content.
	 */
	public static function clean_remote_comment_html( $content ) {
		if ( ! \is_string( $content ) || '' === $content ) {
			return '';
		}

		$allowed_html = Interactions::allowed_comment_html( \wp_kses_allowed_html( 'pre_comment_content' ), 'pre_comment_content' );

		return \wp_kses( self::strip_comments( $content ), $allowed_html, \wp_allowed_protocols() );
	}

	/**
	 * Remove HTML comments from content a remote server wrote.
	 *
	 * Block delimiters are HTML comments, and kses has no opinion about those.
	 * `do_blocks()` then runs over the stored value -- through `the_content` for posts and
	 * {@see \Activitypub\Comment::render_blocks()} for comments -- and core's block
	 * supports rebuild CSS from the delimiter's own JSON. A remote
	 * group delimiter carrying `style.background.backgroundImage.url` comes back out as
	 * `style="background-image:url(...)"`, which is exactly what
	 * {@see Sanitize::get_allowed_remote_html()} drops the `style` attribute to prevent.
	 * Dynamic blocks are the same story with their render callbacks.
	 *
	 * Every comment goes, not just `wp:` ones: the block parser tolerates spacing and
	 * casing a prefix match would have to chase, and a comment carries nothing a reader
	 * would see anyway. The plugin's own emoji and image delimiters are added after this
	 * runs, so they are unaffected.
	 *
	 * @since unreleased
	 *
	 * @param string $content The content to strip.
	 *
	 * @return string The content without HTML comments.
	 */
	private static function strip_comments( $content ) {
		// preg_replace() returns null if PCRE bails; an empty string is the safe reading.
		return \preg_replace( '/<!--.*?-->/s', '', $content ) ?? '';
	}

	/**
	 * Returns the allowed HTML for content that was written by a remote server.
	 *
	 * This is the post allowlist without the `style` attribute, which `wp_kses_post()`
	 * would keep. Core's `safecss_filter_attr()` then allows `background-image:url()`
	 * for https targets, plus `position`, `z-index` and the offset properties. That is
	 * fine for a post written by a trusted local user, but not for a federated one:
	 *
	 * - `background-image:url()` gets around the media cache, which is there so the
	 *   browser never loads anything from a remote host while rendering the content.
	 * - `position:fixed` with a high `z-index` lets a remote actor cover the screen
	 *   with their own markup, for example a fake login prompt.
	 *
	 * Mastodon strips inline styles on its side too, so there should be nothing real
	 * to lose here.
	 *
	 * Unlike {@see Sanitize::get_allowed_html()} this has no filter on purpose. That
	 * one is a formatting policy and extending it is reasonable, while this one is a
	 * trust boundary, and a filter would let any plugin put `style` back.
	 *
	 * @since unreleased
	 *
	 * @return array The allowed HTML structure for wp_kses.
	 */
	public static function get_allowed_remote_html() {
		$allowed_html = \wp_kses_allowed_html( 'post' );

		foreach ( $allowed_html as $tag => $attributes ) {
			if ( \is_array( $attributes ) ) {
				unset( $allowed_html[ $tag ]['style'] );
			}
		}

		return $allowed_html;
	}

	/**
	 * Strip whitespace between HTML tags.
	 *
	 * Removes newlines, carriage returns, and tabs that appear between HTML tags,
	 * preserving whitespace within text content and preformatted elements.
	 *
	 * @param string $content The content to process.
	 *
	 * @return string The content with whitespace between tags removed.
	 */
	public static function strip_whitespace( $content ) {
		return \trim( \preg_replace( '/>[\n\r\t]+</', '><', $content ) );
	}

	/**
	 * Sanitize a redirect URI, preserving custom protocol schemes.
	 *
	 * WordPress's sanitize_url() and esc_url_raw() strip unknown protocols.
	 * This method extracts the scheme and passes it as allowed so custom
	 * URI schemes for native apps (RFC 8252 Section 7.1) are preserved.
	 *
	 * @since 8.1.0
	 *
	 * @param string $uri The redirect URI to sanitize.
	 * @return string The sanitized URI.
	 */
	public static function redirect_uri( $uri ) {
		/*
		 * Extract scheme manually because wp_parse_url() returns false
		 * for URIs like "myapp://" (scheme + empty authority, no path).
		 */
		if ( ! \preg_match( '/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $uri, $matches ) ) {
			return '';
		}

		$scheme = \strtolower( $matches[1] );

		// For standard schemes, use default sanitization.
		if ( \in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return \sanitize_url( $uri );
		}

		// For custom schemes, include the scheme in allowed protocols.
		return \sanitize_url( $uri, \array_merge( \wp_allowed_protocols(), array( $scheme ) ) );
	}

	/**
	 * Clean HTML for ActivityPub federation.
	 *
	 * Uses a positive allowlist based on FEP-b2b8 (Long-form Text) for the
	 * `content` property, extended with common WordPress content elements.
	 * Interactive, navigational, and scripting elements are stripped entirely.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/b2b8/fep-b2b8.md
	 * @see https://github.com/Automattic/wordpress-activitypub/issues/2619
	 *
	 * @param string $content The HTML content to clean.
	 *
	 * @return string The cleaned HTML content.
	 */
	public static function clean_html( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		$content = self::strip_elements( $content );

		/**
		 * Fires the deprecated attribute removal filter.
		 *
		 * @deprecated 8.1.0 Use the {@see 'activitypub_allowed_html'} filter instead.
		 */
		if ( \has_filter( 'activitypub_remove_html_attributes' ) ) {
			\_deprecated_hook( 'activitypub_remove_html_attributes', '8.1.0', 'activitypub_allowed_html' );
		}

		/**
		 * Filters the allowed HTML for ActivityPub content.
		 *
		 * The default allowlist is based on FEP-b2b8 (Long-form Text),
		 * extended with common WordPress content elements like figures,
		 * tables, definition lists, and horizontal rules.
		 *
		 * @param array $allowed_html The allowed HTML structure for wp_kses.
		 */
		$allowed_html = \apply_filters( 'activitypub_allowed_html', self::get_allowed_html() );

		return \wp_kses( $content, $allowed_html, \wp_allowed_protocols() );
	}

	/**
	 * Returns the allowed HTML elements and attributes for ActivityPub content.
	 *
	 * Based on the FEP-b2b8 allowlist for the `content` property, extended
	 * with additional WordPress content elements (figures, tables, definition
	 * lists, horizontal rules, etc.).
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/b2b8/fep-b2b8.md
	 *
	 * @return array The allowed HTML structure for wp_kses.
	 */
	public static function get_allowed_html() {
		// FEP-b2b8 core allowlist.
		$allowed_html = array(
			'p'          => array(),
			'span'       => array(
				'class' => true,
			),
			'br'         => array(),
			'a'          => array(
				'href'  => true,
				'rel'   => true,
				'class' => true,
				'title' => true,
			),
			'h1'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'h6'         => array(),
			'del'        => array(),
			'pre'        => array(),
			'code'       => array(),
			'em'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'i'          => array(),
			'u'          => array(),
			'ul'         => array(),
			'ol'         => array(
				'start'    => true,
				'reversed' => true,
			),
			'li'         => array(
				'value' => true,
			),
			'blockquote' => array(
				'cite' => true,
			),
			'img'        => array(
				'src'    => true,
				'alt'    => true,
				'title'  => true,
				'width'  => true,
				'height' => true,
			),
			'video'      => array(
				'src'      => true,
				'controls' => true,
				'loop'     => true,
				'poster'   => true,
				'width'    => true,
				'height'   => true,
			),
			'audio'      => array(
				'src'      => true,
				'controls' => true,
				'loop'     => true,
			),
			'source'     => array(
				'src'  => true,
				'type' => true,
			),
			'ruby'       => array(),
			'rt'         => array(),
			'rp'         => array(),
		);

		// WordPress content extensions beyond FEP-b2b8.
		$allowed_html['figure']     = array();
		$allowed_html['figcaption'] = array();
		$allowed_html['hr']         = array();
		$allowed_html['div']        = array();
		$allowed_html['table']      = array();
		$allowed_html['thead']      = array();
		$allowed_html['tbody']      = array();
		$allowed_html['tfoot']      = array();
		$allowed_html['tr']         = array();
		$allowed_html['th']         = array(
			'colspan' => true,
			'rowspan' => true,
		);
		$allowed_html['td']         = array(
			'colspan' => true,
			'rowspan' => true,
		);
		$allowed_html['caption']    = array();
		$allowed_html['dl']         = array();
		$allowed_html['dt']         = array();
		$allowed_html['dd']         = array();
		$allowed_html['s']          = array();
		$allowed_html['sub']        = array();
		$allowed_html['sup']        = array();
		$allowed_html['abbr']       = array(
			'title' => true,
		);
		$allowed_html['mark']       = array();
		$allowed_html['ins']        = array();
		$allowed_html['cite']       = array();
		$allowed_html['time']       = array(
			'datetime' => true,
		);
		$allowed_html['track']      = array(
			'src'     => true,
			'kind'    => true,
			'label'   => true,
			'srclang' => true,
		);

		// MathML safe elements per W3C MathML safe list.
		$allowed_html['math']          = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'display' => true,
			)
		);
		$allowed_html['merror']        = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mfrac']         = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'linethickness' => true,
			)
		);
		$allowed_html['mi']            = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mmultiscripts'] = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mn']            = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mo']            = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'form'          => true,
				'fence'         => true,
				'separator'     => true,
				'lspace'        => true,
				'rspace'        => true,
				'stretchy'      => true,
				'symmetric'     => true,
				'maxsize'       => true,
				'minsize'       => true,
				'largeop'       => true,
				'movablelimits' => true,
			)
		);
		$allowed_html['mover']         = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mpadded']       = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'width'   => true,
				'height'  => true,
				'depth'   => true,
				'lspace'  => true,
				'voffset' => true,
			)
		);
		$allowed_html['mprescripts']   = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mroot']         = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mrow']          = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['ms']            = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mspace']        = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'width'  => true,
				'height' => true,
				'depth'  => true,
			)
		);
		$allowed_html['msqrt']         = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mstyle']        = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['msub']          = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['msubsup']       = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['msup']          = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mtable']        = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mtd']           = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'columnspan' => true,
				'rowspan'    => true,
			)
		);
		$allowed_html['mtext']         = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['mtr']           = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['munder']        = self::MATHML_GLOBAL_ATTRS;
		$allowed_html['munderover']    = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'accent'      => true,
				'accentunder' => true,
			)
		);
		$allowed_html['semantics']     = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'encoding' => true,
			)
		);
		$allowed_html['annotation']    = \array_merge(
			self::MATHML_GLOBAL_ATTRS,
			array(
				'encoding' => true,
			)
		);
		return $allowed_html;
	}
}
