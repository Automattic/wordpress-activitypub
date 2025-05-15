/**
 * Gets the background color from a style object.
 *
 * @param {Object|string} color Color object or string.
 * @return {string|null} Background color.
 */
function getBackgroundColor( color ) {
	// If color is a string, it's a var like this.
	if ( typeof color === 'string' ) {
		return `var(--wp--preset--color--${ color })`;
	}

	return color?.color?.background || null;
}

/**
 * Gets the link color from a style object.
 *
 * @param {string} text Text color.
 * @return {string|null} Link color.
 */
function getLinkColor( text ) {
	if ( typeof text !== 'string' ) {
		return null;
	}
	// If it starts with a hash, leave it be.
	if ( text.match( /^#/ ) ) {
		// We don't handle the alpha channel if present.
		return text.substring( 0, 7 );
	}
	// var:preset|color|luminous-vivid-amber
	// var(--wp--preset--color--luminous-vivid-amber)
	// We will receive the top format, we need to output the bottom format.
	const [ , , color ] = text.split( '|' );
	return `var(--wp--preset--color--${ color })`;
}

/**
 * Generates a CSS selector.
 *
 * @param {string} selector CSS selector.
 * @param {string} prop CSS property.
 * @param {string|null} value CSS value.
 * @param {string} pseudo Pseudo-selector.
 * @return {string} CSS selector.
 */
function generateSelector( selector, prop, value = null, pseudo = '' ) {
	if ( ! value ) {
		return '';
	}
	return `${ selector }${ pseudo } { ${ prop }: ${ value }; }\n`;
}

/**
 * Gets styles for a button.
 *
 * @param {string} selector CSS selector.
 * @param {string} button Button color.
 * @param {string} text Text color.
 * @param {string} hover Hover color.
 * @return {string} CSS styles.
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
 * Gets block styles.
 *
 * @param {string} base Base selector.
 * @param {Object} style Style object.
 * @param {Object|string} backgroundColor Background color.
 * @return {string} CSS styles.
 */
export function getBlockStyles( base, style, backgroundColor ) {
	const selector = `${ base } .wp-block-button__link`;

	// We grab the background color if set as a good color for our button text.
	const buttonTextColor =
		getBackgroundColor( backgroundColor ) ||
		// Background might be in this form.
		style?.color?.background;

	// We misuse the link color for the button background.
	const buttonColor = getLinkColor( style?.elements?.link?.color?.text );
	const buttonHoverColor = getLinkColor( style?.elements?.link?.[ ':hover' ]?.color?.text );

	return getStyles( selector, buttonColor, buttonTextColor, buttonHoverColor );
}

/**
 * Gets popup styles.
 *
 * @param {Object} style Style object.
 * @return {string} CSS styles.
 */
export function getPopupStyles( style ) {
	// We don't accept backgroundColor because the popup is always white (right?).
	const buttonColor = getLinkColor( style?.elements?.link?.color?.text ) || '#111';
	const buttonTextColor = '#fff';
	const buttonHoverColor = getLinkColor( style?.elements?.link?.[ ':hover' ]?.color?.text ) || '#333';
	const selector = '.activitypub-dialog__button-group .wp-block-button';

	return getStyles( selector, buttonColor, buttonTextColor, buttonHoverColor );
}
