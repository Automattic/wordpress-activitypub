/**
 * Posts and Replies block Interactivity API store.
 *
 * Handles tab switching between "Posts" and "Posts & Replies" views.
 */
import { store, getContext } from '@wordpress/interactivity';

store( 'activitypub/posts-and-replies', {
	actions: {
		/**
		 * Switch between tabs.
		 *
		 * @param {Event} event The click event.
		 */
		switchTab( event ) {
			event.preventDefault();
			const context = getContext();
			const tab = event.target.closest( '[data-tab]' )?.dataset?.tab;
			if ( tab ) {
				context.activeTab = tab;
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
	},
} );
