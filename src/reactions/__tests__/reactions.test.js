import { render, screen, waitFor } from '@testing-library/react';
import { FacepileRow, Reactions } from '../reactions';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

import apiFetch from '@wordpress/api-fetch';

// Suppress console warnings for testing
const originalError = console.error;

const RESPONSE_WITH_REACTIONS = {
	likes: {
		label: '1 like',
		items: [ { name: 'User One', url: 'https://example.com/user1', avatar: 'user1.jpg' } ],
	},
};

describe( 'Reactions fetch gating', () => {
	beforeAll( () => {
		console.error = jest.fn();
	} );

	afterAll( () => {
		console.error = originalError;
	} );

	beforeEach( () => {
		window._activityPubOptions = {
			defaultAvatarUrl: 'default.jpg',
			namespace: 'activitypub/1.0',
		};
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( RESPONSE_WITH_REACTIONS );
	} );

	afterEach( () => {
		delete window._activityPubOptions;
		jest.clearAllMocks();
	} );

	test( 'fetches reactions for a publicly queryable post', async () => {
		render( <Reactions postId={ 42 } publiclyQueryable={ true } /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/activitypub/1.0/posts/42/reactions',
			} );
		} );
	} );

	test( 'does not fetch reactions for a page', () => {
		render( <Reactions postId={ 42 } publiclyQueryable={ false } /> );

		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	test.each( [ 'local', 'private' ] )( 'does not fetch reactions for %s visibility posts', () => {
		render( <Reactions postId={ 42 } publiclyQueryable={ false } /> );

		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	test( 'does not fetch while the post data is still loading', () => {
		render( <Reactions postId={ 42 } publiclyQueryable={ null } /> );

		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	test( 'does not fetch without a numeric post ID', () => {
		render( <Reactions postId={ undefined } publiclyQueryable={ true } /> );

		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	test( 'uses the fallback when the post is not publicly queryable', () => {
		const fallback = { likes: { label: 'Fallback', items: [ { name: 'Fallback User', url: '#', avatar: '' } ] } };
		const { container } = render(
			<Reactions postId={ 42 } publiclyQueryable={ false } fallbackReactions={ fallback } />
		);

		expect( container.querySelector( '.activitypub-reactions' ) ).toBeTruthy();
		expect( screen.getByText( 'Fallback' ) ).toBeInTheDocument();
	} );

	test( 'renders nothing when the post is not publicly queryable and no fallback exists', () => {
		const { container } = render( <Reactions postId={ 42 } publiclyQueryable={ false } /> );

		expect( container.firstChild ).toBeNull();
	} );
} );

describe( 'FacepileRow', () => {
	beforeAll( () => {
		console.error = jest.fn();
	} );

	afterAll( () => {
		console.error = originalError;
	} );
	const mockReactions = [
		{
			avatar: 'user1.jpg',
			url: 'https://example.com/user1',
			name: 'User One',
		},
		{
			avatar: 'user2.jpg',
			url: 'https://example.com/user2',
			name: 'User Two',
		},
	];

	beforeEach( () => {
		// Mock window._activityPubOptions for useOptions hook
		window._activityPubOptions = {
			defaultAvatarUrl: 'default.jpg',
		};
	} );

	afterEach( () => {
		delete window._activityPubOptions;
		jest.clearAllMocks();
	} );

	test( 'renders reaction avatars', () => {
		render( <FacepileRow reactions={ mockReactions } /> );

		const avatars = screen.getAllByRole( 'img' );
		expect( avatars ).toHaveLength( 2 );
		expect( avatars[ 0 ].src ).toContain( 'user1.jpg' );
		expect( avatars[ 1 ].src ).toContain( 'user2.jpg' );
	} );

	test( 'creates clickable links to user profiles', () => {
		render( <FacepileRow reactions={ mockReactions } /> );

		const links = screen.getAllByRole( 'link' );
		expect( links ).toHaveLength( 2 );
		expect( links[ 0 ].href ).toBe( 'https://example.com/user1' );
		expect( links[ 1 ].href ).toBe( 'https://example.com/user2' );
	} );

	test( 'uses default avatar when reaction avatar is missing', () => {
		const reactionsWithoutAvatar = [
			{
				url: 'https://example.com/user3',
				name: 'User Three',
			},
		];

		render( <FacepileRow reactions={ reactionsWithoutAvatar } /> );

		const avatar = screen.getByRole( 'img' );
		expect( avatar.src ).toContain( 'default.jpg' );
	} );

	test( 'renders empty list when no reactions provided', () => {
		render( <FacepileRow reactions={ [] } /> );

		const list = screen.getByRole( 'list' );
		expect( list.children ).toHaveLength( 0 );
	} );

	test( 'renders avatars when displayStyle is facepile', () => {
		render( <FacepileRow reactions={ mockReactions } displayStyle="facepile" /> );

		const avatars = screen.getAllByRole( 'img' );
		expect( avatars ).toHaveLength( 2 );
	} );

	test( 'returns null when displayStyle is compact', () => {
		const { container } = render( <FacepileRow reactions={ mockReactions } displayStyle="compact" /> );

		expect( container.firstChild ).toBeNull();
	} );

	test( 'renders avatars when displayStyle is undefined (default)', () => {
		render( <FacepileRow reactions={ mockReactions } /> );

		const avatars = screen.getAllByRole( 'img' );
		expect( avatars ).toHaveLength( 2 );
	} );
} );
