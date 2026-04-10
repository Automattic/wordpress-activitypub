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
 * Generate an image block wrapper for a remote image.
 *
 * @param string $url      The remote image URL.
 * @param string $img_html The img tag HTML.
 *
 * @return string The wrapped image block.
 */
function generate_image_block( $url, $img_html ) {
	return \sprintf(
		'<!-- wp:activitypub/image %s -->%s<!-- /wp:activitypub/image -->',
		\wp_json_encode( array( 'url' => $url ) ),
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
	return \sprintf(
		'<!-- wp:activitypub/audio %s --><figure class="wp-block-audio"><audio controls src="%s"></audio></figure><!-- /wp:activitypub/audio -->',
		\wp_json_encode( array( 'url' => $url ) ),
		\esc_url( $url )
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
	return \sprintf(
		'<!-- wp:activitypub/video %s --><figure class="wp-block-video"><video controls src="%s"></video></figure><!-- /wp:activitypub/video -->',
		\wp_json_encode( array( 'url' => $url ) ),
		\esc_url( $url )
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
		while ( $processor->next_tag( 'IMG' ) ) {
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

				// Extract src URL from the img tag.
				if ( \preg_match( '/src=["\']([^"\']+)["\']/', $img_html, $src_match ) ) {
					return generate_image_block( $src_match[1], $img_html );
				}

				return $matches[0];
			},
			$content
		);
	}

	// Append attachments not already in content.
	if ( ! empty( $attachments ) ) {
		foreach ( $attachments as $attachment ) {
			$url = $attachment['url'] ?? '';
			if ( empty( $url ) || isset( $seen_urls[ $url ] ) ) {
				continue;
			}

			// Also check if URL appears in content (in case it wasn't in an img tag).
			if ( false !== \strpos( $content, $url ) ) {
				continue;
			}

			$alt     = ! empty( $attachment['alt'] ) ? \esc_attr( $attachment['alt'] ) : '';
			$img_tag = $alt
				? \sprintf( '<img src="%s" alt="%s" />', \esc_url( $url ), $alt )
				: \sprintf( '<img src="%s" />', \esc_url( $url ) );

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
