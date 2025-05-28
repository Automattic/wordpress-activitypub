import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';
import edit from './edit';
import metadata from './block.json';
import './style.scss';

const save = () => null;

registerBlockType( metadata, { edit, save, icon: people } );
