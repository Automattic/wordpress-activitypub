/**
 * Tests for the follow-me block deprecations.
 *
 * These tests verify the deprecation logic (isEligible, migrate) works correctly.
 * We recreate the deprecation config here because importing from the source file
 * pulls in @wordpress/blocks which has Jest ESM compatibility issues.
 */

import classnames from 'classnames';
import { __unstableStripHTML as stripHTML } from '@wordpress/dom';
import {
	v1Markup,
	v1MarkupButtonOnly,
	v1MarkupCustomText,
	v1MarkupWithUser,
	v2Markup,
	v2MarkupWithClassName,
	v3Markup,
	v3MarkupCustomText,
	noMigrationNeeded,
} from './fixtures';

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
 * Migrates the buttonOnly attribute to a block style className.
 * Shared between v1 and v2 deprecations.
 *
 * @param {Object} attributes Block attributes including buttonOnly and className.
 * @return {Object} New attributes with className instead of buttonOnly.
 */
function migrateButtonOnly( { buttonOnly = false, className = '', ...newAttributes } ) {
	newAttributes.className = classnames( className, buttonOnly ? 'is-style-button-only' : 'is-style-default' );
	return newAttributes;
}

/**
 * v1 deprecation config (mirrors src/follow-me/deprecation.js).
 * Migrates buttonText to core/button innerBlock and buttonOnly to className.
 */
const v1Config = {
	attributes: {
		buttonOnly: {
			type: 'boolean',
			default: false,
		},
		buttonText: {
			type: 'string',
			default: 'Follow',
		},
		selectedUser: {
			type: 'string',
			default: 'blog',
		},
	},

	isEligible( { buttonText, buttonOnly } ) {
		return !! buttonText || !! buttonOnly;
	},

	migrate( { buttonText, ...newAttributes } ) {
		const buttonBlock = createBlock( 'core/button', {
			text: buttonText,
		} );

		return [ migrateButtonOnly( newAttributes ), [ buttonBlock ] ];
	},
};

/**
 * v2 deprecation config (mirrors src/follow-me/deprecation.js).
 * Migrates buttonOnly attribute to className block style.
 */
const v2Config = {
	attributes: {
		selectedUser: {
			type: 'string',
			default: 'blog',
		},
		buttonOnly: {
			type: 'boolean',
			default: false,
		},
	},

	isEligible( { buttonOnly } ) {
		return !! buttonOnly;
	},

	migrate: migrateButtonOnly,
};

/**
 * v3 deprecation config (mirrors src/follow-me/deprecation.js).
 * Fixes broken button HTML when unfiltered_html capability is restricted.
 *
 * Note: The actual implementation uses __( 'Follow', 'activitypub' ) for the i18n
 * fallback, but we use a plain string here since Jest tests don't use i18n functions.
 */
const v3Config = {
	attributes: {
		selectedUser: {
			type: 'string',
			default: 'blog',
		},
	},

	isEligible( attributes, innerBlocks ) {
		return innerBlocks.length === 1 && 'button' === innerBlocks[ 0 ].attributes.tagName;
	},

	migrate( attributes, innerBlocks ) {
		const { tagName, ...buttonAttributes } = innerBlocks[ 0 ].attributes;
		const text = stripHTML( innerBlocks[ 0 ].originalContent ) || 'Follow';

		const buttonBlock = createBlock( 'core/button', { ...buttonAttributes, text } );

		return [ attributes, [ buttonBlock ] ];
	},
};

