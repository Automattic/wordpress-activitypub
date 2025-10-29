/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Content component for Social Web editor.
 *
 * @param {Object} props            Component props.
 * @param {string} props.activeView The currently active view.
 * @param {Object} props.settings   Editor settings.
 * @return {JSX.Element} The Content component.
 */
export default function Content( { activeView, settings } ) {
	return (
		<div className="activitypub-social-web-content">
			<div className="activitypub-social-web-content__inner">
				<h2>{ __( 'Welcome to Social Web', 'activitypub' ) }</h2>
				<p>
					{ __(
						'This is the Social Web interface. Content will be displayed here based on your navigation.',
						'activitypub'
					) }
				</p>
				<p>
					{ __( 'Active view:', 'activitypub' ) } <strong>{ activeView }</strong>
				</p>
			</div>
		</div>
	);
}
