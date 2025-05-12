import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Save component for the Follow Me block.
 *
 * This component ensures that inner blocks (the button) are properly saved.
 *
 * @return {JSX.Element|null} Save component.
 */
function save() {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save( {
		className: 'activitypub-profile__button-wrapper',
	} );

	return (
		<div { ...blockProps }>
			<div { ...innerBlocksProps } />
		</div>
	);
}

export default save;
