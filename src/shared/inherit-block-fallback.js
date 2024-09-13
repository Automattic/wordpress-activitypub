import { Card, CardBody } from '@wordpress/components';
import { sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

export function InheritModeBlockFallback( { name } ) {
	const text = sprintf(
		/* translators: %s: block name */
		'This <strong>%s</strong> block will adapt to the page it is on, displaying the associated user on a user archive, or the post author on a single post. It will be <strong>empty</strong> on non-author pages.',
		name
	);

	return (
		<Card>
			<CardBody>{ createInterpolateElement( text, { strong: <strong /> } ) }</CardBody>
		</Card>
	);
}