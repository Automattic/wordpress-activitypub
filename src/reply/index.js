import { registerBlockType, createBlock } from '@wordpress/blocks';
import { commentReplyLink } from '@wordpress/icons';
import edit from './edit';
const save = () => null;

registerBlockType( 'activitypub/reply', {
	edit,
	save,
	icon: commentReplyLink,
	deprecated: [
		{
			attributes: {
				url: {
					type: 'string'
				},
				embedPost: {
					type: 'boolean',
					default: false
				}
			},
			save,
			migrate( attributes ) {
				console.log( 'migrate', attributes );
				return {
					...attributes,
					embedPost: false // Preserve false for existing blocks
				};
			},
			isEligible: ( attributes ) => {
				return attributes.embedPost === undefined;
			}
		}
	]
} );
