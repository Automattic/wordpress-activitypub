import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export const createTitleField = ( onSelectItem: ( id: number ) => void ): Field< FeedPost > => ( {
	id: 'title.rendered',
	label: __( 'Title', 'activitypub' ),
	enableHiding: false,
	enableSorting: true,
	enableGlobalSearch: true,
	getValue: ( { item }: { item: FeedPost } ) => item.title?.rendered || '',
	render: ( { item }: { item: FeedPost } ) => {
		const title = item.title?.rendered || __( '(No title)', 'activitypub' );

		return (
			<button
				onClick={ () => onSelectItem( item.id ) }
				style={ {
					background: 'none',
					border: 'none',
					color: '#1e1e1e',
					cursor: 'pointer',
					textAlign: 'left',
					padding: 0,
					fontSize: '14px',
					fontWeight: 600,
					textDecoration: 'none',
					lineHeight: '1.4',
				} }
				onMouseOver={ ( e ) => {
					e.currentTarget.style.color = 'var(--wp-admin-theme-color, #3858e9)';
				} }
				onMouseOut={ ( e ) => {
					e.currentTarget.style.color = '#1e1e1e';
				} }
			>
				{ title }
			</button>
		);
	},
} );
