import { useBlockProps, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const TEMPLATE = [ [ 'core/post-title', { isLink: true } ], [ 'core/post-date' ], [ 'core/post-excerpt' ] ];

const ALLOWED_BLOCKS = [
	'core/post-title',
	'core/post-date',
	'core/post-excerpt',
	'core/post-content',
	'core/post-featured-image',
	'core/post-terms',
	'core/post-author',
	'core/post-author-name',
	'core/post-author-biography',
	'core/spacer',
	'core/separator',
	'core/group',
	'core/columns',
	'core/row',
	'core/stack',
];

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
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wp-block-post' },
		{
			template: TEMPLATE,
			allowedBlocks: ALLOWED_BLOCKS,
		}
	);

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

			<div className="ap-tabs" role="tablist">
				<span className="ap-tabs__tab is-active">{ __( 'Posts', 'activitypub' ) }</span>
				<span className="ap-tabs__tab">{ __( 'Posts & Replies', 'activitypub' ) }</span>
			</div>

			<ul className="wp-block-post-template is-layout-flow wp-block-post-template-is-layout-flow">
				<li { ...innerBlocksProps } />
			</ul>
		</div>
	);
}
