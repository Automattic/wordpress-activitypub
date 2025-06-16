<?php
/**
 * ActivityPub Welcome template.
 *
 * @package Activitypub
 */

wp_enqueue_style(
	'activitypub-welcome',
	plugins_url( 'assets/css/activitypub-welcome.css', ACTIVITYPUB_PLUGIN_FILE ),
	array(),
	ACTIVITYPUB_PLUGIN_VERSION
);
?>

<div class="activitypub-settings activitypub-welcome-page hide-if-no-js">
	<?php do_settings_sections( 'activitypub_welcome' ); ?>
</div>
