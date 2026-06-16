// Use the real createRegistrySelector from @wordpress/data, but stub core-data
// so we don't pull in the full entity layer. The selector reads
// `select( coreStore ).getCurrentUser()`, so we only need a store handle.
jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

import { selectors } from '../selectors';
import type { State } from '../types';

const mockGetCurrentUser = jest.fn();

/**
 * Registry selectors created with `createRegistrySelector` resolve their
 * `select` from `selector.registry`. Override it so the inner `select( coreStore )`
 * returns our mocked core-data selectors.
 *
 * @param currentUser The value `getCurrentUser()` should return.
 */
function withRegistry( currentUser: unknown ): void {
	mockGetCurrentUser.mockReturnValue( currentUser );
	( selectors.getActiveActorId as unknown as { registry: unknown } ).registry = {
		select: () => ( { getCurrentUser: mockGetCurrentUser } ),
	};
}

describe( 'store selectors', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'getActiveActorId', () => {
		it( 'should return the stored actor id when set', () => {
			withRegistry( { id: 99 } );
			const state: State = { activeActorId: 3 };
			expect( selectors.getActiveActorId( state ) ).toBe( 3 );
			// Should not need the current user when state already has a value.
			expect( mockGetCurrentUser ).not.toHaveBeenCalled();
		} );

		it( 'should fall back to the current user id when not set', () => {
			withRegistry( { id: 7 } );
			const state: State = { activeActorId: null };
			expect( selectors.getActiveActorId( state ) ).toBe( 7 );
		} );

		it( 'should return null when not set and there is no current user', () => {
			withRegistry( undefined );
			const state: State = { activeActorId: null };
			expect( selectors.getActiveActorId( state ) ).toBeNull();
		} );

		it( 'should return the stored id even when it is 0', () => {
			withRegistry( { id: 7 } );
			const state: State = { activeActorId: 0 };
			expect( selectors.getActiveActorId( state ) ).toBe( 0 );
			expect( mockGetCurrentUser ).not.toHaveBeenCalled();
		} );
	} );
} );
