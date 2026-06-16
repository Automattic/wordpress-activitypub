const mockGet = jest.fn();
const mockSet = jest.fn();
const mockGetCurrentUser = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	select: () => ( { get: mockGet } ),
	dispatch: () => ( { set: mockSet } ),
	resolveSelect: () => ( { getCurrentUser: mockGetCurrentUser } ),
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
