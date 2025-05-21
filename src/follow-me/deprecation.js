import { createBlock } from '@wordpress/blocks';

/**
 * Deprecation for the Follow Me block to use a core button block instead of the custom button.
 * This handles the migration of the buttonText and buttonSize attributes to the innerBlock.
 */
const v1 = {
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
			default: 'site',
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
	isEligible( attributes ) {
		// Run migration if buttonText or buttonOnly is set.
		return !! attributes.buttonText || !! attributes.buttonOnly;
	},

	/**
	 * Migrates the Follow Me block to use a core button block instead of the custom button.
	 *
	 * @param {Object} attributes The block attributes.
	 *
	 * @return {[Object, Array]} An array with the new block attributes and inner blocks.
	 */
	migrate( attributes ) {
		const { buttonText, ...newAttributes } = attributes;

		const buttonBlock = createBlock( 'core/button', {
			tagName: 'button',
			text: buttonText,
		} );

		return [ newAttributes, [ buttonBlock ] ];
	},
};

export default [ v1 ];
