import { __ } from '@wordpress/i18n';

/**
 * The maximum note length from the server-side ACTIVITYPUB_NOTE_LENGTH constant.
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

	const textBlockNames = [
		'core/paragraph',
		'core/heading',
		'core/list-item',
		'core/preformatted',
		'core/verse',
		'core/pullquote',
	];

	const galleryBlockNames = [ 'core/gallery', 'jetpack/tiled-gallery', 'jetpack/slideshow' ];

	for ( const block of blocks ) {
		const { name, attributes, innerBlocks } = block;

		if ( name === 'core/image' ) {
			result.imageCount++;
		} else if ( galleryBlockNames.includes( name ) ) {
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

		if ( textBlockNames.includes( name ) ) {
			const content = ( attributes?.content || '' ).replace( /<[^>]*>/g, '' );
			result.textLength += content.length;
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
				'This post contains multiple images. Setting the format to Gallery will share it as a media post, making it visible on platforms like Pixelfed.',
				'activitypub'
			),
		};
	}

	// Image: single image with short text.
	if ( stats.imageCount === 1 && stats.textLength < NOTE_LENGTH ) {
		return {
			format: 'image',
			message: __(
				'This post contains an image. Setting the format to Image will share it as a media post, making it visible on platforms like Pixelfed.',
				'activitypub'
			),
		};
	}

	// Video: video with short text.
	if ( stats.videoCount > 0 && stats.textLength < NOTE_LENGTH ) {
		return {
			format: 'video',
			message: __(
				'This post contains video. Setting the format to Video will share it as a media post, improving compatibility with video-focused platforms.',
				'activitypub'
			),
		};
	}

	// Audio: audio with short text.
	if ( stats.audioCount > 0 && stats.textLength < NOTE_LENGTH ) {
		return {
			format: 'audio',
			message: __(
				'This post contains audio. Setting the format to Audio will share it as a media post, improving compatibility with audio-focused platforms.',
				'activitypub'
			),
		};
	}

	// Status: very short text-only.
	if ( ! hasMedia && stats.textLength > 0 && stats.textLength < 280 && stats.textBlockCount <= 3 ) {
		return {
			format: 'status',
			message: __(
				'This is a short post with no media. Setting the format to Status will share it as a Note, which is the standard format on platforms like Mastodon.',
				'activitypub'
			),
		};
	}

	return null;
};
