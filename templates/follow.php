<?php
/**
 * Admin header template.
 *
 * @package Activitypub
 */

$follow = new \Activitypub\WP_Admin\Follow();
?>

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<h2><?php esc_html_e( 'Follow', 'activitypub' ); ?></h2>
	<?php $follow->display(); ?>
</div>
