import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component for Posts and Replies block.
 *
 * @return {Element} Component element.
 */
export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="admin-post"
				label={ __( 'Posts and Replies', 'activitypub' ) }
				instructions={ __(
					'Displays a tab bar to filter between "Posts" (excluding replies) and "Posts & Replies" on author archives. Place above a Query Loop block with "Inherit query from template" enabled.',
					'activitypub'
				) }
			/>
		</div>
	);
}
