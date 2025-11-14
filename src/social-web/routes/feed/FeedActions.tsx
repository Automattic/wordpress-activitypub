import { __ } from '@wordpress/i18n';
import type { Action } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export function getFeedActions( onSelectItem: ( id: number ) => void ): Action< FeedPost >[] {
	return [
		{
			id: 'view-details',
			label: __( 'View Details', 'activitypub' ),
			isPrimary: true,
			callback: ( items: FeedPost[] ) => {
				if ( items.length === 1 ) {
					onSelectItem( items[ 0 ].id );
				}
			},
		},
		{
			id: 'reply',
			label: __( 'Reply', 'activitypub' ),
			callback: ( items: FeedPost[] ) => {
				if ( items.length === 1 && items[ 0 ].link ) {
					// Open the original post in a new tab for replying
					window.open( items[ 0 ].link, '_blank' );
				}
			},
		},
		{
			id: 'like',
			label: __( 'Like', 'activitypub' ),
			callback: ( items: FeedPost[] ) => {
				if ( items.length === 1 ) {
					// TODO: Implement like functionality via ActivityPub
					// eslint-disable-next-line no-console
					console.log( 'Like post:', items[ 0 ].id );
					// For now, open the original post
					if ( items[ 0 ].link ) {
						window.open( items[ 0 ].link, '_blank' );
					}
				}
			},
		},
		{
			id: 'boost',
			label: __( 'Boost', 'activitypub' ),
			callback: ( items: FeedPost[] ) => {
				if ( items.length === 1 ) {
					// TODO: Implement boost/announce functionality via ActivityPub
					// eslint-disable-next-line no-console
					console.log( 'Boost post:', items[ 0 ].id );
					// For now, open the original post
					if ( items[ 0 ].link ) {
						window.open( items[ 0 ].link, '_blank' );
					}
				}
			},
		},
		{
			id: 'open-original',
			label: __( 'Open Original', 'activitypub' ),
			callback: ( items: FeedPost[] ) => {
				if ( items.length === 1 && items[ 0 ].link ) {
					window.open( items[ 0 ].link, '_blank' );
				}
			},
		},
	];
}
