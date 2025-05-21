import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

/**
 * @typedef {Object} InnerBlocks
 * @property {function(): JSX.Element} Content - The InnerBlocks.Content component.
 */

/**
 * Save function for the reactions block.
 *
 * With server-side rendering via render.php, we only need to output
 * the InnerBlocks content and a placeholder div.
 *
 * @return {JSX.Element} React element to save.
 */
export default function save() {
	return (
		<div { ...useBlockProps.save() }>
			<InnerBlocks.Content />
		</div>
	);
}
