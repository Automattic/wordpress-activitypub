import { registerBlockType } from '@wordpress/blocks';

import deprecated from './deprecation';
import edit from './edit';
import metadata from './block.json';
import save from './save';
import './style.scss';

registerBlockType( metadata.name, {
	deprecated,
	edit,
	save,
} );
