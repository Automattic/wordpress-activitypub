/**
 * WordPress dependencies
 */
import { dispatch, resolveSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { init } from '../init';

jest.mock( '@wordpress/data', () => ( { dispatch: jest.fn(), resolveSelect: jest.fn() } ) );
jest.mock( '@wordpress/boot', () => ( { store: 'wordpress/boot' } ), { virtual: true } );
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );
jest.mock( '../components/object-types', () => ( {
	objectTypeConfig: {
		Article: { label: 'Articles', icon: 'article-icon' },
		Note: { label: 'Notes & Updates', icon: 'note-icon' },
	},
} ) );

describe( 'Feed navigation registration', () => {
	const registerMenuItem = jest.fn();
	const updateMenuItem = jest.fn();
	const getEntityRecords = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		( dispatch as jest.Mock ).mockReturnValue( { registerMenuItem, updateMenuItem } );
		( resolveSelect as jest.Mock ).mockReturnValue( { getEntityRecords } );
	} );

	it( 'registers supported types under Feed in their configured order', async () => {
		getEntityRecords
			.mockResolvedValueOnce( [
				{ id: 9, name: 'Note' },
				{ id: 8, name: 'Article' },
				{ id: 10, name: 'Unknown' },
			] )
			.mockResolvedValueOnce( [] );
		await init();
		expect( registerMenuItem.mock.calls ).toEqual( [
			[
				'feed-type-8',
				{ id: 'feed-type-8', parent: 'feed', to: '/feed/type/8', label: 'Articles', icon: 'article-icon' },
			],
			[
				'feed-type-9',
				{ id: 'feed-type-9', parent: 'feed', to: '/feed/type/9', label: 'Notes & Updates', icon: 'note-icon' },
			],
		] );
	} );

	it( 'registers popular tags as a core dropdown inside Feed', async () => {
		getEntityRecords.mockResolvedValueOnce( [] ).mockResolvedValueOnce( [ { id: 12, name: 'fediverse' } ] );
		await init();
		expect( getEntityRecords ).toHaveBeenCalledWith( 'taxonomy', 'ap_tag', {
			per_page: 5,
			orderby: 'count',
			order: 'desc',
			hide_empty: true,
		} );
		expect( registerMenuItem ).toHaveBeenCalledWith(
			'feed-tags',
			expect.objectContaining( {
				parent: 'feed',
				parent_type: 'dropdown',
				label: 'Popular Tags',
			} )
		);
		expect( registerMenuItem ).toHaveBeenCalledWith( 'feed-tag-12', {
			id: 'feed-tag-12',
			parent: 'feed-tags',
			to: '/feed/tag/12',
			label: '#fediverse',
		} );
	} );

	it( 'keeps the main Feed links usable when terms are unavailable', async () => {
		getEntityRecords.mockResolvedValue( null );
		await init();
		expect( registerMenuItem ).not.toHaveBeenCalled();
		expect( updateMenuItem ).toHaveBeenCalledWith(
			'feed-all',
			expect.objectContaining( { icon: expect.anything() } )
		);
	} );
} );
