/**
 * Toggle the custom distribution-mode fields based on the selected radio.
 *
 * The custom batch-size / pause inputs are only relevant when the
 * "custom" preset is active. They are rendered visible by default so the
 * form remains usable when JavaScript is disabled; this script collapses
 * them on load when another mode is selected, and toggles them when the
 * radio changes.
 */

( function() {
	const radios = document.querySelectorAll( 'input[name="activitypub_distribution_mode"]' );
	const fields = document.getElementById( 'activitypub-custom-distribution-fields' );

	if ( ! fields || ! radios.length ) {
		return;
	}

	function updateVisibility( value ) {
		fields.style.display = 'custom' === value ? '' : 'none';
	}

	const initial = document.querySelector( 'input[name="activitypub_distribution_mode"]:checked' );
	updateVisibility( initial ? initial.value : 'default' );

	radios.forEach( function( radio ) {
		radio.addEventListener( 'change', function() {
			updateVisibility( this.value );
		} );
	} );
}() );
