import { createBlock } from '@wordpress/blocks';

/**
 * Deprecation for the following block to handle the migration from custom title to InnerBlocks.
 */
const v1 = {
	attributes: {
		title: {
			type: 'string',
			default: 'Fediverse Following',
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
	supports: {
		html: false,
	},

	/**
	 * Checks if the block is eligible for migration.
	 *
	 * @param {Object} attributes       The block attributes.
	 * @param {string} attributes.title The block title.
	 * @return {boolean} Whether the block is eligible for migration.
	 */
	isEligible( { title } ) {
		return !! title;
	},

	/**
	 * Migrates the block to use a core heading block instead of the custom heading attribute.
	 *
	 * @param {Object} attributes       The attributes for the block.
	 * @param {string} attributes.title The block title.
	 * @return {[Object, Array]} An array with the new block attributes and inner blocks.
	 */
	migrate: ( { title, ...newAttributes } ) => {
		const headingBlock = createBlock( 'core/heading', {
			content: title,
			level: 3,
		} );

		return [ newAttributes, [ headingBlock ] ];
	},
};

export default [ v1 ];
