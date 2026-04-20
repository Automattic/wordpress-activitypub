/**
 * Toggle the custom distribution-mode fields based on the selected radio.
 *
 * The custom batch-size / pause inputs are only relevant when the
 * "custom" preset is active. They stay hidden for all other presets.
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

	radios.forEach( function( radio ) {
		radio.addEventListener( 'change', function() {
			updateVisibility( this.value );
		} );
	} );
}() );
