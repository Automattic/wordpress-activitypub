<?php
/**
 * ActivityPub Blog Settings template.
 *
 * @package Activitypub
 */

?>

<hr class="wp-header-end">

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<form method="post" action="options.php">
		<?php \settings_fields( 'activitypub_blog' ); ?>
		<?php \do_settings_sections( 'activitypub_blog_settings' ); ?>
		<?php \submit_button(); ?>
	</form>
</div>
