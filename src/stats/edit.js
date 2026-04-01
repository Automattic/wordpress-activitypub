// eslint-disable-next-line import/no-extraneous-dependencies
import ServerSideRender from '@wordpress/server-side-render';
import { SelectControl, PanelBody, Disabled } from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useUserOptions } from '../shared/use-user-options';

const currentYear = new Date().getFullYear();

/**
 * Generate year options for the selector.
 *
 * @return {Array} Year options.
 */
function getYearOptions() {
	const options = [];
	for ( let y = currentYear; y >= currentYear - 5; y-- ) {
		options.push( { label: String( y ), value: String( y ) } );
	}
	return options;
}

/**
 * Edit component for the ActivityPub Stats block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Set block attributes.
 * @return {JSX.Element} Edit component.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { selectedUser, year, displayMode } = attributes;
	const blockProps = useBlockProps();
	const usersOptions = useUserOptions( {} );

	const displayYear = year || currentYear - 1;

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'activitypub' ) }>
					{ usersOptions.length > 1 && (
						<SelectControl
							label={ __( 'Select User', 'activitypub' ) }
							value={ selectedUser }
							options={ usersOptions }
							onChange={ ( value ) => setAttributes( { selectedUser: value } ) }
						/>
					) }
					<SelectControl
						label={ __( 'Year', 'activitypub' ) }
						value={ String( displayYear ) }
						options={ getYearOptions() }
						onChange={ ( value ) => setAttributes( { year: parseInt( value, 10 ) } ) }
					/>
					<SelectControl
						label={ __( 'Display Mode', 'activitypub' ) }
						value={ displayMode || 'card' }
						options={ [
							{ label: __( 'Card', 'activitypub' ), value: 'card' },
							{ label: __( 'Image', 'activitypub' ), value: 'image' },
						] }
						onChange={ ( value ) => setAttributes( { displayMode: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<Disabled>
				<ServerSideRender block="activitypub/stats" attributes={ { ...attributes, year: displayYear } } />
			</Disabled>
		</div>
	);
}
