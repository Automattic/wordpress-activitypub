import { __ } from '@wordpress/i18n';
import type { Action } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export function getFeedActions( onSelectItem: ( id: number ) => void ): Action< FeedPost >[] {
	return [
		{
			id: 'open-original',
			label: __( 'Open Original', 'activitypub' ),
			isEligible: ( item: FeedPost ) => !! item.link,
			callback: ( items: FeedPost[] ) => {
				if ( items.length === 1 && items[ 0 ].link ) {
					window.open( items[ 0 ].link, '_blank' );
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
	];
}
