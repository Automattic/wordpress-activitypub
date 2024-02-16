import { Card, TextControl, Button, Modal } from "@wordpress/components";
import { cancelCircleFilled, check, Icon } from '@wordpress/icons';
import { __ } from "@wordpress/i18n";
import { MediaUpload } from '@wordpress/media-utils';
import { useState, useRef, useEffect } from "@wordpress/element";
import useProfile from "../shared/use-profile";
import './style.scss';

const HTMLRegExp = /<\/?[a-z][^>]*?>/gi;

function stripTags( html ) {
	if ( ! html ) {
		return '';
	}

	return html.replace( HTMLRegExp, '' );
}

function TextField( { update, value, className } ) {
	const [ isEditing, setIsEditing ] = useState( false );
	const classes = ! isEditing ? `${ className } not-editing` : className;

	return (
		<div className={ classes }>
			<TextControl
				value={ value }
				onChange={ update }
				onBlur={ () => setIsEditing( false ) }
				onFocus={ () => setIsEditing( true) }
			/>
		</div>
	);
}

function ImageField( { update, value, className, buttonText, mediaText } ) {
	const ref = useRef();
	const uploadButton = ( { open } ) => (
		<Button ref={ ref } onClick={ open } className="activitypub-hover-button" isPrimary>
			{ buttonText }
		</Button>
	);

	return (
		<div className={ className }>
			{ value && <img src={ value } alt="" /> }
			<MediaUpload
				title={ mediaText }
				onClose={ () => ref.current.blur() }
				onSelect={ ( media ) => update( media.url, media.id ) }
				type="image"
				value={ 0 }
				render={ uploadButton }
			/>
		</div>
	);
}

function Avatar( { url, update } ) {
	return (
		<ImageField
			className="activitypub-profile-editor__avatar"
			buttonText={ __( 'Upload Avatar', 'activitypub' ) }
			mediaText={ __( 'Select or Upload Avatar', 'activitypub' ) }
			value={ url }
			update={ update }
		/>
	);
}

// Mastodon says: "This image will be downscaled to 1500x500"
function Header( { url, update } ) {

	// use ImageField
	return (
		<ImageField
			className="activitypub-profile-editor__header"
			buttonText={ __( 'Upload Header', 'activitypub' ) }
			mediaText={ __( 'Select or Upload Header', 'activitypub' ) }
			value={ url }
			update={ update }
		/>
	);
}

function Name( { name, update } ) {
	// use TextField
	return (
		<TextField className="activitypub-profile-editor__name" value={ name } update={ update } />
	);
}

function Description( { description, update } ) {
	const strippedDescription = stripTags( description );
	const desriptionWithFallback = strippedDescription || __( 'No description provided.', 'activitypub' );

	return (
		<TextField className="activitypub-profile-editor__description" value={ desriptionWithFallback } update={ update } />
	);
}

export default function ProfileEditor() {
	const userId = 0;
	const { profile, isDirty, isLoading, error, updateProfile, saveProfile, resetProfile } = useProfile( userId );
	const { avatar, header, name, handle, summary } = profile;

	function updateFor( name ) {
		return ( value, ...args ) => updateProfile( name, value, ...args );
	}

	useEffect(() => {
		const handleBeforeUnload = (event) => {
			if ( isDirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};

		window.addEventListener( 'beforeunload', handleBeforeUnload );

		return () => {
				window.removeEventListener( 'beforeunload', handleBeforeUnload );
		};
}, [ isDirty ] );

	if ( error ) {
		return (
			<Card className="activitypub-profile-editor">
				<p>{ __( 'There was an error loading your profile.', 'activitypub' ) }</p>
			</Card>
		);
	}

	return (
		<Card className="activitypub-profile-editor">
			{ isDirty && (
				<div className="activitypub-profile-editor__actions">
					<Button onClick={ resetProfile }>
						<Icon icon={ cancelCircleFilled } />
					</Button>
					<Button isPrimary onClick={ saveProfile }>
						<Icon icon={ check } />
					</Button>
				</div>
				)
			}

			<Header url={ header } update={ updateFor( 'header' ) } />
			<Avatar url={ avatar } update={ updateFor( 'avatar' ) } />
			<div className='activitypub-profile-editor__text-wrap'>
				<Name name={ name } handle={ handle } update={ updateFor( 'name' ) } />
				<Description description={ summary } update={ updateFor( 'summary' ) } />
			</div>
		</Card>
	);
}