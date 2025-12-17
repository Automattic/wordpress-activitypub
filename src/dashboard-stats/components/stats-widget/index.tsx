import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';
import { SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import StatHighlights from '../stat-highlights';
import LineChart from '../line-chart';
import TopSupporter from '../top-supporter';
import TopPosts from '../top-posts';
import type { Settings, StatsResponse } from '../../types';

/**
 * Get dashboard stats settings from global window.
 */
function useSettings(): Settings {
	return window.activitypubDashboardStats || { actors: [] };
}

/**
 * Stats Widget Component.
 */
export default function StatsWidget() {
	const { actors = [] } = useSettings();
	const [ selectedActor, setSelectedActor ] = useState< number | null >( () => actors[ 0 ]?.id ?? null );
	const [ stats, setStats ] = useState< StatsResponse | null >( null );
	const [ isLoading, setIsLoading ] = useState( true );

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
