import { reducer } from '../reducer';
import { DEFAULT_STATE, SET_ACTIVE_ACTOR } from '../types';
import type { Action } from '../types';

describe( 'store reducer', () => {
	it( 'should return the default state when called with undefined', () => {
		// @ts-expect-error -- testing the runtime default for an unknown action.
		const state = reducer( undefined, { type: 'UNKNOWN' } );
		expect( state ).toEqual( DEFAULT_STATE );
		expect( state.activeActorId ).toBeNull();
	} );

	it( 'should return the same state for an unknown action', () => {
		const initial = { activeActorId: 7 };
		// @ts-expect-error -- intentionally passing an unhandled action type.
		const state = reducer( initial, { type: 'NOPE' } );
		expect( state ).toBe( initial );
	} );

	it( 'should set the active actor on SET_ACTIVE_ACTOR', () => {
		const action: Action = { type: SET_ACTIVE_ACTOR, actorId: 42 };
		const state = reducer( DEFAULT_STATE, action );
		expect( state.activeActorId ).toBe( 42 );
	} );

	it( 'should not mutate the previous state', () => {
		const initial = { activeActorId: 1 };
		const action: Action = { type: SET_ACTIVE_ACTOR, actorId: 2 };
		const state = reducer( initial, action );
		expect( state ).not.toBe( initial );
		expect( initial.activeActorId ).toBe( 1 );
		expect( state.activeActorId ).toBe( 2 );
	} );

	it( 'should preserve unrelated state fields', () => {
		const initial = { activeActorId: 1, extra: 'keep-me' } as never;
		const action: Action = { type: SET_ACTIVE_ACTOR, actorId: 9 };
		const state = reducer( initial, action ) as { activeActorId: number; extra: string };
		expect( state.extra ).toBe( 'keep-me' );
		expect( state.activeActorId ).toBe( 9 );
	} );
} );
