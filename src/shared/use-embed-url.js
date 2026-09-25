import { useInnerBlocksProps } from '@wordpress/block-editor';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { createBlock } from '@wordpress/blocks';

/**
 * Shared editor behaviour of the blocks that point at a Fediverse post.
 *
 * Checks the URL against the oEmbed proxy, keeps the embed's inner block in step with it,
 * and handles the keyboard and focus of the URL field. The Reply and the Quote block differ
 * in their wording and in what they do with the result, not in any of this.
 *
 * @param {Object}   options               Hook options.
 * @param {string}   options.url           The URL the block points at.
 * @param {string}   options.clientId      Block client ID.
 * @param {boolean}  options.embedPost     Whether the block embeds the post.
 * @param {Function} options.setAttributes Function to update block attributes.
 * @param {Function} [options.onChecked]   Called with the checked URL once it is known to be
 *                                         a valid ActivityPub object, and with no URL when it
 *                                         is not, so a block can add its own lookup. Receives
 *                                         an `isStale()` to check before it applies a result
 *                                         of its own, since the URL may have changed since.
 *
 * @return {Object} The state and props the block needs.
 */
export function useEmbedUrl( { url, clientId, embedPost, setAttributes, onChecked } ) {
	const [ isValidEmbed, setIsValidEmbed ] = useState( false );
	const [ isCheckingEmbed, setIsCheckingEmbed ] = useState( false );
	const urlInputRef = useRef();
	// The URL the running check belongs to: a slower answer for an older one must not land.
	const checkedUrlRef = useRef();
	const { insertAfterBlock, removeBlock, replaceInnerBlocks } = useDispatch( 'core/block-editor' );

	// Show the embed whether or not the block is selected.
	const showEmbed = embedPost && ! isCheckingEmbed && isValidEmbed;

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'activitypub-embed-container' },
		{
			allowedBlocks: [ 'core/embed' ],
			template: url && showEmbed ? [ [ 'core/embed', { url } ] ] : [],
			templateLock: 'all',
		}
	);

	// Keep the inner blocks in step with the URL and the embed toggle.
	useEffect( () => {
		if ( url && showEmbed ) {
			replaceInnerBlocks( clientId, [ createBlock( 'core/embed', { url } ) ] );
		} else {
			replaceInnerBlocks( clientId, [] );
		}
	}, [ url, showEmbed, clientId, replaceInnerBlocks ] );

	const checkUrl = useCallback(
		async ( urlToCheck ) => {
			checkedUrlRef.current = urlToCheck;

			const isStale = () => checkedUrlRef.current !== urlToCheck;

			if ( ! urlToCheck ) {
				setIsValidEmbed( false );
				// A check for the old URL may still be running; its own reset is skipped as stale.
				setIsCheckingEmbed( false );
				onChecked?.( null, isStale );
				return;
			}

			try {
				setIsCheckingEmbed( true );

				new URL( urlToCheck ); // Throws if the URL is not valid.

				const response = await apiFetch( {
					path: addQueryArgs( '/oembed/1.0/proxy', { url: urlToCheck, activitypub: true } ),
				} );

				if ( isStale() ) {
					return;
				}

				if ( response?.provider_name ) {
					// Embedding is turned on for us, the URL answered as an ActivityPub object.
					setAttributes( { embedPost: true, isValidActivityPub: true } );
					setIsValidEmbed( true );
					await onChecked?.( urlToCheck, isStale );
				} else {
					setAttributes( { isValidActivityPub: false } );
					setIsValidEmbed( false );
					onChecked?.( null, isStale );
				}
			} catch ( error ) {
				if ( isStale() ) {
					return;
				}

				setAttributes( { isValidActivityPub: false } );
				setIsValidEmbed( false );
				onChecked?.( null, isStale );
			} finally {
				if ( ! isStale() ) {
					setIsCheckingEmbed( false );
				}
			}
		},
		[ setAttributes, onChecked ]
	);

	// Debounce the check so typing does not fire a request per keystroke.
	const debouncedCheckUrl = useDebounce( checkUrl, 250 );

	useEffect( () => {
		/*
		 * What the last check found belongs to the URL it was made for. Retiring it here rather
		 * than when the debounced check runs closes the window in which an answer for the old
		 * URL could still arrive and be taken for the new one.
		 */
		checkedUrlRef.current = url;
		setIsValidEmbed( false );

		debouncedCheckUrl( url );
	}, [ url, debouncedCheckUrl ] );

	const focusInput = () => {
		setTimeout( () => urlInputRef.current?.focus(), 50 );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			insertAfterBlock( clientId );
		}
		if ( ! url && [ 'Backspace', 'Delete' ].includes( event.key ) ) {
			removeBlock( clientId );
		}
	};

	return { isValidEmbed, isCheckingEmbed, showEmbed, innerBlocksProps, urlInputRef, focusInput, onKeyDown };
}
