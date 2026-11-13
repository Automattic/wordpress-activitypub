import { __unstableStripHTML as stripHTML } from '@wordpress/dom';
import { __ } from '@wordpress/i18n';

/**
 * The maximum note length from the server-side ACTIVITYPUB_NOTE_LENGTH constant.
 * Fallback matches ACTIVITYPUB_NOTE_LENGTH default; keep in sync with includes/constants.php.
 *
 * @type {number}
 */
const NOTE_LENGTH = window._activityPubOptions?.noteLength || 500;

/**
 * Video embed providers for detecting video content in embed blocks.
 *
 * @type {string[]}
 */
const VIDEO_PROVIDERS = [ 'youtube', 'vimeo', 'dailymotion', 'tiktok', 'videopress' ];

/**
 * Audio embed providers for detecting audio content in embed blocks.
 *
 * @type {string[]}
 */
const AUDIO_PROVIDERS = [ 'spotify', 'soundcloud', 'mixcloud' ];

/**
 * Block names that contain text content.
 *
 * @type {string[]}
 */
const TEXT_BLOCK_NAMES = [
	'core/paragraph',
	'core/heading',
	'core/list-item',
	'core/preformatted',
	'core/verse',
	'core/pullquote',
];

/**
 * Block names that represent gallery content.
 *
 * @type {string[]}
 */
const GALLERY_BLOCK_NAMES = [ 'core/gallery', 'jetpack/tiled-gallery', 'jetpack/slideshow' ];

/**
 * Translatable labels for post format slugs used in suggestions.
 *
 * @type {Object<string, string>}
 */
export const FORMAT_LABELS = {
	gallery: __( 'Gallery', 'activitypub' ),
	video: __( 'Video', 'activitypub' ),
	audio: __( 'Audio', 'activitypub' ),
	image: __( 'Image', 'activitypub' ),
	status: __( 'Status', 'activitypub' ),
};

/**
 * Returns the text content attribute for a given block name.
 *
 * Most text blocks store content in `attributes.content`, but some
 * blocks use different attribute keys (e.g. `core/pullquote` uses `value`).
 *
 * @param {string} name       The block name.
 * @param {Object} attributes The block attributes.
 *
 * @return {string} The text content.
 */
const getBlockTextContent = ( name, attributes ) => {
	if ( name === 'core/pullquote' ) {
		return attributes?.value || '';
	}
	return attributes?.content || '';
};

/**
 * Recursively analyzes blocks and returns content statistics.
 *
 * Walks all blocks (including inner blocks) and counts media elements
 * and text content to help determine the best post format.
 *
 * @param {Array} blocks The blocks to analyze.
 *
 * @return {Object} Content statistics with imageCount, galleryCount, videoCount, audioCount, textLength, textBlockCount.
 */
export const analyzeBlocks = ( blocks ) => {
	const result = {
		imageCount: 0,
		galleryCount: 0,
		videoCount: 0,
		audioCount: 0,
		textLength: 0,
		textBlockCount: 0,
	};

	if ( ! blocks || ! blocks.length ) {
		return result;
	}

	for ( const block of blocks ) {
		const { name, attributes, innerBlocks } = block;

		if ( name === 'core/image' ) {
			result.imageCount++;
		} else if ( GALLERY_BLOCK_NAMES.includes( name ) ) {
			result.galleryCount++;
		} else if ( name === 'core/video' ) {
			result.videoCount++;
		} else if ( name === 'core/audio' ) {
			result.audioCount++;
		} else if ( name === 'core/embed' ) {
			const provider = ( attributes?.providerNameSlug || '' ).toLowerCase();
			if ( VIDEO_PROVIDERS.includes( provider ) ) {
				result.videoCount++;
			} else if ( AUDIO_PROVIDERS.includes( provider ) ) {
				result.audioCount++;
			}
		}

		if ( TEXT_BLOCK_NAMES.includes( name ) ) {
			const text = stripHTML( getBlockTextContent( name, attributes ) );
			result.textLength += text.length;
			result.textBlockCount++;
		}

		if ( innerBlocks && innerBlocks.length ) {
			const inner = analyzeBlocks( innerBlocks );
			result.imageCount += inner.imageCount;
			result.galleryCount += inner.galleryCount;
			result.videoCount += inner.videoCount;
			result.audioCount += inner.audioCount;
			result.textLength += inner.textLength;
			result.textBlockCount += inner.textBlockCount;
		}
	}

	return result;
};

/**
 * Suggests a post format based on block content analysis.
 *
 * Returns a suggestion only when the current format is the default (standard).
 * If the user has explicitly chosen a format, no suggestion is made.
 *
 * @param {Array}  blocks        The blocks to analyze.
 * @param {string} currentFormat The current post format.
 *
 * @return {Object|null} Suggestion object with `format` and `message`, or null.
 */
export const getSuggestedPostFormat = ( blocks, currentFormat ) => {
	// Don't suggest if user explicitly set a format.
	if ( currentFormat && currentFormat !== 'standard' ) {
		return null;
	}

	const stats = analyzeBlocks( blocks );
	const hasMedia = stats.imageCount > 0 || stats.galleryCount > 0 || stats.videoCount > 0 || stats.audioCount > 0;

	// Gallery: gallery blocks or multiple images with short text.
	if ( ( stats.galleryCount > 0 || stats.imageCount > 1 ) && stats.textLength < NOTE_LENGTH ) {
		return {
			format: 'gallery',
			message: __(
				'This post contains multiple images. With the Gallery format, it is shared as a media post on the Fediverse and shows up on platforms like Pixelfed. Your post content stays the same.',
				'activitypub'
			),
		};
	}

	// Video: video with short text.
	if ( stats.videoCount > 0 && stats.textLength < NOTE_LENGTH ) {
		return {
			format: 'video',
			message: __(
				'This post contains a video. With the Video format, it is shared as a media post on the Fediverse and works better on video platforms. Your post content stays the same.',
				'activitypub'
			),
		};
	}

	// Audio: audio with short text.
	if ( stats.audioCount > 0 && stats.textLength < NOTE_LENGTH ) {
		return {
			format: 'audio',
			message: __(
				'This post contains audio. With the Audio format, it is shared as a media post on the Fediverse and works better on audio platforms. Your post content stays the same.',
				'activitypub'
			),
		};
	}

	// Image: single image with short text (after video/audio to avoid wrong suggestion for mixed media).
	if (
		stats.imageCount === 1 &&
		stats.videoCount === 0 &&
		stats.audioCount === 0 &&
		stats.textLength < NOTE_LENGTH
	) {
		return {
			format: 'image',
			message: __(
				'This post contains an image. With the Image format, it is shared as a media post on the Fediverse and shows up on platforms like Pixelfed. Your post content stays the same.',
				'activitypub'
			),
		};
	}

	// Status: very short text-only.
	if ( ! hasMedia && stats.textLength > 0 && stats.textLength < 280 && stats.textBlockCount <= 3 ) {
		return {
			format: 'status',
			message: __(
				'This is a short post without media. With the Status format, it is shared as a short post on the Fediverse, the way posts look on platforms like Mastodon. Your post content stays the same.',
				'activitypub'
			),
		};
	}

	return null;
};
