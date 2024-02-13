import { Card, TextControl, Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { MediaUpload } from '@wordpress/media-utils';
import { useState, useRef } from "@wordpress/element";
import useProfile from "../shared/use-profile";
import './style.scss';

function Avatar( { url } ) {
	return (
		<div className="activitypub-profile-editor__avatar">
			{ url && <img src={ url } alt="" /> }
		</div>
	);
}

// Mastodon says: "This image will be downscaled to 1500x500"
function Header( { url, isEditing, update } ) {
	const ref = useRef();
	const uploadButton = ( { open } ) => (
		<Button ref={ ref } onClick={ open } className="activitypub-hover-button" isPrimary>
			{ __( 'Upload Header' ) }
		</Button>
	);

	return (
		<div className="activitypub-profile-editor__header">
			{ url && <img src={ url } alt="" /> }
			<MediaUpload
				title={ __( 'Select or Upload Header', 'activitypub' ) }
				onClose={ () => ref.current.blur() }
				onSelect={ ( media ) => update( media.url, media.id ) }
				type="image"
				value={ 0 }
				render={ uploadButton }
			/>
		</div>
	);
}

function Name( { name, update } ) {
	return (
		<TextControl className="activitypub-profile-editor__name"
			value={ name }
			onChange={ update }
		/>
	);
}

function Description( { description } ) {
	return (
		<div className="activitypub-profile-editor__description">
			{ description && <div dangerouslySetInnerHTML={ { __html: description } } /> }
		</div>
	);
}

export default function ProfileEditor() {
	const userId = 0;
	const { profile, isDirty, isLoading, error, updateProfile, saveProfile, resetProfile } = useProfile( userId );
	const { avatar, header, name, handle, summary } = profile;
	const [ isEditing, setIsEditing ] = useState( false );

	function cancel() {
		setIsEditing( false );
		resetProfile();
	}

	function save() {
		saveProfile();
		setIsEditing( false );
	}

	function updateFor( name ) {
		return ( value, ...args ) => updateProfile( name, value, ...args );
	}

	return (
		<Card className="activitypub-profile-editor">
			{ isDirty && (
				<div className="activitypub-profile-editor__actions">
					<Button isPrimary onClick={ save }>Save</Button>
					<Button onClick={ cancel }>Cancel</Button>
				</div>
				)
			}

			<Header url={ header } isEditing={ isEditing } update={ updateFor( 'header' ) } />
			<Avatar url={ avatar } isEditing={ isEditing } update={ updateFor( 'avatar' ) } />
			<Name name={ name } handle={ handle } isEditing={ isEditing } update={ updateFor( 'name' ) } />
			<Description description={ summary } isEditing={ isEditing } update={ updateFor( 'description' ) } />
		</Card>
	);
}