/**
 * Fediverse Year in Review — Share Image Generator.
 *
 * Toggles between the HTML card view and a canvas-rendered
 * share image, reading the page's computed colors.
 */

/**
 * Read the computed background and text colors from the card element.
 *
 * @param {HTMLElement} card The card element.
 * @return {Object} An object with bg (background) and fg (foreground) color strings.
 */
function getCardColors( card ) {
	const styles = window.getComputedStyle( card );
	const bg = styles.backgroundColor || '#fff';
	const fg = styles.color || '#1d2327';
	return { bg, fg };
}

/**
 * Mix a foreground color at a given alpha against a background.
 *
 * @param {string} fg    Foreground color as "rgb(r, g, b)".
 * @param {number} alpha Opacity (0-1).
 * @param {string} bg    Background color as "rgb(r, g, b)".
 * @return {string} Blended color as "rgb(r, g, b)".
 */
function blend( fg, alpha, bg ) {
	const parse = ( c ) => {
		const m = c.match( /(\d+)/g );
		return m ? m.map( Number ) : [ 0, 0, 0 ];
	};
	const f = parse( fg );
	const b = parse( bg );
	const r = Math.round( f[ 0 ] * alpha + b[ 0 ] * ( 1 - alpha ) );
	const g = Math.round( f[ 1 ] * alpha + b[ 1 ] * ( 1 - alpha ) );
	const bl = Math.round( f[ 2 ] * alpha + b[ 2 ] * ( 1 - alpha ) );
	return `rgb(${ r }, ${ g }, ${ bl })`;
}

/**
 * Render the year-in-review card onto a canvas element.
 *
 * @param {HTMLElement}       card   The card element to read data from.
 * @param {HTMLCanvasElement} canvas The canvas to draw on.
 */
