// Stub core-data so we only need a store handle ('core'); the selector reads
// `select( coreStore ).getCurrentUser()`.
jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

import { createRegistry } from '@wordpress/data';
import { selectors } from '../selectors';

/**
 * Builds a real, isolated data registry with a stub core store and the app
 * selectors registered, then resolves `getActiveActorId` through it. This
 * exercises the registry-selector wiring the way `@wordpress/data` does at
 * runtime, without coupling to `createRegistrySelector` internals.
 *
 * @param activeActorId The app store's stored actor id.
 * @param currentUser   The value the core store's `getCurrentUser` returns.
 */
function resolveActiveActorId( activeActorId: number | null, currentUser: unknown ): number | null {
	const registry = createRegistry();

	registry.registerStore( 'core', {
		reducer: ( state = {} ) => state,
		selectors: { getCurrentUser: () => currentUser },
	} );

	registry.registerStore( 'activitypub/app', {
		reducer: ( state = { activeActorId } ) => state,
		selectors,
	} );

	return registry.select( 'activitypub/app' ).getActiveActorId();
}

describe( 'store selectors', () => {
	describe( 'getActiveActorId', () => {
		it( 'should return the stored actor id when set', () => {
			expect( resolveActiveActorId( 3, { id: 99 } ) ).toBe( 3 );
		} );

		it( 'should fall back to the current user id when not set', () => {
			expect( resolveActiveActorId( null, { id: 7 } ) ).toBe( 7 );
		} );

		it( 'should return null when not set and there is no current user', () => {
			expect( resolveActiveActorId( null, undefined ) ).toBeNull();
		} );

		it( 'should return the stored id even when it is 0', () => {
			expect( resolveActiveActorId( 0, { id: 7 } ) ).toBe( 0 );
		} );
	} );
} );
