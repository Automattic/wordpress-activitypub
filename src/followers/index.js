import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';
import edit from './edit';
const save = () => null;
registerBlockType( 'activitypub/followers', { edit, save, icon: people } );