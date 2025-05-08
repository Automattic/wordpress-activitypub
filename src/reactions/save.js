import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

export default function save() {
	return (
		<>
			<InnerBlocks.Content />
			<div className="activitypub-reactions-block"></div>
		</>
	);
}
