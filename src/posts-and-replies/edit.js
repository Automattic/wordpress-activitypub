import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Editor component for Posts and Replies block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set attributes.
 * @return {Element} Component element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { postsPerPage } = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'activitypub' ) }>
					<RangeControl
						label={ __( 'Posts per page', 'activitypub' ) }
						value={ postsPerPage }
						onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
						min={ 1 }
						max={ 50 }
						__next40pxDefaultSize
					/>
				</PanelBody>
			</InspectorControls>

			<Placeholder
				icon="admin-post"
				label={ __( 'Posts and Replies', 'activitypub' ) }
				instructions={ __(
					'This block displays posts with tabs for "Posts" (excluding replies) and "Posts & Replies". It works on author archive pages.',
					'activitypub'
				) }
			/>
		</div>
	);
}
