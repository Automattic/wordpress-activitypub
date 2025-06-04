import { createBlock } from '@wordpress/blocks';

/**
 * Deprecation for the followers block to handle the migration from custom title to InnerBlocks.
 */
const v1 = {
	attributes: {
		title: {
			type: 'string',
			default: 'Fediverse Followers',
		},
		selectedUser: {
			type: 'string',
			default: 'site',
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

	isEligible( attributes ) {
		// Run migration if the title attribute exists.
		return !! attributes.title;
	},

	migrate: ( attributes ) => {
		const { title, ...newAttributes } = attributes;

		const headingBlock = createBlock( 'core/heading', {
			content: title,
			level: 3,
		} );

		return [ newAttributes, [ headingBlock ] ];
	},
};

export default [ v1 ];
