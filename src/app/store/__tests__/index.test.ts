jest.mock( '@wordpress/preferences', () => ( { store: 'core/preferences' } ) );
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );

describe( 'store registration', () => {
	it( 'should register the store once when several bundles load it', () => {
		// Every app bundle carries its own copy of the store module but shares `wp.data`.
		const data = jest.requireActual( '@wordpress/data' );
		const consoleError = jest.spyOn( console, 'error' ).mockImplementation( () => {} );

		jest.doMock( '@wordpress/data', () => data );

		for ( let i = 0; i < 2; i++ ) {
			jest.isolateModules( () => {
				require( '../index' );
			} );
		}

		expect( consoleError ).not.toHaveBeenCalled();
		expect( data.select( 'activitypub/app' ).getActiveActorId ).toEqual( expect.any( Function ) );

		consoleError.mockRestore();
	} );
} );
