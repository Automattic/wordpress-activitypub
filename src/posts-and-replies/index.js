import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import edit from './edit';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata, {
	edit,
	save: () => <InnerBlocks.Content />,
} );
