import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';
import edit from './edit';

// Register the block
registerBlockType( 'activitypub/follow-me', {
	edit,
	icon: people,
	save: () => null,
} );
