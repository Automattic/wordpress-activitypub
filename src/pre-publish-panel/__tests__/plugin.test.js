/**
 * @jest-environment jsdom
 */

// Set up the global options before importing utils, so NOTE_LENGTH picks up the value.
window._activityPubOptions = { noteLength: 500 };

import { analyzeBlocks, getSuggestedPostFormat } from '../utils';

describe( 'analyzeBlocks', () => {
	test( 'returns all zeros for empty blocks', () => {
		expect( analyzeBlocks( [] ) ).toEqual( {
			imageCount: 0,
			galleryCount: 0,
			videoCount: 0,
			audioCount: 0,
			textLength: 0,
			textBlockCount: 0,
		} );
	} );

	test( 'returns all zeros for null/undefined', () => {
		expect( analyzeBlocks( null ) ).toEqual( {
			imageCount: 0,
			galleryCount: 0,
			videoCount: 0,
			audioCount: 0,
			textLength: 0,
			textBlockCount: 0,
		} );
	} );

	test( 'counts text from a single paragraph', () => {
		const blocks = [
			{
				name: 'core/paragraph',
				attributes: { content: 'Hello world' },
				innerBlocks: [],
			},
		];
		const result = analyzeBlocks( blocks );
		expect( result.textLength ).toBe( 11 );
		expect( result.textBlockCount ).toBe( 1 );
	} );

	test( 'strips HTML from text content', () => {
		const blocks = [
			{
				name: 'core/paragraph',
				attributes: {
					content: '<strong>Hello</strong> <em>world</em>',
				},
				innerBlocks: [],
			},
		];
		const result = analyzeBlocks( blocks );
		expect( result.textLength ).toBe( 11 ); // "Hello world"
	} );

	test( 'counts image blocks', () => {
		const blocks = [
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
		];
		const result = analyzeBlocks( blocks );
		expect( result.imageCount ).toBe( 2 );
	} );

	test( 'counts gallery blocks', () => {
		const blocks = [ { name: 'core/gallery', attributes: {}, innerBlocks: [] } ];
		const result = analyzeBlocks( blocks );
		expect( result.galleryCount ).toBe( 1 );
	} );

	test( 'counts jetpack gallery blocks', () => {
		const blocks = [
			{
				name: 'jetpack/tiled-gallery',
				attributes: {},
				innerBlocks: [],
			},
			{ name: 'jetpack/slideshow', attributes: {}, innerBlocks: [] },
		];
		const result = analyzeBlocks( blocks );
		expect( result.galleryCount ).toBe( 2 );
	} );

	test( 'counts video blocks', () => {
		const blocks = [ { name: 'core/video', attributes: {}, innerBlocks: [] } ];
		const result = analyzeBlocks( blocks );
		expect( result.videoCount ).toBe( 1 );
	} );

	test( 'counts audio blocks', () => {
		const blocks = [ { name: 'core/audio', attributes: {}, innerBlocks: [] } ];
		const result = analyzeBlocks( blocks );
		expect( result.audioCount ).toBe( 1 );
	} );

	test( 'detects video embed providers', () => {
		const blocks = [
			{
				name: 'core/embed',
				attributes: { providerNameSlug: 'youtube' },
				innerBlocks: [],
			},
			{
				name: 'core/embed',
				attributes: { providerNameSlug: 'vimeo' },
				innerBlocks: [],
			},
		];
		const result = analyzeBlocks( blocks );
		expect( result.videoCount ).toBe( 2 );
	} );

	test( 'detects audio embed providers', () => {
		const blocks = [
			{
				name: 'core/embed',
				attributes: { providerNameSlug: 'spotify' },
				innerBlocks: [],
			},
			{
				name: 'core/embed',
				attributes: { providerNameSlug: 'soundcloud' },
				innerBlocks: [],
			},
		];
		const result = analyzeBlocks( blocks );
		expect( result.audioCount ).toBe( 2 );
	} );

	test( 'counts nested inner blocks recursively', () => {
		const blocks = [
			{
				name: 'core/group',
				attributes: {},
				innerBlocks: [
					{
						name: 'core/image',
						attributes: {},
						innerBlocks: [],
					},
					{
						name: 'core/paragraph',
						attributes: { content: 'Nested text' },
						innerBlocks: [],
					},
				],
			},
		];
		const result = analyzeBlocks( blocks );
		expect( result.imageCount ).toBe( 1 );
		expect( result.textLength ).toBe( 11 );
		expect( result.textBlockCount ).toBe( 1 );
	} );

	test( 'counts multiple text block types', () => {
		const blocks = [
			{
				name: 'core/heading',
				attributes: { content: 'Title' },
				innerBlocks: [],
			},
			{
				name: 'core/preformatted',
				attributes: { content: 'Code' },
				innerBlocks: [],
			},
			{
				name: 'core/verse',
				attributes: { content: 'Poem' },
				innerBlocks: [],
			},
			{
				name: 'core/pullquote',
				attributes: { value: 'Quote' },
				innerBlocks: [],
			},
			{
				name: 'core/list-item',
				attributes: { content: 'Item' },
				innerBlocks: [],
			},
		];
		const result = analyzeBlocks( blocks );
		expect( result.textBlockCount ).toBe( 5 );
		expect( result.textLength ).toBe( 22 ); // Title(5) + Code(4) + Poem(4) + Quote(5) + Item(4)
	} );
} );

