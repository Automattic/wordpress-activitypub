const mockGet = jest.fn();
const mockSet = jest.fn();
const mockGetCurrentUser = jest.fn();
const mockSelect = jest.fn( ( _store: unknown ) => ( { get: mockGet } ) );
const mockDispatch = jest.fn( ( _store: unknown ) => ( { set: mockSet } ) );
const mockResolveSelect = jest.fn( ( _store: unknown ) => ( { getCurrentUser: mockGetCurrentUser } ) );

jest.mock( '@wordpress/data', () => ( {
	select: ( store: unknown ) => mockSelect( store ),
	dispatch: ( store: unknown ) => mockDispatch( store ),
	resolveSelect: ( store: unknown ) => mockResolveSelect( store ),
} ) );

jest.mock( '@wordpress/preferences', () => ( { store: 'core/preferences' } ) );
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );

import { getActiveActorId } from '../resolvers';
import { SET_ACTIVE_ACTOR } from '../types';

describe( 'store resolvers', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'getActiveActorId', () => {
		it( 'should return an action from a saved preference without fetching the user', () => {
			mockGet.mockReturnValue( 5 );

			const generator = getActiveActorId();
			const result = generator.next();

			expect( result.done ).toBe( true );
			expect( result.value ).toEqual( { type: SET_ACTIVE_ACTOR, actorId: 5 } );
			expect( mockGetCurrentUser ).not.toHaveBeenCalled();
			expect( mockSet ).not.toHaveBeenCalled();
			// The preference is read from the preferences store.
			expect( mockSelect ).toHaveBeenCalledWith( 'core/preferences' );
		} );

		it( 'should fall back to the current user and persist it when no preference exists', () => {
			mockGet.mockReturnValue( undefined );

			const generator = getActiveActorId();
			// First step yields the resolveSelect( coreStore ).getCurrentUser() control.
			const first = generator.next();
			expect( first.done ).toBe( false );

			// Resume with the resolved current user.
			const second = generator.next( { id: 7 } );
			expect( second.done ).toBe( true );
			expect( second.value ).toEqual( { type: SET_ACTIVE_ACTOR, actorId: 7 } );
			expect( mockSet ).toHaveBeenCalledWith( 'activitypub/app', 'activeActorId', 7 );
			// The current user comes from core-data and the value is persisted to preferences.
			expect( mockResolveSelect ).toHaveBeenCalledWith( 'core' );
			expect( mockDispatch ).toHaveBeenCalledWith( 'core/preferences' );
		} );

		it( 'should treat null preference the same as undefined', () => {
			mockGet.mockReturnValue( null );

			const generator = getActiveActorId();
			generator.next();
			const second = generator.next( { id: 3 } );

			expect( second.value ).toEqual( { type: SET_ACTIVE_ACTOR, actorId: 3 } );
		} );

		it( 'should return nothing when no preference and no current user id', () => {
			mockGet.mockReturnValue( undefined );

			const generator = getActiveActorId();
			generator.next();
			const second = generator.next( {} );

			expect( second.done ).toBe( true );
			expect( second.value ).toBeUndefined();
			expect( mockSet ).not.toHaveBeenCalled();
		} );
	} );
} );
