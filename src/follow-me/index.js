import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';
import deprecated from './deprecation';
import edit from './edit';
import save from './save';

// Register the block.
registerBlockType( 'activitypub/follow-me', {
	deprecated,
	edit,
	icon: people,
	save,
} );
