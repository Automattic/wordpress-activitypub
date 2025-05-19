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

	isEligible( attributes ) {
		// Run migration if the title attribute exists.
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
