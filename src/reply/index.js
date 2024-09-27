import { registerBlockType } from '@wordpress/blocks';
import { commentReplyLink } from '@wordpress/icons';
import edit from './edit';
const save = () => null;
registerBlockType( 'activitypub/reply', { edit, save, icon: commentReplyLink } );
