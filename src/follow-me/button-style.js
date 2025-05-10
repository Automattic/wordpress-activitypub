/**
 * Convert a color name to a CSS variable string.
 *
 * @param {string} color The color name.

 * @returns {string} The CSS variable string.
 */
function presetVarColorCss( color ) {
	return `var(--wp--preset--color--${ color })`;
}

/**
 * Get the background color CSS variable or value.
 *
 * @param {string|Object} color The color value or object.

 * @returns {string|null} The CSS variable or color value, or null if not found.
 */
function getBackgroundColor( color ) {
	// if color is a string, it's a var like this.
	if ( typeof color === 'string' ) {
		return presetVarColorCss( color );
	}

	return color?.color?.background || null;
}

/**
 * Get the link color CSS variable from the text.
 *
 * @param {string} text The color value or variable string.

 * @returns {string|null} The CSS variable or color value, or null if not found.
 */
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

/**
 * Generate a CSS selector string for a given property and value.
 *
 * @param {string}       selector    The CSS selector.
 * @param {string}       prop        The CSS property.
 * @param {string|null} [value=null] The value for the property.
 * @param {string}      [pseudo='']  Optional pseudo-selector.

 * @returns {string} The generated CSS string or empty string if no value.
 */
function generateSelector( selector, prop, value = null, pseudo = '' ) {
	if ( ! value ) {
		return '';
	}
	return `${ selector }${ pseudo } { ${ prop }: ${ value }; }\n`;
}

/**
 * Get the styles for the block button and hover states.
 *
 * @param {string} selector The CSS selector.
 * @param {string} button   The button color.
 * @param {string} text     The text color.
 * @param {string} hover    The hover color.

 * @returns {string} The generated CSS string for all states.
 */
function getStyles( selector, button, text, hover ) {
	return (
		generateSelector( selector, 'background-color', button ) +
		generateSelector( selector, 'color', text ) +
		generateSelector( selector, 'background-color', hover, ':hover' ) +
		generateSelector( selector, 'background-color', hover, ':focus' )
	);
}

/**
 * Get the block styles for the follow button.
 *
 * @param {string} base            The base CSS selector.
 * @param {Object} style           The style object.
 * @param {string} backgroundColor The background color.

 * @returns {string} The generated CSS for the block button.
 */
function getBlockStyles( base, style, backgroundColor ) {
	const selector = `${ base } .components-button`;
	// We grab the background color if set as a good color for our button text.
	const buttonTextColor =
		getBackgroundColor( backgroundColor ) ||
		// bg might be in this form.
		style?.color?.background;
	// We misuse the link color for the button background.
	const buttonColor = getLinkColor( style?.elements?.link?.color?.text );
	// hover!
	const buttonHoverColor = getLinkColor( style?.elements?.link?.[ ':hover' ]?.color?.text );

	return getStyles( selector, buttonColor, buttonTextColor, buttonHoverColor );
}

/**
 * Get popup styles for the modal button group.
 *
 * @param {Object} style The style object.

 * @returns {string} The generated CSS for the modal button group.
 */
export function getPopupStyles( style ) {
	// We don't accept backgroundColor because the popup is always white (right?)
	const buttonColor = getLinkColor( style?.elements?.link?.color?.text ) || '#111';
	const buttonTextColor = '#fff';
	const buttonHoverColor = getLinkColor( style?.elements?.link?.[ ':hover' ]?.color?.text ) || '#333';
	const selector = '.apfmd__button-group .components-button';

	return getStyles( selector, buttonColor, buttonTextColor, buttonHoverColor );
}

/**
 * ButtonStyle component for injecting block button styles.
 *
 * @param {Object} props                 Component props.
 * @param {string} props.selector        The CSS selector for the block.
 * @param {Object} props.style           The style object.
 * @param {string} props.backgroundColor The background color.

 * @returns {JSX.Element} The style element with generated CSS.
 */
export function ButtonStyle( { selector, style, backgroundColor } ) {
	const css = getBlockStyles( selector, style, backgroundColor );
	return <style>{ css }</style>;
}
