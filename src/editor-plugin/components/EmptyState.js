import { Icon, media as mediaIcon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Empty state component for attachment selector.
 *
 * @returns {React.JSX.Element} The empty state component.
 */
const EmptyState = () => {
	return (
		<div className="activitypub-attachment-selector__empty">
			<Icon icon={ mediaIcon } size={ 24 } />
			<p>{ __( 'No attachments found for this post.', 'activitypub' ) }</p>
		</div>
	);
};

export default EmptyState;
