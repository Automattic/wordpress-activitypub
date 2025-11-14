/**
 * Example: How to use the Actors Entity
 *
 * This file demonstrates various ways to use the actors entity
 * registered with WordPress Core Data API.
 *
 * NOTE: This is an example/documentation file only. It is not built or included
 * in the plugin. The examples below show how to use the actors entity in your
 * own code.
 *
 * @package Activitypub
 */

/* eslint-disable */

/**
 * WordPress dependencies
 */
import { useEntityRecords, useEntityRecord } from '@wordpress/core-data';
import { SelectControl, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Example 1: Display all actors in a list
 *
 * @return {Element} Component displaying all actors
 */
export function ActorsList() {
	const { records: actors, isResolving } = useEntityRecords( 'activitypub/v1', 'actor' );

	if ( isResolving ) {
		return <Spinner />;
	}

	if ( ! actors || actors.length === 0 ) {
		return <p>No actors found.</p>;
	}

	return (
		<ul>
			{ actors.map( ( actor ) => (
				<li key={ actor.id }>
					<strong>{ actor.name }</strong> <span>(@{ actor.preferred_username })</span>{ ' ' }
					<em>({ actor.type })</em>
				</li>
			) ) }
		</ul>
	);
}

/**
 * Example 2: Display a single actor with full details
 *
 * @param {Object} props          Component props
 * @param {number} props.actorId  Actor ID to display
 * @return {Element} Component displaying actor details
 */
export function ActorProfile( { actorId } ) {
	const { record: actor, isResolving } = useEntityRecord( 'activitypub/v1', 'actor', actorId );

	if ( isResolving ) {
		return <Spinner />;
	}

	if ( ! actor ) {
		return <p>Actor not found.</p>;
	}

	return (
		<div className="activitypub-actor-profile">
			{ actor.icon?.url && <img src={ actor.icon.url } alt={ actor.name } width="48" height="48" /> }
			<h3>{ actor.name }</h3>
			<p>
				<strong>Username:</strong> @{ actor.preferred_username }
			</p>
			<p>
				<strong>Type:</strong> { actor.type }
			</p>
			{ actor.summary && (
				<div
					// eslint-disable-next-line react/no-danger
					dangerouslySetInnerHTML={ { __html: actor.summary } }
				/>
			) }
			<p>
				<a href={ actor.url } target="_blank" rel="noopener noreferrer">
					View Profile
				</a>
			</p>
			<p>
				<small>
					<strong>ActivityPub ID:</strong> { actor.activitypub_id }
				</small>
			</p>
		</div>
	);
}

/**
 * Example 3: Actor selector dropdown
 *
 * @param {Object}   props          Component props
 * @param {number}   props.value    Selected actor ID
 * @param {Function} props.onChange Callback when selection changes
 * @return {Element} Select control for choosing an actor
 */
export function ActorSelector( { value, onChange } ) {
	const { records: actors, isResolving } = useEntityRecords( 'activitypub/v1', 'actor' );

	if ( isResolving ) {
		return <SelectControl disabled label="Loading actors..." />;
	}

	if ( ! actors || actors.length === 0 ) {
		return <SelectControl disabled label="No actors available" />;
	}

	const options = [
		{ label: 'Select an actor...', value: '' },
		...actors.map( ( actor ) => ( {
			label: `${ actor.name } (@${ actor.preferred_username }) [${ actor.type }]`,
			value: actor.id,
		} ) ),
	];

	return <SelectControl label="Select Actor" value={ value } options={ options } onChange={ onChange } />;
}

/**
 * Example 4: Filter actors by type
 *
 * @param {Object} props       Component props
 * @param {string} props.type  Actor type to filter ('user', 'blog', 'application')
 * @return {Element} Component displaying filtered actors
 */
export function ActorsByType( { type } ) {
	const { records: actors, isResolving } = useEntityRecords( 'activitypub/v1', 'actor' );

	if ( isResolving ) {
		return <Spinner />;
	}

	const filteredActors = actors?.filter( ( actor ) => actor.type === type );

	if ( ! filteredActors || filteredActors.length === 0 ) {
		return <p>No { type } actors found.</p>;
	}

	return (
		<div>
			<h3>{ type.charAt( 0 ).toUpperCase() + type.slice( 1 ) } Actors</h3>
			<ul>
				{ filteredActors.map( ( actor ) => (
					<li key={ actor.id }>
						{ actor.name } (@{ actor.preferred_username })
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * Example 5: Complete example with state management
 *
 * @return {Element} Interactive component with actor selection
 */
export function ActorBrowser() {
	const [ selectedActorId, setSelectedActorId ] = useState( '' );

	return (
		<div className="activitypub-actor-browser">
			<h2>Actor Browser</h2>

			<ActorSelector value={ selectedActorId } onChange={ setSelectedActorId } />

			{ selectedActorId && <ActorProfile actorId={ parseInt( selectedActorId ) } /> }

			<hr />

			<h3>All Actors</h3>
			<ActorsList />
		</div>
	);
}
