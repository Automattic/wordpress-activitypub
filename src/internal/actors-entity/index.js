/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';

/**
 * Register the ActivityPub actors entity with WordPress Core Data API.
 *
 * This allows components to interact with actors using hooks like:
 * - useEntityRecords( 'activitypub/v1', 'actor' )
 * - useEntityRecord( 'activitypub/v1', 'actor', id )
 */
const registerActorEntity = () => {
	const { registerEntityType } = dispatch( coreDataStore );

	// Register the actor entity
	registerEntityType( {
		// The kind is typically the namespace of your REST endpoint
		kind: 'activitypub/v1',

		// The name is the entity type name
		name: 'actor',

		// The label used in the UI
		label: 'Actor',

		// The plural label
		plural: 'Actors',

		// The base URL for the REST API endpoint
		baseURL: '/wp-json/activitypub/v1/internal/actors',

		// The key to use as the unique identifier
		key: 'id',

		// Whether this is a transient entity (not saved to the database directly)
		transientEdits: {
			// Since this is read-only, mark all fields as transient
			name: true,
			preferred_username: true,
			url: true,
			icon: true,
			summary: true,
			activitypub_id: true,
		},

		// Define which methods are supported
		supportsPagination: false,
	} );
};

// Register the entity when the script loads
registerActorEntity();
