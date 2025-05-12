import { createBlock } from '@wordpress/blocks';

/**
 * Deprecation for the Follow Me block to use a core button block instead of the custom button.
 * This handles the migration of the buttonText and buttonSize attributes to the innerBlock.
 */
const v1 = {
	attributes: {
		buttonText: {
			type: 'string',
			default: 'Follow',
		},
		buttonSize: {
			type: 'string',
			default: 'default',
			enum: [ 'small', 'default', 'compact' ],
		},
	},

	isEligible( attributes ) {
		// Run migration if buttonText attribute exists.
		return !! attributes.buttonText;
	},

	migrate( attributes ) {
		const { buttonText, buttonSize, ...newAttributes } = attributes;

		// Map buttonSize to core/button className.
		let className = '';
		if ( buttonSize === 'small' ) {
			className = 'is-small';
		} else if ( buttonSize === 'compact' ) {
			className = 'is-compact';
		}

		// Create a core button block with the buttonText and buttonSize.
		const buttonBlock = createBlock( 'core/button', {
			className,
			tagName: 'button',
			text: buttonText,
		} );

		return [ newAttributes, [ buttonBlock ] ];
	},
};

export default [ v1 ];
