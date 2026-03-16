import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { loop as icon } from '@wordpress/icons';
import edit from './edit';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata, {
	icon,
	edit,
	save: () => <InnerBlocks.Content />,
} );
