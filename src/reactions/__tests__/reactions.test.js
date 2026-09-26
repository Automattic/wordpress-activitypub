import { render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { FacepileRow, Reactions } from '../reactions';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// Suppress console warnings for testing
const originalError = console.error;

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

describe( 'Reactions', () => {
	const reactions = {
		likes: {
			label: '1 like',
			items: [ { avatar: 'user1.jpg', url: 'https://example.com/user1', name: 'User One' } ],
		},
	};

	const fallback = {
		likes: {
			label: 'Fallback',
			items: [ { avatar: '', url: '#', name: 'Fallback User' } ],
		},
	};

	beforeEach( () => {
		window._activityPubOptions = {
			namespace: 'activitypub/1.0',
			defaultAvatarUrl: 'default.jpg',
		};
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( reactions );
	} );

	afterEach( () => {
		delete window._activityPubOptions;
		jest.clearAllMocks();
	} );

	test( 'fetches reactions for a post', async () => {
		render( <Reactions postId={ 42 } /> );

		expect( apiFetch ).toHaveBeenCalledWith( { path: '/activitypub/1.0/posts/42/reactions' } );
		await waitFor( () => expect( screen.getByRole( 'img' ).src ).toContain( 'user1.jpg' ) );
	} );

	test( 'does not fetch when reactions are provided', () => {
		render( <Reactions postId={ 42 } reactions={ reactions } /> );

		expect( apiFetch ).not.toHaveBeenCalled();
		expect( screen.getByRole( 'img' ).src ).toContain( 'user1.jpg' );
	} );

	test( 'does not fetch without a numeric post ID and shows the fallback', () => {
		render( <Reactions postId="wp_template" fallbackReactions={ fallback } /> );

		expect( apiFetch ).not.toHaveBeenCalled();
		expect( screen.getByText( 'Fallback' ) ).toBeInTheDocument();
	} );

	test( 'renders nothing without a post ID and without a fallback', () => {
		const { container } = render( <Reactions /> );

		expect( apiFetch ).not.toHaveBeenCalled();
		expect( container.firstChild ).toBeNull();
	} );

	test( 'uses the fallback when the post has no reactions yet', async () => {
		apiFetch.mockResolvedValue( { likes: { label: '0 likes', items: [] } } );

		render( <Reactions postId={ 42 } fallbackReactions={ fallback } /> );

		await waitFor( () => expect( screen.getByText( 'Fallback' ) ).toBeInTheDocument() );
	} );

	test( 'uses the fallback when the request fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'rest_invalid_param' ) );

		render( <Reactions postId={ 42 } fallbackReactions={ fallback } /> );

		await waitFor( () => expect( screen.getByText( 'Fallback' ) ).toBeInTheDocument() );
	} );
} );
