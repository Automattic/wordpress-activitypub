import { registerBlockType } from '@wordpress/blocks';
import { people } from '@wordpress/icons';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import edit from './edit';
import deprecated from './deprecation';

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

// Register the block.
registerBlockType( 'activitypub/follow-me', {
	edit,
	icon: people,
	save,
	deprecated,
} );
