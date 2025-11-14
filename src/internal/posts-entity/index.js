/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';

/**
 * Register the ActivityPub posts entity with WordPress Core Data API.
 *
 * This allows components to interact with ActivityPub posts using hooks like:
 * - useEntityRecords( 'activitypub/1.0', 'post' )
 * - useEntityRecord( 'activitypub/1.0', 'post', id )
 */
const registerPostEntity = () => {
	const { registerEntityType } = dispatch( coreDataStore );

	// Register the post entity
	registerEntityType( {
		// The kind is typically the namespace of your REST endpoint
		kind: 'activitypub/1.0',

		// The name is the entity type name
		name: 'post',

		// The label used in the UI
		label: 'Post',

		// The plural label
		plural: 'Posts',

		// The base URL for the REST API endpoint
		baseURL: '/wp-json/activitypub/1.0/internal/posts',

		// The key to use as the unique identifier
		key: 'wp_id',

		// Whether this is a transient entity (not saved to the database directly)
		transientEdits: {
			// Since this is read-only, mark all fields as transient
			id: true,
			type: true,
			name: true,
			content: true,
			summary: true,
			published: true,
			wp_status: true,
		},

		// Define which methods are supported
		supportsPagination: false,
	} );
};

// Register the entity when the script loads
registerPostEntity();
