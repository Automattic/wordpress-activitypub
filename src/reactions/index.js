import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, { edit } );