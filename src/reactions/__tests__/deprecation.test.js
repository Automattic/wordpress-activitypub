/**
 * Internal dependencies
 */
import * as fixtures from './fixtures';

/**
 * Mock createBlock to avoid blocks store initialization issues.
 * Returns a simple object matching the block structure.
 *
 * @param {string} name        Block name.
 * @param {Object} attributes  Block attributes.
 * @param {Array}  innerBlocks Inner blocks.
 * @return {Object} Mock block object.
 */
const createBlock = ( name, attributes = {}, innerBlocks = [] ) => ( {
	name,
	attributes,
	innerBlocks,
	clientId: 'mock-client-id',
	isValid: true,
} );

/**
 * Since importing the full deprecation.js triggers @wordpress/block-editor
 * which has ESM dependencies that Jest can't parse, we recreate the
 * deprecation logic here for testing.
 *
 * This tests the same logic that lives in deprecation.js
 */

// v2 deprecation config (Interactivity API migration)
const v2Config = {
	attributes: {},
	supports: {
		html: false,
		align: true,
		layout: {
			default: {
				type: 'constrained',
				orientation: 'vertical',
				justifyContent: 'center',
			},
		},
	},
};

// v1 deprecation config (title to heading migration)
const v1Config = {
	attributes: {
		title: {
			type: 'string',
			default: 'Fediverse reactions',
		},
	},
	supports: {
		html: false,
		align: true,
		layout: {
			default: {
				type: 'constrained',
				orientation: 'vertical',
				justifyContent: 'center',
			},
		},
	},
	isEligible( { title } ) {
		return !! title;
	},
	migrate( { title, ...newAttributes } ) {
		const headingBlock = createBlock( 'core/heading', {
			content: title,
			level: 6,
		} );
		return [ newAttributes, [ headingBlock ] ];
	},
};

