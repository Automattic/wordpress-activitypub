<?php
/**
 * User follow template.
 *
 * @package Activitypub
 */

$follow = new Activitypub\WP_Admin\Follow(
	get_current_user_id(),
	admin_url( 'users.php' )
);
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Follow', 'activitypub' ); ?></h1>
	<?php $follow->display(); ?>
</div>
