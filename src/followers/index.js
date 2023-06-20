import { registerBlockType } from '@wordpress/blocks';
import edit from './edit';
const save = () => null;
registerBlockType( 'activitypub/followers', { edit, save } );