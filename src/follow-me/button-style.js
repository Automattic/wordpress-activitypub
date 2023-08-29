function presetVarColorCss( color ) {
	return `var(--wp--preset--color--${ color })`;
}

function getBackgroundColor( color ) {
	// if color is a string, it's a var like this.
	if ( typeof color === 'string' ) {
		return presetVarColorCss( color );
	}

	return color?.color?.background || null;
}

function getLinkColor( text ) {
	if ( typeof text !== 'string' ) {
		return null;
	}
	// if it starts with a hash, leave it be
	if ( text.match( /^#/ ) ) {
		// we don't handle the alpha channel if present.
		return text.substring( 0, 7 );
	}
	// var:preset|color|luminous-vivid-amber
	// var(--wp--preset--color--luminous-vivid-amber)
	// we will receive the top format, we need to output the bottom format
	const [ , , color ] = text.split( '|' );
	return presetVarColorCss( color );
}

function generateSelector( selector, prop, value = null, pseudo = '' ) {
	if ( ! value ) {
		return '';
	}
	return `${ selector }${ pseudo } { ${ prop }: ${ value }; }\n`;
}

function getStyles( selector, button, text, hover ) {
	return generateSelector( selector, 'background-color', button )
	+ generateSelector( selector, 'color', text )
	+ generateSelector( selector, 'background-color', hover, ':hover' )
	+ generateSelector( selector, 'background-color', hover, ':focus' );
}

function getBlockStyles( base, style, backgroundColor ) {
	const selector = `${ base } .components-button`;
	// we grab the background color if set as a good color for our button text
	const buttonTextColor = getBackgroundColor( backgroundColor )
		// bg might be in this form.
		|| style?.color?.background;
	// we misuse the link color for the button background
	const buttonColor = getLinkColor( style?.elements?.link?.color?.text );
	// hover!
	const buttonHoverColor = getLinkColor( style?.elements?.link?.[':hover']?.color?.text );

	return getStyles( selector, buttonColor, buttonTextColor, buttonHoverColor );
}

export function getPopupStyles( style ) {
	// we don't acept backgroundColor because the popup is always white (right?)
	const buttonColor = getLinkColor( style?.elements?.link?.color?.text )
		|| '#111';
	const buttonTextColor = '#fff';
	const buttonHoverColor = getLinkColor( style?.elements?.link?.[':hover']?.color?.text )
		|| '#333';
	const selector = '.apfmd__button-group .components-button';

	return getStyles( selector, buttonColor, buttonTextColor, buttonHoverColor );
}

export function ButtonStyle( { selector, style, backgroundColor } ) {
	const css = getBlockStyles( selector, style, backgroundColor );
	return (
		<style>{ css }</style>
	);
}