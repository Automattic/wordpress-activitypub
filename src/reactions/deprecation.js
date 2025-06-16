import { createBlock } from '@wordpress/blocks';

const v1 = {
	attributes: {
		title: {
			type: 'string',
			default: 'Fediverse reactions',
		},
	},

	supports: {
		html: false,
		color: {
			gradients: true,
			link: true,
			__experimentalDefaultControls: {
				background: true,
				text: true,
				link: true,
			},
		},
		__experimentalBorder: {
			radius: true,
			width: true,
			color: true,
			style: true,
		},
		typography: {
			fontSize: true,
			__experimentalDefaultControls: {
				fontSize: true,
			},
		},
	},

	/**
	 * Checks if the block is eligible for migration.
	 *
	 * @param {Object} attributes The block attributes.
	 *
	 * @return {boolean} Whether the block is eligible for migration.
	 */
	isEligible( { title } ) {
		return !! title;
	},

	/**
	 * Migrates the block to use a core heading block instead of the custom heading attribute.
	 *
	 * @param {Object} attributes The attributes for the block.
	 *
	 * @return {Array} The new attributes and inner blocks.
	 */
	migrate( { title, ...newAttributes } ) {
		const headingBlock = createBlock( 'core/heading', {
			content: title,
			level: 6,
		} );

		return [ newAttributes, [ headingBlock ] ];
	},
};

export default [ v1 ];
