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
	// if it starts with a hash, leave it be
	if ( typeof text === 'string' && text.match( /^#/ ) ) {
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

export const BODY_CLASS = 'activitypub-follow-modal-active';

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

	return generateSelector( selector, 'color', buttonTextColor )
	+ generateSelector( selector, 'background-color', buttonColor )
	+ generateSelector( selector, 'background-color', buttonHoverColor, ':hover' )
}

export function getPopupStyles( style ) {
	const base = `.${ BODY_CLASS } .components-modal__content .components-button`;
	const primary = `${ base }.is-primary`;
	const secondary = `${ base }.is-tertiary`;
	// we misuse the link color for the button background
	const buttonColor = getLinkColor( style?.elements?.link?.color?.text );
	// hover!
	const buttonHoverColor = getLinkColor( style?.elements?.link?.[':hover']?.color?.text );

	return generateSelector( primary, 'background-color', buttonColor )
	+ generateSelector( primary, 'background-color', buttonHoverColor, ':hover' )
	+ generateSelector( secondary, 'color', buttonColor )
}

export function ButtonStyle( { selector, style, backgroundColor } ) {
	const css = getBlockStyles( selector, style, backgroundColor );
	return (
		<style>{ css }</style>
	);
}