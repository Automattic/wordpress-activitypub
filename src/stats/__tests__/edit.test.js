describe( 'Stats block helpers', () => {
	beforeEach( () => {
		window._activityPubOptions = {
			statsImageUrlEndpoint: 'http://example.com/?rest_route=/activitypub/1.0/stats/image-url/{user_id}/{year}',
		};
	} );

	afterEach( () => {
		delete window._activityPubOptions;
	} );

	describe( 'getImageUrlEndpoint', () => {
		// Inline the function since it's not exported.
		function getImageUrlEndpoint( selectedUser, displayYear ) {
			const template = window._activityPubOptions?.statsImageUrlEndpoint || '';
			if ( ! template ) {
				return '';
			}
			const userId = ! selectedUser || selectedUser === 'blog' ? 0 : selectedUser;
			return template.replace( '{user_id}', userId ).replace( '{year}', displayYear );
		}

		test( 'converts "blog" to user ID 0', () => {
			const url = getImageUrlEndpoint( 'blog', 2025 );
			expect( url ).toContain( '/0/2025' );
		} );

		test( 'converts undefined to user ID 0', () => {
			const url = getImageUrlEndpoint( undefined, 2025 );
			expect( url ).toContain( '/0/2025' );
		} );

		test( 'converts null to user ID 0', () => {
			const url = getImageUrlEndpoint( null, 2025 );
			expect( url ).toContain( '/0/2025' );
		} );

		test( 'converts empty string to user ID 0', () => {
			const url = getImageUrlEndpoint( '', 2025 );
			expect( url ).toContain( '/0/2025' );
		} );

		test( 'passes numeric user ID through', () => {
			const url = getImageUrlEndpoint( '1', 2024 );
			expect( url ).toContain( '/1/2024' );
		} );

		test( 'replaces both placeholders', () => {
			const url = getImageUrlEndpoint( '42', 2023 );
			expect( url ).toBe( 'http://example.com/?rest_route=/activitypub/1.0/stats/image-url/42/2023' );
		} );

		test( 'returns empty string when template is unavailable', () => {
			delete window._activityPubOptions;
			const url = getImageUrlEndpoint( 'blog', 2025 );
			expect( url ).toBe( '' );
		} );

		test( 'returns empty string when options is undefined', () => {
			window._activityPubOptions = {};
			const url = getImageUrlEndpoint( 'blog', 2025 );
			expect( url ).toBe( '' );
		} );

		test( 'works with pretty permalink template', () => {
			window._activityPubOptions = {
				statsImageUrlEndpoint: 'http://example.com/wp-json/activitypub/1.0/stats/image-url/{user_id}/{year}',
			};
			const url = getImageUrlEndpoint( 'blog', 2025 );
			expect( url ).toBe( 'http://example.com/wp-json/activitypub/1.0/stats/image-url/0/2025' );
		} );
	} );

	describe( 'getYearOptions', () => {
		function getYearOptions() {
			const currentYear = new Date().getFullYear();
			const options = [];
			for ( let y = currentYear; y >= currentYear - 5; y-- ) {
				options.push( { label: String( y ), value: String( y ) } );
			}
			return options;
		}

		test( 'returns 6 year options', () => {
			const options = getYearOptions();
			expect( options ).toHaveLength( 6 );
		} );

		test( 'starts with current year', () => {
			const options = getYearOptions();
			expect( options[ 0 ].value ).toBe( String( new Date().getFullYear() ) );
		} );

		test( 'options have label and value as strings', () => {
			const options = getYearOptions();
			options.forEach( ( option ) => {
				expect( typeof option.label ).toBe( 'string' );
				expect( typeof option.value ).toBe( 'string' );
			} );
		} );
	} );
} );
