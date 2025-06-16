import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Save function for the Followers block.
 *
 * @return {JSX.Element} Element to render.
 */
export default function save() {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );

	return <div { ...innerBlocksProps } />;
}
