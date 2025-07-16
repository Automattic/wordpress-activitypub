<?php
/**
 * Blog follow template.
 *
 * @package Activitypub
 */

$follow = new Activitypub\WP_Admin\Follow(
	Activitypub\Collection\Actors::BLOG_USER_ID,
	admin_url( 'options-general.php?page=activitypub&tab=follow' ),
	admin_url( 'options-general.php?page=activitypub&tab=following' )
);
?>

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<h2><?php esc_html_e( 'Follow', 'activitypub' ); ?></h2>
	<?php $follow->display(); ?>
</div>
