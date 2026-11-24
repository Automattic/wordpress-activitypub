<?php
/**
 * Media functions file.
 *
 * Functions for processing remote media (images, audio, video) in ActivityPub content.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Check if a URL is a remote URL (not local to this site).
 *
 * @param string $url The URL to check.
 *
 * @return bool True if the URL is remote, false otherwise.
 */
function is_remote_url( $url ) {
	// Must be http/https URL.
	if ( ! \preg_match( '#^https?://#i', $url ) ) {
		return false;
	}

	// Compare hosts to determine if URL is local.
	$site_host = \wp_parse_url( \home_url(), PHP_URL_HOST );
	$url_host  = \wp_parse_url( $url, PHP_URL_HOST );

	// If hosts match, it's a local URL.
	if ( $site_host && $url_host && 0 === \strcasecmp( $site_host, $url_host ) ) {
		return false;
	}

	// Also check uploads directory with strict prefix match.
	$upload_dir  = \wp_upload_dir();
	$upload_base = isset( $upload_dir['baseurl'] ) ? \trailingslashit( $upload_dir['baseurl'] ) : '';

	if ( $upload_base && \str_starts_with( $url, $upload_base ) ) {
		return false;
	}

	return true;
}

/**
 * Whether an image is too large to decode.
 *
 * An image editor decodes the whole bitmap before it can resize it, so a small file that
 * declares huge dimensions (a flat-color PNG of 30000×30000) takes gigabytes of memory.
 * Only the header is read here. The limit is `ACTIVITYPUB_MAX_IMAGE_PIXELS`, and for a GIF,
 * which may be animated, the lower `ACTIVITYPUB_MAX_GIF_PIXELS`.
 * An image whose dimensions cannot be read counts as too large.
 *
 * @since unreleased
 *
 * @param string $file_path The path to the image file.
 *
 * @return bool True when the image must not be decoded.
 */
function image_exceeds_pixel_limit( $file_path ) {
	$size = \wp_getimagesize( $file_path );

	if ( empty( $size[0] ) || empty( $size[1] ) ) {
		return true;
	}

	$limit = 'image/gif' === $size['mime'] ? ACTIVITYPUB_MAX_GIF_PIXELS : ACTIVITYPUB_MAX_IMAGE_PIXELS;

	return $size[0] * $size[1] > $limit;
}

/**
 * Generate an image block wrapper for a remote image.
 *
 * @param string $url      The remote image URL.
 * @param string $img_html The img tag HTML.
 *
 * @return string The wrapped image block.
 */
function generate_image_block( $url, $img_html ) {
	$processor = new \WP_HTML_Tag_Processor( $img_html );
	$processor->next_tag( array( 'tag_name' => 'IMG' ) );

	return \get_comment_delimited_block_content(
		'activitypub/image',
		array(
			// Core runs block attribute strings through KSES when saving comments.
			'url'        => \htmlspecialchars( $url, ENT_QUOTES, 'UTF-8', true ),
			'urlEncoded' => true,
			'alt'        => \htmlspecialchars( $processor->get_attribute( 'alt' ) ?? '', ENT_QUOTES, 'UTF-8', true ),
		),
		$img_html
	);
}

/**
 * Generate an audio block wrapper for a remote audio file.
 *
 * @param string $url The remote audio URL.
 *
 * @return string The audio block markup.
 */
function generate_audio_block( $url ) {
	// Core escapes the attributes, so a URL cannot end the block comment and start another block.
	return \get_comment_delimited_block_content(
		'activitypub/audio',
		array( 'url' => $url ),
		\sprintf( '<figure class="wp-block-audio"><audio controls src="%s"></audio></figure>', \esc_url( $url ) )
	);
}

/**
 * Generate a video block wrapper for a remote video file.
 *
 * @param string $url The remote video URL.
 *
 * @return string The video block markup.
 */
function generate_video_block( $url ) {
	// Core escapes the attributes, so a URL cannot end the block comment and start another block.
	return \get_comment_delimited_block_content(
		'activitypub/video',
		array( 'url' => $url ),
		\sprintf( '<figure class="wp-block-video"><video controls src="%s"></video></figure>', \esc_url( $url ) )
	);
}

/**
 * Process remote images in content and from attachments.
 *
 * Wraps remote `<img>` tags with activitypub/image blocks for lazy caching,
 * and appends any attachments not already present in the content.
 *
 * @param string $content     The content to process.
 * @param array  $attachments Optional. Array of attachments with 'url' and 'alt' keys.
 *
 * @return string The content with wrapped images and appended attachments.
 */
