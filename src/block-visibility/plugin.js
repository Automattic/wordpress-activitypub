import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Adds a "Fediverse" panel to the block inspector for all blocks,
 * allowing users to control whether a block is included in federated content.
 */
const withFediverseVisibility = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { attributes, setAttributes, isSelected } = props;

		// Get the current fediverse visibility state (default: true).
		const metadata = attributes?.metadata || {};
		const blockVisibility = metadata?.blockVisibility || {};
		const isFediverseVisible = blockVisibility?.fediverse !== false;

		/**
		 * Update the fediverse visibility in block metadata.
		 *
		 * @param {boolean} value Whether the block should be visible on the Fediverse.
		 */
		const onChange = ( value ) => {
			const updatedBlockVisibility = { ...blockVisibility };

			if ( value ) {
				// Remove the fediverse key when true (default state).
				delete updatedBlockVisibility.fediverse;
			} else {
				updatedBlockVisibility.fediverse = false;
			}

			// Clean up: if blockVisibility is empty, remove it from metadata.
			const updatedMetadata = { ...metadata };
			if ( Object.keys( updatedBlockVisibility ).length === 0 ) {
				delete updatedMetadata.blockVisibility;
			} else {
				updatedMetadata.blockVisibility = updatedBlockVisibility;
			}

			// Clean up: if metadata is empty, remove it entirely.
			if ( Object.keys( updatedMetadata ).length === 0 ) {
				setAttributes( { metadata: undefined } );
			} else {
				setAttributes( { metadata: updatedMetadata } );
			}
		};

		return (
			<>
				<BlockEdit { ...props } />
				{ isSelected && (
					<InspectorControls>
						<PanelBody title={ __( 'Fediverse ⁂', 'activitypub' ) } initialOpen={ false }>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Share to the Fediverse', 'activitypub' ) }
								help={
									isFediverseVisible
										? __(
												'This block will be shared to Mastodon and other Fediverse platforms.',
												'activitypub'
										  )
										: __( 'This block will only be visible on your site.', 'activitypub' )
								}
								checked={ isFediverseVisible }
								onChange={ onChange }
							/>
						</PanelBody>
					</InspectorControls>
				) }
			</>
		);
	};
}, 'withFediverseVisibility' );

addFilter( 'editor.BlockEdit', 'activitypub/block-visibility', withFediverseVisibility );
