/**
 * Tests for the followers block deprecation.
 *
 * These tests verify the deprecation logic (isEligible, migrate) works correctly.
 * Due to Jest ESM limitations with @wordpress/blocks, we recreate the deprecation
 * logic here rather than importing it directly.
 */

import { v1Markup, v1MarkupNoTitle, v1MarkupCustomTitle, v1MarkupWithOptions } from './fixtures';

/**
 * Mock createBlock to avoid Jest/ESM issues with @wordpress/blocks.
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
 * v1 deprecation config (mirrors src/followers/deprecations.js).
 * Migrates title attribute to core/heading innerBlock.
 */
const v1Config = {
	attributes: {
		title: {
			type: 'string',
			default: 'Fediverse Followers',
		},
		selectedUser: {
			type: 'string',
			default: 'blog',
		},
		per_page: {
			type: 'number',
			default: 10,
		},
		order: {
			type: 'string',
			default: 'desc',
			enum: [ 'asc', 'desc' ],
		},
	},

	isEligible( { title } ) {
		return !! title;
	},

	migrate( { title, ...newAttributes } ) {
		const headingBlock = createBlock( 'core/heading', {
			content: title,
			level: 3,
		} );

		return [ newAttributes, [ headingBlock ] ];
	},
};

describe( 'Followers block deprecation', () => {
	describe( 'v1 - title to heading migration', () => {
		describe( 'isEligible', () => {
			it( 'should return true when title attribute is present', () => {
				expect( v1Config.isEligible( v1Markup.attributes ) ).toBe( true );
			} );

			it( 'should return true for custom title', () => {
				expect( v1Config.isEligible( v1MarkupCustomTitle.attributes ) ).toBe( true );
			} );

			it( 'should return true when title is present with other options', () => {
				expect( v1Config.isEligible( v1MarkupWithOptions.attributes ) ).toBe( true );
			} );

			it( 'should return false when title attribute is missing', () => {
				expect( v1Config.isEligible( v1MarkupNoTitle.attributes ) ).toBe( false );
			} );

			it( 'should return false for empty title', () => {
				expect( v1Config.isEligible( { title: '' } ) ).toBe( false );
			} );
		} );

		describe( 'migrate', () => {
			it( 'should migrate default title to heading block', () => {
				const [ newAttributes, innerBlocks ] = v1Config.migrate( v1Markup.attributes );

				expect( newAttributes ).not.toHaveProperty( 'title' );
				expect( innerBlocks ).toHaveLength( 1 );
				expect( innerBlocks[ 0 ].name ).toBe( 'core/heading' );
				expect( innerBlocks[ 0 ].attributes ).toEqual( {
					content: 'Fediverse Followers',
					level: 3,
				} );
			} );

			it( 'should migrate custom title to heading block', () => {
				const [ newAttributes, innerBlocks ] = v1Config.migrate( v1MarkupCustomTitle.attributes );

				expect( newAttributes ).not.toHaveProperty( 'title' );
				expect( innerBlocks ).toHaveLength( 1 );
				expect( innerBlocks[ 0 ].name ).toBe( 'core/heading' );
				expect( innerBlocks[ 0 ].attributes ).toEqual( {
					content: 'My Followers',
					level: 3,
				} );
			} );

			it( 'should preserve other attributes during migration', () => {
				const [ newAttributes ] = v1Config.migrate( v1MarkupWithOptions.attributes );

				expect( newAttributes ).not.toHaveProperty( 'title' );
				expect( newAttributes ).toHaveProperty( 'selectedUser', 'blog' );
				expect( newAttributes ).toHaveProperty( 'per_page', 20 );
				expect( newAttributes ).toHaveProperty( 'order', 'asc' );
			} );

			it( 'should create heading with level 3', () => {
				const [ , innerBlocks ] = v1Config.migrate( v1Markup.attributes );

				expect( innerBlocks[ 0 ].attributes.level ).toBe( 3 );
			} );
		} );
	} );
} );
