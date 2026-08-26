/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { render } from '@testing-library/react';
import type { ReactNode } from 'react';
import { titleField } from '../index';
import type { FeedPost } from '../../../../types';

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text: string ) => text,
} ) );

/*
 * `@wordpress/html-entities` is deliberately not mocked: this component decodes, and the
 * point of the test below is that decoding is safe here only because the result is
 * rendered as a React child rather than through innerHTML.
 */

const createMockFeedPost = ( title: string ): FeedPost =>
	( { id: 1, title: { rendered: title } } ) as unknown as FeedPost;

const renderTitle = ( title: string ): HTMLElement => {
	const Render = titleField.render as ( props: { item: FeedPost } ) => ReactNode;
	const { container } = render( <Render item={ createMockFeedPost( title ) } /> );
	return container;
};

describe( 'titleField', () => {
	it( 'renders the title as text', () => {
		expect( renderTitle( 'Hello world' ).textContent ).toBe( 'Hello world' );
	} );

	/*
	 * This component decodes entities and unescapes backslashes, which would both be
	 * unsafe if the result were ever handed to `dangerouslySetInnerHTML`. It is safe
	 * only because React escapes children as text. Pin that invariant so a refactor to
	 * innerHTML breaks CI instead of shipping.
	 */
	it( 'escapes markup rather than rendering it', () => {
		const container = renderTitle(
			'&lt;iframe srcdoc="&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;"&gt;&lt;/iframe&gt;'
		);

		expect( container.querySelector( 'iframe' ) ).toBeNull();
		expect( container.textContent ).toContain( '<iframe' );
	} );

	it( 'keeps backslashes rather than unescaping them', () => {
		expect( renderTitle( 'C:\\path\\to\\file' ).textContent ).toBe( 'C:\\path\\to\\file' );
	} );

	it( 'does not render an element for markup supplied as real tags', () => {
		const container = renderTitle( '<img src=x onerror="alert(1)">' );

		expect( container.querySelector( 'img' ) ).toBeNull();
	} );
} );
