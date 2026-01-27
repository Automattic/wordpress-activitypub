import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import StatHighlights from '../stat-highlights';
import LineChart from '../line-chart';
import TopSupporter from '../top-supporter';
import TopPosts from '../top-posts';
import type { StatsResponse } from '../../types';

// Actor mode constants matching PHP definitions.
const ACTOR_MODE = 'actor';
const BLOG_MODE = 'blog';
const ACTOR_AND_BLOG_MODE = 'actor_blog';

// Blog user ID constant matching PHP.
const BLOG_USER_ID = 0;

/**
 * Stats Widget Component.
 *
 * Displays global engagement stats and follower counts for available actors.
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
	const canUseUserActor: boolean = userModeEnabled && hasUserCap && !! currentUser?.id;

	// User can use the blog actor if blog mode is enabled AND they have the capability.
	const blogModeEnabled: boolean = actorMode === BLOG_MODE || actorMode === ACTOR_AND_BLOG_MODE;
	const canUseBlogActor: boolean = blogModeEnabled && hasBlogCap;

	const [ stats, setStats ] = useState< StatsResponse | null >( null );
	const [ userStats, setUserStats ] = useState< StatsResponse | null >( null );
	const [ blogStats, setBlogStats ] = useState< StatsResponse | null >( null );
	const [ isLoading, setIsLoading ] = useState( true );

	// Load stats for blog (global engagement) and separate follower stats per actor.
	useEffect( () => {
		if ( isResolving ) {
			return;
		}

		setIsLoading( true );

		// Fetch blog stats (global engagement data) - only if user has blog capability.
		const blogStatsPromise = canUseBlogActor
			? apiFetch< StatsResponse >( {
					path: `/activitypub/1.0/stats/${ BLOG_USER_ID }`,
			  } ).catch( () => null )
			: Promise.resolve( null );

		// Fetch user-specific stats if user actor is available.
		const userStatsPromise =
			canUseUserActor && currentUser?.id
				? apiFetch< StatsResponse >( {
						path: `/activitypub/1.0/stats/${ currentUser.id }`,
				  } ).catch( () => null )
				: Promise.resolve( null );

		Promise.all( [ blogStatsPromise, userStatsPromise ] )
			.then( ( [ blogData, userData ] ) => {
				// Use blog stats as primary if available, otherwise fall back to user stats.
				setStats( blogData ?? userData );
				setBlogStats( blogData );
				setUserStats( userData );
			} )
			.finally( () => setIsLoading( false ) );
	}, [ isResolving, canUseUserActor, canUseBlogActor, currentUser?.id ] );

	// Show loading while resolving user data.
	if ( isResolving || isLoading ) {
		return (
			<div className="activitypub-stats-widget">
				<div className="activitypub-stats-loading">
					<Spinner />
				</div>
			</div>
		);
	}

	if ( ! stats ) {
		return (
			<div className="activitypub-stats-widget">
				<p className="activitypub-stats-empty">{ __( 'No statistics available yet.', 'activitypub' ) }</p>
			</div>
		);
	}

	return (
		<div className="activitypub-stats-widget">
			<StatHighlights
				comparison={ stats.comparison }
				userComparison={ userStats?.comparison ?? null }
				blogComparison={ blogStats?.comparison ?? null }
				commentTypes={ stats.comment_types }
				canUseUserActor={ canUseUserActor }
				canUseBlogActor={ canUseBlogActor }
			/>
			<LineChart monthly={ stats.monthly } commentTypes={ stats.comment_types } />
			<TopSupporter multiplicator={ stats.stats?.top_multiplicator } />
			<TopPosts posts={ stats.stats?.top_posts } />
		</div>
	);
}
