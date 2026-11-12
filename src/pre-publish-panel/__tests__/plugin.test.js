/**
 * @jest-environment jsdom
 */

// Set up the global options before importing utils, so NOTE_LENGTH picks up the value.
window._activityPubOptions = { noteLength: 500 };

import { render, screen } from '@testing-library/react';
import { registerPlugin } from '@wordpress/plugins';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { analyzeBlocks, getSuggestedPostFormat } from '../utils';
import '../plugin';

// The editor state the panels read, changed per test.
let mockEditorState;

jest.mock( '@wordpress/plugins', () => ( { registerPlugin: jest.fn() } ) );

jest.mock( '@wordpress/editor', () => ( {
	PluginPrePublishPanel: ( { title, children } ) => (
		<div data-testid="pre-publish-panel">
			<h2>{ title }</h2>
			{ children }
		</div>
	),
	PostTaxonomiesFlatTermSelector: ( { slug } ) => <div data-testid="term-selector" data-slug={ slug } />,
	store: 'core/editor',
} ) );

jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, onClick } ) => <button onClick={ onClick }>{ children }</button>,
} ) );

jest.mock( '@wordpress/block-editor', () => ( { store: 'core/block-editor' } ) );

jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( { editPost: jest.fn() } ),
	useSelect: ( callback ) =>
		callback( ( store ) => {
			if ( 'core/block-editor' === store ) {
				return { getBlocks: () => mockEditorState.blocks ?? [] };
			}

			if ( 'core' === store ) {
				return { getThemeSupports: () => ( { formats: mockEditorState.themeFormats ?? [] } ) };
			}

			return {
				getEditedPostAttribute: ( attribute ) => mockEditorState.attributes[ attribute ],
				getSuggestedPostFormat: () => mockEditorState.coreSuggestion ?? null,
			};
		} ),
} ) );

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

describe( 'Tags suggestion', () => {
	const Panels = () => {
		const { render: Render } = registerPlugin.mock.calls[ 0 ][ 1 ];
		return <Render />;
	};

	const coreText =
		'%s help users and search engines navigate your site and find your content. Add a few keywords to describe your post.';

	beforeEach( () => {
		window._activityPubOptions = { noteLength: 500 };
		mockEditorState = { attributes: {} };
	} );

	test( 'marks the panels with the asterism icon', () => {
		const { container } = render( registerPlugin.mock.calls[ 0 ][ 1 ].icon );

		expect( container.querySelector( 'svg text' ) ).toHaveTextContent( '⁂' );
	} );

	test( 'renders no panel of its own', () => {
		render( <Panels /> );

		expect( screen.queryByTestId( 'pre-publish-panel' ) ).toBeNull();
	} );

	test( 'adds a note on hashtags to core suggestion text', () => {
		// Core's call, through the default i18n instance.
		expect(
			sprintf(
				/* translators: %s: The taxonomy name. */
				__(
					'%s help users and search engines navigate your site and find your content. Add a few keywords to describe your post.'
				),
				'Tags'
			)
		).toBe(
			'Tags help users and search engines navigate your site and find your content. Add a few keywords to describe your post. Tags are also shared as hashtags, which help people on the Fediverse find your post.'
		);
	} );

	test( 'mentions hashtags in the content when they are enabled', () => {
		window._activityPubOptions.useHashtags = true;

		expect( sprintf( applyFilters( 'i18n.gettext_default', coreText, coreText, undefined ), 'Tags' ) ).toMatch(
			/Any #hashtag you write in your post is added as a tag too\.$/
		);
	} );

	test( 'leaves other core strings alone', () => {
		expect( __( 'Add a few keywords to describe your post.' ) ).toBe( 'Add a few keywords to describe your post.' );
	} );
} );

describe( 'Post format panel', () => {
	const Panels = () => {
		const { render: Render } = registerPlugin.mock.calls[ 0 ][ 1 ];
		return <Render />;
	};

	beforeEach( () => {
		window._activityPubOptions = { noteLength: 500, objectType: 'wordpress-post-format' };
		mockEditorState = {
			attributes: { format: 'standard' },
			blocks: [ { name: 'core/image', attributes: {}, innerBlocks: [] } ],
		};
	} );

	test( 'titles a format suggestion as a suggestion', () => {
		render( <Panels /> );

		expect( screen.getByRole( 'heading' ) ).toHaveTextContent( 'Suggestion:Use a post format' );
	} );

	test( 'titles the confirmation of a set format as the post format', () => {
		mockEditorState.attributes.format = 'image';
		render( <Panels /> );

		expect( screen.getByRole( 'heading' ) ).toHaveTextContent( /^Post format$/ );
	} );

	test( 'leaves the suggestion to core when core suggests a format', () => {
		mockEditorState.coreSuggestion = 'image';
		mockEditorState.themeFormats = [ 'image' ];
		render( <Panels /> );

		expect( screen.queryByTestId( 'pre-publish-panel' ) ).toBeNull();
	} );

	test( "suggests a format when the theme does not support core's suggestion", () => {
		mockEditorState.coreSuggestion = 'image';
		render( <Panels /> );

		expect( screen.getByRole( 'heading' ) ).toHaveTextContent( 'Suggestion:Use a post format' );
	} );
} );
