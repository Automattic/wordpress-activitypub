import { registerBlockType, createBlock } from '@wordpress/blocks';
import { quote } from '@wordpress/icons';
import edit from './edit';
import './editor.scss';
const save = () => null;

registerBlockType( 'activitypub/quote', {
	edit,
	save,
	icon: quote,
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/embed' ],
				transform: ( attributes ) => {
					return createBlock( 'activitypub/quote', {
						url: attributes.url || '',
						embedPost: true,
					} );
				},
			},
		],
		to: [
			{
				type: 'block',
				blocks: [ 'core/embed' ],
				transform: ( attributes ) => {
					return createBlock( 'core/embed', {
						url: attributes.url || '',
					} );
				},
			},
		],
	},
} );
