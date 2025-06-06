import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * Save component for the ActivityPub Reply block.
 *
 * @param {Object} props - Component props.
 * @param {Object} props.attributes - Block attributes.
 * @return {JSX.Element} Element to render.
 */
export default function save( { attributes } ) {
	const { url, embedPost } = attributes;
	const blockProps = useBlockProps.save( {
		'aria-label': __( 'Reply', 'activitypub' ),
		class: 'activitypub-reply-block',
		'data-in-reply-to': url,
	} );

	// Setup inner blocks props for saving.
	const innerBlocksProps = useInnerBlocksProps.save( { className: 'activitypub-embed-container' } );

	return (
		<div { ...blockProps }>
			{ embedPost && <div { ...innerBlocksProps } /> }

			{ ! embedPost && url && (
				<p>
					<a
						title={ __( 'This post is a response to the referenced content.', 'activitypub' ) }
						aria-label={ __( 'This post is a response to the referenced content.', 'activitypub' ) }
						href={ url }
						className="u-in-reply-to"
						target="_blank"
						rel="noreferrer"
					>
						{ '↬' + url.replace( /^https?:\/\//, '' ) }
					</a>
				</p>
			) }
		</div>
	);
}
