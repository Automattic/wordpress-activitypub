import { registerBlockType } from '@wordpress/blocks';
import { commentReplyLink } from '@wordpress/icons';
import edit from './edit';
const save = () => null;

const migrate = ( attributes ) => {
	if ( attributes.embedPost === undefined ) {
		attributes.embedPost = false;
	}
	return attributes;
};

registerBlockType( 'activitypub/reply', {
	edit,
	save,
	icon: commentReplyLink,
	attributes: {
		embedPost: {
			type: 'boolean',
			default: true
		}
	},
	migrate,
} );
