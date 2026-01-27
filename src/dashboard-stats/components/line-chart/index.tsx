import { __ } from '@wordpress/i18n';
import type { MonthData, CommentType } from '../../types';

interface Props {
	monthly: MonthData[] | null;
	commentTypes: Record< string, CommentType > | null;
}

// WordPress default color palette (always available).
// @see https://developer.wordpress.org/themes/global-settings-and-styles/settings/color/
const WP_DEFAULT_COLORS = [
	{ slug: 'vivid-red', hex: '#cf2e2e' },
	{ slug: 'vivid-green-cyan', hex: '#00d084' },
	{ slug: 'luminous-vivid-amber', hex: '#fcb900' },
	{ slug: 'vivid-purple', hex: '#9b51e0' },
	{ slug: 'vivid-cyan-blue', hex: '#0693e3' },
	{ slug: 'luminous-vivid-orange', hex: '#ff6900' },
];

/**
 * Simple string hash function for deterministic color assignment.
 * Uses djb2 algorithm for consistent results across page loads.
 * @param str The string to hash.
 */
function hashString( str: string ): number {
	let hash = 5381;
	for ( let i = 0; i < str.length; i++ ) {
		// eslint-disable-next-line no-bitwise -- djb2 hash algorithm requires XOR.
		hash = ( hash * 33 ) ^ str.charCodeAt( i );
	}
	return Math.abs( hash );
}

/**
 * Get CSS variable with fallback to hex value.
 * Uses CSS var() with fallback for best compatibility.
 * Color assignment is deterministic based on type slug hash.
 * @param typeSlug The comment type slug for deterministic color.
 */
function getColorForType( typeSlug: string ): string {
	const index = hashString( typeSlug ) % WP_DEFAULT_COLORS.length;
	const color = WP_DEFAULT_COLORS[ index ];
	return `var(--wp--preset--color--${ color.slug }, ${ color.hex })`;
}

/**
 * Get the engagement color (primary/accent, uses vivid-cyan-blue).
 */
function getEngagementColor(): string {
	return 'var(--wp--preset--color--vivid-cyan-blue, #0693e3)';
}

/**
 * Line Chart Component.
 *
 * Renders an SVG line chart for monthly engagement data.
 * @param root0
 * @param root0.monthly
 * @param root0.commentTypes
 */
export default function LineChart( { monthly, commentTypes }: Props ) {
	if ( ! monthly?.length ) {
		return null;
	}

	// Get colors once at render time.
	const engagementColor = getEngagementColor();

	const width = 600;
	const height = 200;
	const padding = { top: 20, right: 20, bottom: 30, left: 40 };
	const chartWidth = width - padding.left - padding.right;
	const chartHeight = height - padding.top - padding.bottom;

	// Get engagement type slugs from commentTypes.
	const typeKeys = commentTypes ? Object.keys( commentTypes ) : [];

	// Get max value across all engagement types for proper scaling.
	const maxEngagement = Math.max(
		...monthly.map( ( m ) => m.engagement || 0 ),
		...typeKeys.flatMap( ( type ) => monthly.map( ( m ) => ( m[ `${ type }_count` ] as number ) || 0 ) ),
		1
	);

	// Calculate x positions for each month.
	const xPositions = monthly.map( ( _, index ) => {
		return padding.left + ( index / ( monthly.length - 1 || 1 ) ) * chartWidth;
	} );

	// Calculate points for the total engagement line.
	const engagementPoints = monthly.map( ( month, index ) => {
		const x = xPositions[ index ];
		const y = padding.top + chartHeight - ( ( month.engagement || 0 ) / maxEngagement ) * chartHeight;
		return { x, y, month };
	} );

	// Create path for the engagement line.
	const engagementPath = engagementPoints
		.map( ( point, index ) => ( index === 0 ? `M ${ point.x } ${ point.y }` : `L ${ point.x } ${ point.y }` ) )
		.join( ' ' );

	// Create path for the area fill.
	const areaPath =
		engagementPath +
		` L ${ engagementPoints[ engagementPoints.length - 1 ].x } ${ padding.top + chartHeight }` +
		` L ${ engagementPoints[ 0 ].x } ${ padding.top + chartHeight } Z`;

	// Helper to create line path for a specific type.
	const createLinePath = ( type: string ) => {
		return monthly
			.map( ( month, index ) => {
				const value = ( month[ `${ type }_count` ] as number ) || 0;
				const x = xPositions[ index ];
				const y = padding.top + chartHeight - ( value / maxEngagement ) * chartHeight;
				return index === 0 ? `M ${ x } ${ y }` : `L ${ x } ${ y }`;
			} )
			.join( ' ' );
	};

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
		{ key: 'engagement', label: __( 'Total Engagement', 'activitypub' ), color: engagementColor },
	];

	if ( commentTypes ) {
		Object.entries( commentTypes ).forEach( ( [ slug, type ] ) => {
			legendItems.push( { key: slug, label: type.label, color: getColorForType( slug ) } );
		} );
	}

	return (
		<div className="activitypub-stats-chart">
			<h3>{ __( 'Engagement Over Time', 'activitypub' ) }</h3>
			<div className="activitypub-chart-container">
				<svg
					viewBox={ `0 0 ${ width } ${ height }` }
					className="activitypub-line-chart"
					role="img"
					aria-labelledby="activitypub-chart-title"
				>
					<title id="activitypub-chart-title">
						{ __( 'Line chart showing engagement trends over the past 12 months', 'activitypub' ) }
					</title>
					<defs>
						<linearGradient id="areaGradient" x1="0%" y1="0%" x2="0%" y2="100%">
							<stop offset="0%" stopColor={ engagementColor } stopOpacity={ 0.3 } />
							<stop offset="100%" stopColor={ engagementColor } stopOpacity={ 0.05 } />
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

					{ /* Area fill for total engagement */ }
					<path d={ areaPath } fill="url(#areaGradient)" />

					{ /* Lines for each engagement type */ }
					{ typeKeys.map( ( type ) => (
						<path
							key={ type }
							d={ createLinePath( type ) }
							fill="none"
							stroke={ getColorForType( type ) }
							strokeWidth="2"
							strokeOpacity="0.7"
						/>
					) ) }

					{ /* Total engagement line */ }
					<path d={ engagementPath } fill="none" stroke={ engagementColor } strokeWidth="2" />

					{ /* Data points for total engagement */ }
					{ engagementPoints.map( ( point, index ) => (
						<circle key={ index } cx={ point.x } cy={ point.y } r="4" fill={ engagementColor } />
					) ) }

					{ /* X-axis labels */ }
					{ engagementPoints.map( ( point, index ) => (
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