function renderToCanvas( card, canvas ) {
	const ctx = canvas.getContext( '2d' );
	const scale = 2;
	const width = 1200;
	const height = 630;

	canvas.width = width * scale;
	canvas.height = height * scale;
	canvas.style.width = '100%';
	canvas.style.maxWidth = width / 2 + 'px';
	canvas.style.height = 'auto';
	ctx.scale( scale, scale );

	const { bg, fg } = getCardColors( card );
	const fgMuted = blend( fg, 0.6, bg );
	const fgSubtle = blend( fg, 0.4, bg );
	const borderColor = blend( fg, 0.15, bg );

	ctx.fillStyle = bg;
	ctx.fillRect( 0, 0, width, height );

	const drawText = (
		text,
		x,
		y,
		{ font = '16px system-ui, sans-serif', color = fg, align = 'left', maxWidth } = {}
	) => {
		ctx.font = font;
		ctx.fillStyle = color;
		ctx.textAlign = align;
		if ( maxWidth ) {
			ctx.fillText( text, x, y, maxWidth );
		} else {
			ctx.fillText( text, x, y );
		}
	};

	const roundRect = ( x, y, w, h, r ) => {
		ctx.beginPath();
		ctx.moveTo( x + r, y );
		ctx.lineTo( x + w - r, y );
		ctx.quadraticCurveTo( x + w, y, x + w, y + r );
		ctx.lineTo( x + w, y + h - r );
		ctx.quadraticCurveTo( x + w, y + h, x + w - r, y + h );
		ctx.lineTo( x + r, y + h );
		ctx.quadraticCurveTo( x, y + h, x, y + h - r );
		ctx.lineTo( x, y + r );
		ctx.quadraticCurveTo( x, y, x + r, y );
		ctx.closePath();
	};

	const title = card.querySelector( '.activitypub-wrapped__title' )?.textContent?.trim() || '';
	const subtitle = card.querySelector( '.activitypub-wrapped__subtitle' )?.textContent?.trim() || '';
	const branding = card.querySelector( '.activitypub-wrapped__branding' )?.textContent?.trim() || '';

	const highlightStats = [];
	card.querySelectorAll( '.activitypub-wrapped__stat--highlight' ).forEach( ( el ) => {
		highlightStats.push( {
			value: el.querySelector( '.activitypub-wrapped__stat-value' )?.textContent?.trim() || '0',
			label: el.querySelector( '.activitypub-wrapped__stat-label' )?.textContent?.trim() || '',
		} );
	} );

	const engagementStats = [];
	card.querySelectorAll( '.activitypub-wrapped__engagement .activitypub-wrapped__stat' ).forEach( ( el ) => {
		engagementStats.push( {
			value: el.querySelector( '.activitypub-wrapped__stat-value' )?.textContent?.trim() || '0',
			label: el.querySelector( '.activitypub-wrapped__stat-label' )?.textContent?.trim() || '',
		} );
	} );

	const details = [];
	card.querySelectorAll( '.activitypub-wrapped__detail' ).forEach( ( el ) => {
		details.push( {
			label: el.querySelector( '.activitypub-wrapped__detail-label' )?.textContent?.trim() || '',
			value: el.querySelector( '.activitypub-wrapped__detail-value' )?.textContent?.trim() || '',
			extra: el.querySelector( '.activitypub-wrapped__detail-extra' )?.textContent?.trim() || '',
		} );
	} );

	let curY = 50;

	drawText( title, width / 2, curY, { font: 'bold 42px system-ui, sans-serif', align: 'center' } );
	curY += 35;

	drawText( subtitle, width / 2, curY, {
		font: '20px system-ui, sans-serif',
		color: fgMuted,
		align: 'center',
	} );
	curY += 50;

	if ( highlightStats.length ) {
		const boxWidth = ( width - 80 - 16 * ( highlightStats.length - 1 ) ) / highlightStats.length;
		highlightStats.forEach( ( stat, i ) => {
			const x = 40 + i * ( boxWidth + 16 );
			ctx.strokeStyle = borderColor;
			ctx.lineWidth = 1;
			roundRect( x, curY, boxWidth, 90, 12 );
			ctx.stroke();
			drawText( stat.value, x + boxWidth / 2, curY + 48, {
				font: 'bold 36px system-ui, sans-serif',
				align: 'center',
			} );
			drawText( stat.label, x + boxWidth / 2, curY + 72, {
				font: '12px system-ui, sans-serif',
				color: fgMuted,
				align: 'center',
			} );
		} );
		curY += 110;
	}

	if ( engagementStats.length ) {
		const cols = Math.min( engagementStats.length, 4 );
		const boxWidth = ( width - 80 - 12 * ( cols - 1 ) ) / cols;
		engagementStats.forEach( ( stat, i ) => {
			const col = i % cols;
			const row = Math.floor( i / cols );
			const x = 40 + col * ( boxWidth + 12 );
			const y = curY + row * 70;
			ctx.strokeStyle = borderColor;
			ctx.lineWidth = 1;
			roundRect( x, y, boxWidth, 58, 8 );
			ctx.stroke();
			drawText( stat.value, x + boxWidth / 2, y + 30, {
				font: 'bold 22px system-ui, sans-serif',
				align: 'center',
			} );
			drawText( stat.label, x + boxWidth / 2, y + 48, {
				font: '10px system-ui, sans-serif',
				color: fgMuted,
				align: 'center',
			} );
		} );
		const rows = Math.ceil( engagementStats.length / cols );
		curY += rows * 70 + 20;
	}

	if ( details.length ) {
		const cols = Math.min( details.length, 3 );
		const boxWidth = ( width - 80 - 16 * ( cols - 1 ) ) / cols;
		details.forEach( ( detail, i ) => {
			const x = 40 + i * ( boxWidth + 16 );
			ctx.strokeStyle = borderColor;
			ctx.lineWidth = 1;
			roundRect( x, curY, boxWidth, 80, 8 );
			ctx.stroke();
			drawText( detail.label, x + 12, curY + 20, {
				font: '10px system-ui, sans-serif',
				color: fgSubtle,
			} );
			drawText( detail.value, x + 12, curY + 46, {
				font: 'bold 20px system-ui, sans-serif',
				maxWidth: boxWidth - 24,
			} );
			if ( detail.extra ) {
				drawText( detail.extra, x + 12, curY + 66, {
					font: '11px system-ui, sans-serif',
					color: fgSubtle,
					maxWidth: boxWidth - 24,
				} );
			}
		} );
	}

	drawText( branding, width / 2, height - 25, {
		font: '13px system-ui, sans-serif',
		color: fgSubtle,
		align: 'center',
	} );
}

/**
 * Auto-render canvases for blocks set to image display mode.
 */
function init() {
	document.querySelectorAll( '.activitypub-wrapped__canvas' ).forEach( ( canvas ) => {
		const wrapper = document.getElementById( canvas.dataset.blockId );
		if ( ! wrapper ) {
			return;
		}

		const card = wrapper.querySelector( '.activitypub-wrapped__card' );
		if ( ! card ) {
			return;
		}

		renderToCanvas( card, canvas );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
