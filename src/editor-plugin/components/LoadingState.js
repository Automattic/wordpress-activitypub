import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Loading state component for attachment selector.
 *
 * @returns {React.JSX.Element} The loading state component.
 */
const LoadingState = () => {
	return (
		<div className="activitypub-attachment-selector__loading">
			<Spinner />
			<p>{ __( 'Loading attachments...', 'activitypub' ) }</p>
		</div>
	);
};

export default LoadingState;
