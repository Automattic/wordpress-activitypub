/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl, Placeholder, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { useUserOptions } from '../shared/use-user-options';

/**
 * Editor component for Extra Fields block.
 *
 * @param {Object} props               Component props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set attributes.
 * @param {Object} props.context       Block context.
 * @return {Element} Component element.
 */
export default function Edit( { attributes, setAttributes, context } ) {
	const { selectedUser, maxFields } = attributes;
	const [ fields, setFields ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const blockProps = useBlockProps( {
		className: 'activitypub-extra-fields-block-wrapper',
	} );

	// Get author ID from context
	const authorId = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		if ( ! editor ) {
			return null;
		}
		return editor.getCurrentPostAttribute( 'author' );
	}, [] );

	// Get user options for dropdown
	const userOptions = useUserOptions( {
		withInherit: true,
	} );

	// Determine which user ID to fetch
	const getUserId = () => {
		if ( selectedUser === 'blog' ) {
			return 0;
		}

		if ( selectedUser === 'inherit' ) {
			if ( authorId ) {
				return authorId;
			}
			return null;
		}

		return selectedUser;
	};

	const userId = getUserId();

	// Fetch extra fields
	useEffect( () => {
		if ( userId === null ) {
			setFields( [] );
			return;
		}

		setIsLoading( true );
		setError( null );

		apiFetch( {
			path: `/activitypub/1.0/actors/${ userId }`,
			headers: { Accept: 'application/activity+json' },
		} )
			.then( ( actor ) => {
				// Extract fields from attachment array
				const attachments = actor.attachment || [];
				// Filter to only PropertyValue types (the main format)
				const propertyValues = attachments.filter( ( item ) => item.type === 'PropertyValue' );
				setFields( propertyValues );
				setIsLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message );
				setIsLoading( false );
			} );
	}, [ userId ] );

	// Apply max fields limit for preview
	const displayFields = maxFields > 0 ? fields.slice( 0, maxFields ) : fields;

	// Extract background color for cards style
	const getCardStyle = () => {
		const isCardsStyle = attributes.className?.includes( 'is-style-cards' );
		if ( ! isCardsStyle ) {
			return {};
		}

		// Get background color from block attributes
		const style = attributes.style || {};
		const backgroundColor = attributes.backgroundColor;
		const customColor = style.color?.background;

		if ( backgroundColor ) {
			return {
				backgroundColor: `var(--wp--preset--color--${ backgroundColor })`,
			};
		} else if ( customColor ) {
			return {
				backgroundColor: customColor,
			};
		}

		return {};
	};

	const cardStyle = getCardStyle();

	// Render placeholder if inherit mode but no author
	if ( selectedUser === 'inherit' && ! authorId ) {
		return (
			<div { ...blockProps }>
				<Placeholder label={ __( 'Fediverse Profile Fields', 'activitypub' ) } icon="list-view">
					<p>
						{ __(
							'This block will display extra fields based on the post author when published.',
							'activitypub'
						) }
					</p>
				</Placeholder>
			</div>
		);
	}

	// Render loading state
	if ( isLoading ) {
		return (
			<div { ...blockProps }>
				<Placeholder label={ __( 'Fediverse Profile Fields', 'activitypub' ) } icon="list-view">
					<Spinner />
				</Placeholder>
			</div>
		);
	}

	// Render error state
	if ( error ) {
		return (
			<div { ...blockProps }>
				<Placeholder label={ __( 'Fediverse Profile Fields', 'activitypub' ) } icon="list-view">
					<p>
						{ __( 'Error loading extra fields:', 'activitypub' ) } { error }
					</p>
				</Placeholder>
			</div>
		);
	}

	// Render empty state
	if ( displayFields.length === 0 ) {
		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Settings', 'activitypub' ) } initialOpen={ true }>
						<SelectControl
							label={ __( 'User', 'activitypub' ) }
							value={ selectedUser }
							options={ userOptions }
							onChange={ ( value ) => setAttributes( { selectedUser: value } ) }
						/>
						<RangeControl
							label={ __( 'Maximum Fields', 'activitypub' ) }
							value={ maxFields }
							onChange={ ( value ) => setAttributes( { maxFields: value } ) }
							min={ 0 }
							max={ 20 }
							help={ __( 'Limit the number of fields displayed. 0 = show all.', 'activitypub' ) }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<Placeholder label={ __( 'Fediverse Profile Fields', 'activitypub' ) } icon="list-view">
						<p>{ __( 'No extra fields found. Add fields in your profile settings.', 'activitypub' ) }</p>
					</Placeholder>
				</div>
			</>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'activitypub' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'User', 'activitypub' ) }
						value={ selectedUser }
						options={ userOptions }
						onChange={ ( value ) => setAttributes( { selectedUser: value } ) }
					/>
					<RangeControl
						label={ __( 'Maximum Fields', 'activitypub' ) }
						value={ maxFields }
						onChange={ ( value ) => setAttributes( { maxFields: value } ) }
						min={ 0 }
						max={ 20 }
						help={ __( 'Limit the number of fields displayed. 0 = show all.', 'activitypub' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<dl className="activitypub-extra-fields">
					{ displayFields.map( ( field, index ) => (
						<div key={ `${field.name}-${field.value}` } className="activitypub-extra-field" style={ cardStyle }>
							<dt>{ field.name }</dt>
							<dd
								dangerouslySetInnerHTML={ {
									__html: field.value,
								} }
							/>
						</div>
					) ) }
				</dl>
			</div>
		</>
	);
}
