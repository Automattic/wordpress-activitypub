/**
 * Posts and Replies block Interactivity API store.
 *
 * Handles tab switching between "Posts" and "Posts & Replies" views
 * with full ARIA tabs keyboard navigation (ArrowLeft/Right, Home/End).
 */
import { store, getContext } from '@wordpress/interactivity';

const TABS = [ 'posts', 'posts-and-replies' ];

store( 'activitypub/posts-and-replies', {
	actions: {
		/**
		 * Switch between tabs via click.
		 *
		 * @param {Event} event The click event.
		 */
		switchTab( event ) {
			event.preventDefault();
			const context = getContext();
			const tab = event.currentTarget?.dataset?.tab;
			if ( tab ) {
				context.activeTab = tab;
				event.currentTarget.focus();
			}
		},

		/**
		 * Handle keyboard navigation for ARIA tabs pattern.
		 *
		 * @param {KeyboardEvent} event The keydown event.
		 */
		onKeyDown( event ) {
			const context = getContext();
			const currentIndex = TABS.indexOf( context.activeTab );
			let newIndex = -1;

			switch ( event.key ) {
				case 'ArrowRight':
					newIndex = ( currentIndex + 1 ) % TABS.length;
					break;
				case 'ArrowLeft':
					newIndex = ( currentIndex - 1 + TABS.length ) % TABS.length;
					break;
				case 'Home':
					newIndex = 0;
					break;
				case 'End':
					newIndex = TABS.length - 1;
					break;
				default:
					return;
			}

			event.preventDefault();
			context.activeTab = TABS[ newIndex ];

			// Move focus to the newly active tab button.
			const tablist = event.currentTarget.closest( '[role="tablist"]' );
			if ( tablist ) {
				const newTab = tablist.querySelector( `[data-tab="${ TABS[ newIndex ] }"]` );
				if ( newTab ) {
					newTab.focus();
				}
			}
		},
	},
	state: {
		/**
		 * Whether the "Posts" tab is active.
		 *
		 * @return {boolean} True if posts tab is active.
		 */
		get isPostsTab() {
			return getContext().activeTab === 'posts';
		},

		/**
		 * Whether the "Posts & Replies" tab is active.
		 *
		 * @return {boolean} True if posts-and-replies tab is active.
		 */
		get isPostsAndRepliesTab() {
			return getContext().activeTab === 'posts-and-replies';
		},

		/**
		 * Tabindex for the "Posts" tab (roving tabindex).
		 *
		 * @return {string} '0' if active, '-1' otherwise.
		 */
		get postsTabIndex() {
			return getContext().activeTab === 'posts' ? '0' : '-1';
		},

		/**
		 * Tabindex for the "Posts & Replies" tab (roving tabindex).
		 *
		 * @return {string} '0' if active, '-1' otherwise.
		 */
		get postsAndRepliesTabIndex() {
			return getContext().activeTab === 'posts-and-replies' ? '0' : '-1';
		},
	},
} );
