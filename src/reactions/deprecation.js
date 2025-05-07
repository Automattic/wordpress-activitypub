import { useBlockProps } from '@wordpress/block-editor';

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

	save( { attributes } ) {
		return (
			<div { ...useBlockProps.save() }>
				{ attributes.title && <h2>{ attributes.title }</h2> }
			</div>
		);
	},

	migrate( attributes ) {
		const { title, ...newAttributes } = attributes;

		if ( title ) {
			return {
				...newAttributes,
				innerBlocks: [
					[
						'core/heading',
						{
							level: 6,
							content: title,
						},
					],
				],
			};
		}

		return newAttributes;
	},

	isEligible( attributes ) {
		// Run migration if title attribute exists
		return !! attributes.title;
	},
};

export const deprecated = [ v1 ];
