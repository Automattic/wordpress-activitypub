import { useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, cancelCircleFilled } from '@wordpress/icons';
import { Dialog } from '../shared/dialog';
import { useRemoteUser } from '../shared/use-remote-user';
import { useOptions } from '../shared/use-options';
import './style.scss';

function DialogReply( { selectedComment, commentId } ) {
	const { namespace } = useOptions();
	const actionText = __( 'Reply', 'activitypub' );
	const resourceUrl = `/${ namespace }/comments/${commentId}/remote-reply?resource=`;
	const copyDescription = __( 'Copy and paste the Comment URL into the search field of your favorite fediverse app or server.', 'activitypub' );

	return <Dialog
		actionText={ actionText }
		copyDescription={ copyDescription }
		handle={ selectedComment }
		resourceUrl={ resourceUrl }
		myProfile={ __( 'Original Comment URL', 'activitypub' ) }
		rememberProfile={true}
	/>;
}

function RemoteUser( { profileURL, template, commentURL, deleteRemoteUser } ) {
	const opener = () => {
		const url = template.replace( '{uri}', commentURL );
		window.open( url, '_blank' );
	}

	return (
		<>
			<Button variant="link" className="comment-reply-link activitypub-remote-reply__button" onClick={ opener } >
				{ sprintf( __( 'Reply as %s', 'activitypub' ), profileURL ) }
			</Button>

			<Button
				className="activitypub-remote-profile-delete"
				onClick={ deleteRemoteUser }
				title={ __( 'Delete Remote Profile', 'activitypub' ) }
			>
				<Icon icon={ cancelCircleFilled } size={ 18 } />
			</Button>
		</>
	);

}

export default function RemoteReply( { selectedComment, commentId } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const title = __( 'Remote Reply', 'activitypub' );
	const { profileURL, template, deleteRemoteUser } = useRemoteUser();
	const hasProfile = profileURL && template;

	return(
		<>
			{ hasProfile ? (
				<RemoteUser profileURL={ profileURL } template={ template } commentURL={ selectedComment } deleteRemoteUser={ deleteRemoteUser } />
			) : (
				<Button variant="link" className="comment-reply-link activitypub-remote-reply__button" onClick={ () => setIsOpen( true ) } >
					{ __( 'Reply on the Fediverse', 'activitypub' ) }
				</Button>
			) }

			{ isOpen && (
				<Modal
					className="activitypub-remote-reply__modal activitypub__modal"
					onRequestClose={ () => setIsOpen( false ) }
					title={ title }
					>
					<DialogReply selectedComment={ selectedComment } commentId={ commentId } />
				</Modal>
			) }
		</>
	)
}
