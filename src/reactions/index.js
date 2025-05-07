import { registerBlockType } from '@wordpress/blocks';

import edit from './edit';
import metadata from './block.json';
import './style.scss';
import { deprecated } from './deprecation';

registerBlockType( metadata.name, {
    edit,
    deprecated
});
