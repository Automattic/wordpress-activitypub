import { useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Dialog } from '../shared/dialog';
import './style.scss';
const { namespace } = window._activityPubOptions;


function DialogReply( { selectedComment, commentId } ) {
	const actionText = __( 'Reply', 'activitypub' );
	const resourceUrl = `/${ namespace }/comments/${commentId}/remote-reply?resource=`;
	const copyDescription = __( 'Copy and paste the Comment URL into the search field of your favorite fediverse app or server.', 'activitypub' );

	return <Dialog
		actionText={ actionText }
		copyDescription={ copyDescription }
		handle={ selectedComment }
		resourceUrl={ resourceUrl }
	/>;
}

export default function RemoteReply( { selectedComment, commentId } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const title = __( 'Remote Reply', 'activitypub' );

	return(
		<>
			<Button isLink className="comment-reply-link activitypub-remote-reply__button" onClick={ () => setIsOpen( true ) } >
				{ __( 'Reply on the Fediverse', 'activitypub' ) }
			</Button>
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
