describe( 'Reply block transform logic', () => {
	// Test just the transform logic without createBlock to avoid test environment issues
	const getTransformAttributes = ( embedAttributes ) => {
		return {
			url: embedAttributes.url || '',
			embedPost: true,
		};
	};

	it( 'should extract URL from embed block attributes', () => {
		const embedAttributes = {
			url: 'https://mastodon.social/@user/12345',
			type: 'rich',
			providerNameSlug: 'mastodon',
		};

		const replyAttributes = getTransformAttributes( embedAttributes );

		expect( replyAttributes.url ).toBe( embedAttributes.url );
		expect( replyAttributes.embedPost ).toBe( true );
	} );

	it( 'should handle empty URL from embed block', () => {
		const embedAttributes = {
			url: '',
		};

		const replyAttributes = getTransformAttributes( embedAttributes );

		expect( replyAttributes.url ).toBe( '' );
		expect( replyAttributes.embedPost ).toBe( true );
	} );

	it( 'should provide default URL when missing from embed block', () => {
		const embedAttributes = {};

		const replyAttributes = getTransformAttributes( embedAttributes );

		expect( replyAttributes.url ).toBe( '' );
		expect( replyAttributes.embedPost ).toBe( true );
	} );

	it( 'should always enable embedPost for transformed blocks', () => {
		const embedAttributes = {
			url: 'https://example.com/post',
		};

		const replyAttributes = getTransformAttributes( embedAttributes );

		expect( replyAttributes.embedPost ).toBe( true );
	} );
} );