function process_remote_images( $content, $attachments = array() ) {
	if ( empty( $content ) && empty( $attachments ) ) {
		return $content;
	}

	// Track URLs we've seen to avoid duplicates.
	$seen_urls = array();

	// Process existing images in content.
	if ( ! empty( $content ) && false !== \strpos( $content, '<img' ) ) {
		$processor = new \WP_HTML_Tag_Processor( $content );

		// Mark remote images for wrapping using a data attribute.
		while ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
			$src = $processor->get_attribute( 'src' );

			if ( $src && is_remote_url( $src ) && ! isset( $seen_urls[ $src ] ) ) {
				$processor->set_attribute( 'data-activitypub-wrap', '1' );
				$seen_urls[ $src ] = true;
			}
		}

		$content = $processor->get_updated_html();

		// Wrap marked images, skipping those already in image blocks.
		$content = \preg_replace_callback(
			'/<!-- wp:activitypub\/image[^>]*-->.*?<!-- \/wp:activitypub\/image -->(*SKIP)(?!)|<img\s+([^>]*)data-activitypub-wrap="1"([^>]*)>/is',
			function ( $matches ) {
				if ( empty( $matches[1] ) && empty( $matches[2] ) ) {
					return $matches[0]; // Skipped block.
				}

				// Reconstruct img tag without the marker attribute.
				$img_html = '<img ' . \trim( $matches[1] . $matches[2] ) . '>';

				$processor = new \WP_HTML_Tag_Processor( $img_html );
				if ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
					$src = $processor->get_attribute( 'src' );
					if ( \is_string( $src ) && $src ) {
						return generate_image_block( $src, $img_html );
					}
				}

				return $matches[0];
			},
			$content
		);
	}

	// Append attachments not already in content.
	if ( ! empty( $attachments ) ) {
		foreach ( $attachments as $attachment ) {
			if ( 'image' !== ( $attachment['type'] ?? 'image' ) ) {
				continue;
			}

			$url = $attachment['url'] ?? '';
			if ( empty( $url ) || isset( $seen_urls[ $url ] ) ) {
				continue;
			}

			// Also check if URL appears in content (in case it wasn't in an img tag).
			if ( false !== \strpos( $content, $url ) ) {
				continue;
			}

			$alt     = ! empty( $attachment['alt'] ) ? \esc_attr( \htmlspecialchars( $attachment['alt'], ENT_QUOTES, 'UTF-8', true ) ) : '';
			$img_tag = $alt
				? \sprintf( '<img src="%s" alt="%s" />', \esc_url( \htmlspecialchars( $url, ENT_QUOTES, 'UTF-8', true ) ), $alt )
				: \sprintf( '<img src="%s" />', \esc_url( \htmlspecialchars( $url, ENT_QUOTES, 'UTF-8', true ) ) );

			$content          .= "\n\n" . generate_image_block( $url, $img_tag );
			$seen_urls[ $url ] = true;
		}
	}

	return $content;
}

/**
 * Process remote media in content and from attachments.
 *
 * Delegates image attachments to process_remote_images() and appends
 * audio/video attachments as their respective block types.
 *
 * @param string $content     The content to process.
 * @param array  $attachments Optional. Array of attachments with 'url', 'alt', and 'type' keys.
 *
 * @return string The content with wrapped images and appended audio/video blocks.
 */
function process_remote_media( $content, $attachments = array() ) {
	// Separate attachments by type.
	$image_attachments = array();
	$media_attachments = array();

	foreach ( $attachments as $attachment ) {
		$type = $attachment['type'] ?? 'image';

		if ( 'audio' === $type || 'video' === $type ) {
			$media_attachments[] = $attachment;
		} else {
			$image_attachments[] = $attachment;
		}
	}

	// Delegate image attachments to existing handler.
	$content = process_remote_images( $content, $image_attachments );

	// Append audio/video blocks.
	foreach ( $media_attachments as $attachment ) {
		$url = $attachment['url'] ?? '';
		if ( empty( $url ) ) {
			continue;
		}

		// Skip if URL already appears in content.
		if ( false !== \strpos( $content, $url ) ) {
			continue;
		}

		$type = $attachment['type'];

		if ( 'audio' === $type ) {
			$content .= "\n\n" . generate_audio_block( $url );
		} elseif ( 'video' === $type ) {
			$content .= "\n\n" . generate_video_block( $url );
		}
	}

	return $content;
}
