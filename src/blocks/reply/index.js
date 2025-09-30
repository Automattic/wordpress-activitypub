import { registerBlockType } from '@wordpress/blocks';
import { commentReplyLink } from '@wordpress/icons';
import edit from './edit';
import './editor.scss';
const save = () => null;

registerBlockType( 'activitypub/reply', {
	edit,
	save,
	icon: commentReplyLink,
} );
