<?php
/**
 * ActivityPub settings template.
 *
 * @package Activitypub
 */

?>

<hr class="wp-header-end">

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<?php \do_action( 'above_activitypub_settings' ); ?>
	<form method="post" action="options.php">
		<?php \settings_fields( 'activitypub' ); ?>
		<?php \do_settings_sections( 'activitypub_settings' ); ?>
		<?php \submit_button(); ?>
	</form>
	<?php \do_action( 'below_activitypub_settings' ); ?>
</div>
