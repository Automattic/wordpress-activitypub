import { __ } from '@wordpress/i18n';
import type { MonthData, CommentType } from '../../types';

interface Props {
	monthly: MonthData[] | null;
	commentTypes: Record< string, CommentType > | null;
}

// Colors for different engagement types.
const COLORS = {
	engagement: '#3858e9',
	like: '#d63638',
	repost: '#00a32a',
	comment: '#dba617',
};

/**
 * Line Chart Component.
 *
 * Renders an SVG line chart for monthly engagement data.
 */
export default function LineChart( { monthly, commentTypes }: Props ) {
	if ( ! monthly?.length ) {
		return null;
	}

	const width = 600;
	const height = 200;
	const padding = { top: 20, right: 20, bottom: 30, left: 40 };
	const chartWidth = width - padding.left - padding.right;
	const chartHeight = height - padding.top - padding.bottom;

	// Get all engagement values to find max.
	const maxEngagement = Math.max( ...monthly.map( ( m ) => m.engagement || 0 ), 1 );

	// Calculate points for the line.
	const points = monthly.map( ( month, index ) => {
		const x = padding.left + ( index / ( monthly.length - 1 || 1 ) ) * chartWidth;
		const y = padding.top + chartHeight - ( ( month.engagement || 0 ) / maxEngagement ) * chartHeight;
		return { x, y, month };
	} );

	// Create path for the line.
	const linePath = points
		.map( ( point, index ) => {
			return index === 0 ? `M ${ point.x } ${ point.y }` : `L ${ point.x } ${ point.y }`;
		} )
		.join( ' ' );

	// Create path for the area fill.
	const areaPath =
		linePath +
		` L ${ points[ points.length - 1 ].x } ${ padding.top + chartHeight }` +
		` L ${ points[ 0 ].x } ${ padding.top + chartHeight } Z`;

	// Month labels.
	const monthLabels = [
		__( 'Jan', 'activitypub' ),
		__( 'Feb', 'activitypub' ),
		__( 'Mar', 'activitypub' ),
		__( 'Apr', 'activitypub' ),
		__( 'May', 'activitypub' ),
		__( 'Jun', 'activitypub' ),
		__( 'Jul', 'activitypub' ),
		__( 'Aug', 'activitypub' ),
		__( 'Sep', 'activitypub' ),
		__( 'Oct', 'activitypub' ),
		__( 'Nov', 'activitypub' ),
		__( 'Dec', 'activitypub' ),
	];

	// Build legend items from comment types.
	const legendItems = [
		{ key: 'engagement', label: __( 'Total Engagement', 'activitypub' ), color: COLORS.engagement },
	];

	if ( commentTypes ) {
		Object.entries( commentTypes ).forEach( ( [ slug, type ] ) => {
			const color = COLORS[ slug as keyof typeof COLORS ] || '#8c8f94';
			legendItems.push( { key: slug, label: type.label, color } );
		} );
	}

	return (
		<div className="activitypub-stats-chart">
			<h4>{ __( 'Engagement Over Time', 'activitypub' ) }</h4>
			<div className="activitypub-chart-container">
				<svg viewBox={ `0 0 ${ width } ${ height }` } className="activitypub-line-chart">
					<defs>
						<linearGradient id="areaGradient" x1="0%" y1="0%" x2="0%" y2="100%">
							<stop offset="0%" stopColor={ COLORS.engagement } stopOpacity={ 0.3 } />
							<stop offset="100%" stopColor={ COLORS.engagement } stopOpacity={ 0.05 } />
						</linearGradient>
					</defs>

					{ /* Grid lines */ }
					{ [ 0, 0.25, 0.5, 0.75, 1 ].map( ( ratio ) => (
						<line
							key={ ratio }
							x1={ padding.left }
							y1={ padding.top + chartHeight * ( 1 - ratio ) }
							x2={ width - padding.right }
							y2={ padding.top + chartHeight * ( 1 - ratio ) }
							stroke="#e0e0e0"
							strokeWidth="1"
						/>
					) ) }

					{ /* Area fill */ }
					<path d={ areaPath } fill="url(#areaGradient)" />

					{ /* Line */ }
					<path d={ linePath } fill="none" stroke={ COLORS.engagement } strokeWidth="2" />

					{ /* Data points */ }
					{ points.map( ( point, index ) => (
						<circle key={ index } cx={ point.x } cy={ point.y } r="4" fill={ COLORS.engagement } />
					) ) }

					{ /* X-axis labels */ }
					{ points.map( ( point, index ) => (
						<text key={ index } x={ point.x } y={ height - 5 } textAnchor="middle" className="chart-label">
							{ monthLabels[ point.month.month - 1 ] }
						</text>
					) ) }

					{ /* Y-axis labels */ }
					{ [ 0, 0.5, 1 ].map( ( ratio ) => (
						<text
							key={ ratio }
							x={ padding.left - 5 }
							y={ padding.top + chartHeight * ( 1 - ratio ) + 4 }
							textAnchor="end"
							className="chart-label"
						>
							{ Math.round( maxEngagement * ratio ) }
						</text>
					) ) }
				</svg>

				{ /* Legend */ }
				<div className="activitypub-chart-legend">
					{ legendItems.map( ( item ) => (
						<div key={ item.key } className="activitypub-legend-item">
							<span className="legend-color" style={ { backgroundColor: item.color } } />
							{ item.label }
						</div>
					) ) }
				</div>
			</div>
		</div>
	);
}
