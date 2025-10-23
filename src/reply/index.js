import { registerBlockType, createBlock } from '@wordpress/blocks';
import { commentReplyLink } from '@wordpress/icons';
import edit from './edit';
import './editor.scss';
const save = () => null;

registerBlockType( 'activitypub/reply', {
	edit,
	save,
	icon: commentReplyLink,
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/embed' ],
				transform: ( attributes ) => {
					return createBlock( 'activitypub/reply', {
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