describe( 'getSuggestedPostFormat', () => {
	test( 'returns null when format is already set', () => {
		const blocks = [ { name: 'core/image', attributes: {}, innerBlocks: [] } ];
		expect( getSuggestedPostFormat( blocks, 'image' ) ).toBeNull();
		expect( getSuggestedPostFormat( blocks, 'gallery' ) ).toBeNull();
		expect( getSuggestedPostFormat( blocks, 'aside' ) ).toBeNull();
	} );

	test( 'suggests gallery for gallery blocks with short text', () => {
		const blocks = [
			{ name: 'core/gallery', attributes: {}, innerBlocks: [] },
			{
				name: 'core/paragraph',
				attributes: { content: 'Caption' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, 'standard' );
		expect( result.format ).toBe( 'gallery' );
	} );

	test( 'suggests gallery for multiple images with short text', () => {
		const blocks = [
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
			{
				name: 'core/paragraph',
				attributes: { content: 'Photos' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'gallery' );
	} );

	test( 'suggests image for single image with short text', () => {
		const blocks = [
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
			{
				name: 'core/paragraph',
				attributes: { content: 'My photo' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'image' );
	} );

	test( 'suggests video for video with short text', () => {
		const blocks = [
			{ name: 'core/video', attributes: {}, innerBlocks: [] },
			{
				name: 'core/paragraph',
				attributes: { content: 'Watch this' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'video' );
	} );

	test( 'suggests video for YouTube embed', () => {
		const blocks = [
			{
				name: 'core/embed',
				attributes: { providerNameSlug: 'youtube' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'video' );
	} );

	test( 'suggests audio for audio with short text', () => {
		const blocks = [
			{ name: 'core/audio', attributes: {}, innerBlocks: [] },
			{
				name: 'core/paragraph',
				attributes: { content: 'Listen' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'audio' );
	} );

	test( 'suggests audio for Spotify embed', () => {
		const blocks = [
			{
				name: 'core/embed',
				attributes: { providerNameSlug: 'spotify' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'audio' );
	} );

	test( 'suggests status for very short text-only posts', () => {
		const blocks = [
			{
				name: 'core/paragraph',
				attributes: { content: 'Just a quick thought.' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'status' );
	} );

	test( 'does not suggest status when text is too long', () => {
		const blocks = [
			{
				name: 'core/paragraph',
				attributes: { content: 'A'.repeat( 300 ) },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result ).toBeNull();
	} );

	test( 'does not suggest status when there are too many text blocks', () => {
		const blocks = [
			{
				name: 'core/paragraph',
				attributes: { content: 'One' },
				innerBlocks: [],
			},
			{
				name: 'core/paragraph',
				attributes: { content: 'Two' },
				innerBlocks: [],
			},
			{
				name: 'core/paragraph',
				attributes: { content: 'Three' },
				innerBlocks: [],
			},
			{
				name: 'core/paragraph',
				attributes: { content: 'Four' },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result ).toBeNull();
	} );

	test( 'returns null for long text articles', () => {
		const blocks = [
			{
				name: 'core/image',
				attributes: {},
				innerBlocks: [],
			},
			{
				name: 'core/paragraph',
				attributes: { content: 'A'.repeat( 600 ) },
				innerBlocks: [],
			},
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result ).toBeNull();
	} );

	test( 'returns null when no blocks present', () => {
		expect( getSuggestedPostFormat( [], '' ) ).toBeNull();
	} );

	test( 'works with falsy currentFormat values', () => {
		const blocks = [ { name: 'core/image', attributes: {}, innerBlocks: [] } ];
		// All falsy values should allow suggestion.
		expect( getSuggestedPostFormat( blocks, '' ) ).not.toBeNull();
		expect( getSuggestedPostFormat( blocks, null ) ).not.toBeNull();
		expect( getSuggestedPostFormat( blocks, undefined ) ).not.toBeNull();
		expect( getSuggestedPostFormat( blocks, 'standard' ) ).not.toBeNull();
	} );

	test( 'suggests video over image for mixed media', () => {
		const blocks = [
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
			{ name: 'core/video', attributes: {}, innerBlocks: [] },
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'video' );
	} );

	test( 'suggests audio over image for mixed media', () => {
		const blocks = [
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
			{ name: 'core/audio', attributes: {}, innerBlocks: [] },
		];
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'audio' );
	} );

	test( 'gallery has higher priority than image', () => {
		const blocks = [
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
			{ name: 'core/image', attributes: {}, innerBlocks: [] },
		];
		// Multiple images should suggest gallery, not image.
		const result = getSuggestedPostFormat( blocks, '' );
		expect( result.format ).toBe( 'gallery' );
	} );
} );
