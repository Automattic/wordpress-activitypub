/**
 * ActivityPub Statistics Dashboard Widget JavaScript
 *
 * @package Activitypub
 */

/* global jQuery, activitypubStats */

( function( $ ) {
	'use strict';

	/**
	 * Initialize statistics widgets.
	 */
	function init() {
		$( '.activitypub-stats-widget' ).each( function() {
			var $widget = $( this );
			var $actorSelect = $widget.find( '.activitypub-stats-actor-select' );

			// Actor selector change event.
			$actorSelect.on( 'change', function() {
				loadStats( $widget, $( this ).val() );
			} );
		} );
	}

	/**
	 * Load statistics via AJAX.
	 *
	 * @param {jQuery} $widget The widget container.
	 * @param {string} userId  The user ID to load stats for.
	 */
	function loadStats( $widget, userId ) {
		$widget.addClass( 'loading' );

		$.ajax( {
			url: activitypubStats.ajaxUrl,
			type: 'POST',
			data: {
				action: 'activitypub_get_stats',
				nonce: activitypubStats.nonce,
				user_id: userId
			},
			success: function( response ) {
				if ( response.success ) {
					updateWidget( $widget, response.data, userId );
				}
			},
			complete: function() {
				$widget.removeClass( 'loading' );
			}
		} );
	}

	/**
	 * Update widget with new data.
	 *
	 * @param {jQuery} $widget The widget container.
	 * @param {Object} data    The statistics data.
	 * @param {string} userId  The user ID.
	 */
	function updateWidget( $widget, data, userId ) {
		// Update user ID data attribute.
		$widget.attr( 'data-user-id', userId );

		// Update comparison highlights.
		updateHighlights( $widget, data.comparison );

		// Update line chart.
		updateLineChart( $widget, data.monthly );

		// Update top multiplicator.
		updateMultiplicator( $widget, data.stats.top_multiplicator );

		// Update top posts.
		updateTopPosts( $widget, data.stats.top_posts );

		// Update wrapped card link.
		$widget.find( '.activitypub-wrapped-link' ).attr( 'href', data.wrapped_url );
	}

	/**
	 * Update highlight stats with comparison.
	 *
	 * @param {jQuery} $widget    The widget container.
	 * @param {Object} comparison The comparison data.
	 */
	function updateHighlights( $widget, comparison ) {
		// Build list of stats to update: posts, all comment types, and followers.
		var stats = [ 'posts' ];

		// Add dynamic comment types from settings.
		if ( activitypubStats.commentTypes ) {
			Object.keys( activitypubStats.commentTypes ).forEach( function( type ) {
				stats.push( type );
			} );
		}

		stats.push( 'followers' );

		stats.forEach( function( stat ) {
			var $highlight = $widget.find( '.activitypub-highlight[data-stat="' + stat + '"]' );
			var data = comparison[ stat ];

			if ( ! data ) {
				return;
			}

			$highlight.find( '.activitypub-highlight-value' ).text( data.current );

			var $change = $highlight.find( '.activitypub-highlight-change' );
			if ( data.change_formatted ) {
				if ( $change.length === 0 ) {
					$highlight.find( '.activitypub-highlight-value' ).after(
						'<span class="activitypub-highlight-change">(' + escapeHtml( data.change_formatted ) + ')</span>'
					);
					$change = $highlight.find( '.activitypub-highlight-change' );
				} else {
					$change.text( '(' + data.change_formatted + ')' );
				}

				$change.removeClass( 'positive negative' );
				if ( data.change > 0 ) {
					$change.addClass( 'positive' );
				} else if ( data.change < 0 ) {
					$change.addClass( 'negative' );
				}
			} else {
				$change.remove();
			}
		} );
	}

	/**
	 * Update the line chart with new data.
	 *
	 * @param {jQuery} $widget  The widget container.
	 * @param {Array}  monthly  The monthly data array.
	 */
	function updateLineChart( $widget, monthly ) {
		var $container = $widget.find( '.activitypub-graph-container' );

		if ( monthly.length < 2 ) {
			$container.html( '<div class="activitypub-graph-empty">' + ( activitypubStats.i18n.notEnoughData || 'Not enough data yet' ) + '</div>' );
			return;
		}

		var width = 400;
		var height = 120;
		var padding = 10;
		var chartW = width - ( padding * 2 );
		var chartH = height - ( padding * 2 );
		var numMonths = monthly.length;

		// Get comment types and colors from settings.
		var commentTypes = activitypubStats.commentTypes || {};
		var chartColors = activitypubStats.chartColors || {};
		var typeKeys = Object.keys( commentTypes );

		// Find max value across all comment types.
		var maxValue = 1;
		typeKeys.forEach( function( type ) {
			var key = type + '_count';
			var max = Math.max.apply( null, monthly.map( function( m ) { return m[ key ] || 0; } ) );
			if ( max > maxValue ) {
				maxValue = max;
			}
		} );

		// Generate points for each comment type.
		var allPoints = {};
		typeKeys.forEach( function( type ) {
			allPoints[ type ] = [];
		} );
		var xLabels = [];

		monthly.forEach( function( data, i ) {
			var x = padding + ( i / ( numMonths - 1 ) ) * chartW;

			typeKeys.forEach( function( type ) {
				var key = type + '_count';
				var count = data[ key ] || 0;
				var y = padding + chartH - ( ( count / maxValue ) * chartH );
				allPoints[ type ].push( x.toFixed( 1 ) + ',' + y.toFixed( 1 ) );
			} );

			xLabels.push( {
				x: x,
				label: activitypubStats.monthNames[ data.month - 1 ] || ''
			} );
		} );

		// Build SVG.
		var baseY = padding + chartH;
		var svg = '<svg class="activitypub-line-chart" viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="xMidYMid meet">';

		// Grid lines.
		svg += '<g class="grid-lines">';
		for ( var i = 0; i <= 4; i++ ) {
			var y = padding + ( i / 4 ) * chartH;
			svg += '<line x1="' + padding + '" y1="' + y + '" x2="' + ( width - padding ) + '" y2="' + y + '" stroke="#e5e7eb" stroke-width="1" />';
		}
		svg += '</g>';

		// Area fills for each comment type.
		typeKeys.forEach( function( type ) {
			var color = chartColors[ type ] || '#8c8f94';
			var fillRgba = hexToRgba( color, 0.1 );
			svg += '<polygon class="area-' + type + '" points="' + padding + ',' + baseY + ' ' + allPoints[ type ].join( ' ' ) + ' ' + ( width - padding ) + ',' + baseY + '" fill="' + fillRgba + '" />';
		} );

		// Lines for each comment type.
		typeKeys.forEach( function( type ) {
			var color = chartColors[ type ] || '#8c8f94';
			svg += '<polyline class="line-' + type + '" points="' + allPoints[ type ].join( ' ' ) + '" fill="none" stroke="' + color + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />';
		} );

		// Data points.
		svg += '<g class="data-points">';
		monthly.forEach( function( data, i ) {
			var x = padding + ( i / ( numMonths - 1 ) ) * chartW;

			typeKeys.forEach( function( type ) {
				var key = type + '_count';
				var count = data[ key ] || 0;
				var y = padding + chartH - ( ( count / maxValue ) * chartH );
				var color = chartColors[ type ] || '#8c8f94';
				var label = commentTypes[ type ] ? ( commentTypes[ type ].singular || commentTypes[ type ].label ).toLowerCase() : type;

				svg += '<circle cx="' + x.toFixed( 1 ) + '" cy="' + y.toFixed( 1 ) + '" r="3" fill="' + color + '"><title>' + count + ' ' + label + '</title></circle>';
			} );
		} );
		svg += '</g>';
		svg += '</svg>';

		// Month labels.
		svg += '<div class="activitypub-graph-labels">';
		xLabels.forEach( function( label ) {
			var left = ( label.x / width ) * 100;
			svg += '<span style="left: ' + left.toFixed( 1 ) + '%;">' + escapeHtml( label.label ) + '</span>';
		} );
		svg += '</div>';

		$container.html( svg );
	}

	/**
	 * Convert hex color to rgba.
	 *
	 * @param {string} hex   The hex color.
	 * @param {number} alpha The alpha value (0-1).
	 * @return {string} The rgba color string.
	 */
	function hexToRgba( hex, alpha ) {
		hex = hex.replace( '#', '' );

		if ( hex.length === 3 ) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}

		var r = parseInt( hex.substring( 0, 2 ), 16 );
		var g = parseInt( hex.substring( 2, 4 ), 16 );
		var b = parseInt( hex.substring( 4, 6 ), 16 );

		return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
	}

	/**
	 * Update top multiplicator section.
	 *
	 * @param {jQuery}      $widget       The widget container.
	 * @param {Object|null} multiplicator The multiplicator data or null.
	 */
	function updateMultiplicator( $widget, multiplicator ) {
		var $section = $widget.find( '.activitypub-stats-multiplicator' );

		if ( multiplicator && multiplicator.name ) {
			var html = '<h4>' + activitypubStats.i18n.topSupporter + '</h4>' +
				'<p><a href="' + escapeHtml( multiplicator.url ) + '" target="_blank" rel="noopener noreferrer">' +
				escapeHtml( multiplicator.name ) + '</a> (' + multiplicator.count + ' boosts)</p>';

			if ( $section.length === 0 ) {
				$widget.find( '.activitypub-stats-graph' ).after(
					'<div class="activitypub-stats-multiplicator">' + html + '</div>'
				);
			} else {
				$section.html( html );
			}
		} else {
			$section.remove();
		}
	}

	/**
	 * Update top posts section.
	 *
	 * @param {jQuery} $widget  The widget container.
	 * @param {Array}  topPosts The top posts array.
	 */
	function updateTopPosts( $widget, topPosts ) {
		var $section = $widget.find( '.activitypub-stats-top-posts' );

		if ( topPosts && topPosts.length > 0 ) {
			var html = '<h4>' + activitypubStats.i18n.topPosts + '</h4><ul>';

			topPosts.forEach( function( post ) {
				html += '<li><a href="' + escapeHtml( post.url ) + '" target="_blank" rel="noopener noreferrer">' +
					escapeHtml( post.title || activitypubStats.i18n.noTitle ) + '</a>' +
					'<span class="engagement-count">' + post.engagement_count + ' ' + activitypubStats.i18n.engagements + '</span></li>';
			} );
			html += '</ul>';

			if ( $section.length === 0 ) {
				var $insertAfter = $widget.find( '.activitypub-stats-multiplicator' );
				if ( $insertAfter.length === 0 ) {
					$insertAfter = $widget.find( '.activitypub-stats-graph' );
				}
				$insertAfter.after( '<div class="activitypub-stats-top-posts">' + html + '</div>' );
			} else {
				$section.html( html );
			}
		} else {
			$section.remove();
		}
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text The text to escape.
	 * @return {string} The escaped text.
	 */
	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	// Initialize on document ready.
	$( document ).ready( init );

}( jQuery ) );