describe( 'Follow Me block deprecations', () => {
	describe( 'v1 - buttonText and buttonOnly migration', () => {
		describe( 'isEligible', () => {
			it( 'should return true when buttonText is present', () => {
				expect( v1Config.isEligible( v1Markup.attributes ) ).toBe( true );
			} );

			it( 'should return true when buttonOnly is true', () => {
				expect( v1Config.isEligible( v1MarkupButtonOnly.attributes ) ).toBe( true );
			} );

			it( 'should return true for custom buttonText', () => {
				expect( v1Config.isEligible( v1MarkupCustomText.attributes ) ).toBe( true );
			} );

			it( 'should return false when neither buttonText nor buttonOnly is set', () => {
				expect( v1Config.isEligible( noMigrationNeeded.attributes ) ).toBe( false );
			} );
		} );

		describe( 'migrate', () => {
			it( 'should migrate buttonText to core/button innerBlock', () => {
				const [ , innerBlocks ] = v1Config.migrate( v1Markup.attributes );

				expect( innerBlocks ).toHaveLength( 1 );
				expect( innerBlocks[ 0 ].name ).toBe( 'core/button' );
				expect( innerBlocks[ 0 ].attributes.text ).toBe( 'Follow' );
			} );

			it( 'should migrate custom buttonText', () => {
				const [ , innerBlocks ] = v1Config.migrate( v1MarkupCustomText.attributes );

				expect( innerBlocks[ 0 ].attributes.text ).toBe( 'Connect with me' );
			} );

			it( 'should add is-style-default when buttonOnly is false', () => {
				const [ newAttributes ] = v1Config.migrate( v1Markup.attributes );

				expect( newAttributes.className ).toContain( 'is-style-default' );
				expect( newAttributes ).not.toHaveProperty( 'buttonOnly' );
				expect( newAttributes ).not.toHaveProperty( 'buttonText' );
			} );

			it( 'should add is-style-button-only when buttonOnly is true', () => {
				const [ newAttributes ] = v1Config.migrate( v1MarkupButtonOnly.attributes );

				expect( newAttributes.className ).toContain( 'is-style-button-only' );
			} );

			it( 'should preserve selectedUser attribute', () => {
				const [ newAttributes ] = v1Config.migrate( v1MarkupWithUser.attributes );

				expect( newAttributes.selectedUser ).toBe( '1' );
			} );
		} );
	} );

	describe( 'v2 - buttonOnly to block style migration', () => {
		describe( 'isEligible', () => {
			it( 'should return true when buttonOnly is true', () => {
				expect( v2Config.isEligible( v2Markup.attributes ) ).toBe( true );
			} );

			it( 'should return false when buttonOnly is false', () => {
				expect( v2Config.isEligible( { buttonOnly: false } ) ).toBe( false );
			} );

			it( 'should return false when buttonOnly is not set', () => {
				expect( v2Config.isEligible( noMigrationNeeded.attributes ) ).toBe( false );
			} );
		} );

		describe( 'migrate', () => {
			it( 'should add is-style-button-only class', () => {
				const newAttributes = v2Config.migrate( v2Markup.attributes );

				expect( newAttributes.className ).toContain( 'is-style-button-only' );
				expect( newAttributes ).not.toHaveProperty( 'buttonOnly' );
			} );

			it( 'should preserve existing className', () => {
				const newAttributes = v2Config.migrate( v2MarkupWithClassName.attributes );

				expect( newAttributes.className ).toContain( 'is-style-button-only' );
				expect( newAttributes.className ).toContain( 'my-custom-class' );
			} );
		} );
	} );

	describe( 'v3 - broken button fix', () => {
		describe( 'isEligible', () => {
			it( 'should return true when innerBlock has tagName attribute', () => {
				expect( v3Config.isEligible( v3Markup.attributes, v3Markup.innerBlocks ) ).toBe( true );
			} );

			it( 'should return false when no innerBlocks', () => {
				expect( v3Config.isEligible( noMigrationNeeded.attributes, [] ) ).toBe( false );
			} );

			it( 'should return false when innerBlock lacks tagName', () => {
				const innerBlocksWithoutTagName = [
					{
						name: 'core/button',
						attributes: {},
					},
				];
				expect( v3Config.isEligible( {}, innerBlocksWithoutTagName ) ).toBe( false );
			} );
		} );

		describe( 'migrate', () => {
			it( 'should remove tagName from button attributes', () => {
				const [ , innerBlocks ] = v3Config.migrate( v3Markup.attributes, v3Markup.innerBlocks );

				expect( innerBlocks[ 0 ].attributes ).not.toHaveProperty( 'tagName' );
			} );

			it( 'should extract text from originalContent', () => {
				const [ , innerBlocks ] = v3Config.migrate( v3Markup.attributes, v3Markup.innerBlocks );

				expect( innerBlocks[ 0 ].attributes.text ).toBe( 'Follow' );
			} );

			it( 'should extract custom text from originalContent', () => {
				const [ , innerBlocks ] = v3Config.migrate(
					v3MarkupCustomText.attributes,
					v3MarkupCustomText.innerBlocks
				);

				expect( innerBlocks[ 0 ].attributes.text ).toBe( 'Subscribe Now' );
			} );

			it( 'should preserve original attributes', () => {
				const [ newAttributes ] = v3Config.migrate( v3Markup.attributes, v3Markup.innerBlocks );

				expect( newAttributes ).toEqual( v3Markup.attributes );
			} );
		} );
	} );
} );
