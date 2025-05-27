import { registerBlockType } from '@wordpress/blocks';

import deprecated from './deprecation';
import edit from './edit';
import metadata from './block.json';
import save from './save';

registerBlockType( metadata, { deprecated, edit, save } );
