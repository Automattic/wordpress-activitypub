import { Card, CardBody } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { useOptions } from './use-options';

export function InheritModeBlockFallback( { name } ) {
	const { enabled } = useOptions();
	const nonAuthorExtra = enabled?.site ? '' : __( 'It will be empty in other non-author contexts.', 'activitypub' );
	const text = sprintf(
		/* translators: %1$s: block name, %2$s: extra information for non-author context */
		__( 'This <strong>%1$s</strong> block will adapt to the page it is on, displaying the user profile associated with a post author (in a loop) or a user archive. %2$s', 'activitypub' ),
		name,
		nonAuthorExtra
	).trim();

	return (
		<Card>
			<CardBody>{ createInterpolateElement( text, { strong: <strong /> } ) }</CardBody>
		</Card>
	);
}
