/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import './style.scss';

/**
 * Register the Extra Fields block.
 *
 * This block uses server-side rendering, so the save function returns null.
 */
registerBlockType( metadata.name, {
	edit,
	save: () => null,
} );
