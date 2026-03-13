import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';
import deprecated from './deprecations';
import edit from './edit';
import metadata from './block.json';
import save from './save';
import './style.scss';

registerBlockType( metadata, { deprecated, edit, save, icon: people } );
