import { registerBlockType } from '@wordpress/blocks';
import { commentReplyLink } from '@wordpress/icons';
import edit from './edit';
import save from './save';
import './editor.scss';

registerBlockType( 'activitypub/reply', {
	edit,
	save,
	icon: commentReplyLink,
} );
