import useProfile from "../shared/use-profile";

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

function Name( { name, handle } ) {
	return (
		<div className="activitypub-profile-editor__name">
			{ name && <h1>{ name }</h1> }
			{ handle && <h2>{ handle }</h2> }
		</div>
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

	return (
		<div>
			<Header url={ header } />
			<Avatar url={ avatar } />
			<Name name={ name } handle={ handle } />
			<Description description={ summary } />
		</div>
	);
}