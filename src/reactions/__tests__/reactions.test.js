import { render, screen } from '@testing-library/react';
import { FacepileRow } from '../reactions';

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
} );
