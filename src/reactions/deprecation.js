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
		align: true,
		layout: {
			default: {
				type: 'constrained',
				orientation: 'vertical',
				justifyContent: 'center',
			},
		},
	},

	isEligible( attributes ) {
		// Run migration if title attribute exists.
		return !! attributes.title;
	},

	migrate( attributes ) {
		const { title, ...newAttributes } = attributes;

		return [
			newAttributes,
			[
				createBlock( 'core/heading', {
					content: title,
					level: 6,
				} ),
			],
		];
	},
};

export default [ v1 ];
