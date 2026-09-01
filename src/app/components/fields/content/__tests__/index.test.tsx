/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { render } from '@testing-library/react';
import type { ReactNode } from 'react';
import { contentField } from '../index';
import type { FeedPost } from '../../../../types';

// Mock WordPress dependencies.
jest.mock( '@wordpress/i18n', () => ( {
	__: ( text: string ) => text,
} ) );

/*
 * `@wordpress/html-entities` and `@wordpress/dom` are deliberately NOT mocked. These tests
 * exercise the interaction between the real `decodeEntities()` and the real `safeHTML()`, so a
 * stubbed `decodeEntities` would make them pass whether or not the behaviour is correct.
 */

let mockObjectTypeName: string | null = 'Note';

jest.mock( '../../../../contexts/object-type-context', () => ( {
	useObjectType: () => ( {
		getObjectTypeName: () => mockObjectTypeName,
		isLoading: false,
	} ),
} ) );

const createMockFeedPost = ( content: string ): FeedPost =>
	( {
		id: 1,
		date: '2024-01-15T12:00:00',
		date_gmt: '2024-01-15T12:00:00',
		modified: '2024-01-15T12:00:00',
		modified_gmt: '2024-01-15T12:00:00',
		slug: 'test-post',
		status: 'publish',
		type: 'ap_post',
		guid: { rendered: 'https://example.com/?p=1' },
		comment_status: 'open',
		ping_status: 'open',
		author: 1,
		ap_object_type: [ 1 ],
		title: { rendered: 'Test' },
		excerpt: { rendered: '' },
		content: { rendered: content },
	} ) as unknown as FeedPost;

const renderContent = ( content: string ): HTMLElement => {
	const Render = contentField.render as ( props: { item: FeedPost } ) => ReactNode;
	const { container } = render( <Render item={ createMockFeedPost( content ) } /> );
	return container;
};

describe( 'contentField', () => {
	beforeEach( () => {
		mockObjectTypeName = 'Note';
	} );

	describe( 'security', () => {
		/*
		 * A remote actor controls `content`. `Sanitize::content()` sanitizes it
		 * on it server-side, which leaves valid entities untouched, so markup the server rejected
		 * is stored as inert text. Decoding client-side would turn that text back into markup, and
		 * `safeHTML()` is not a substitute: it strips `<script>` elements and `on*` attributes and
		 * nothing else. The stored value must be rendered without any decoding pass.
		 */
		it( 'does not revive an entity-encoded iframe srcdoc payload', () => {
			const stored =
				'<p>&lt;iframe srcdoc="&amp;lt;script&amp;gt;alert(document.domain)&amp;lt;/script&amp;gt;"&gt;&lt;/iframe&gt;</p>';

			const container = renderContent( stored );

			expect( container.querySelector( 'iframe' ) ).toBeNull();
			expect( container.textContent ).toContain( '<iframe' );
		} );

		it( 'does not revive an entity-encoded script tag', () => {
			const container = renderContent( '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>' );

			expect( container.querySelector( 'script' ) ).toBeNull();
			expect( container.textContent ).toContain( '<script>' );
		} );

		it( 'does not revive entity-encoded event-handler markup', () => {
			const container = renderContent( '<p>&lt;img src=x onerror="alert(1)"&gt;</p>' );

			expect( container.querySelector( 'img' ) ).toBeNull();
		} );

		it( 'strips script elements that reach it as real markup', () => {
			const container = renderContent( '<p>hi</p><script>alert(1)</script>' );

			expect( container.querySelector( 'script' ) ).toBeNull();
		} );
	} );

	describe( 'rendering', () => {
		it( 'renders server-sanitised markup for Notes', () => {
			const container = renderContent( '<p>Hello <strong>world</strong></p>' );

			expect( container.querySelector( 'strong' ) ).not.toBeNull();
			expect( container.textContent ).toContain( 'Hello world' );
		} );

		it( 'renders entities as the literal characters they encode', () => {
			const container = renderContent( '<p>Tom &amp; Jerry &lt;3</p>' );

			expect( container.textContent ).toContain( 'Tom & Jerry <3' );
		} );

		/*
		 * `stripHTML()` returns `textContent`, which the parser has already decoded.
		 * A second `decodeEntities()` pass collapsed a literal `&` (stored as `&amp;amp;`)
		 * into nothing, so this fails if that pass comes back.
		 */
		it( 'decodes the excerpt exactly once', () => {
			mockObjectTypeName = 'Article';

			const container = renderContent( '<p>Tom &amp;amp; Jerry</p>' );

			expect( container.textContent ).toContain( 'Tom &amp; Jerry' );
		} );

		it( 'falls back to a plain-text excerpt for non-Note types', () => {
			mockObjectTypeName = 'Article';

			const container = renderContent( '<p>Hello <strong>world</strong></p>' );

			expect( container.querySelector( 'strong' ) ).toBeNull();
			expect( container.textContent ).toContain( 'Hello world' );
		} );
	} );
} );
