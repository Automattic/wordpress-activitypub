/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { __unstableStripHTML as stripHTML, safeHTML } from '@wordpress/dom';
import type { Field } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { FeedPost } from '../../../types';
import { useObjectType } from '../../../contexts/object-type-context';
import './style.scss';

/**
 * Helper to get the plain text value from a post.
 *
 * @param item The feed post
 * @return Plain text content
 */
function getPlainTextValue( item: FeedPost ): string {
	// `stripHTML()` returns `textContent`, which is already decoded -- decoding it again
	// would collapse a second level of entities and turn a literal `&amp;` into `&`.
	return stripHTML( item.excerpt?.rendered || item.content?.rendered || '' );
}

/**
 * Content renderer component that handles the display logic.
 *
 * @param props      Component props
 * @param props.item The feed post to render
 * @return Rendered content
 */
function ContentRenderer( { item }: { item: FeedPost } ): ReactNode {
	const { getObjectTypeName, isLoading } = useObjectType();

	// Get the object type name from the cached map
	const objectTypeId: number | undefined = item.ap_object_type?.[ 0 ];
	const objectTypeName: string | null = getObjectTypeName( objectTypeId );

	// While loading, show a placeholder to prevent flicker
	if ( isLoading && ! objectTypeName ) {
		return <div className="activitypub-feed-excerpt">{ '\u00A0' }</div>;
	}

	// Check if this is a Note type
	const isNote: boolean = objectTypeName === 'Note';

	if ( isNote ) {
		/*
		 * `content.rendered` is already server-sanitised HTML: remote content is run
		 * through `Sanitize::content()` (`Sanitize::clean_remote_html()`) before it is stored,
		 * and the REST render filters do not decode entities. Decoding here would undo that --
		 * an entity-encoded `&lt;iframe srcdoc="..."&gt;` that kses deliberately stored as
		 * inert text would become a live element again, and `safeHTML()` only strips
		 * `<script>` elements and `on*` attributes, so it would not catch it.
		 * Entities in the stored value are literal characters and render correctly as-is.
		 */
		const content: string = safeHTML( item.content?.rendered || '' );

		return (
			<div className="activitypub-feed-post">
				<div
					className="activitypub-feed-content"
					dangerouslySetInnerHTML={ { __html: content || '<p>\u00A0</p>' } }
				/>
			</div>
		);
	}

	// Show excerpt for Articles and other types (plain text)
	const plainText: string = getPlainTextValue( item ).trim();

	return <div className="activitypub-feed-excerpt">{ plainText || '\u00A0' }</div>;
}

/**
 * Smart content field that automatically chooses between excerpt and content
 * based on the post's ActivityPub object type.
 *
 * - Notes: Show full content (HTML)
 * - All other types (Articles, etc.): Show excerpt (plain text)
 */
export const contentField: Field< FeedPost > = {
	id: 'content',
	label: __( 'Content', 'activitypub' ),
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ): string => getPlainTextValue( item ),
	render: ContentRenderer,
};
