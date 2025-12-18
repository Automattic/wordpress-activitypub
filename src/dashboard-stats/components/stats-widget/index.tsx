import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import StatHighlights from '../stat-highlights';
import LineChart from '../line-chart';
import TopSupporter from '../top-supporter';
import TopPosts from '../top-posts';
import type { Actor, StatsResponse } from '../../types';

// Actor mode constants matching PHP definitions.
const ACTOR_MODE = 'actor';
const BLOG_MODE = 'blog';
const ACTOR_AND_BLOG_MODE = 'actor_blog';

// Blog user ID constant matching PHP.
const BLOG_USER_ID = 0;

/**
 * Stats Widget Component.
 */
export default function StatsWidget() {
	const { currentUser, actorMode, hasUserCap, hasBlogCap, isResolving } = useSelect(
		( select ) => ( {
			currentUser: select( coreStore ).getCurrentUser(),
			actorMode:
				(
					select( coreStore ).getEntityRecord( 'root', 'site' ) as
						| { activitypub_actor_mode?: string }
						| undefined
				 )?.activitypub_actor_mode ?? ACTOR_AND_BLOG_MODE,
			// Check if user has the activitypub capability (can create user extra fields).
			hasUserCap: select( coreStore ).canUser( 'create', {
				kind: 'postType',
				name: 'ap_extrafield',
			} ),
			// Check if user can manage options (can create blog extra fields).
			hasBlogCap: select( coreStore ).canUser( 'create', {
				kind: 'postType',
				name: 'ap_extrafield_blog',
			} ),
			isResolving: select( coreStore ).isResolving( 'getCurrentUser', [] ),
		} ),
		[]
	);

	// User can use their actor if user mode is enabled AND they have the capability.
	const userModeEnabled: boolean = actorMode === ACTOR_MODE || actorMode === ACTOR_AND_BLOG_MODE;
	const canUseUserActor: boolean = userModeEnabled && hasUserCap;

	// User can use the blog actor if blog mode is enabled AND they have the capability.
	const blogModeEnabled: boolean = actorMode === BLOG_MODE || actorMode === ACTOR_AND_BLOG_MODE;
	const canUseBlogActor: boolean = blogModeEnabled && hasBlogCap;

	// Build actors list based on capabilities.
	const actors: Actor[] = [];
	if ( canUseUserActor && currentUser?.id ) {
		actors.push( {
			id: currentUser.id,
			label: __( 'Your Stats', 'activitypub' ),
		} );
	}
	if ( canUseBlogActor ) {
		actors.push( {
			id: BLOG_USER_ID,
			label: __( 'Blog Stats', 'activitypub' ),
		} );
	}

	const [ selectedActor, setSelectedActor ] = useState< number | null >( null );
	const [ stats, setStats ] = useState< StatsResponse | null >( null );
	const [ isLoading, setIsLoading ] = useState( true );

	// Set initial selected actor when actors are determined.
	useEffect( () => {
		if ( actors.length > 0 && selectedActor === null ) {
			setSelectedActor( actors[ 0 ].id );
		}
	}, [ actors, selectedActor ] );

	// Load stats when actor changes.
	useEffect( () => {
		if ( selectedActor === null ) {
			setIsLoading( false );
			return;
		}

		setIsLoading( true );

		apiFetch< StatsResponse >( { path: `/activitypub/1.0/stats/${ selectedActor }` } )
			.then( ( data ) => setStats( data ) )
			.catch( () => setStats( null ) )
			.finally( () => setIsLoading( false ) );
	}, [ selectedActor ] );

	const actorOptions = actors.map( ( actor ) => ( {
		label: actor.label,
		value: actor.id,
	} ) );

	// Show loading while resolving user data.
	if ( isResolving ) {
		return (
			<div className="activitypub-stats-widget">
				<div className="activitypub-stats-loading">
					<Spinner />
				</div>
			</div>
		);
	}

	return (
		<div className="activitypub-stats-widget">
			{ actors.length > 1 && selectedActor !== null && (
				<div className="activitypub-stats-header">
					<SelectControl
						value={ selectedActor }
						options={ actorOptions }
						onChange={ ( value ) => setSelectedActor( parseInt( String( value ), 10 ) ) }
						__nextHasNoMarginBottom
					/>
				</div>
			) }

			{ isLoading ? (
				<div className="activitypub-stats-loading">
					<Spinner />
				</div>
			) : stats ? (
				<>
					<StatHighlights comparison={ stats.comparison } commentTypes={ stats.comment_types } />
					<LineChart monthly={ stats.monthly } commentTypes={ stats.comment_types } />
					<TopSupporter multiplicator={ stats.stats?.top_multiplicator } />
					<TopPosts posts={ stats.stats?.top_posts } />
				</>
			) : (
				<p className="activitypub-stats-empty">{ __( 'No statistics available yet.', 'activitypub' ) }</p>
			) }
		</div>
	);
}
