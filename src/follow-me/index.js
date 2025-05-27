import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';
import deprecated from './deprecation';
import edit from './edit';
import metadata from './block.json';
import save from './save';

// Register the block.
registerBlockType( metadata, { deprecated, edit, icon: people, save } );
