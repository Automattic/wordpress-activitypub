import { Card, TextControl, Button } from "@wordpress/components";
import { useState } from "@wordpress/element";
import useProfile from "../shared/use-profile";
import './style.scss';

function Avatar( { url } ) {
	return (
		<div className="activitypub-profile-editor__avatar">
			{ url && <img src={ url } alt="" /> }
		</div>
	);
}

function Header( { url } ) {
	return (
		<div className="activitypub-profile-editor__header">
			{ url && <img src={ url } alt="" /> }
		</div>
	);
}

function Name( { name, setName } ) {
	return (
		<TextControl className="activitypub-profile-editor__name"
			value={ name }
			onChange={ setName }
		/>


	);
}

function Description( { description } ) {
	return (
		<div className="activitypub-profile-editor__description">
			{ description && <p>{ description }</p> }
		</div>
	);
}

export default function ProfileEditor() {
	const userId = 0;
	const { profile, isLoading, error, updateProfile, saveProfile, resetProfile } = useProfile( userId );
	const { avatar, header, name, handle, summary } = profile;
	const [ isEditing, setIsEditing ] = useState( false );

	function update( field ) {
		return ( value ) => updateProfile( field, value );
	}

	function cancel() {
		setIsEditing( false );
		resetProfile();
	}

	return (
		<Card className="activitypub-profile-editor">
			<div className="activitypub-profile-editor__actions">
			{ ! isEditing
				&& (
					<Button isPrimary onClick={ () => setIsEditing( true ) }>Edit</Button>
				) || (
				<>
					<Button isPrimary onClick={ saveProfile }>Save</Button>
					<Button onClick={ cancel }>Cancel</Button>
				</>
			 )
			}
			</div>

			<Header url={ header } />
			<Avatar url={ avatar } />
			<Name name={ name } handle={ handle } setName={ update( 'name' ) } />
			<Description description={ summary } />
		</Card>
	);
}