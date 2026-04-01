// eslint-disable-next-line import/no-extraneous-dependencies
import ServerSideRender from '@wordpress/server-side-render';
import { SelectControl, PanelBody, Disabled, ExternalLink, Button, TextControl } from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
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
 * Build the full URL for the stats image-url endpoint.
 *
 * @param {string} selectedUser The selected user ID.
 * @param {number} displayYear  The year to display.
 * @return {string} The full URL, or empty string if template unavailable.
 */
function getImageUrlEndpoint( selectedUser, displayYear ) {
	const template = window._activityPubOptions?.statsImageUrlEndpoint || '';
	if ( ! template ) {
		return '';
	}
	const userId = ! selectedUser || selectedUser === 'blog' ? 0 : selectedUser;
	return template.replace( '{user_id}', userId ).replace( '{year}', displayYear );
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
	const { selectedUser, year } = attributes;
	const blockProps = useBlockProps( {
		style: {
			border: 'none',
			borderRadius: undefined,
			padding: undefined,
			margin: undefined,
			background: undefined,
			backgroundColor: undefined,
			color: undefined,
		},
	} );
	const usersOptions = useUserOptions( {} );
	const [ copied, setCopied ] = useState( false );

	// Set the selected user to the first available option when no user is selected yet.
	useEffect( () => {
		if ( selectedUser || ! usersOptions.length ) {
			return;
		}
		setAttributes( { selectedUser: usersOptions[ 0 ].value } );
	}, [ usersOptions ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const displayYear = year || currentYear - 1;
	const [ imageUrl, setImageUrl ] = useState( '' );

	// Fetch the resolved image URL (cached file or REST endpoint).
	const fetchImageUrl = useCallback( () => {
		const endpoint = getImageUrlEndpoint( selectedUser || 'blog', displayYear );
		if ( ! endpoint ) {
			return;
		}
		apiFetch( { url: endpoint } )
			.then( ( response ) => setImageUrl( response.url || '' ) )
			.catch( () => setImageUrl( '' ) );
	}, [ selectedUser, displayYear ] );

	useEffect( () => {
		fetchImageUrl();
	}, [ fetchImageUrl ] );

	const handleCopy = () => {
		navigator.clipboard.writeText( imageUrl ).then( () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} );
	};

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
				</PanelBody>
				{ imageUrl && (
					<PanelBody title={ __( 'Share Image', 'activitypub' ) } initialOpen={ false }>
						<p className="description">
							{ __( 'Use this URL to share your stats as an image on social media.', 'activitypub' ) }
						</p>
						<TextControl
							__nextHasNoMarginBottom
							value={ imageUrl }
							readOnly
							onClick={ ( e ) => e.target.select() }
						/>
						<div style={ { display: 'flex', gap: '8px', alignItems: 'center' } }>
							<Button variant="secondary" onClick={ handleCopy }>
								{ copied ? __( 'Copied!', 'activitypub' ) : __( 'Copy URL', 'activitypub' ) }
							</Button>
							<ExternalLink href={ imageUrl }>{ __( 'Preview', 'activitypub' ) }</ExternalLink>
						</div>
					</PanelBody>
				) }
			</InspectorControls>

			<Disabled>
				<ServerSideRender block="activitypub/stats" attributes={ { ...attributes, year: displayYear } } />
			</Disabled>
		</div>
	);
}
