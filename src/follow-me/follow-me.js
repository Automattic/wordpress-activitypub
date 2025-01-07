import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { ButtonStyle, getPopupStyles } from './button-style';
import { Dialog } from '../shared/dialog';
import { useOptions } from '../shared/use-options';
import './style.scss';

const DEFAULT_PROFILE_DATA = {
	avatar: '',
	webfinger: '@well@hello.dolly',
	name: __( 'Hello Dolly Fan Account', 'activitypub' ),
	url: '#',
};

function getNormalizedProfile( profile ) {
	if ( ! profile ) {
		return DEFAULT_PROFILE_DATA;
	}
	const data = { ...DEFAULT_PROFILE_DATA, ...profile };
	data.avatar = data?.icon?.url;
	return data;
}

function fetchProfile( userId ) {
	const { namespace } = useOptions();
	const fetchOptions = {
		headers: { Accept: 'application/activity+json' },
		path: `/${ namespace }/actors/${ userId }`,
	};
	return apiFetch( fetchOptions );
}

function Profile( {
	profile,
	popupStyles,
	userId,
	buttonText,
	buttonOnly,
	buttonSize,
} ) {
	const { webfinger, avatar, name } = profile;
	// check if webfinger starts with @ and add it if it doesn't
	const webfingerWithAt = webfinger.startsWith( '@' ) ? webfinger : `@${ webfinger }`;

	if ( buttonOnly ) {
		return (
			<div className="activitypub-profile">
				<Follow
					profile={ profile }
					popupStyles={ popupStyles }
					userId={ userId }
					buttonText={ buttonText }
					buttonSize={ buttonSize }
				/>
			</div>
		);
	}

	return (
		<div className="activitypub-profile">
			<img className="activitypub-profile__avatar" src={ avatar } alt={ name } />
			<div className="activitypub-profile__content">
				<div className="activitypub-profile__name">{ name }</div>
				<div className="activitypub-profile__handle" title={ webfingerWithAt }>{ webfingerWithAt }</div>
			</div>
			<Follow
				profile={ profile }
				popupStyles={ popupStyles }
				userId={ userId }
				buttonText={ buttonText }
				buttonSize={ buttonSize }
			/>
		</div>
	);
}

function Follow( {
	profile,
	popupStyles,
	userId,
	buttonText,
	buttonSize,
} ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const title = sprintf( __( 'Follow %s', 'activitypub' ), profile?.name );

	return (
		<>
			<Button
				className="activitypub-profile__follow"
				onClick={ () => setIsOpen( true ) }
				aria-haspopup="dialog"
				aria-expanded={ isOpen }
				aria-label={  __( 'Follow me on the Fediverse', 'activitypub' ) }
				size={ buttonSize }
			>
				{ buttonText }
			</Button>
			{ isOpen && (
				<Modal
				className="activitypub-profile__confirm activitypub__modal"
				onRequestClose={ () => setIsOpen( false ) }
				title={ title }
				aria-label={ title }
				role="dialog"
				>
					<DialogFollow profile={ profile } userId={ userId } />
					<style>{ popupStyles }</style>
			</Modal>
			) }
		</>
	);
}

function DialogFollow( { profile, userId } ) {
	const { namespace } = useOptions();
	const { webfinger } = profile;
	const actionText = __( 'Follow', 'activitypub' );
	const resourceUrl = `/${ namespace }/actors/${userId}/remote-follow?resource=`;
	const copyDescription = __( 'Copy and paste my profile into the search field of your favorite fediverse app or server.', 'activitypub' );
	const webfingerWithAt = webfinger.startsWith( '@' ) ? webfinger : `@${ webfinger }`;

	return <Dialog
		actionText={ actionText }
		copyDescription={ copyDescription }
		handle={ webfingerWithAt }
		resourceUrl={ resourceUrl }
	/>;
}

export default function FollowMe( {
	selectedUser,
	style,
	backgroundColor,
	id,
	useId = false,
	profileData = false,
	buttonOnly = false,
	buttonText = __( 'Follow', 'activitypub' ),
	buttonSize = 'default',
} ) {
	const [ profile, setProfile ] = useState( getNormalizedProfile() );
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const popupStyles = getPopupStyles( style );
	const wrapperProps = useId ? { id } : {};

	useEffect( () => {
		if ( profileData ) {
			setProfile( getNormalizedProfile( profileData ) );
			return;
		}

		fetchProfile( userId ).then( ( data ) => {
			setProfile( getNormalizedProfile( data ) );
		} );
	}, [ userId, profileData ] );

	return (
		<div { ...wrapperProps } className="activitypub-follow-me-block-wrapper">
			<ButtonStyle selector={ `#${ id }` } style={ style } backgroundColor={ backgroundColor } />
			<Profile 
				profile={ profile } 
				userId={ userId } 
				popupStyles={ popupStyles } 
				buttonText={ buttonText }
				buttonOnly={ buttonOnly }
				buttonSize={ buttonSize }
			/>
		</div>
	);
}
