import { getFeedViewUpdate, normalizeFieldOrder } from '../utils';

const FIELDS = [ { id: 'a' }, { id: 'b' }, { id: 'c' }, { id: 'd' } ];

describe( 'normalizeFieldOrder', () => {
	it( 'should return the view unchanged when it has no fields', () => {
		const view = { type: 'list' } as never;
		expect( normalizeFieldOrder( view, FIELDS ) ).toBe( view );
	} );

	it( 'should sort fields into the canonical order', () => {
		const view = { type: 'list', fields: [ 'c', 'a', 'd', 'b' ] } as never;
		const result = normalizeFieldOrder( view, FIELDS ) as { fields: string[] };
		expect( result.fields ).toEqual( [ 'a', 'b', 'c', 'd' ] );
	} );

	it( 'should place unknown fields at the end', () => {
		const view = { type: 'list', fields: [ 'unknown', 'b', 'a' ] } as never;
		const result = normalizeFieldOrder( view, FIELDS ) as { fields: string[] };
		expect( result.fields ).toEqual( [ 'a', 'b', 'unknown' ] );
	} );

	it( 'should not mutate the original view fields array', () => {
		const original = [ 'c', 'a' ];
		const view = { type: 'list', fields: original } as never;
		normalizeFieldOrder( view, FIELDS );
		expect( original ).toEqual( [ 'c', 'a' ] );
	} );

	it( 'should preserve other view properties', () => {
		const view = { type: 'table', page: 2, fields: [ 'b', 'a' ] } as never;
		const result = normalizeFieldOrder( view, FIELDS ) as { type: string; page: number; fields: string[] };
		expect( result.type ).toBe( 'table' );
		expect( result.page ).toBe( 2 );
	} );
} );

describe( 'getFeedViewUpdate', () => {
	it( 'should reset pagination when search changes', () => {
		const currentView = {
			type: 'list',
			search: '',
			page: 4,
			perPage: 20,
			startPosition: 61,
			filters: [],
		} as never;
		const updatedView = {
			type: 'list',
			search: 'activitypub',
			page: 4,
			perPage: 20,
			startPosition: 61,
			filters: [],
		} as never;

		const result = getFeedViewUpdate( currentView, updatedView ) as {
			page: number;
			startPosition: number;
		};

		expect( result.page ).toBe( 1 );
		expect( result.startPosition ).toBe( 1 );
	} );

	it( 'should reset pagination when filters change', () => {
		const currentView = {
			type: 'list',
			search: '',
			page: 3,
			startPosition: 41,
			filters: [],
		} as never;
		const updatedView = {
			type: 'list',
			search: '',
			page: 3,
			startPosition: 41,
			filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 7 ] } ],
		} as never;

		const result = getFeedViewUpdate( currentView, updatedView ) as {
			page: number;
			startPosition: number;
		};

		expect( result.page ).toBe( 1 );
		expect( result.startPosition ).toBe( 1 );
	} );

	it( 'should map infinite scroll start position to a page without resetting stable searches', () => {
		const currentView = {
			type: 'list',
			search: 'activitypub',
			page: 1,
			perPage: 20,
			startPosition: 1,
			filters: [],
		} as never;
		const updatedView = {
			type: 'list',
			search: 'activitypub',
			page: 1,
			perPage: 20,
			startPosition: 41,
			filters: [],
		} as never;

		const result = getFeedViewUpdate( currentView, updatedView ) as {
			page: number;
			startPosition: number;
		};

		expect( result.page ).toBe( 3 );
		expect( result.startPosition ).toBe( 41 );
	} );
} );
