/**
 * @jest-environment jsdom
 */

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Pagination } from '../pagination';

// Mock @wordpress/i18n
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
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