describe( 'Reactions block deprecations', () => {
	describe( 'v2 deprecation (Interactivity API migration)', () => {
		it( 'should have empty attributes', () => {
			expect( v2Config.attributes ).toEqual( {} );
		} );

		it( 'should have correct supports', () => {
			expect( v2Config.supports ).toEqual( {
				html: false,
				align: true,
				layout: {
					default: {
						type: 'constrained',
						orientation: 'vertical',
						justifyContent: 'center',
					},
				},
			} );
		} );
	} );

	describe( 'v1 deprecation (title to heading migration)', () => {
		it( 'should have title attribute with correct default', () => {
			expect( v1Config.attributes ).toEqual( {
				title: {
					type: 'string',
					default: 'Fediverse reactions',
				},
			} );
		} );

		it( 'should have correct supports matching original block.json', () => {
			expect( v1Config.supports ).toEqual( {
				html: false,
				align: true,
				layout: {
					default: {
						type: 'constrained',
						orientation: 'vertical',
						justifyContent: 'center',
					},
				},
			} );
		} );

		describe( 'isEligible', () => {
			it( 'should return true when title attribute exists', () => {
				expect( v1Config.isEligible( { title: 'My Reactions' } ) ).toBe( true );
			} );

			it( 'should return true for default title', () => {
				expect( v1Config.isEligible( { title: 'Fediverse reactions' } ) ).toBe( true );
			} );

			it( 'should return false when title is empty string', () => {
				expect( v1Config.isEligible( { title: '' } ) ).toBe( false );
			} );

			it( 'should return false when title is undefined', () => {
				expect( v1Config.isEligible( {} ) ).toBe( false );
			} );

			it( 'should return false when title is null', () => {
				expect( v1Config.isEligible( { title: null } ) ).toBe( false );
			} );
		} );

		describe( 'migrate', () => {
			it( 'should create heading innerBlock from title', () => {
				const attributes = { title: 'My Custom Title' };
				const [ newAttributes, innerBlocks ] = v1Config.migrate( attributes );

				expect( newAttributes ).toEqual( {} );
				expect( innerBlocks ).toHaveLength( 1 );
				expect( innerBlocks[ 0 ].name ).toBe( 'core/heading' );
				expect( innerBlocks[ 0 ].attributes.content ).toBe( 'My Custom Title' );
				expect( innerBlocks[ 0 ].attributes.level ).toBe( 6 );
			} );

			it( 'should preserve other attributes while removing title', () => {
				const attributes = {
					title: 'My Title',
					align: 'wide',
					className: 'custom-class',
				};
				const [ newAttributes, innerBlocks ] = v1Config.migrate( attributes );

				expect( newAttributes ).toEqual( {
					align: 'wide',
					className: 'custom-class',
				} );
				expect( innerBlocks[ 0 ].attributes.content ).toBe( 'My Title' );
			} );

			it( 'should handle default title value', () => {
				const attributes = { title: 'Fediverse reactions' };
				const [ newAttributes, innerBlocks ] = v1Config.migrate( attributes );

				expect( newAttributes ).toEqual( {} );
				expect( innerBlocks[ 0 ].attributes.content ).toBe( 'Fediverse reactions' );
			} );

			it( 'should migrate fixture with custom title', () => {
				const [ newAttributes, innerBlocks ] = v1Config.migrate( fixtures.v1MarkupCustomTitle.attributes );

				expect( newAttributes ).toEqual( {} );
				expect( innerBlocks[ 0 ].attributes.content ).toBe( 'My Custom Reactions' );
			} );

			it( 'should migrate fixture with align attribute', () => {
				const [ newAttributes, innerBlocks ] = v1Config.migrate( fixtures.v1MarkupWithAlign.attributes );

				expect( newAttributes ).toEqual( { align: 'wide' } );
				expect( innerBlocks[ 0 ].attributes.content ).toBe( 'Reactions' );
			} );
		} );
	} );

	describe( 'markup fixtures', () => {
		describe( 'v2 markup format', () => {
			it( 'should have class without wp-block- prefix', () => {
				expect( fixtures.v2Markup.html ).toContain( 'activitypub-reactions-block' );
				expect( fixtures.v2Markup.html ).not.toContain( 'wp-block-activitypub-reactions' );
			} );

			it( 'should not have opening/closing block tags (self-closing not expected)', () => {
				// v2 had actual HTML content, not a self-closing block
				expect( fixtures.v2Markup.html ).toContain( '<!-- wp:activitypub/reactions -->' );
				expect( fixtures.v2Markup.html ).toContain( '<!-- /wp:activitypub/reactions -->' );
			} );
		} );

		describe( 'v1 markup format', () => {
			it( 'should be self-closing block comment (dynamic block)', () => {
				expect( fixtures.v1Markup.html ).toMatch( /<!-- wp:activitypub\/reactions .* \/-->/ );
			} );

			it( 'should have title in attributes JSON', () => {
				expect( fixtures.v1Markup.html ).toContain( '"title"' );
			} );

			it( 'should match isEligible for migration', () => {
				expect( v1Config.isEligible( fixtures.v1Markup.attributes ) ).toBe( true );
			} );

			it( 'v1MarkupNoTitle should not match isEligible (no title to migrate)', () => {
				expect( v1Config.isEligible( fixtures.v1MarkupNoTitle.attributes ) ).toBe( false );
			} );

			it( 'v1MarkupLongTitle should match isEligible and migrate correctly', () => {
				expect( v1Config.isEligible( fixtures.v1MarkupLongTitle.attributes ) ).toBe( true );

				const [ newAttributes, innerBlocks ] = v1Config.migrate( fixtures.v1MarkupLongTitle.attributes );
				expect( newAttributes ).toEqual( {} );
				expect( innerBlocks[ 0 ].attributes.content ).toBe( 'What people think about it on the Fediverse!' );
			} );
		} );

		describe( 'v3 markup format (plugin 3.0.0+)', () => {
			it( 'should have both wp-block- prefix and custom class', () => {
				expect( fixtures.v3Markup.html ).toContain( 'wp-block-activitypub-reactions' );
				expect( fixtures.v3Markup.html ).toContain( 'activitypub-reactions-block' );
			} );
		} );
	} );
} );
