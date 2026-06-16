jest.mock( '@wordpress/views', () => ( {} ) );

import { normalizeFieldOrder } from '../utils';

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
