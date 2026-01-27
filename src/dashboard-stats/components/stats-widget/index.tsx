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

interface FollowerCounts {
	user: number | null;
	blog: number | null;
}

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
	const [ followerCounts, setFollowerCounts ] = useState< FollowerCounts >( { user: null, blog: null } );
	const [ isLoading, setIsLoading ] = useState( true );

	// Load stats - engagement is global, so we fetch from blog endpoint.
	useEffect( () => {
		if ( isResolving ) {
			return;
		}

		setIsLoading( true );

		// Fetch global stats (from blog endpoint).
		const statsPromise = apiFetch< StatsResponse >( {
			path: `/activitypub/1.0/stats/${ BLOG_USER_ID }`,
		} ).catch( () => null );

		// Fetch user follower count if available.
		const userFollowersPromise =
			canUseUserActor && currentUser?.id
				? apiFetch< StatsResponse >( {
						path: `/activitypub/1.0/stats/${ currentUser.id }`,
				  } )
						.then( ( data ) => data?.comparison?.followers?.current ?? null )
						.catch( () => null )
				: Promise.resolve( null );

		// Fetch blog follower count if available.
		const blogFollowersPromise = canUseBlogActor
			? apiFetch< StatsResponse >( {
					path: `/activitypub/1.0/stats/${ BLOG_USER_ID }`,
			  } )
					.then( ( data ) => data?.comparison?.followers?.current ?? null )
					.catch( () => null )
			: Promise.resolve( null );

		Promise.all( [ statsPromise, userFollowersPromise, blogFollowersPromise ] )
			.then( ( [ statsData, userFollowers, blogFollowers ] ) => {
				setStats( statsData );
				setFollowerCounts( {
					user: userFollowers,
					blog: blogFollowers,
				} );
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
				commentTypes={ stats.comment_types }
				followerCounts={ followerCounts }
				canUseUserActor={ canUseUserActor }
				canUseBlogActor={ canUseBlogActor }
			/>
			<LineChart monthly={ stats.monthly } commentTypes={ stats.comment_types } />
			<TopSupporter multiplicator={ stats.stats?.top_multiplicator } />
			<TopPosts posts={ stats.stats?.top_posts } />
		</div>
	);
}
