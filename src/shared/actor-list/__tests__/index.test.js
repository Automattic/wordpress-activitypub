/**
 * @jest-environment jsdom
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { Pagination } from '../pagination';
import { ActorList } from '../actor-list';

// Mock @wordpress/i18n
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

// Mock @wordpress/api-fetch
jest.mock( '@wordpress/api-fetch' );

// Mock @wordpress/url
jest.mock( '@wordpress/url', () => ( {
	addQueryArgs: ( path, args ) => {
		const params = new URLSearchParams( args ).toString();
		return `${ path }?${ params }`;
	},
} ) );

// Mock useOptions hook
jest.mock( '../../use-options', () => ( {
	useOptions: () => ( {
		namespace: 'activitypub/v1',
		defaultAvatarUrl: 'https://example.com/default-avatar.png',
		showAvatars: true,
	} ),
} ) );

describe( 'Pagination', () => {
	test( 'returns null when pages <= 1', () => {
		const { container } = render( <Pagination page={ 1 } pages={ 1 } setPage={ jest.fn() } /> );
		expect( container.firstChild ).toBeNull();
	} );

	test( 'renders pagination when pages > 1', () => {
		render( <Pagination page={ 1 } pages={ 3 } setPage={ jest.fn() } /> );
		expect( screen.getByRole( 'navigation' ) ).toBeInTheDocument();
		expect( screen.getByText( '1 / 3' ) ).toBeInTheDocument();
	} );

	test( 'disables previous link on first page', () => {
		render( <Pagination page={ 1 } pages={ 3 } setPage={ jest.fn() } /> );
		const prevLink = screen.getByLabelText( 'Previous page' );
		expect( prevLink ).toHaveAttribute( 'aria-disabled', 'true' );
	} );

	test( 'disables next link on last page', () => {
		render( <Pagination page={ 3 } pages={ 3 } setPage={ jest.fn() } /> );
		const nextLink = screen.getByLabelText( 'Next page' );
		expect( nextLink ).toHaveAttribute( 'aria-disabled', 'true' );
	} );

	test( 'calls setPage when clicking previous', async () => {
		const user = userEvent.setup();
		const setPage = jest.fn();
		render( <Pagination page={ 2 } pages={ 3 } setPage={ setPage } /> );

		await user.click( screen.getByLabelText( 'Previous page' ) );
		expect( setPage ).toHaveBeenCalledWith( 1 );
	} );

	test( 'calls setPage when clicking next', async () => {
		const user = userEvent.setup();
		const setPage = jest.fn();
		render( <Pagination page={ 2 } pages={ 3 } setPage={ setPage } /> );

		await user.click( screen.getByLabelText( 'Next page' ) );
		expect( setPage ).toHaveBeenCalledWith( 3 );
	} );

	test( 'does not call setPage when clicking disabled previous', async () => {
		const user = userEvent.setup();
		const setPage = jest.fn();
		render( <Pagination page={ 1 } pages={ 3 } setPage={ setPage } /> );

		await user.click( screen.getByLabelText( 'Previous page' ) );
		expect( setPage ).not.toHaveBeenCalled();
	} );

	test( 'does not call setPage when clicking disabled next', async () => {
		const user = userEvent.setup();
		const setPage = jest.fn();
		render( <Pagination page={ 3 } pages={ 3 } setPage={ setPage } /> );

		await user.click( screen.getByLabelText( 'Next page' ) );
		expect( setPage ).not.toHaveBeenCalled();
	} );

	test( 'uses custom navLabel', () => {
		render( <Pagination page={ 1 } pages={ 3 } setPage={ jest.fn() } navLabel="Follower navigation" /> );
		expect( screen.getByText( 'Follower navigation' ) ).toBeInTheDocument();
	} );
} );

describe( 'ActorList', () => {
	const mockActors = [
		{
			name: 'John Doe',
			webfinger: 'john@example.com',
			url: 'https://example.com/@john',
			icon: { url: 'https://example.com/john.jpg' },
		},
		{
			name: 'Jane Smith',
			webfinger: 'jane@example.org',
			url: 'https://example.org/@jane',
			icon: { url: 'https://example.org/jane.jpg' },
		},
	];

	beforeEach( () => {
		apiFetch.mockReset();
	} );

	test( 'uses initialData on page 1 without fetching', async () => {
		const initialData = {
			items: mockActors,
			total: 2,
		};

		render(
			<ActorList selectedUser="1" perPage={ 10 } order="desc" endpoint="followers" initialData={ initialData } />
		);

		// Should render actors from initialData
		await waitFor( () => {
			expect( screen.getByText( 'John Doe' ) ).toBeInTheDocument();
			expect( screen.getByText( 'Jane Smith' ) ).toBeInTheDocument();
		} );

		// Should not have called apiFetch
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	test( 'fetches with correct REST path', async () => {
		apiFetch.mockResolvedValue( {
			orderedItems: mockActors,
			totalItems: 2,
		} );

		render( <ActorList selectedUser="42" perPage={ 5 } order="asc" endpoint="following" /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: expect.stringContaining( '/activitypub/v1/actors/42/following' ),
			} );
		} );

		// Verify query args
		const callPath = apiFetch.mock.calls[ 0 ][ 0 ].path;
		expect( callPath ).toContain( 'per_page=5' );
		expect( callPath ).toContain( 'order=asc' );
		expect( callPath ).toContain( 'page=1' );
		expect( callPath ).toContain( 'context=full' );
	} );

	test( 'converts "blog" selectedUser to 0', async () => {
		apiFetch.mockResolvedValue( {
			orderedItems: [],
			totalItems: 0,
		} );

		render( <ActorList selectedUser="blog" perPage={ 10 } order="desc" endpoint="followers" /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: expect.stringContaining( '/activitypub/v1/actors/0/followers' ),
			} );
		} );
	} );

	test( 'renders empty state on fetch failure', async () => {
		apiFetch.mockRejectedValue( new Error( 'Network error' ) );

		render(
			<ActorList
				selectedUser="1"
				perPage={ 10 }
				order="desc"
				endpoint="followers"
				emptyMessage="No followers found."
			/>
		);

		await waitFor( () => {
			expect( screen.getByText( 'No followers found.' ) ).toBeInTheDocument();
		} );
	} );

	test( 'renders empty state when no actors', async () => {
		apiFetch.mockResolvedValue( {
			orderedItems: [],
			totalItems: 0,
		} );

		render(
			<ActorList
				selectedUser="1"
				perPage={ 10 }
				order="desc"
				endpoint="followers"
				emptyMessage="No results found."
			/>
		);

		await waitFor( () => {
			expect( screen.getByText( 'No results found.' ) ).toBeInTheDocument();
		} );
	} );

	test( 'renders actors when data is fetched', async () => {
		apiFetch.mockResolvedValue( {
			orderedItems: mockActors,
			totalItems: 2,
		} );

		render( <ActorList selectedUser="1" perPage={ 10 } order="desc" endpoint="followers" /> );

		await waitFor( () => {
			expect( screen.getByText( 'John Doe' ) ).toBeInTheDocument();
			expect( screen.getByText( 'Jane Smith' ) ).toBeInTheDocument();
		} );
	} );

	test( 'shows pagination when total exceeds perPage', async () => {
		apiFetch.mockResolvedValue( {
			orderedItems: mockActors,
			totalItems: 25,
		} );

		render( <ActorList selectedUser="1" perPage={ 10 } order="desc" endpoint="followers" /> );

		await waitFor( () => {
			expect( screen.getByRole( 'navigation' ) ).toBeInTheDocument();
			expect( screen.getByText( '1 / 3' ) ).toBeInTheDocument();
		} );
	} );

	test( 'hides pagination when total is less than perPage', async () => {
		apiFetch.mockResolvedValue( {
			orderedItems: mockActors,
			totalItems: 2,
		} );

		render( <ActorList selectedUser="1" perPage={ 10 } order="desc" endpoint="followers" /> );

		await waitFor( () => {
			expect( screen.getByText( 'John Doe' ) ).toBeInTheDocument();
		} );

		expect( screen.queryByRole( 'navigation' ) ).not.toBeInTheDocument();
	} );
} );
