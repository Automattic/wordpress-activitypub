import { DEFAULT_VIEW, getFeedView, getFeedViewUpdate, normalizeFieldOrder, viewToQuery } from '../utils';

describe( 'getFeedView', () => {
	it( 'keeps the main feed configurable', () => {
		expect( getFeedView( DEFAULT_VIEW, {} ) ).toBe( DEFAULT_VIEW );
	} );
	it.each( [
		[ 'type', 'ap_object_type', 'is', 7 ],
		[ 'tag', 'ap_tag', 'isAny', [ 7 ] ],
	] )( 'uses an editable filter for %s shortcuts', ( taxonomy, field, operator, value ) => {
		expect( getFeedView( DEFAULT_VIEW, { taxonomy: taxonomy as string, termId: '7' } ) ).toEqual( {
			...DEFAULT_VIEW,
			filters: [ { field, operator, value } ],
		} );
	} );
	it( 'replaces a saved tag filter but preserves other filters', () => {
		const view = {
			...DEFAULT_VIEW,
			filters: [
				{ field: 'ap_tag', operator: 'isAny' as const, value: [ 3 ] },
				{ field: 'ap_object_type', operator: 'is' as const, value: 8 },
			],
		};
		expect( getFeedView( view, { taxonomy: 'tag', termId: '7' } ).filters ).toEqual( [
			view.filters[ 1 ],
			{ field: 'ap_tag', operator: 'isAny', value: [ 7 ] },
		] );
		expect( view.filters[ 0 ].value ).toEqual( [ 3 ] );
	} );
} );

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

describe( 'viewToQuery', () => {
	it( 'should map pagination, ordering and search to REST query args', () => {
		const view = {
			...DEFAULT_VIEW,
			perPage: 5,
			page: 2,
			sort: { field: 'title', direction: 'asc' as const },
			search: 'hello',
		};

		const query = viewToQuery( view, 9 );

		expect( query.per_page ).toBe( 5 );
		expect( query.page ).toBe( 2 );
		expect( query.orderby ).toBe( 'title' );
		expect( query.order ).toBe( 'asc' );
		expect( query.search ).toBe( 'hello' );
		expect( query.user_id ).toBe( 9 );
	} );

	it( 'should fall back to the defaults for what the view leaves out', () => {
		const view = { ...DEFAULT_VIEW, perPage: undefined, page: undefined, sort: undefined, search: undefined };

		const query = viewToQuery( view as never, null );

		expect( query.per_page ).toBe( 20 );
		expect( query.page ).toBe( 1 );
		expect( query.orderby ).toBe( 'date' );
		expect( query.order ).toBe( 'desc' );
		expect( query.search ).toBe( '' );
		expect( query.user_id ).toBeUndefined();
	} );

	it( 'should pass user_id 0 through', () => {
		expect( viewToQuery( DEFAULT_VIEW, 0 ).user_id ).toBe( 0 );
	} );

	it( 'should wrap a single ap_object_type filter value in an array', () => {
		const view = { ...DEFAULT_VIEW, filters: [ { field: 'ap_object_type', operator: 'is', value: 4 } ] };
		expect( viewToQuery( view as never, 1 ).ap_object_type ).toEqual( [ 4 ] );
	} );

	it( 'should pass an array ap_object_type filter value through unchanged', () => {
		const view = { ...DEFAULT_VIEW, filters: [ { field: 'ap_object_type', operator: 'isAny', value: [ 4, 5 ] } ] };
		expect( viewToQuery( view as never, 1 ).ap_object_type ).toEqual( [ 4, 5 ] );
	} );

	it( 'should pass the ap_tag filter value through as-is', () => {
		const view = { ...DEFAULT_VIEW, filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 7 ] } ] };
		expect( viewToQuery( view as never, 1 ).ap_tag ).toEqual( [ 7 ] );
	} );

	it( 'should map a before date filter to the before query arg', () => {
		const view = {
			...DEFAULT_VIEW,
			filters: [ { field: 'date', operator: 'before', value: '2026-09-01T00:00:00' } ],
		};
		const query = viewToQuery( view as never, 1 );
		expect( query.before ).toBe( '2026-09-01T00:00:00' );
		expect( query.after ).toBeUndefined();
	} );

	it( 'should map an after date filter to the after query arg', () => {
		const view = {
			...DEFAULT_VIEW,
			filters: [ { field: 'date', operator: 'after', value: '2026-09-01T00:00:00' } ],
		};
		const query = viewToQuery( view as never, 1 );
		expect( query.after ).toBe( '2026-09-01T00:00:00' );
		expect( query.before ).toBeUndefined();
	} );

	it( 'should ignore a date filter without a value', () => {
		const view = { ...DEFAULT_VIEW, filters: [ { field: 'date', operator: 'before', value: '' } ] };
		const query = viewToQuery( view as never, 1 );
		expect( query.before ).toBeUndefined();
	} );

	it( 'should not add filter args when the view has no filters', () => {
		const query = viewToQuery( { ...DEFAULT_VIEW, filters: undefined } as never, 1 );
		expect( query.ap_object_type ).toBeUndefined();
		expect( query.ap_tag ).toBeUndefined();
	} );

	it( 'should request the fields the list and inspector rely on', () => {
		expect( viewToQuery( DEFAULT_VIEW, 1 )._fields ).toEqual(
			expect.arrayContaining( [ 'id', 'title', 'actor_info', 'ap_object_type', 'ap_tag' ] )
		);
	} );
} );
