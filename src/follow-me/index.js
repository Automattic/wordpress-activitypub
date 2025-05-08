import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';
import edit from './edit';
const save = () => null;
registerBlockType( 'activitypub/follow-me', { edit, save, icon: people } );