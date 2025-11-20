/**
 * @jest-environment jsdom
 */

import { render, screen, fireEvent } from '@testing-library/react';
import { metadataField } from '../index';
import { SettingsProvider } from '../../../../contexts/settings-context';
import type { FeedPost } from '../../../types';
import type { SocialWebSettings } from '../../../../types';

// Mock WordPress dependencies
jest.mock( '@wordpress/i18n', () => ( {
	__: ( text: string ) => text,
} ) );

jest.mock( '@wordpress/html-entities', () => ( {
	decodeEntities: ( text: string ) => text,
} ) );

const mockSettings: SocialWebSettings = {
	adminUrl: 'https://example.com/wp-admin',
	defaultAvatar: 'https://example.com/default-avatar.jpg',
	nonce: 'test-nonce',
	restUrl: 'https://example.com/wp-json',
	siteTitle: 'Test Site',
};

const createMockFeedPost = ( overrides?: Partial< FeedPost > ): FeedPost => ( {
	id: 1,
	date: '2024-01-15T12:00:00',
	actor_info: {
		name: 'John Doe',
		icon: 'https://example.com/avatar.jpg',
	},
	title: { rendered: 'Test Post' },
	content: { rendered: 'Test content' },
	excerpt: { rendered: 'Test excerpt' },
	link: 'https://example.com/post/1',
	...overrides,
} );

describe( 'metadataField', () => {
	describe( 'getValue', () => {
		it( 'should return formatted metadata string', () => {
			const post = createMockFeedPost();
			const value = metadataField.getValue?.( { item: post } );
			expect( value ).toBe( 'John Doe · 1/15/2024' );
		} );

		it( 'should handle missing actor name', () => {
			const post = createMockFeedPost( {
				actor_info: undefined,
			} );
			const value = metadataField.getValue?.( { item: post } );
			expect( value ).toMatch( /·/ ); // Should still have separator
		} );

		it( 'should handle missing date', () => {
			const post = createMockFeedPost( {
				date: '',
			} );
			const value = metadataField.getValue?.( { item: post } );
			expect( value ).toBe( 'John Doe · ' );
		} );
	} );

	describe( 'render', () => {
		const renderMetadataField = ( post: FeedPost ) => {
			const Wrapper = ( { children }: { children: React.ReactNode } ) => (
				<SettingsProvider settings={ mockSettings }>{ children }</SettingsProvider>
			);

			const RenderComponent = metadataField.render;
			if ( ! RenderComponent ) {
				throw new Error( 'render function not defined' );
			}

			return render( <RenderComponent item={ post } />, { wrapper: Wrapper } );
		};

		it( 'should render avatar with actor icon when available', () => {
			const post = createMockFeedPost();
			renderMetadataField( post );

			const avatar = screen.getByAltText( 'John Doe' ) as HTMLImageElement;
			expect( avatar ).toBeInTheDocument();
			expect( avatar.src ).toBe( 'https://example.com/avatar.jpg' );
		} );

		it( 'should use default avatar when actor icon is missing', () => {
			const post = createMockFeedPost( {
				actor_info: {
					name: 'John Doe',
					icon: undefined,
				},
			} );
			renderMetadataField( post );

			const avatar = screen.getByAltText( 'John Doe' ) as HTMLImageElement;
			expect( avatar ).toBeInTheDocument();
			expect( avatar.src ).toBe( mockSettings.defaultAvatar );
		} );

		it( 'should use default avatar when actor_info is missing', () => {
			const post = createMockFeedPost( {
				actor_info: undefined,
			} );
			renderMetadataField( post );

			const avatar = screen.getByAltText( 'Unknown author' ) as HTMLImageElement;
			expect( avatar ).toBeInTheDocument();
			expect( avatar.src ).toBe( mockSettings.defaultAvatar );
		} );

		it( 'should fallback to default avatar on image load error', () => {
			const post = createMockFeedPost();
			renderMetadataField( post );

			const avatar = screen.getByAltText( 'John Doe' ) as HTMLImageElement;
			expect( avatar.src ).toBe( 'https://example.com/avatar.jpg' );

			// Simulate image load error
			fireEvent.error( avatar );

			expect( avatar.src ).toBe( mockSettings.defaultAvatar );
		} );

		it( 'should render author name', () => {
			const post = createMockFeedPost();
			renderMetadataField( post );

			expect( screen.getByText( 'John Doe' ) ).toBeInTheDocument();
		} );

		it( 'should render unknown author when actor_info is missing', () => {
			const post = createMockFeedPost( {
				actor_info: undefined,
			} );
			renderMetadataField( post );

			expect( screen.getByText( 'Unknown author' ) ).toBeInTheDocument();
		} );

		it( 'should render formatted date', () => {
			const post = createMockFeedPost( {
				date: '2024-01-15T12:00:00',
			} );
			renderMetadataField( post );

			// Date format: "Jan 15, 2024"
			expect( screen.getByText( /Jan 15, 2024/i ) ).toBeInTheDocument();
		} );

		it( 'should not render date separator when date is missing', () => {
			const post = createMockFeedPost( {
				date: '',
			} );
			renderMetadataField( post );

			// Should render author but not the separator or date
			expect( screen.getByText( 'John Doe' ) ).toBeInTheDocument();
			expect( screen.queryByText( '·' ) ).not.toBeInTheDocument();
		} );

		it( 'should have correct CSS class on avatar', () => {
			const post = createMockFeedPost();
			renderMetadataField( post );

			const avatar = screen.getByAltText( 'John Doe' );
			expect( avatar ).toHaveClass( 'activitypub-feed-avatar' );
		} );

		it( 'should have correct CSS class on container', () => {
			const post = createMockFeedPost();
			const { container } = renderMetadataField( post );

			const metaDiv = container.querySelector( '.activitypub-feed-post-meta' );
			expect( metaDiv ).toBeInTheDocument();
		} );
	} );
} );
