import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';

/**
 * Block edit function.
 *
 * @returns {JSX.Element} The block's edit component.
 */
import edit from './edit';

/**
 * Block save function (returns null, as this block is dynamic).
 *
 * @returns {null}
 */
const save = () => null;

/**
 * Registers the block.
 */
registerBlockType( 'activitypub/follow-me', { edit, save, icon: people } );
