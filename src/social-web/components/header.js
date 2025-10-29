/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { wordpress } from '@wordpress/icons';

/**
 * Header component for Social Web editor.
 *
 * @param {Object} props          Component props.
 * @param {Object} props.settings Editor settings.
 * @return {JSX.Element} The Header component.
 */
export default function Header( { settings } ) {
	return (
		<div className="activitypub-social-web-header">
			<div className="activitypub-social-web-header__start">
				<Button
					icon={ wordpress }
					href={ settings.adminUrl }
					className="activitypub-social-web-header__home"
					label={ __( 'Back to dashboard', 'activitypub' ) }
				/>
				<h1 className="activitypub-social-web-header__title">{ __( 'Social Web', 'activitypub' ) }</h1>
			</div>
			<div className="activitypub-social-web-header__end">{ /* Additional header actions can go here */ }</div>
		</div>
	);
}
