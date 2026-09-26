/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import type { Field } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { FeedPost } from '../../../types';

export const titleField: Field< FeedPost > = {
	id: 'title.rendered',
	label: __( 'Title', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	enableGlobalSearch: true,
	getValue: ( { item }: { item: FeedPost } ): string => decodeEntities( item.title?.rendered || '' ),
	render: ( { item }: { item: FeedPost } ): ReactNode => {
		if ( ! item.title?.rendered ) {
			return null;
		}

		/*
		 * No backslash-unescaping pass here: nothing on the ingest path doubles
		 * backslashes, so it only ate legitimate ones out of remote titles (paths, code).
		 * The inspector renders this same value the same way.
		 */
		return <div className="activitypub-feed-post-title">{ decodeEntities( item.title.rendered ) }</div>;
	},
};
