import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import { name } from './block.json';
import './style.scss';

registerBlockType( name, { edit } );