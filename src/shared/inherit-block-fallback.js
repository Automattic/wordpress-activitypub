import { Card, CardBody } from '@wordpress/components';
import { sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
const enabled = window._activityPubOptions?.enabled;

export function InheritModeBlockFallback( { name } ) {
	let text = sprintf(
		/* translators: %s: block name */
		'This <strong>%s</strong> block will adapt to the page it is on, displaying the user profile associated with a post author (in a loop) or a user archive.',
		name
	);

	if ( ! enabled?.site ) {
		text += ' ' + 'It will be <strong>empty</strong> in other non-author contexts.';
	}

	return (
		<Card>
			<CardBody>{ createInterpolateElement( text, { strong: <strong /> } ) }</CardBody>
		</Card>
	);
}
