/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl, Placeholder, Spinner, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { useOptions } from '../shared/use-options';
import { useUserOptions } from '../shared/use-user-options';

/**
 * Editor component for Extra Fields block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set attributes.
 * @param {Object}   props.context       Block context.
 * @return {Element} Component element.
 */
export default function Edit( { attributes, setAttributes, context } ) {
	const { selectedUser, maxFields } = attributes;
	const { postId: contextPostId, postType: contextPostType } = context ?? {};
	const [ fields, setFields ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	// Get author ID from context or current post depending on editor.
	const authorId = useSelect(
		( select ) => {
			const editorStore = select( 'core/editor' );
			const coreStore = select( 'core' );

			if ( contextPostId && contextPostType && coreStore ) {
				const editedRecord =
					coreStore.getEditedEntityRecord?.( 'postType', contextPostType, contextPostId ) ?? null;
				if ( editedRecord?.author ) {
					return editedRecord.author;
				}

				const record = coreStore.getEntityRecord?.( 'postType', contextPostType, contextPostId ) ?? null;
				if ( record?.author ) {
					return record.author;
				}
			}

			if ( editorStore && editorStore.getCurrentPostAttribute ) {
				return editorStore.getCurrentPostAttribute( 'author' );
			}

			return null;
		},
		[ contextPostId, contextPostType ]
	);

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

	// Get ActivityPub options
	const { namespace = 'activitypub/1.0', profileUrls = {} } = useOptions();

	// Select profile settings URL based on user type
	const profileUrl = selectedUser === 'blog' ? profileUrls.blog : profileUrls.user;

	const blockProps = useBlockProps( {
		className: 'activitypub-extra-fields-block-wrapper',
	} );

	// Get user options for dropdown
	const userOptions = useUserOptions( {
		withInherit: true,
	} );

	// Fetch extra fields
	useEffect( () => {
		if ( userId === null ) {
			setFields( [] );
			return;
		}

		setIsLoading( true );
		setError( null );

		apiFetch( {
			path: `/${ namespace }/actors/${ userId }`,
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
	}, [ userId, namespace ] );

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

	const settingsPanel = (
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
	);

	// Render placeholder if inherit mode but no author. Keep controls mounted for recovery.
	if ( selectedUser === 'inherit' && ! authorId ) {
		return (
			<>
				{ settingsPanel }
				<div { ...blockProps }>
					<Placeholder label={ __( 'Fediverse Extra Fields', 'activitypub' ) }>
						<p>
							{ __(
								'This block will display extra fields based on the post author when published.',
								'activitypub'
							) }
						</p>
					</Placeholder>
				</div>
			</>
		);
	}

	// Render loading state
	if ( isLoading ) {
		return (
			<div { ...blockProps }>
				<Placeholder label={ __( 'Fediverse Extra Fields', 'activitypub' ) }>
					<Spinner />
				</Placeholder>
			</div>
		);
	}

	// Render error state
	if ( error ) {
		return (
			<div { ...blockProps }>
				<Placeholder label={ __( 'Fediverse Extra Fields', 'activitypub' ) }>
					<p>
						{ sprintf(
							/* translators: %s: Error message */
							__( 'Error loading extra fields: %s', 'activitypub' ),
							error
						) }
					</p>
				</Placeholder>
			</div>
		);
	}

	// Render empty state
	if ( displayFields.length === 0 ) {
		return (
			<>
				{ settingsPanel }
				<div { ...blockProps }>
					<Placeholder label={ __( 'Fediverse Extra Fields', 'activitypub' ) }>
						<p>
							{ __( 'No extra fields found.', 'activitypub' ) }{ ' ' }
							{ profileUrl && (
								<Button
									variant="link"
									onClick={ () => {
										window.location.href = profileUrl;
									} }
								>
									{ __( 'Add fields in your profile settings', 'activitypub' ) }
								</Button>
							) }
						</p>
					</Placeholder>
				</div>
			</>
		);
	}

	return (
		<>
			{ settingsPanel }
			<div { ...blockProps }>
				<dl className="activitypub-extra-fields">
					{ displayFields.map( ( field ) => (
						<div
							key={ `${ field.name }-${ field.value }` }
							className="activitypub-extra-field"
							style={ cardStyle }
						>
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
