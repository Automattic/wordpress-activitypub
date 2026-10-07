/**
 * WordPress dependencies
 */
import { dispatch, resolveSelect } from '@wordpress/data';
import { store as bootStore } from '@wordpress/boot';
import { store as coreStore } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import { postList, tag } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { objectTypeConfig } from './object-types';

/**
 * Boot init module: runs after menu items and routes are registered, before
 * the app renders. Icons cannot be passed from PHP, so they are set here.
 */
export async function init(): Promise< void > {
	const { registerMenuItem, updateMenuItem } = dispatch( bootStore );
	updateMenuItem( 'feed', { icon: postList } );
	updateMenuItem( 'feed-all', { icon: postList } );

	const [ types, tags ] = await Promise.all( [
		resolveSelect( coreStore ).getEntityRecords< Term >( 'taxonomy', 'ap_object_type', {
			per_page: -1,
			hide_empty: true,
		} ),
		resolveSelect( coreStore ).getEntityRecords< Term >( 'taxonomy', 'ap_tag', {
			per_page: 5,
			orderby: 'count',
			order: 'desc',
			hide_empty: true,
		} ),
	] );

	for ( const [ name, config ] of Object.entries( objectTypeConfig ) ) {
		const term = types?.find( ( item ) => item.name === name );
		if ( term ) {
			registerMenuItem( `feed-type-${ term.id }`, {
				id: `feed-type-${ term.id }`,
				parent: 'feed',
				to: `/feed/type/${ term.id }`,
				...config,
			} );
		}
	}

	if ( tags?.length ) {
		registerMenuItem( 'feed-tags', {
			id: 'feed-tags',
			parent: 'feed',
			parent_type: 'dropdown',
			to: '/feed/tag',
			label: __( 'Popular Tags', 'activitypub' ),
			icon: tag,
		} );
		for ( const term of tags ) {
			registerMenuItem( `feed-tag-${ term.id }`, {
				id: `feed-tag-${ term.id }`,
				parent: 'feed-tags',
				to: `/feed/tag/${ term.id }`,
				label: `#${ term.name }`,
			} );
		}
	}
}
