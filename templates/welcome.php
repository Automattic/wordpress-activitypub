<?php
/**
 * ActivityPub Welcome template.
 *
 * @package Activitypub
 */

?>

<div class="activitypub-settings activitypub-welcome-page hide-if-no-js">
	<?php settings_errors(); ?>

	<?php
	settings_fields( 'activitypub_welcome' );
	do_settings_sections( 'activitypub_welcome' );
	?>
</div>
